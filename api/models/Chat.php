<?php
/**
 * Chat Model
 * Political Memes XYZ
 */

require_once __DIR__ . '/../../config/database.php';

class Chat {
    private $db;
    
    public function __construct() {
        $this->db = DB::getInstance();
    }

    /**
     * Get all public chat rooms
     */
    public function getPublicRooms() {
        $sql = "SELECT cr.*, 
                COUNT(DISTINCT cp.user_id) as participant_count,
                COUNT(DISTINCT CASE WHEN cp.is_online = TRUE THEN cp.user_id END) as online_count,
                (SELECT cm.created_at FROM chat_messages cm WHERE cm.room_id = cr.id ORDER BY cm.created_at DESC LIMIT 1) as last_message_at
                FROM chat_rooms cr
                LEFT JOIN chat_participants cp ON cr.id = cp.room_id
                WHERE cr.room_type = 'public' AND cr.is_active = TRUE
                GROUP BY cr.id
                ORDER BY cr.name";
        
        return $this->db->fetchAll($sql);
    }

    /**
     * Get politician-specific chat rooms
     */
    public function getPoliticianRooms($politicianId) {
        $sql = "SELECT cr.*,
                COUNT(DISTINCT cp.user_id) as participant_count,
                COUNT(DISTINCT CASE WHEN cp.is_online = TRUE THEN cp.user_id END) as online_count
                FROM chat_rooms cr
                LEFT JOIN chat_participants cp ON cr.id = cp.room_id
                WHERE cr.politician_id = ? AND cr.is_active = TRUE
                GROUP BY cr.id
                ORDER BY cr.created_at DESC";
        
        return $this->db->fetchAll($sql, [$politicianId]);
    }

    /**
     * Join a chat room
     */
    public function joinRoom($roomId, $userId, $role = 'member') {
        $sql = "INSERT INTO chat_participants (room_id, user_id, role, is_online) 
                VALUES (?, ?, ?, TRUE)
                ON DUPLICATE KEY UPDATE 
                    is_online = TRUE, 
                    last_seen_at = CURRENT_TIMESTAMP";
        
        return $this->db->execute($sql, [$roomId, $userId, $role]);
    }

    /**
     * Leave a chat room
     */
    public function leaveRoom($roomId, $userId) {
        $sql = "UPDATE chat_participants 
                SET is_online = FALSE, last_seen_at = CURRENT_TIMESTAMP 
                WHERE room_id = ? AND user_id = ?";
        
        return $this->db->execute($sql, [$roomId, $userId]);
    }

    /**
     * Send a message
     */
    public function sendMessage($roomId, $userId, $messageText, $messageType = 'text', $replyToId = null) {
        // Check if user is in the room
        if (!$this->isUserInRoom($roomId, $userId)) {
            throw new Exception("User is not a member of this room");
        }

        $sql = "INSERT INTO chat_messages (room_id, user_id, message_text, message_type, reply_to_message_id) 
                VALUES (?, ?, ?, ?, ?)";
        
        $this->db->execute($sql, [$roomId, $userId, $messageText, $messageType, $replyToId]);
        return $this->db->lastInsertId();
    }

    /**
     * Get messages for a room
     */
    public function getRoomMessages($roomId, $limit = 50, $offset = 0) {
        $sql = "SELECT cm.*, u.username, ua.avatar_url, ua.initials, ua.background_color,
                rt.message_text as reply_to_text, ru.username as reply_to_username
                FROM chat_messages cm
                JOIN users u ON cm.user_id = u.id
                LEFT JOIN user_avatars ua ON u.id = ua.user_id
                LEFT JOIN chat_messages rt ON cm.reply_to_message_id = rt.id
                LEFT JOIN users ru ON rt.user_id = ru.id
                WHERE cm.room_id = ?
                ORDER BY cm.created_at DESC
                LIMIT ? OFFSET ?";
        
        return $this->db->fetchAll($sql, [$roomId, $limit, $offset]);
    }

    /**
     * Get online users in a room
     */
    public function getOnlineUsers($roomId) {
        $sql = "SELECT u.id, u.username, ua.avatar_url, ua.initials, ua.background_color, cp.role
                FROM chat_participants cp
                JOIN users u ON cp.user_id = u.id
                LEFT JOIN user_avatars ua ON u.id = ua.user_id
                WHERE cp.room_id = ? AND cp.is_online = TRUE
                ORDER BY cp.role DESC, u.username";
        
        return $this->db->fetchAll($sql, [$roomId]);
    }

    /**
     * Check if user is in room
     */
    public function isUserInRoom($roomId, $userId) {
        $sql = "SELECT id FROM chat_participants WHERE room_id = ? AND user_id = ?";
        return $this->db->fetchOne($sql, [$roomId, $userId]) !== false;
    }

    /**
     * Create a new chat room
     */
    public function createRoom($name, $roomType, $description = null, $politicianId = null, $createdBy = null) {
        $sql = "INSERT INTO chat_rooms (name, room_type, description, politician_id, created_by) 
                VALUES (?, ?, ?, ?, ?)";
        
        $this->db->execute($sql, [$name, $roomType, $description, $politicianId, $createdBy]);
        return $this->db->lastInsertId();
    }

