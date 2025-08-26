<?php
/**
 * Politician Model
 * Political Memes XYZ
 */

require_once __DIR__ . '/../../config/database.php';

class Politician {
    private $db;
    
    public function __construct() {
        $this->db = DB::getInstance();
    }

    /**
     * Create a new politician
     */
    public function create($data) {
        $slug = $this->generateSlug($data['name']);
        
        $sql = "INSERT INTO politicians (name, slug, position, party, city, state, county, zip_code, district, office_level, photo_url, bio, website_url, social_media, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $data['name'],
            $slug,
            $data['position'],
            $data['party'] ?? null,
            $data['city'] ?? null,
            $data['state'] ?? null,
            $data['county'] ?? null,
            $data['zip_code'] ?? null,
            $data['district'] ?? null,
            $data['office_level'],
            $data['photo_url'] ?? null,
            $data['bio'] ?? null,
            $data['website_url'] ?? null,
            isset($data['social_media']) ? json_encode($data['social_media']) : null,
            $data['created_by'] ?? null
        ];

        $this->db->execute($sql, $params);
        return $this->db->lastInsertId();
    }

    /**
     * Find politician by ID
     */
    public function findById($id) {
        $sql = "SELECT * FROM politicians WHERE id = ? AND status = 'active'";
        return $this->db->fetchOne($sql, [$id]);
    }

    /**
     * Find politician by slug
     */
    public function findBySlug($slug) {
        $sql = "SELECT * FROM politicians WHERE slug = ? AND status = 'active'";
        return $this->db->fetchOne($sql, [$slug]);
    }

    /**
     * Search politicians
     */
    public function search($params = []) {
        $conditions = ["p.status = 'active'"];
        $queryParams = [];

        if (!empty($params['name'])) {
            $conditions[] = "p.name LIKE ?";
            $queryParams[] = '%' . $params['name'] . '%';
        }

        if (!empty($params['city'])) {
            $conditions[] = "p.city = ?";
            $queryParams[] = $params['city'];
        }

        if (!empty($params['state'])) {
            $conditions[] = "p.state = ?";
            $queryParams[] = $params['state'];
        }

        if (!empty($params['county'])) {
            $conditions[] = "p.county = ?";
            $queryParams[] = $params['county'];
        }

        if (!empty($params['zip_code'])) {
            $conditions[] = "p.zip_code = ?";
            $queryParams[] = $params['zip_code'];
        }

        if (!empty($params['office_level'])) {
            $conditions[] = "p.office_level = ?";
            $queryParams[] = $params['office_level'];
        }

        if (!empty($params['party'])) {
            $conditions[] = "p.party = ?";
            $queryParams[] = $params['party'];
        }

        $whereClause = implode(' AND ', $conditions);
        $limit = isset($params['limit']) ? (int)$params['limit'] : 20;
        $offset = isset($params['offset']) ? (int)$params['offset'] : 0;

        $sql = "SELECT p.*, 
                (SELECT COUNT(*) FROM politician_votes pv WHERE pv.politician_id = p.id AND pv.vote_type = 'upvote') as upvotes,
                (SELECT COUNT(*) FROM politician_votes pv WHERE pv.politician_id = p.id AND pv.vote_type = 'downvote') as downvotes,
                (SELECT COUNT(*) FROM comments c WHERE c.politician_id = p.id AND c.status = 'active') as comment_count
                FROM politicians p 
                WHERE {$whereClause} 
                ORDER BY p.name 
                LIMIT ? OFFSET ?";

        $queryParams[] = $limit;
        $queryParams[] = $offset;

        return $this->db->fetchAll($sql, $queryParams);
    }

    /**
     * Get politicians by location (for GeoIP)
     */
    public function getByLocation($zipCode, $city = null, $state = null) {
        $conditions = ["p.status = 'active'"];
        $params = [];

        if ($zipCode) {
            $conditions[] = "p.zip_code = ?";
            $params[] = $zipCode;
        }

        if ($city) {
            $conditions[] = "p.city = ?";
            $params[] = $city;
        }

        if ($state) {
            $conditions[] = "p.state = ?";
            $params[] = $state;
        }

        $whereClause = implode(' AND ', $conditions);

        $sql = "SELECT p.*, 
                (SELECT COUNT(*) FROM politician_votes pv WHERE pv.politician_id = p.id AND pv.vote_type = 'upvote') as upvotes,
                (SELECT COUNT(*) FROM politician_votes pv WHERE pv.politician_id = p.id AND pv.vote_type = 'downvote') as downvotes,
                (SELECT COUNT(*) FROM comments c WHERE c.politician_id = p.id AND c.status = 'active') as comment_count
                FROM politicians p 
                WHERE {$whereClause} 
                ORDER BY p.office_level, p.name
                LIMIT 20";

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Get politician with detailed stats
     */
    public function getWithStats($id) {
        $politician = $this->findById($id);
        if (!$politician) {
            return null;
        }

        // Get vote counts
        $voteSql = "SELECT 
                    SUM(CASE WHEN vote_type = 'upvote' THEN 1 ELSE 0 END) as upvotes,
                    SUM(CASE WHEN vote_type = 'downvote' THEN 1 ELSE 0 END) as downvotes,
                    COUNT(*) as total_votes
                    FROM politician_votes 
                    WHERE politician_id = ?";
        
        $voteStats = $this->db->fetchOne($voteSql, [$id]);
        
        // Get comment count
        $commentSql = "SELECT COUNT(*) as comment_count FROM comments WHERE politician_id = ? AND status = 'active'";
        $commentStats = $this->db->fetchOne($commentSql, [$id]);

        // Calculate approval rating
        $totalVotes = $voteStats['total_votes'] ?? 0;
        $upvotes = $voteStats['upvotes'] ?? 0;
        $approvalRating = $totalVotes > 0 ? round(($upvotes / $totalVotes) * 100, 1) : 0;

        $politician['upvotes'] = $voteStats['upvotes'] ?? 0;
        $politician['downvotes'] = $voteStats['downvotes'] ?? 0;
        $politician['total_votes'] = $totalVotes;
        $politician['approval_rating'] = $approvalRating;
        $politician['comment_count'] = $commentStats['comment_count'] ?? 0;

        // Decode social media JSON
        if ($politician['social_media']) {
            $politician['social_media'] = json_decode($politician['social_media'], true);
        }

        return $politician;
    }

    /**
     * Vote on politician
     */
    public function vote($userId, $politicianId, $voteType, $creditsSpent = 2) {
        // Check if user already voted
        $existingVote = $this->getUserVote($userId, $politicianId);
        
        if ($existingVote) {
            // Update existing vote
            $sql = "UPDATE politician_votes SET vote_type = ?, credits_spent = ?, updated_at = CURRENT_TIMESTAMP 
                    WHERE user_id = ? AND politician_id = ?";
            $this->db->execute($sql, [$voteType, $creditsSpent, $userId, $politicianId]);
        } else {
            // Create new vote
            $sql = "INSERT INTO politician_votes (user_id, politician_id, vote_type, credits_spent) 
                    VALUES (?, ?, ?, ?)";
            $this->db->execute($sql, [$userId, $politicianId, $voteType, $creditsSpent]);
        }

        return true;
    }

    /**
     * Get user's vote for a politician
     */
    public function getUserVote($userId, $politicianId) {
        $sql = "SELECT * FROM politician_votes WHERE user_id = ? AND politician_id = ?";
        return $this->db->fetchOne($sql, [$userId, $politicianId]);
    }

    /**
     * Get politician's policies
     */
    public function getPolicies($politicianId, $limit = 10) {
        $sql = "SELECT p.*, 
                (SELECT COUNT(*) FROM policy_votes pv WHERE pv.policy_id = p.id AND pv.vote_type = 'support') as support_votes,
                (SELECT COUNT(*) FROM policy_votes pv WHERE pv.policy_id = p.id AND pv.vote_type = 'oppose') as oppose_votes
                FROM policies p 
                WHERE p.politician_id = ? 
                ORDER BY p.created_at DESC 
                LIMIT ?";
        
        return $this->db->fetchAll($sql, [$politicianId, $limit]);
    }

    /**
     * Get politician's comments
     */
    public function getComments($politicianId, $sortBy = 'newest', $limit = 20, $offset = 0) {
        $orderBy = 'c.created_at DESC';
        
        switch ($sortBy) {
            case 'oldest':
                $orderBy = 'c.created_at ASC';
                break;
            case 'popular':
                $orderBy = 'c.upvotes DESC, c.created_at DESC';
                break;
            case 'priority':
                $orderBy = 'c.is_priority DESC, c.created_at DESC';
                break;
        }

        $sql = "SELECT c.*, u.username, u.id as user_id
                FROM comments c 
                JOIN users u ON c.user_id = u.id 
                WHERE c.politician_id = ? AND c.status = 'active' AND c.parent_comment_id IS NULL
                ORDER BY {$orderBy}
                LIMIT ? OFFSET ?";
        
        return $this->db->fetchAll($sql, [$politicianId, $limit, $offset]);
    }

    /**
     * Add comment to politician
     */
    public function addComment($userId, $politicianId, $commentText, $isPriority = false, $creditsSpent = 0) {
        $sql = "INSERT INTO comments (user_id, politician_id, comment_text, is_priority, credits_spent) 
                VALUES (?, ?, ?, ?, ?)";
        
        $this->db->execute($sql, [$userId, $politicianId, $commentText, $isPriority, $creditsSpent]);
        return $this->db->lastInsertId();
    }

    /**
     * Send message to politician
     */
    public function sendMessage($userId, $politicianId, $subject, $messageText, $isPublic = false) {
        $sql = "INSERT INTO messages (from_user_id, to_politician_id, subject, message_text, is_public) 
                VALUES (?, ?, ?, ?, ?)";
        
        $this->db->execute($sql, [$userId, $politicianId, $subject, $messageText, $isPublic]);
        return $this->db->lastInsertId();
    }

    /**
     * Get trending politicians
     */
    public function getTrending($limit = 10) {
        $sql = "SELECT p.*, 
                (SELECT COUNT(*) FROM politician_votes pv WHERE pv.politician_id = p.id AND pv.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as recent_votes,
                (SELECT COUNT(*) FROM comments c WHERE c.politician_id = p.id AND c.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as recent_comments,
                (SELECT COUNT(*) FROM politician_votes pv WHERE pv.politician_id = p.id AND pv.vote_type = 'upvote') as upvotes,
                (SELECT COUNT(*) FROM politician_votes pv WHERE pv.politician_id = p.id AND pv.vote_type = 'downvote') as downvotes
                FROM politicians p 
                WHERE p.status = 'active'
                ORDER BY (recent_votes + recent_comments) DESC, p.name
                LIMIT ?";
        
        return $this->db->fetchAll($sql, [$limit]);
    }

    /**
     * Get featured politician (meme of the day)
     */
    public function getFeatured() {
        $sql = "SELECT p.*, fc.feature_type, fc.start_date, fc.end_date
                FROM politicians p 
                JOIN featured_content fc ON fc.content_id = p.id 
                WHERE fc.content_type = 'politician' 
                AND fc.feature_type = 'meme_of_day' 
                AND fc.start_date <= CURDATE() 
                AND (fc.end_date IS NULL OR fc.end_date >= CURDATE())
                AND p.status = 'active'
                ORDER BY fc.priority DESC, fc.start_date DESC
                LIMIT 1";
        
        return $this->db->fetchOne($sql);
    }

    /**
     * Generate unique slug
     */
    private function generateSlug($name) {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
        
        // Check if slug exists
        $counter = 0;
        $originalSlug = $slug;
        
        while ($this->slugExists($slug)) {
            $counter++;
            $slug = $originalSlug . '-' . $counter;
        }
        
        return $slug;
    }

    /**
     * Check if slug exists
     */
    private function slugExists($slug) {
        $sql = "SELECT id FROM politicians WHERE slug = ?";
        return $this->db->fetchOne($sql, [$slug]) !== false;
    }

    /**
     * Update politician verification status
     */
    public function updateVerificationStatus($id, $status) {
        $sql = "UPDATE politicians SET verification_status = ? WHERE id = ?";
        return $this->db->execute($sql, [$status, $id]);
    }

    /**
     * Get politicians by office level
     */
    public function getByOfficeLevel($officeLevel, $limit = 20) {
        $sql = "SELECT p.*, 
                (SELECT COUNT(*) FROM politician_votes pv WHERE pv.politician_id = p.id AND pv.vote_type = 'upvote') as upvotes,
                (SELECT COUNT(*) FROM politician_votes pv WHERE pv.politician_id = p.id AND pv.vote_type = 'downvote') as downvotes
                FROM politicians p 
                WHERE p.office_level = ? AND p.status = 'active'
                ORDER BY p.name
                LIMIT ?";
        
        return $this->db->fetchAll($sql, [$officeLevel, $limit]);
    }
}
?>
