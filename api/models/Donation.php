<?php
/**
 * Donation Model
 * Political Memes XYZ
 */

require_once __DIR__ . '/../../config/database.php';

class Donation {
    private $db;
    
    public function __construct() {
        $this->db = DB::getInstance();
    }

    /**
     * Create a new donation
     */
    public function create($data) {
        // Calculate processing fee and net amount
        $processingFeePercent = $data['processing_fee_percent'] ?? $this->getDefaultProcessingFee();
        $processingFeeAmount = $data['amount_usd'] * ($processingFeePercent / 100);
        $netAmount = $data['amount_usd'] - $processingFeeAmount;

        $sql = "INSERT INTO donations (
                    user_id, politician_id, amount_usd, processing_fee_percent, 
                    processing_fee_amount, net_amount, payment_method, payment_reference,
                    donor_name, donor_email, donor_address, is_anonymous
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $data['user_id'],
            $data['politician_id'],
            $data['amount_usd'],
            $processingFeePercent,
            $processingFeeAmount,
            $netAmount,
            $data['payment_method'],
            $data['payment_reference'] ?? null,
            $data['donor_name'] ?? null,
            $data['donor_email'] ?? null,
            $data['donor_address'] ?? null,
            $data['is_anonymous'] ?? false
        ];

        $this->db->execute($sql, $params);
        return $this->db->lastInsertId();
    }

    /**
     * Get politician's donation settings
     */
    public function getDonationSettings($politicianId) {
        $sql = "SELECT * FROM donation_settings WHERE politician_id = ?";
        return $this->db->fetchOne($sql, [$politicianId]);
    }

    /**
     * Check if politician accepts donations
     */
    public function canAcceptDonations($politicianId) {
        $settings = $this->getDonationSettings($politicianId);
        return $settings && $settings['donations_enabled'];
    }

    /**
     * Update donation settings for a politician
     */
    public function updateDonationSettings($politicianId, $settings) {
        $sql = "INSERT INTO donation_settings (
                    politician_id, donations_enabled, min_donation_amount, 
                    max_donation_amount, processing_fee_percent, campaign_finance_id,
                    campaign_contact_email, donation_instructions
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    donations_enabled = VALUES(donations_enabled),
                    min_donation_amount = VALUES(min_donation_amount),
                    max_donation_amount = VALUES(max_donation_amount),
                    processing_fee_percent = VALUES(processing_fee_percent),
                    campaign_finance_id = VALUES(campaign_finance_id),
                    campaign_contact_email = VALUES(campaign_contact_email),
                    donation_instructions = VALUES(donation_instructions)";

        $params = [
            $politicianId,
            $settings['donations_enabled'] ?? true,
            $settings['min_donation_amount'] ?? 5.00,
            $settings['max_donation_amount'] ?? 2800.00,
            $settings['processing_fee_percent'] ?? $this->getDefaultProcessingFee(),
            $settings['campaign_finance_id'] ?? null,
            $settings['campaign_contact_email'] ?? null,
            $settings['donation_instructions'] ?? null
        ];

        return $this->db->execute($sql, $params);
    }

    /**
     * Get donations for a politician
     */
    public function getPoliticianDonations($politicianId, $limit = 50, $offset = 0) {
        $sql = "SELECT d.*, u.username as donor_username
                FROM donations d 
                LEFT JOIN users u ON d.user_id = u.id 
                WHERE d.politician_id = ? AND d.status = 'completed'
                ORDER BY d.created_at DESC 
                LIMIT ? OFFSET ?";
        
        return $this->db->fetchAll($sql, [$politicianId, $limit, $offset]);
    }

    /**
     * Get user's donation history
     */
    public function getUserDonations($userId, $limit = 50) {
        $sql = "SELECT d.*, p.name as politician_name, p.slug as politician_slug
                FROM donations d 
                JOIN politicians p ON d.politician_id = p.id 
                WHERE d.user_id = ? 
                ORDER BY d.created_at DESC 
                LIMIT ?";
        
        return $this->db->fetchAll($sql, [$userId, $limit]);
    }

    /**
     * Get donation statistics for a politician
     */
    public function getDonationStats($politicianId) {
        $sql = "SELECT 
                    COUNT(*) as total_donations,
                    SUM(amount_usd) as total_amount,
                    SUM(net_amount) as total_net_amount,
                    AVG(amount_usd) as average_donation,
                    COUNT(DISTINCT user_id) as unique_donors
                FROM donations 
                WHERE politician_id = ? AND status = 'completed'";
        
        return $this->db->fetchOne($sql, [$politicianId]);
    }

    /**
     * Process donation (mark as completed)
     */
    public function processPayment($donationId, $paymentReference = null) {
        $sql = "UPDATE donations 
                SET status = 'completed', payment_reference = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?";
        
        return $this->db->execute($sql, [$paymentReference, $donationId]);
    }

    /**
     * Mark donation as forwarded to campaign
     */
    public function markAsForwarded($donationId, $forwardedReference = null) {
        $sql = "UPDATE donations 
                SET status = 'forwarded', forwarded_at = CURRENT_TIMESTAMP, forwarded_reference = ?
                WHERE id = ?";
        
        return $this->db->execute($sql, [$forwardedReference, $donationId]);
    }

    /**
     * Get donations ready to be forwarded
     */
    public function getDonationsToForward() {
        $sql = "SELECT d.*, p.name as politician_name, ds.campaign_contact_email
                FROM donations d
                JOIN politicians p ON d.politician_id = p.id
                LEFT JOIN donation_settings ds ON d.politician_id = ds.politician_id
                WHERE d.status = 'completed' 
                AND d.created_at <= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                ORDER BY d.politician_id, d.created_at";
        
        return $this->db->fetchAll($sql);
    }

    /**
     * Get top donors for a politician
     */
    public function getTopDonors($politicianId, $limit = 10) {
        $sql = "SELECT 
                    u.username,
                    SUM(d.amount_usd) as total_donated,
                    COUNT(d.id) as donation_count,
                    MAX(d.created_at) as last_donation
                FROM donations d
                JOIN users u ON d.user_id = u.id
                WHERE d.politician_id = ? AND d.status = 'completed' AND d.is_anonymous = FALSE
                GROUP BY d.user_id, u.username
                ORDER BY total_donated DESC
                LIMIT ?";
        
        return $this->db->fetchAll($sql, [$politicianId, $limit]);
    }

    /**
     * Validate donation amount
     */
    public function validateDonationAmount($politicianId, $amount) {
        $settings = $this->getDonationSettings($politicianId);
        
        if (!$settings) {
            return ['valid' => false, 'error' => 'Donation settings not found'];
        }

        if (!$settings['donations_enabled']) {
            return ['valid' => false, 'error' => 'This politician is not accepting donations'];
        }

        if ($amount < $settings['min_donation_amount']) {
            return ['valid' => false, 'error' => "Minimum donation is ${$settings['min_donation_amount']}"];
        }

        if ($amount > $settings['max_donation_amount']) {
            return ['valid' => false, 'error' => "Maximum donation is ${$settings['max_donation_amount']}"];
        }

        return ['valid' => true];
    }

    /**
     * Get recent donations (public feed)
     */
    public function getRecentDonations($limit = 20) {
        $sql = "SELECT 
                    d.amount_usd,
                    d.created_at,
                    d.is_anonymous,
                    CASE 
                        WHEN d.is_anonymous = TRUE THEN 'Anonymous'
                        ELSE u.username
                    END as donor_name,
                    p.name as politician_name,
                    p.slug as politician_slug
                FROM donations d
                JOIN politicians p ON d.politician_id = p.id
                LEFT JOIN users u ON d.user_id = u.id
                WHERE d.status = 'completed'
                ORDER BY d.created_at DESC
                LIMIT ?";
        
        return $this->db->fetchAll($sql, [$limit]);
    }

    /**
     * Get monthly donation totals
     */
    public function getMonthlyDonationTotals($politicianId, $months = 12) {
        $sql = "SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as month,
                    COUNT(*) as donation_count,
                    SUM(amount_usd) as total_amount,
                    SUM(net_amount) as net_amount
                FROM donations
                WHERE politician_id = ? 
                AND status = 'completed'
                AND created_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                ORDER BY month DESC";
        
        return $this->db->fetchAll($sql, [$politicianId, $months]);
    }

    /**
     * Get default processing fee percentage
     */
    private function getDefaultProcessingFee() {
        $sql = "SELECT config_value FROM system_config WHERE config_key = 'donation_processing_fee_percent'";
        $result = $this->db->fetchOne($sql);
        return $result ? (float)$result['config_value'] : 3.00;
    }

    /**
     * Refund a donation
     */
    public function refundDonation($donationId, $reason = null) {
        $sql = "UPDATE donations 
                SET status = 'refunded', updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?";
        
        $result = $this->db->execute($sql, [$donationId]);
        
        if ($result && $reason) {
            // Log the refund reason
            $logSql = "INSERT INTO audit_log (action, table_name, record_id, new_values) 
                       VALUES ('refund', 'donations', ?, ?)";
            $this->db->execute($logSql, [$donationId, json_encode(['reason' => $reason])]);
        }
        
        return $result;
    }

    /**
     * Get donation by ID
     */
    public function findById($id) {
        $sql = "SELECT d.*, p.name as politician_name, u.username as donor_username
                FROM donations d
                JOIN politicians p ON d.politician_id = p.id
                LEFT JOIN users u ON d.user_id = u.id
                WHERE d.id = ?";
        
        return $this->db->fetchOne($sql, [$id]);
    }
}
?>
