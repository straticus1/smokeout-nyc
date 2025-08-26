<?php
/**
 * User Model
 * Political Memes XYZ
 */

require_once __DIR__ . '/../../config/database.php';

class User {
    private $db;
    
    public function __construct() {
        $this->db = DB::getInstance();
    }

    /**
     * Create a new user
     */
    public function create($userData) {
        $sql = "INSERT INTO users (username, email, password_hash, phone, zip_code, city, state, oauth_provider, oauth_id, verification_token) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $userData['username'],
            $userData['email'],
            password_hash($userData['password'], PASSWORD_DEFAULT),
            $userData['phone'] ?? null,
            $userData['zip_code'] ?? null,
            $userData['city'] ?? null,
            $userData['state'] ?? null,
            $userData['oauth_provider'] ?? null,
            $userData['oauth_id'] ?? null,
            $userData['verification_token'] ?? null
        ];

        $this->db->execute($sql, $params);
        return $this->db->lastInsertId();
    }

    /**
     * Find user by email
     */
    public function findByEmail($email) {
        $sql = "SELECT * FROM users WHERE email = ? AND status = 'active'";
        return $this->db->fetchOne($sql, [$email]);
    }

    /**
     * Find user by username
     */
    public function findByUsername($username) {
        $sql = "SELECT * FROM users WHERE username = ? AND status = 'active'";
        return $this->db->fetchOne($sql, [$username]);
    }

    /**
     * Find user by ID
     */
    public function findById($id) {
        $sql = "SELECT * FROM users WHERE id = ? AND status = 'active'";
        return $this->db->fetchOne($sql, [$id]);
    }

    /**
     * Verify user password
     */
    public function verifyPassword($email, $password) {
        $user = $this->findByEmail($email);
        if ($user && password_verify($password, $user['password_hash'])) {
            return $user;
        }
        return false;
    }

    /**
     * Update user credits
     */
    public function updateCredits($userId, $credits) {
        $sql = "UPDATE users SET credits = credits + ? WHERE id = ?";
        return $this->db->execute($sql, [$credits, $userId]);
    }

    /**
     * Get user credits
     */
    public function getCredits($userId) {
        $sql = "SELECT credits FROM users WHERE id = ?";
        $result = $this->db->fetchOne($sql, [$userId]);
        return $result ? $result['credits'] : 0;
    }

    /**
     * Update user location
     */
    public function updateLocation($userId, $zipCode, $city, $state) {
        $sql = "UPDATE users SET zip_code = ?, city = ?, state = ? WHERE id = ?";
        return $this->db->execute($sql, [$zipCode, $city, $state, $userId]);
    }

    /**
     * Verify email
     */
    public function verifyEmail($token) {
        $sql = "UPDATE users SET email_verified = TRUE, verification_token = NULL 
                WHERE verification_token = ?";
        return $this->db->execute($sql, [$token]) > 0;
    }

    /**
     * Update last login
     */
    public function updateLastLogin($userId) {
        $sql = "UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?";
        return $this->db->execute($sql, [$userId]);
    }

    /**
     * Check if user has enough credits
     */
    public function hasEnoughCredits($userId, $requiredCredits) {
        $credits = $this->getCredits($userId);
        return $credits >= $requiredCredits;
    }

    /**
     * Spend user credits
     */
    public function spendCredits($userId, $credits, $description, $referenceType = null, $referenceId = null) {
        if (!$this->hasEnoughCredits($userId, $credits)) {
            throw new Exception("Insufficient credits");
        }

        // Start transaction
        $this->db->getConnection()->beginTransaction();

        try {
            // Update user credits
            $this->updateCredits($userId, -$credits);

            // Log transaction
            $sql = "INSERT INTO credit_transactions (user_id, transaction_type, credits_amount, description, reference_type, reference_id, status) 
                    VALUES (?, 'spend', ?, ?, ?, ?, 'completed')";
            $this->db->execute($sql, [$userId, $credits, $description, $referenceType, $referenceId]);

            $this->db->getConnection()->commit();
            return true;
        } catch (Exception $e) {
            $this->db->getConnection()->rollback();
            throw $e;
        }
    }

    /**
     * Get user's voting history
     */
    public function getVotingHistory($userId, $limit = 50) {
        $sql = "SELECT pv.*, p.name as politician_name, p.slug as politician_slug 
                FROM politician_votes pv 
                JOIN politicians p ON pv.politician_id = p.id 
                WHERE pv.user_id = ? 
                ORDER BY pv.created_at DESC 
                LIMIT ?";
        return $this->db->fetchAll($sql, [$userId, $limit]);
    }

    /**
     * Get user's comments
     */
    public function getComments($userId, $limit = 50) {
        $sql = "SELECT c.*, p.name as politician_name, p.slug as politician_slug 
                FROM comments c 
                JOIN politicians p ON c.politician_id = p.id 
                WHERE c.user_id = ? AND c.status = 'active'
                ORDER BY c.created_at DESC 
                LIMIT ?";
        return $this->db->fetchAll($sql, [$userId, $limit]);
    }

    /**
     * Get user's followed politicians
     */
    public function getFollowedPoliticians($userId) {
        $sql = "SELECT p.* 
                FROM user_follows uf 
                JOIN politicians p ON uf.politician_id = p.id 
                WHERE uf.user_id = ? AND p.status = 'active'
                ORDER BY p.name";
        return $this->db->fetchAll($sql, [$userId]);
    }

    /**
     * Follow a politician
     */
    public function followPolitician($userId, $politicianId) {
        $sql = "INSERT IGNORE INTO user_follows (user_id, politician_id) VALUES (?, ?)";
        return $this->db->execute($sql, [$userId, $politicianId]);
    }

    /**
     * Unfollow a politician
     */
    public function unfollowPolitician($userId, $politicianId) {
        $sql = "DELETE FROM user_follows WHERE user_id = ? AND politician_id = ?";
        return $this->db->execute($sql, [$userId, $politicianId]);
    }

    /**
     * Create user session
     */
    public function createSession($userId, $sessionToken, $refreshToken = null, $expiresAt = null, $ipAddress = null, $userAgent = null) {
        if (!$expiresAt) {
            $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
        }

        $sql = "INSERT INTO user_sessions (user_id, session_token, refresh_token, expires_at, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        return $this->db->execute($sql, [$userId, $sessionToken, $refreshToken, $expiresAt, $ipAddress, $userAgent]);
    }

    /**
     * Validate session
     */
    public function validateSession($sessionToken) {
        $sql = "SELECT us.*, u.* 
                FROM user_sessions us 
                JOIN users u ON us.user_id = u.id 
                WHERE us.session_token = ? AND us.expires_at > NOW() AND u.status = 'active'";
        
        $session = $this->db->fetchOne($sql, [$sessionToken]);
        
        if ($session) {
            // Update last used
            $updateSql = "UPDATE user_sessions SET last_used_at = CURRENT_TIMESTAMP WHERE session_token = ?";
            $this->db->execute($updateSql, [$sessionToken]);
        }
        
        return $session;
    }

    /**
     * Delete session (logout)
     */
    public function deleteSession($sessionToken) {
        $sql = "DELETE FROM user_sessions WHERE session_token = ?";
        return $this->db->execute($sql, [$sessionToken]);
    }

    /**
     * Get user profile data
     */
    public function getProfile($userId) {
        $user = $this->findById($userId);
        if (!$user) {
            return null;
        }

        // Remove sensitive data
        unset($user['password_hash']);
        unset($user['verification_token']);

        // Get additional stats
        $user['total_votes'] = $this->getTotalVotes($userId);
        $user['total_comments'] = $this->getTotalComments($userId);
        $user['following_count'] = $this->getFollowingCount($userId);

        return $user;
    }

    private function getTotalVotes($userId) {
        $sql = "SELECT COUNT(*) as count FROM politician_votes WHERE user_id = ?";
        $result = $this->db->fetchOne($sql, [$userId]);
        return $result['count'];
    }

    private function getTotalComments($userId) {
        $sql = "SELECT COUNT(*) as count FROM comments WHERE user_id = ? AND status = 'active'";
        $result = $this->db->fetchOne($sql, [$userId]);
        return $result['count'];
    }

    private function getFollowingCount($userId) {
        $sql = "SELECT COUNT(*) as count FROM user_follows WHERE user_id = ?";
        $result = $this->db->fetchOne($sql, [$userId]);
        return $result['count'];
    }
}
?>
