<?php
/**
 * Avatar Model
 * Political Memes XYZ
 */

require_once __DIR__ . '/../../config/database.php';

class Avatar {
    private $db;
    private $uploadDir;
    
    public function __construct() {
        $this->db = DB::getInstance();
        $this->uploadDir = __DIR__ . '/../../uploads/avatars/';
        
        // Create upload directory if it doesn't exist
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }

    /**
     * Get user avatar
     */
    public function getUserAvatar($userId) {
        $sql = "SELECT * FROM user_avatars WHERE user_id = ?";
        $avatar = $this->db->fetchOne($sql, [$userId]);
        
        if (!$avatar) {
            // Create default avatar
            return $this->createDefaultAvatar($userId);
        }
        
        return $avatar;
    }

    /**
     * Create default avatar for user
     */
    public function createDefaultAvatar($userId) {
        $user = $this->getUserInfo($userId);
        if (!$user) {
            throw new Exception("User not found");
        }

        $initials = $this->generateInitials($user['username']);
        $backgroundColor = $this->generateColor($user['username']);
        
        $sql = "INSERT INTO user_avatars (user_id, avatar_type, initials, background_color, text_color) 
                VALUES (?, 'default', ?, ?, '#ffffff')
                ON DUPLICATE KEY UPDATE
                    initials = VALUES(initials),
                    background_color = VALUES(background_color)";
        
        $this->db->execute($sql, [$userId, $initials, $backgroundColor]);
        
        return [
            'user_id' => $userId,
            'avatar_type' => 'default',
            'initials' => $initials,
            'background_color' => $backgroundColor,
            'text_color' => '#ffffff'
        ];
    }

    /**
     * Upload user avatar
     */
    public function uploadAvatar($userId, $file) {
        // Validate file
        $validation = $this->validateAvatarFile($file);
        if (!$validation['valid']) {
            throw new Exception($validation['error']);
        }

        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'user_' . $userId . '_' . time() . '.' . $extension;
        $filepath = $this->uploadDir . $filename;

        // Process and resize image
        $processedImage = $this->processAvatarImage($file['tmp_name'], $filepath);
        
        if (!$processedImage) {
            throw new Exception("Failed to process avatar image");
        }

        // Save to database
        $avatarUrl = '/uploads/avatars/' . $filename;
        
        $sql = "INSERT INTO user_avatars (user_id, avatar_type, avatar_url) 
                VALUES (?, 'upload', ?)
                ON DUPLICATE KEY UPDATE
                    avatar_type = 'upload',
                    avatar_url = VALUES(avatar_url),
                    updated_at = CURRENT_TIMESTAMP";
        
        $this->db->execute($sql, [$userId, $avatarUrl]);
        
        return [
            'user_id' => $userId,
            'avatar_type' => 'upload',
            'avatar_url' => $avatarUrl
        ];
    }

    /**
     * Generate avatar from initials
     */
    public function generateAvatar($userId, $backgroundColor = null, $textColor = '#ffffff') {
        $user = $this->getUserInfo($userId);
        if (!$user) {
            throw new Exception("User not found");
        }

        $initials = $this->generateInitials($user['username']);
        $bgColor = $backgroundColor ?: $this->generateColor($user['username']);
        
        // Create SVG avatar
        $svgContent = $this->createSvgAvatar($initials, $bgColor, $textColor);
        
        // Save as data URL or file
        $avatarData = 'data:image/svg+xml;base64,' . base64_encode($svgContent);
        
        $sql = "INSERT INTO user_avatars (user_id, avatar_type, avatar_url, initials, background_color, text_color) 
                VALUES (?, 'generated', ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    avatar_type = 'generated',
                    avatar_url = VALUES(avatar_url),
                    initials = VALUES(initials),
                    background_color = VALUES(background_color),
                    text_color = VALUES(text_color),
                    updated_at = CURRENT_TIMESTAMP";
        
        $this->db->execute($sql, [$userId, $avatarData, $initials, $bgColor, $textColor]);
        
        return [
            'user_id' => $userId,
            'avatar_type' => 'generated',
            'avatar_url' => $avatarData,
            'initials' => $initials,
            'background_color' => $bgColor,
            'text_color' => $textColor
        ];
    }

    /**
     * Get politician images
     */
    public function getPoliticianImages($politicianId) {
        $sql = "SELECT * FROM politician_images 
                WHERE politician_id = ? 
                ORDER BY is_primary DESC, created_at DESC";
        
        return $this->db->fetchAll($sql, [$politicianId]);
    }

    /**
     * Get politician primary image
     */
    public function getPoliticianPrimaryImage($politicianId) {
        $sql = "SELECT * FROM politician_images 
                WHERE politician_id = ? AND is_primary = TRUE 
                LIMIT 1";
        
        $primary = $this->db->fetchOne($sql, [$politicianId]);
        
        if (!$primary) {
            // Get any image as fallback
            $sql = "SELECT * FROM politician_images 
                    WHERE politician_id = ? 
                    ORDER BY created_at DESC 
                    LIMIT 1";
            $primary = $this->db->fetchOne($sql, [$politicianId]);
        }
        
        return $primary;
    }

    /**
     * Add politician image
     */
    public function addPoliticianImage($politicianId, $imageUrl, $imageType = 'official', $source = null, $sourceUrl = null, $copyrightInfo = null, $isPrimary = false) {
        // If setting as primary, unset other primary images
        if ($isPrimary) {
            $this->unsetPrimaryImages($politicianId);
        }

        $sql = "INSERT INTO politician_images (
                    politician_id, image_url, image_type, source, source_url, 
                    copyright_info, is_primary
                ) VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $params = [$politicianId, $imageUrl, $imageType, $source, $sourceUrl, $copyrightInfo, $isPrimary];
        
        $this->db->execute($sql, $params);
        return $this->db->lastInsertId();
    }

