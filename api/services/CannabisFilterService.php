<?php
/**
 * Cannabis Filter Service
 * Handles cannabis-friendly candidate filtering and donation constraints
 * SmokeoutNYC v2.3+
 */

class CannabisFilterService {
    private $db;

    public function __construct() {
        $this->db = DB::getInstance()->getConnection();
    }

    /**
     * Check if a donation is allowed based on cannabis policies
     */
    public function isDonationAllowed($politicianId, $userId = null, $amount = 0) {
        $politician = $this->getPoliticianCannabisInfo($politicianId);
        
        if (!$politician) {
            return [
                'allowed' => false,
                'reason' => 'Politician not found',
                'restrictions' => []
            ];
        }

        $restrictions = [];
        $allowed = true;

        // Check system-wide constraints
        $systemConstraints = $this->getActiveSystemConstraints();
        foreach ($systemConstraints as $constraint) {
            $constraintResult = $this->applyConstraint($constraint, $politician, $userId, $amount);
            if (!$constraintResult['allowed']) {
                $allowed = false;
                $restrictions[] = $constraintResult['reason'];
            }
        }

        // Check user preferences if user is provided
        if ($userId) {
            $userPrefs = $this->getUserCannabisPreferences($userId);
            if ($userPrefs) {
                $userResult = $this->applyUserPreferences($userPrefs, $politician, $politicianId);
                if (!$userResult['allowed']) {
                    $allowed = false;
                    $restrictions = array_merge($restrictions, $userResult['restrictions']);
                }
            }
        }

        return [
            'allowed' => $allowed,
            'politician' => $politician,
            'restrictions' => $restrictions,
            'cannabis_score' => $politician['cannabis_score'],
            'cannabis_stance' => $politician['cannabis_stance']
        ];
    }

    /**
     * Filter politicians list based on cannabis criteria
     */
    public function filterPoliticians($politicians, $userId = null, $criteria = []) {
        $minScore = $criteria['min_cannabis_score'] ?? 0;
        $requiredStance = $criteria['required_stance'] ?? null;
        $cannabisFriendlyOnly = $criteria['cannabis_friendly_only'] ?? false;
        
        $filtered = [];
        
        foreach ($politicians as $politician) {
            $cannabisInfo = $this->getPoliticianCannabisInfo($politician['id']);
            
            if (!$cannabisInfo) {
                continue;
            }

            // Apply basic filtering
            if ($cannabisFriendlyOnly) {
                if ($cannabisInfo['cannabis_stance'] !== 'pro_cannabis' && 
                    ($cannabisInfo['cannabis_score'] ?? 0) < 60) {
                    continue;
                }
            }

            if ($minScore > 0 && ($cannabisInfo['cannabis_score'] ?? 0) < $minScore) {
                continue;
            }

            if ($requiredStance && $cannabisInfo['cannabis_stance'] !== $requiredStance) {
                continue;
            }

            // Check donation eligibility
            $donationCheck = $this->isDonationAllowed($politician['id'], $userId);
            
            $politician['cannabis_info'] = $cannabisInfo;
            $politician['donation_allowed'] = $donationCheck['allowed'];
            $politician['donation_restrictions'] = $donationCheck['restrictions'];
            
            $filtered[] = $politician;
        }

        return $filtered;
    }