    /**
     * Update user online status
     */
    public function updateOnlineStatus($userId, $isOnline = true, $currentPage = null) {
        $sql = "INSERT INTO user_online_status (user_id, is_online, current_page) 
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    is_online = VALUES(is_online), 
                    current_page = VALUES(current_page),
                    last_activity = CURRENT_TIMESTAMP";
        
        return $this->db->execute($sql, [$userId, $isOnline, $currentPage]);
    }

    /**
     * Get all online users
     */
    public function getOnlineUsers() {
        $sql = "SELECT u.id, u.username, ua.avatar_url, ua.initials, ua.background_color, 
                uos.current_page, uos.last_activity
                FROM user_online_status uos
                JOIN users u ON uos.user_id = u.id
                LEFT JOIN user_avatars ua ON u.id = ua.user_id
                WHERE uos.is_online = TRUE 
                AND uos.last_activity > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
                ORDER BY uos.last_activity DESC";
        
        return $this->db->fetchAll($sql);
    }

    /**
     * Edit a message
     */
    public function editMessage($messageId, $userId, $newText) {
        // Check if user owns the message
        $sql = "SELECT user_id FROM chat_messages WHERE id = ?";
        $message = $this->db->fetchOne($sql, [$messageId]);
        
        if (!$message || $message['user_id'] != $userId) {
            throw new Exception("You can only edit your own messages");
        }

        $updateSql = "UPDATE chat_messages 
                      SET message_text = ?, is_edited = TRUE, edited_at = CURRENT_TIMESTAMP 
                      WHERE id = ?";
        
        return $this->db->execute($updateSql, [$newText, $messageId]);
    }

    /**
     * Delete a message
     */
    public function deleteMessage($messageId, $userId, $isAdmin = false) {
        // Check permissions
        $sql = "SELECT user_id FROM chat_messages WHERE id = ?";
        $message = $this->db->fetchOne($sql, [$messageId]);
        
        if (!$message || ($message['user_id'] != $userId && !$isAdmin)) {
            throw new Exception("You can only delete your own messages");
        }

        $deleteSql = "DELETE FROM chat_messages WHERE id = ?";
        return $this->db->execute($deleteSql, [$messageId]);
    }

    /**
     * Get room info
     */
    public function getRoomInfo($roomId) {
        $sql = "SELECT cr.*, p.name as politician_name, p.slug as politician_slug,
                COUNT(DISTINCT cp.user_id) as total_participants,
                COUNT(DISTINCT CASE WHEN cp.is_online = TRUE THEN cp.user_id END) as online_participants
                FROM chat_rooms cr
                LEFT JOIN politicians p ON cr.politician_id = p.id
                LEFT JOIN chat_participants cp ON cr.id = cp.room_id
                WHERE cr.id = ?
                GROUP BY cr.id";
        
        return $this->db->fetchOne($sql, [$roomId]);
    }

    /**
     * Search messages
     */
    public function searchMessages($roomId, $query, $limit = 20) {
        $sql = "SELECT cm.*, u.username
                FROM chat_messages cm
                JOIN users u ON cm.user_id = u.id
                WHERE cm.room_id = ? AND cm.message_text LIKE ?
                ORDER BY cm.created_at DESC
                LIMIT ?";
        
        return $this->db->fetchAll($sql, [$roomId, "%{$query}%", $limit]);
    }

    /**
     * Get user's chat rooms
     */
    public function getUserRooms($userId) {
        $sql = "SELECT cr.*, cp.role, cp.last_seen_at,
                COUNT(DISTINCT cp2.user_id) as participant_count,
                (SELECT COUNT(*) FROM chat_messages cm WHERE cm.room_id = cr.id AND cm.created_at > cp.last_seen_at) as unread_count
                FROM chat_participants cp
                JOIN chat_rooms cr ON cp.room_id = cr.id
                LEFT JOIN chat_participants cp2 ON cr.id = cp2.room_id
                WHERE cp.user_id = ? AND cr.is_active = TRUE
                GROUP BY cr.id, cp.role, cp.last_seen_at
                ORDER BY cr.name";
        
        return $this->db->fetchAll($sql, [$userId]);
    }

    /**
     * Mark room as read for user
     */
    public function markRoomAsRead($roomId, $userId) {
        $sql = "UPDATE chat_participants 
                SET last_seen_at = CURRENT_TIMESTAMP 
                WHERE room_id = ? AND user_id = ?";
        
        return $this->db->execute($sql, [$roomId, $userId]);
    }

    /**
     * Clean up offline users (run periodically)
     */
    public function cleanupOfflineUsers($timeoutMinutes = 15) {
        $sql = "UPDATE user_online_status 
                SET is_online = FALSE 
                WHERE last_activity < DATE_SUB(NOW(), INTERVAL ? MINUTE)";
        
        return $this->db->execute($sql, [$timeoutMinutes]);
    }
}
?>