    /**
     * Bulk create politician images for major offices
     */
    public function createPoliticianImages() {
        // This would typically be run as a background job
        // For now, we'll create placeholders for the major offices mentioned
        
        $majorOffices = [
            // Federal level
            ['name' => 'President Biden', 'position' => 'President', 'office_level' => 'federal'],
            ['name' => 'Vice President Harris', 'position' => 'Vice President', 'office_level' => 'federal'],
            
            // Example House Representatives (would need full list)
            ['name' => 'Nancy Pelosi', 'position' => 'House Representative', 'office_level' => 'federal'],
            ['name' => 'Kevin McCarthy', 'position' => 'House Representative', 'office_level' => 'federal'],
            
            // Example Senators
            ['name' => 'Chuck Schumer', 'position' => 'Senator', 'office_level' => 'federal'],
            ['name' => 'Mitch McConnell', 'position' => 'Senator', 'office_level' => 'federal'],
            
            // Example Governors
            ['name' => 'Gavin Newsom', 'position' => 'Governor', 'office_level' => 'state', 'state' => 'CA'],
            ['name' => 'Ron DeSantis', 'position' => 'Governor', 'office_level' => 'state', 'state' => 'FL'],
            
            // Example Mayors
            ['name' => 'Eric Adams', 'position' => 'Mayor', 'office_level' => 'city', 'city' => 'New York', 'state' => 'NY'],
            ['name' => 'Karen Bass', 'position' => 'Mayor', 'office_level' => 'city', 'city' => 'Los Angeles', 'state' => 'CA'],
        ];

        $createdCount = 0;
        
        foreach ($majorOffices as $office) {
            // Check if politician already exists
            $existing = $this->findPoliticianByName($office['name']);
            
            if (!$existing) {
                // Create politician
                $politicianId = $this->createPolitician($office);
                
                // Add placeholder image (would be replaced with actual sourced images)
                $this->addPoliticianImage(
                    $politicianId,
                    $this->generatePoliticianImageUrl($office['name']),
                    'official',
                    'Government Source',
                    null,
                    'Official government photo',
                    true
                );
                
                $createdCount++;
            }
        }
        
        return $createdCount;
    }

    /**
     * Source images from official government websites
     */
    public function sourceGovernmentImages() {
        // This would implement scraping/API calls to official sources:
        // - congress.gov for House/Senate members
        // - whitehouse.gov for President/VP
        // - State government websites for Governors
        // - City websites for Mayors
        // - Cabinet member photos from department websites
        
        $sources = [
            'congress' => 'https://www.congress.gov/members',
            'whitehouse' => 'https://www.whitehouse.gov/administration/',
            'governors' => 'https://www.nga.org/governors/',
            // Add more sources as needed
        ];
        
        // For now, return placeholder implementation
        return [
            'message' => 'Image sourcing would be implemented here',
            'sources' => $sources,
            'note' => 'Would require API keys and web scraping implementation'
        ];
    }

    // Private helper methods
    