    /**
     * Get cannabis-friendly politicians with scoring
     */
    public function getCannabsFriendlyPoliticians($filters = []) {
        $stance = $filters['stance'] ?? 'pro_cannabis';
        $minScore = $filters['min_score'] ?? 60;
        $office = $filters['office_level'] ?? null;
        $state = $filters['state'] ?? null;
        $limit = min($filters['limit'] ?? 50, 100);
        $offset = $filters['offset'] ?? 0;

        $sql = "SELECT p.*, 
                       p.cannabis_score,
                       p.cannabis_stance,
                       COUNT(DISTINCT cp.id) as policy_positions_count,
                       COUNT(DISTINCT cv.id) as votes_count,
                       COUNT(DISTINCT ce.id) as endorsements_count,
                       AVG(CASE cv.cannabis_impact 
                           WHEN 'very_positive' THEN 5
                           WHEN 'positive' THEN 4
                           WHEN 'neutral' THEN 3
                           WHEN 'negative' THEN 2
                           WHEN 'very_negative' THEN 1
                       END) as avg_vote_impact
                FROM politicians p
                LEFT JOIN cannabis_policy_positions cp ON p.id = cp.politician_id
                LEFT JOIN cannabis_votes cv ON p.id = cv.politician_id
                LEFT JOIN cannabis_endorsements ce ON p.id = ce.politician_id
                WHERE p.status = 'active'";
        
        $params = [];

        if ($stance !== 'all') {
            $sql .= " AND p.cannabis_stance = :stance";
            $params['stance'] = $stance;
        }

        if ($minScore > 0) {
            $sql .= " AND COALESCE(p.cannabis_score, 0) >= :min_score";
            $params['min_score'] = $minScore;
        }

        if ($office) {
            $sql .= " AND p.office_level = :office";
            $params['office'] = $office;
        }

        if ($state) {
            $sql .= " AND p.state = :state";
            $params['state'] = $state;
        }

        $sql .= " GROUP BY p.id 
                 ORDER BY COALESCE(p.cannabis_score, 0) DESC, policy_positions_count DESC 
                 LIMIT :limit OFFSET :offset";
        
        $params['limit'] = $limit;
        $params['offset'] = $offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get anti-cannabis politicians for blocking/awareness
     */
    public function getAntiCannabisPoliticians($filters = []) {
        $maxScore = $filters['max_score'] ?? 30;
        $office = $filters['office_level'] ?? null;
        $state = $filters['state'] ?? null;
        $limit = min($filters['limit'] ?? 50, 100);

        $sql = "SELECT p.*, 
                       p.cannabis_score,
                       p.cannabis_stance,
                       COUNT(DISTINCT cv.id) as anti_cannabis_votes,
                       AVG(CASE cv.cannabis_impact 
                           WHEN 'very_negative' THEN 5
                           WHEN 'negative' THEN 4
                           WHEN 'neutral' THEN 3
                           WHEN 'positive' THEN 2
                           WHEN 'very_positive' THEN 1
                       END) as avg_opposition_score
                FROM politicians p
                LEFT JOIN cannabis_votes cv ON p.id = cv.politician_id 
                    AND cv.cannabis_impact IN ('negative', 'very_negative')
                WHERE p.status = 'active' 
                    AND (p.cannabis_stance = 'anti_cannabis' 
                         OR COALESCE(p.cannabis_score, 50) <= :max_score)";
        
        $params = ['max_score' => $maxScore];

        if ($office) {
            $sql .= " AND p.office_level = :office";
            $params['office'] = $office;
        }

        if ($state) {
            $sql .= " AND p.state = :state";
            $params['state'] = $state;
        }

        $sql .= " GROUP BY p.id 
                 ORDER BY avg_opposition_score DESC, anti_cannabis_votes DESC 
                 LIMIT :limit";
        
        $params['limit'] = $limit;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Calculate cannabis friendliness score based on multiple factors
     */
    public function calculateCannabisScore($politicianId) {
        $score = 50; // Neutral baseline

        // Get policy positions (30% weight)
        $stmt = $this->db->prepare("
            SELECT stance, COUNT(*) as count 
            FROM cannabis_policy_positions 
            WHERE politician_id = :id 
            GROUP BY stance
        ");
        $stmt->execute(['id' => $politicianId]);
        $positions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $positionScore = 0;
        $totalPositions = 0;
        foreach ($positions as $pos) {
            $totalPositions += $pos['count'];
            switch ($pos['stance']) {
                case 'strongly_support': $positionScore += $pos['count'] * 5; break;
                case 'support': $positionScore += $pos['count'] * 4; break;
                case 'neutral': $positionScore += $pos['count'] * 3; break;
                case 'oppose': $positionScore += $pos['count'] * 2; break;
                case 'strongly_oppose': $positionScore += $pos['count'] * 1; break;
            }
        }
        
        if ($totalPositions > 0) {
            $score += (($positionScore / $totalPositions) - 3) * 10; // Adjust from neutral
        }

        // Get voting record (40% weight)
        $stmt = $this->db->prepare("
            SELECT cannabis_impact, COUNT(*) as count 
            FROM cannabis_votes 
            WHERE politician_id = :id 
            GROUP BY cannabis_impact
        ");
        $stmt->execute(['id' => $politicianId]);
        $votes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $voteScore = 0;
        $totalVotes = 0;
        foreach ($votes as $vote) {
            $totalVotes += $vote['count'];
            switch ($vote['cannabis_impact']) {
                case 'very_positive': $voteScore += $vote['count'] * 5; break;
                case 'positive': $voteScore += $vote['count'] * 4; break;
                case 'neutral': $voteScore += $vote['count'] * 3; break;
                case 'negative': $voteScore += $vote['count'] * 2; break;
                case 'very_negative': $voteScore += $vote['count'] * 1; break;
            }
        }

        if ($totalVotes > 0) {
            $score += (($voteScore / $totalVotes) - 3) * 13.33; // 40% weight
        }

        // Get endorsements (30% weight)
        $stmt = $this->db->prepare("
            SELECT organization_type, score, max_score
            FROM cannabis_endorsements 
            WHERE politician_id = :id
        ");
        $stmt->execute(['id' => $politicianId]);
        $endorsements = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($endorsements)) {
            $endorsementScore = 0;
            $endorsementCount = 0;
            
            foreach ($endorsements as $endorsement) {
                if (is_numeric($endorsement['score']) && is_numeric($endorsement['max_score'])) {
                    $normalizedScore = ($endorsement['score'] / $endorsement['max_score']) * 100;
                    $endorsementScore += $normalizedScore;
                    $endorsementCount++;
                }
            }
            
            if ($endorsementCount > 0) {
                $avgEndorsement = $endorsementScore / $endorsementCount;
                $score += ($avgEndorsement - 50) * 0.1; // 10% weight, normalized to 50 baseline
            }
        }

        // Ensure score is within bounds
        return max(0, min(100, round($score)));
    }

    private function getPoliticianCannabisInfo($politicianId) {
        $stmt = $this->db->prepare("
            SELECT id, name, cannabis_stance, cannabis_score, last_policy_update
            FROM politicians 
            WHERE id = :id AND status = 'active'
        ");
        $stmt->execute(['id' => $politicianId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function getUserCannabisPreferences($userId) {
        $stmt = $this->db->prepare("
            SELECT * FROM user_cannabis_preferences 
            WHERE user_id = :user_id
        ");
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function getActiveSystemConstraints() {
        $stmt = $this->db->prepare("
            SELECT * FROM donation_constraints 
            WHERE is_active = 1
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function applyConstraint($constraint, $politician, $userId, $amount) {
        $constraintValue = json_decode($constraint['constraint_value'], true);
        
        switch ($constraint['constraint_type']) {
            case 'cannabis_stance_filter':
                $blockedStances = $constraintValue['blocked_stances'] ?? [];
                if (in_array($politician['cannabis_stance'], $blockedStances)) {
                    return [
                        'allowed' => false,
                        'reason' => "System policy blocks donations to {$politician['cannabis_stance']} politicians"
                    ];
                }
                break;

            case 'minimum_score':
                $minScore = $constraintValue['minimum_score'] ?? 0;
                if (($politician['cannabis_score'] ?? 0) < $minScore) {
                    return [
                        'allowed' => false,
                        'reason' => "System requires minimum cannabis score of {$minScore}"
                    ];
                }
                break;

            case 'blacklist':
                $blacklisted = $constraintValue['politician_ids'] ?? [];
                if (in_array($politician['id'], $blacklisted)) {
                    return [
                        'allowed' => false,
                        'reason' => "Politician is blacklisted by system policy"
                    ];
                }
                break;

            case 'auto_approval':
                $threshold = $constraintValue['cannabis_score_threshold'] ?? 80;
                $maxAmount = $constraintValue['auto_approve_amount_limit'] ?? 500;
                // This constraint doesn't block donations, just affects approval workflow
                break;
        }

        return ['allowed' => true, 'reason' => ''];
    }

    private function applyUserPreferences($prefs, $politician, $politicianId) {
        $restrictions = [];
        $allowed = true;

        if ($prefs['only_cannabis_friendly']) {
            if ($politician['cannabis_stance'] !== 'pro_cannabis' && 
                ($politician['cannabis_score'] ?? 0) < 60) {
                $allowed = false;
                $restrictions[] = 'User preference: Only cannabis-friendly donations allowed';
            }
        }

        if ($prefs['minimum_cannabis_score'] && 
            ($politician['cannabis_score'] ?? 0) < $prefs['minimum_cannabis_score']) {
            $allowed = false;
            $restrictions[] = "User requires minimum cannabis score of {$prefs['minimum_cannabis_score']}";
        }

        $blockedPoliticians = json_decode($prefs['blocked_politicians'] ?? '[]', true);
        if (in_array($politicianId, $blockedPoliticians)) {
            $allowed = false;
            $restrictions[] = 'Politician blocked by user';
        }

        return [
            'allowed' => $allowed,
            'restrictions' => $restrictions
        ];
    }
}
?>