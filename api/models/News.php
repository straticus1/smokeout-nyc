<?php
/**
 * News Model
 * Political Memes XYZ
 */

require_once __DIR__ . '/../../config/database.php';

class News {
    private $db;
    
    public function __construct() {
        $this->db = DB::getInstance();
    }

    /**
     * Get all news articles
     */
    public function getArticles($limit = 20, $offset = 0, $category = null, $source = null) {
        $conditions = ["status = 'published'"];
        $params = [];

        if ($category) {
            $conditions[] = "category = ?";
            $params[] = $category;
        }

        if ($source) {
            $conditions[] = "source = ?";
            $params[] = $source;
        }

        $whereClause = implode(' AND ', $conditions);
        
        $sql = "SELECT id, title, excerpt, author, source, source_url, image_url, 
                article_type, category, published_at, view_count, is_featured
                FROM news_articles 
                WHERE {$whereClause}
                ORDER BY is_featured DESC, published_at DESC 
                LIMIT ? OFFSET ?";

        $params[] = $limit;
        $params[] = $offset;

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Get article by ID
     */
    public function getArticleById($id) {
        $sql = "SELECT * FROM news_articles WHERE id = ? AND status = 'published'";
        $article = $this->db->fetchOne($sql, [$id]);
        
        if ($article) {
            // Increment view count
            $this->incrementViewCount($id);
            
            // Decode tags JSON
            if ($article['tags']) {
                $article['tags'] = json_decode($article['tags'], true);
            }
        }
        
        return $article;
    }

    /**
     * Create internal news article
     */
    public function createArticle($data) {
        $sql = "INSERT INTO news_articles (
                    title, content, excerpt, author, source, source_url, 
                    image_url, article_type, category, tags, published_at, is_featured
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'internal', ?, ?, ?, ?)";
        
        $params = [
            $data['title'],
            $data['content'],
            $data['excerpt'] ?? null,
            $data['author'],
            $data['source'] ?? 'PoliticalMemes.XYZ',
            $data['source_url'] ?? null,
            $data['image_url'] ?? null,
            $data['category'] ?? 'politics',
            isset($data['tags']) ? json_encode($data['tags']) : null,
            $data['published_at'] ?? date('Y-m-d H:i:s'),
            $data['is_featured'] ?? false
        ];

        $this->db->execute($sql, $params);
        return $this->db->lastInsertId();
    }

    /**
     * Get featured articles
     */
    public function getFeaturedArticles($limit = 5) {
        $sql = "SELECT id, title, excerpt, author, source, image_url, 
                article_type, category, published_at, view_count
                FROM news_articles 
                WHERE status = 'published' AND is_featured = TRUE
                ORDER BY published_at DESC 
                LIMIT ?";
        
        return $this->db->fetchAll($sql, [$limit]);
    }

    /**
     * Search articles
     */
    public function searchArticles($query, $limit = 20) {
        $sql = "SELECT id, title, excerpt, author, source, image_url, 
                article_type, category, published_at, view_count,
                MATCH(title, content, excerpt) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
                FROM news_articles 
                WHERE status = 'published' 
                AND MATCH(title, content, excerpt) AGAINST(? IN NATURAL LANGUAGE MODE)
                ORDER BY relevance DESC, published_at DESC 
                LIMIT ?";
        
        return $this->db->fetchAll($sql, [$query, $query, $limit]);
    }

    /**
     * Get articles by category
     */
    public function getArticlesByCategory($category, $limit = 20) {
        $sql = "SELECT id, title, excerpt, author, source, image_url, 
                article_type, category, published_at, view_count
                FROM news_articles 
                WHERE status = 'published' AND category = ?
                ORDER BY published_at DESC 
                LIMIT ?";
        
        return $this->db->fetchAll($sql, [$category, $limit]);
    }

    /**
     * Get trending articles (most viewed in last 24 hours)
     */
    public function getTrendingArticles($limit = 10) {
        $sql = "SELECT id, title, excerpt, author, source, image_url, 
                article_type, category, published_at, view_count
                FROM news_articles 
                WHERE status = 'published' 
                AND published_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                ORDER BY view_count DESC, published_at DESC 
                LIMIT ?";
        
        return $this->db->fetchAll($sql, [$limit]);
    }