    private function validateAvatarFile($file) {
        $maxSize = $this->getMaxAvatarSize() * 1024 * 1024; // Convert MB to bytes
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'error' => 'File upload error'];
        }
        
        if ($file['size'] > $maxSize) {
            return ['valid' => false, 'error' => 'File too large. Maximum size: ' . ($maxSize / 1024 / 1024) . 'MB'];
        }
        
        if (!in_array($file['type'], $allowedTypes)) {
            return ['valid' => false, 'error' => 'Invalid file type. Allowed: JPEG, PNG, GIF, WebP'];
        }
        
        return ['valid' => true];
    }

    private function processAvatarImage($sourcePath, $targetPath) {
        $imageInfo = getimagesize($sourcePath);
        if (!$imageInfo) {
            return false;
        }
        
        $sourceImage = null;
        switch ($imageInfo[2]) {
            case IMAGETYPE_JPEG:
                $sourceImage = imagecreatefromjpeg($sourcePath);
                break;
            case IMAGETYPE_PNG:
                $sourceImage = imagecreatefrompng($sourcePath);
                break;
            case IMAGETYPE_GIF:
                $sourceImage = imagecreatefromgif($sourcePath);
                break;
            default:
                return false;
        }
        
        if (!$sourceImage) {
            return false;
        }
        
        // Resize to 200x200
        $size = 200;
        $resizedImage = imagecreatetruecolor($size, $size);
        
        // Preserve transparency for PNG/GIF
        if ($imageInfo[2] == IMAGETYPE_PNG || $imageInfo[2] == IMAGETYPE_GIF) {
            imagealphablending($resizedImage, false);
            imagesavealpha($resizedImage, true);
            $transparent = imagecolorallocatealpha($resizedImage, 255, 255, 255, 127);
            imagefilledrectangle($resizedImage, 0, 0, $size, $size, $transparent);
        }
        
        imagecopyresampled($resizedImage, $sourceImage, 0, 0, 0, 0, $size, $size, $imageInfo[0], $imageInfo[1]);
        
        // Save as JPEG
        $result = imagejpeg($resizedImage, $targetPath, 90);
        
        imagedestroy($sourceImage);
        imagedestroy($resizedImage);
        
        return $result;
    }

    private function generateInitials($name) {
        $words = explode(' ', trim($name));
        $initials = '';
        
        foreach ($words as $word) {
            if (strlen($word) > 0) {
                $initials .= strtoupper($word[0]);
                if (strlen($initials) >= 2) break;
            }
        }
        
        return $initials ?: 'U';
    }

    private function generateColor($seed) {
        $colors = [
            '#6366f1', '#8b5cf6', '#ec4899', '#ef4444', '#f97316',
            '#f59e0b', '#eab308', '#84cc16', '#22c55e', '#10b981',
            '#14b8a6', '#06b6d4', '#0ea5e9', '#3b82f6', '#6366f1'
        ];
        
        $hash = md5($seed);
        $index = hexdec(substr($hash, 0, 2)) % count($colors);
        
        return $colors[$index];
    }

    private function createSvgAvatar($initials, $backgroundColor, $textColor) {
        return "
        <svg width='200' height='200' xmlns='http://www.w3.org/2000/svg'>
            <rect width='200' height='200' fill='{$backgroundColor}'/>
            <text x='100' y='115' font-family='Arial, sans-serif' font-size='60' font-weight='bold' 
                  text-anchor='middle' fill='{$textColor}'>{$initials}</text>
        </svg>";
    }

    private function getUserInfo($userId) {
        $sql = "SELECT username FROM users WHERE id = ?";
        return $this->db->fetchOne($sql, [$userId]);
    }

    private function getMaxAvatarSize() {
        $sql = "SELECT config_value FROM system_config WHERE config_key = 'max_avatar_size_mb'";
        $result = $this->db->fetchOne($sql);
        return $result ? (int)$result['config_value'] : 5;
    }

    private function unsetPrimaryImages($politicianId) {
        $sql = "UPDATE politician_images SET is_primary = FALSE WHERE politician_id = ?";
        return $this->db->execute($sql, [$politicianId]);
    }

    private function findPoliticianByName($name) {
        $sql = "SELECT id FROM politicians WHERE name = ?";
        return $this->db->fetchOne($sql, [$name]);
    }

    private function createPolitician($data) {
        $sql = "INSERT INTO politicians (name, slug, position, office_level, city, state, verification_status) 
                VALUES (?, ?, ?, ?, ?, ?, 'verified')";
        
        $slug = strtolower(str_replace(' ', '-', $data['name']));
        
        $params = [
            $data['name'],
            $slug,
            $data['position'],
            $data['office_level'],
            $data['city'] ?? null,
            $data['state'] ?? null
        ];
        
        $this->db->execute($sql, $params);
        return $this->db->lastInsertId();
    }

    private function generatePoliticianImageUrl($name) {
        // Generate placeholder image URL
        $encodedName = urlencode($name);
        return "https://via.placeholder.com/200x200/6366f1/ffffff?text={$encodedName}";
    }
}
?>
