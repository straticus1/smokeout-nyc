<?php
/**
 * Cannabis Politics API
 * Handles cannabis-friendly politician tracking and donation constraints
 * SmokeoutNYC v2.3+
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'config/database.php';
require_once 'config/auth.php';

class CannabisPoliticsAPI {
    private $db;

    public function __construct() {
        $this->db = DB::getInstance()->getConnection();
    }

    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = $_GET['action'] ?? '';
        
        try {
            switch ($method) {
                case 'GET':
                    $this->handleGet($path);
                    break;
                case 'POST':
                    $this->handlePost($path);
                    break;
                case 'PUT':
                    $this->handlePut($path);
                    break;
                case 'DELETE':
                    $this->handleDelete($path);
                    break;
                default:
                    throw new Exception('Method not allowed', 405);
            }
        } catch (Exception $e) {
            http_response_code($e->getCode() ?: 500);
            echo json_encode([
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
        }
    }

    private function handleGet($path) {
        switch ($path) {
            case 'cannabis-friendly':
                $this->getCannabsFriendlyPoliticians();
                break;
            case 'politician-details':
                $this->getPoliticianCannabisDetails();
                break;
            case 'policy-positions':
                $this->getPolicyPositions();
                break;
            case 'voting-record':
                $this->getVotingRecord();
                break;
            case 'endorsements':
                $this->getEndorsements();
                break;
            case 'donation-eligibility':
                $this->checkDonationEligibility();
                break;
            case 'search-candidates':
                $this->searchCandidates();
                break;
            default:
                throw new Exception('Endpoint not found', 404);
        }
    }

    private function handlePost($path) {
        $user = $this->requireAuth();
        $data = json_decode(file_get_contents('php://input'), true);
        
        switch ($path) {
            case 'update-stance':
                $this->updateCannabisStance($user, $data);
                break;
            case 'add-policy-position':
                $this->addPolicyPosition($user, $data);
                break;
            case 'add-voting-record':
                $this->addVotingRecord($user, $data);
                break;
            case 'add-endorsement':
                $this->addEndorsement($user, $data);
                break;
            case 'add-statement':
                $this->addStatement($user, $data);
                break;
            case 'set-user-preferences':
                $this->setUserCannabisPreferences($user, $data);
                break;
            case 'verify-donation':
                $this->verifyDonationEligibility($user, $data);
                break;
            default:
                throw new Exception('Endpoint not found', 404);
        }
    }

    private function getCannabsFriendlyPoliticians() {
        $stance = $_GET['stance'] ?? 'pro_cannabis';
        $minScore = intval($_GET['min_score'] ?? 60);
        $office = $_GET['office_level'] ?? null;
        $state = $_GET['state'] ?? null;
        $limit = min(intval($_GET['limit'] ?? 50), 100);
        $offset = intval($_GET['offset'] ?? 0);

        $sql = "SELECT * FROM cannabis_friendly_politicians WHERE 1=1";
        $params = [];

        if ($stance !== 'all') {
            $sql .= " AND cannabis_stance = :stance";
            $params['stance'] = $stance;
        }

        if ($minScore > 0) {
            $sql .= " AND effective_score >= :min_score";
            $params['min_score'] = $minScore;
        }

        if ($office) {
            $sql .= " AND office_level = :office";
            $params['office'] = $office;
        }

        if ($state) {
            $sql .= " AND state = :state";
            $params['state'] = $state;
        }

        $sql .= " ORDER BY effective_score DESC, policy_positions_count DESC LIMIT :limit OFFSET :offset";
        $params['limit'] = $limit;
        $params['offset'] = $offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $politicians = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => $politicians,
            'pagination' => [
                'limit' => $limit,
                'offset' => $offset,
                'total' => $this->countCannabsFriendlyPoliticians($stance, $minScore, $office, $state)
            ]
        ]);
    }

    private function getPoliticianCannabisDetails() {
        $politicianId = intval($_GET['politician_id'] ?? 0);
        if (!$politicianId) {
            throw new Exception('Politician ID required', 400);
        }

        // Get politician basic info with cannabis data
        $stmt = $this->db->prepare("
            SELECT p.*, cfp.effective_score, cfp.policy_positions_count, 
                   cfp.votes_count, cfp.endorsements_count, cfp.avg_vote_impact
            FROM politicians p
            LEFT JOIN cannabis_friendly_politicians cfp ON p.id = cfp.id
            WHERE p.id = :id AND p.status = 'active'
        ");
        $stmt->execute(['id' => $politicianId]);
        $politician = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$politician) {
            throw new Exception('Politician not found', 404);
        }

        // Get policy positions
        $stmt = $this->db->prepare("
            SELECT * FROM cannabis_policy_positions 
            WHERE politician_id = :id 
            ORDER BY position_type, updated_at DESC
        ");
        $stmt->execute(['id' => $politicianId]);
        $politician['policy_positions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get recent votes
        $stmt = $this->db->prepare("
            SELECT * FROM cannabis_votes 
            WHERE politician_id = :id 
            ORDER BY vote_date DESC 
            LIMIT 10
        ");
        $stmt->execute(['id' => $politicianId]);
        $politician['recent_votes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get endorsements
        $stmt = $this->db->prepare("
            SELECT * FROM cannabis_endorsements 
            WHERE politician_id = :id 
            ORDER BY endorsement_date DESC
        ");
        $stmt->execute(['id' => $politicianId]);
        $politician['endorsements'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get recent statements
        $stmt = $this->db->prepare("
            SELECT * FROM cannabis_statements 
            WHERE politician_id = :id AND verified = 1
            ORDER BY statement_date DESC 
            LIMIT 5
        ");
        $stmt->execute(['id' => $politicianId]);
        $politician['statements'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => $politician
        ]);
    }

    private function checkDonationEligibility() {
        $politicianId = intval($_GET['politician_id'] ?? 0);
        $userId = intval($_GET['user_id'] ?? 0);
        $amount = floatval($_GET['amount'] ?? 0);

        if (!$politicianId) {
            throw new Exception('Politician ID required', 400);
        }

        // Get politician eligibility
        $stmt = $this->db->prepare("SELECT * FROM donation_eligibility WHERE id = :id");
        $stmt->execute(['id' => $politicianId]);
        $politician = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$politician) {
            throw new Exception('Politician not found', 404);
        }

        $result = [
            'politician_id' => $politicianId,
            'eligible' => $politician['donation_eligibility'] === 'eligible',
            'status' => $politician['donation_eligibility'],
            'cannabis_stance' => $politician['cannabis_stance'],
            'cannabis_score' => $politician['cannabis_score'],
            'restrictions' => []
        ];

        // Check user preferences if provided
        if ($userId) {
            $stmt = $this->db->prepare("SELECT * FROM user_cannabis_preferences WHERE user_id = :user_id");
            $stmt->execute(['user_id' => $userId]);
            $prefs = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($prefs) {
                if ($prefs['only_cannabis_friendly'] && $politician['cannabis_stance'] !== 'pro_cannabis') {
                    $result['eligible'] = false;
                    $result['restrictions'][] = 'User only allows cannabis-friendly donations';
                }

                if ($prefs['minimum_cannabis_score'] && 
                    $politician['cannabis_score'] < $prefs['minimum_cannabis_score']) {
                    $result['eligible'] = false;
                    $result['restrictions'][] = "User requires minimum score of {$prefs['minimum_cannabis_score']}";
                }

                $blockedPols = json_decode($prefs['blocked_politicians'] ?? '[]', true);
                if (in_array($politicianId, $blockedPols)) {
                    $result['eligible'] = false;
                    $result['restrictions'][] = 'Politician blocked by user';
                }
            }
        }

        // Check system constraints
        $stmt = $this->db->prepare("
            SELECT * FROM donation_constraints 
            WHERE is_active = 1 AND 
                  (applies_to = 'all_users' OR 
                   (applies_to = 'specific_users' AND JSON_CONTAINS(target_users, :user_id)))
        ");
        $stmt->execute(['user_id' => json_encode($userId)]);
        $constraints = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($constraints as $constraint) {
            $constraintValue = json_decode($constraint['constraint_value'], true);
            
            switch ($constraint['constraint_type']) {
                case 'cannabis_stance_filter':
                    if (in_array($politician['cannabis_stance'], $constraintValue['blocked_stances'] ?? [])) {
                        $result['eligible'] = false;
                        $result['restrictions'][] = 'Politician stance blocked by system policy';
                    }
                    break;
                    
                case 'minimum_score':
                    if ($politician['cannabis_score'] < ($constraintValue['minimum_score'] ?? 0)) {
                        $result['eligible'] = false;
                        $result['restrictions'][] = "System requires minimum score of {$constraintValue['minimum_score']}";
                    }
                    break;
            }
        }

        echo json_encode([
            'success' => true,
            'data' => $result
        ]);
    }

    private function searchCandidates() {
        $query = $_GET['q'] ?? '';
        $cannabisOnly = ($_GET['cannabis_friendly'] ?? 'false') === 'true';
        $office = $_GET['office_level'] ?? null;
        $state = $_GET['state'] ?? null;
        $limit = min(intval($_GET['limit'] ?? 20), 100);

        if (strlen($query) < 2) {
            throw new Exception('Search query too short', 400);
        }

        $sql = "SELECT p.*, cfp.effective_score, cfp.policy_positions_count 
                FROM politicians p 
                LEFT JOIN cannabis_friendly_politicians cfp ON p.id = cfp.id
                WHERE p.status = 'active' AND (
                    p.name LIKE :query OR 
                    p.position LIKE :query OR 
                    p.bio LIKE :query
                )";
        
        $params = ['query' => "%{$query}%"];

        if ($cannabisOnly) {
            $sql .= " AND (p.cannabis_stance = 'pro_cannabis' OR p.cannabis_score >= 60)";
        }

        if ($office) {
            $sql .= " AND p.office_level = :office";
            $params['office'] = $office;
        }

        if ($state) {
            $sql .= " AND p.state = :state";
            $params['state'] = $state;
        }

        $sql .= " ORDER BY cfp.effective_score DESC, p.name ASC LIMIT :limit";
        $params['limit'] = $limit;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => $results,
            'query' => $query,
            'filters' => [
                'cannabis_friendly' => $cannabisOnly,
                'office_level' => $office,
                'state' => $state
            ]
        ]);
    }

    private function updateCannabisStance($user, $data) {
        $this->requireAdminRole($user);

        $politicianId = intval($data['politician_id'] ?? 0);
        $stance = $data['stance'] ?? null;
        $score = isset($data['score']) ? intval($data['score']) : null;
        $reason = $data['reason'] ?? '';

        if (!$politicianId || !$stance) {
            throw new Exception('Politician ID and stance required', 400);
        }

        $validStances = ['pro_cannabis', 'anti_cannabis', 'neutral', 'unknown'];
        if (!in_array($stance, $validStances)) {
            throw new Exception('Invalid stance', 400);
        }

        if ($score !== null && ($score < 0 || $score > 100)) {
            throw new Exception('Score must be between 0 and 100', 400);
        }

        // Get current values for history
        $stmt = $this->db->prepare("SELECT cannabis_stance, cannabis_score FROM politicians WHERE id = :id");
        $stmt->execute(['id' => $politicianId]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$current) {
            throw new Exception('Politician not found', 404);
        }

        // Update politician
        $updateSql = "UPDATE politicians SET cannabis_stance = :stance, last_policy_update = NOW(), policy_updated_by = :user_id";
        $params = [
            'stance' => $stance,
            'user_id' => $user['id'],
            'id' => $politicianId
        ];

        if ($score !== null) {
            $updateSql .= ", cannabis_score = :score";
            $params['score'] = $score;
        }

        $updateSql .= " WHERE id = :id";

        $stmt = $this->db->prepare($updateSql);
        $stmt->execute($params);

        // Record history
        if ($current['cannabis_stance'] !== $stance) {
            $this->recordPolicyChange($politicianId, 'cannabis_stance', $current['cannabis_stance'], $stance, $user['id'], $reason);
        }

        if ($score !== null && $current['cannabis_score'] != $score) {
            $this->recordPolicyChange($politicianId, 'cannabis_score', $current['cannabis_score'], $score, $user['id'], $reason);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Cannabis stance updated successfully'
        ]);
    }

    private function setUserCannabisPreferences($user, $data) {
        $prefs = [
            'only_cannabis_friendly' => (bool)($data['only_cannabis_friendly'] ?? false),
            'minimum_cannabis_score' => isset($data['minimum_cannabis_score']) ? intval($data['minimum_cannabis_score']) : null,
            'blocked_politicians' => json_encode($data['blocked_politicians'] ?? []),
            'preferred_policy_positions' => json_encode($data['preferred_policy_positions'] ?? []),
            'auto_donate_to_cannabis_friendly' => (bool)($data['auto_donate_to_cannabis_friendly'] ?? false),
            'auto_donate_amount' => isset($data['auto_donate_amount']) ? floatval($data['auto_donate_amount']) : null,
            'notification_preferences' => json_encode($data['notification_preferences'] ?? [])
        ];

        $sql = "INSERT INTO user_cannabis_preferences 
                (user_id, only_cannabis_friendly, minimum_cannabis_score, blocked_politicians, 
                 preferred_policy_positions, auto_donate_to_cannabis_friendly, auto_donate_amount, 
                 notification_preferences) 
                VALUES (:user_id, :only_cannabis_friendly, :minimum_cannabis_score, :blocked_politicians, 
                        :preferred_policy_positions, :auto_donate_to_cannabis_friendly, :auto_donate_amount, 
                        :notification_preferences)
                ON DUPLICATE KEY UPDATE
                only_cannabis_friendly = VALUES(only_cannabis_friendly),
                minimum_cannabis_score = VALUES(minimum_cannabis_score),
                blocked_politicians = VALUES(blocked_politicians),
                preferred_policy_positions = VALUES(preferred_policy_positions),
                auto_donate_to_cannabis_friendly = VALUES(auto_donate_to_cannabis_friendly),
                auto_donate_amount = VALUES(auto_donate_amount),
                notification_preferences = VALUES(notification_preferences),
                updated_at = NOW()";

        $params = array_merge(['user_id' => $user['id']], $prefs);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        echo json_encode([
            'success' => true,
            'message' => 'Cannabis preferences updated successfully'
        ]);
    }

    private function recordPolicyChange($politicianId, $field, $oldValue, $newValue, $userId, $reason) {
        $stmt = $this->db->prepare("
            INSERT INTO cannabis_policy_history 
            (politician_id, field_changed, old_value, new_value, change_reason, changed_by) 
            VALUES (:politician_id, :field, :old_value, :new_value, :reason, :user_id)
        ");
        
        $stmt->execute([
            'politician_id' => $politicianId,
            'field' => $field,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'reason' => $reason,
            'user_id' => $userId
        ]);
    }

    private function countCannabsFriendlyPoliticians($stance, $minScore, $office, $state) {
        $sql = "SELECT COUNT(*) FROM cannabis_friendly_politicians WHERE 1=1";
        $params = [];

        if ($stance !== 'all') {
            $sql .= " AND cannabis_stance = :stance";
            $params['stance'] = $stance;
        }

        if ($minScore > 0) {
            $sql .= " AND effective_score >= :min_score";
            $params['min_score'] = $minScore;
        }

        if ($office) {
            $sql .= " AND office_level = :office";
            $params['office'] = $office;
        }

        if ($state) {
            $sql .= " AND state = :state";
            $params['state'] = $state;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return intval($stmt->fetchColumn());
    }

    private function requireAuth() {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';
        
        if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            throw new Exception('Authorization header required', 401);
        }
        
        $token = $matches[1];
        $user = Auth::validateToken($token);
        
        if (!$user) {
            throw new Exception('Invalid or expired token', 401);
        }
        
        return $user;
    }

    private function requireAdminRole($user) {
        if (!in_array($user['role'], ['admin', 'super_admin'])) {
            throw new Exception('Admin access required', 403);
        }
    }
}

// Initialize and handle request
$api = new CannabisPoliticsAPI();
$api->handleRequest();
?>