    /**
     * Increment article view count
     */
    private function incrementViewCount($articleId) {
        $sql = "UPDATE news_articles SET view_count = view_count + 1 WHERE id = ?";
        return $this->db->execute($sql, [$articleId]);
    }

    /**
     * Get RSS sources
     */
    public function getRssSources($activeOnly = true) {
        $sql = "SELECT * FROM rss_sources";
        $params = [];
        
        if ($activeOnly) {
            $sql .= " WHERE is_active = TRUE";
        }
        
        $sql .= " ORDER BY name";
        
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Add RSS source
     */
    public function addRssSource($name, $url, $category = null) {
        $sql = "INSERT INTO rss_sources (name, url, category) VALUES (?, ?, ?)";
        $this->db->execute($sql, [$name, $url, $category]);
        return $this->db->lastInsertId();
    }

    /**
     * Fetch and parse RSS feed
     */
    public function fetchRssFeed($sourceId) {
        $source = $this->getRssSourceById($sourceId);
        if (!$source) {
            throw new Exception("RSS source not found");
        }

        try {
            // Fetch RSS content
            $rssContent = $this->fetchRssContent($source['url']);
            
            // Parse RSS
            $articles = $this->parseRssFeed($rssContent, $source);
            
            // Store articles
            $newArticles = 0;
            foreach ($articles as $article) {
                if ($this->createSyndicatedArticle($article, $source)) {
                    $newArticles++;
                }
            }

            // Update last fetched time
            $this->updateRssSourceLastFetched($sourceId);

            return $newArticles;
            
        } catch (Exception $e) {
            error_log("RSS fetch error for source {$sourceId}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Fetch all RSS feeds
     */
    public function fetchAllRssFeeds() {
        $sources = $this->getRssSources(true);
        $totalNewArticles = 0;

        foreach ($sources as $source) {
            try {
                $newArticles = $this->fetchRssFeed($source['id']);
                $totalNewArticles += $newArticles;
                
                // Add delay between requests to be respectful
                sleep(1);
                
            } catch (Exception $e) {
                error_log("Failed to fetch RSS for {$source['name']}: " . $e->getMessage());
            }
        }

        return $totalNewArticles;
    }

    /**
     * Get RSS source by ID
     */
    private function getRssSourceById($id) {
        $sql = "SELECT * FROM rss_sources WHERE id = ?";
        return $this->db->fetchOne($sql, [$id]);
    }

    /**
     * Fetch RSS content from URL
     */
    private function fetchRssContent($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_USERAGENT, 'PoliticalMemes.XYZ RSS Reader');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || $content === false) {
            throw new Exception("Failed to fetch RSS feed: HTTP {$httpCode}");
        }

        return $content;
    }

    /**
     * Parse RSS feed content
     */
    private function parseRssFeed($rssContent, $source) {
        $articles = [];
        
        try {
            $xml = simplexml_load_string($rssContent);
            
            if (!$xml) {
                throw new Exception("Invalid XML content");
            }

            // Handle different RSS formats
            $items = [];
            if (isset($xml->channel->item)) {
                $items = $xml->channel->item;
            } elseif (isset($xml->item)) {
                $items = $xml->item;
            } elseif (isset($xml->entry)) {
                $items = $xml->entry; // Atom feed
            }

            foreach ($items as $item) {
                $article = $this->parseRssItem($item, $source);
                if ($article) {
                    $articles[] = $article;
                }
            }
            
        } catch (Exception $e) {
            throw new Exception("RSS parsing error: " . $e->getMessage());
        }

        return $articles;
    }

    /**
     * Parse individual RSS item
     */
    private function parseRssItem($item, $source) {
        try {
            $title = (string)$item->title;
            $description = (string)($item->description ?? $item->summary ?? '');
            $link = (string)($item->link ?? $item->id ?? '');
            
            // Handle different date formats
            $pubDate = null;
            if (isset($item->pubDate)) {
                $pubDate = date('Y-m-d H:i:s', strtotime((string)$item->pubDate));
            } elseif (isset($item->published)) {
                $pubDate = date('Y-m-d H:i:s', strtotime((string)$item->published));
            } elseif (isset($item->updated)) {
                $pubDate = date('Y-m-d H:i:s', strtotime((string)$item->updated));
            }

            // Extract image
            $imageUrl = null;
            if (isset($item->enclosure) && (string)$item->enclosure['type'] && strpos((string)$item->enclosure['type'], 'image') === 0) {
                $imageUrl = (string)$item->enclosure['url'];
            }

            // Create excerpt from description
            $excerpt = $this->createExcerpt($description);

            return [
                'title' => $title,
                'content' => $description,
                'excerpt' => $excerpt,
                'source_url' => $link,
                'image_url' => $imageUrl,
                'published_at' => $pubDate ?: date('Y-m-d H:i:s'),
                'category' => $source['category'] ?? 'politics'
            ];
            
        } catch (Exception $e) {
            error_log("Error parsing RSS item: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Create syndicated article
     */
    private function createSyndicatedArticle($articleData, $source) {
        // Check if article already exists
        if ($this->articleExists($articleData['source_url'])) {
            return false;
        }

        $sql = "INSERT INTO news_articles (
                    title, content, excerpt, source, source_url, 
                    image_url, article_type, category, published_at, syndicated_at
                ) VALUES (?, ?, ?, ?, ?, ?, 'syndicated', ?, ?, CURRENT_TIMESTAMP)";
        
        $params = [
            $articleData['title'],
            $articleData['content'],
            $articleData['excerpt'],
            $source['name'],
            $articleData['source_url'],
            $articleData['image_url'],
            $articleData['category'],
            $articleData['published_at']
        ];

        $this->db->execute($sql, $params);
        return true;
    }

    /**
     * Check if article already exists
     */
    private function articleExists($sourceUrl) {
        $sql = "SELECT id FROM news_articles WHERE source_url = ?";
        return $this->db->fetchOne($sql, [$sourceUrl]) !== false;
    }

    /**
     * Update RSS source last fetched time
     */
    private function updateRssSourceLastFetched($sourceId) {
        $sql = "UPDATE rss_sources SET last_fetched_at = CURRENT_TIMESTAMP WHERE id = ?";
        return $this->db->execute($sql, [$sourceId]);
    }

    /**
     * Create excerpt from content
     */
    private function createExcerpt($content, $maxLength = 200) {
        $text = strip_tags($content);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);
        
        if (strlen($text) <= $maxLength) {
            return $text;
        }
        
        $excerpt = substr($text, 0, $maxLength);
        $lastSpace = strrpos($excerpt, ' ');
        
        if ($lastSpace !== false) {
            $excerpt = substr($excerpt, 0, $lastSpace);
        }
        
        return $excerpt . '...';
    }

    /**
     * Get sources that need fetching
     */
    public function getSourcesForFetching() {
        $sql = "SELECT * FROM rss_sources 
                WHERE is_active = TRUE 
                AND (last_fetched_at IS NULL 
                     OR last_fetched_at < DATE_SUB(NOW(), INTERVAL fetch_frequency_hours HOUR))
                ORDER BY last_fetched_at ASC";
        
        return $this->db->fetchAll($sql);
    }

    /**
     * Get article categories
     */
    public function getCategories() {
        $sql = "SELECT category, COUNT(*) as article_count 
                FROM news_articles 
                WHERE status = 'published' AND category IS NOT NULL
                GROUP BY category 
                ORDER BY article_count DESC";
        
        return $this->db->fetchAll($sql);
    }

    /**
     * Get article sources
     */
    public function getSources() {
        $sql = "SELECT source, COUNT(*) as article_count,
                MAX(published_at) as latest_article
                FROM news_articles 
                WHERE status = 'published'
                GROUP BY source 
                ORDER BY article_count DESC";
        
        return $this->db->fetchAll($sql);
    }
}
?>
