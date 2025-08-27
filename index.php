<?php
/**
 * RSS Word Frequency Analyzer - PHP Version
 * A clean, single-file PHP application for RSS word frequency analysis
 */

class RSSWordAnalyzer {
    private $settings_file = 'settings.json';
    private $default_stopwords;
    private $custom_stopwords = array();
    private $selected_feeds = array();
    private $default_feeds;
    
    public function __construct() {
        $this->default_stopwords = array(
            'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 
            'of', 'with', 'by', 'from', 'as', 'is', 'are', 'was', 'were', 'be', 
            'been', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 
            'could', 'should', 'may', 'might', 'must', 'can', 'this', 'that', 
            'these', 'those', 'i', 'you', 'he', 'she', 'it', 'we', 'they', 'me', 
            'him', 'her', 'us', 'them', 'my', 'your', 'his', 'her', 'its', 'our', 
            'their', 'am', 'said', 'says', 'say', 'get', 'go', 'going', 'went', 
            'come', 'came', 'time', 'people', 'way', 'day', 'man', 'new', 'first', 
            'last', 'long', 'great', 'little', 'own', 'other', 'old', 'right', 
            'big', 'high', 'different', 'small', 'large', 'next', 'early', 'young', 
            'important', 'few', 'public', 'bad', 'same', 'able', 'also', 'back', 
            'after', 'use', 'her', 'than', 'now', 'look', 'only', 'think', 'see', 
            'know', 'take', 'work', 'life', 'become', 'here', 'how', 'so', 'get', 
            'want', 'make', 'give', 'hand', 'part', 'place', 'where', 'turn', 
            'put', 'end', 'why', 'try', 'good', 'woman', 'through', 'us', 'down', 
            'up', 'out', 'many', 'then', 'them', 'these', 'so', 'some', 'her', 
            'would', 'make', 'like', 'into', 'him', 'has', 'two', 'more', 'very', 
            'what', 'know', 'just', 'first', 'get', 'over', 'think', 'also', 
            'your', 'work', 'life', 'only', 'can', 'still', 'should', 'after', 
            'being', 'now', 'made', 'before', 'here', 'through', 'when', 'where', 
            'much', 'too', 'any', 'may', 'well', 'such'
        );
        
        $this->default_feeds = array(
            'BBC News' => 'http://feeds.bbci.co.uk/news/rss.xml',
            'Reuters' => 'http://feeds.reuters.com/reuters/topNews',
            'CNN' => 'http://rss.cnn.com/rss/edition.rss',
            'TechCrunch' => 'http://feeds.feedburner.com/TechCrunch',
            'Hacker News' => 'https://hnrss.org/frontpage',
            'Ars Technica' => 'http://arstechnica.com/feed/',
            'The Register' => 'http://www.theregister.co.uk/headlines.atom',
            'Slashdot' => 'http://rss.slashdot.org/Slashdot/slashdotMain'
        );
        
        $this->load_settings();
    }
    
    private function load_settings() {
        if (file_exists($this->settings_file)) {
            $settings_json = file_get_contents($this->settings_file);
            $settings = json_decode($settings_json, true);
            if ($settings) {
                $this->custom_stopwords = isset($settings['custom_stopwords']) ? $settings['custom_stopwords'] : array();
                $this->selected_feeds = isset($settings['selected_feeds']) ? $settings['selected_feeds'] : $this->default_feeds;
            } else {
                $this->selected_feeds = $this->default_feeds;
            }
        } else {
            $this->selected_feeds = $this->default_feeds;
        }
    }
    
    public function save_settings() {
        $settings = array(
            'custom_stopwords' => $this->custom_stopwords,
            'selected_feeds' => $this->selected_feeds
        );
        file_put_contents($this->settings_file, json_encode($settings, JSON_PRETTY_PRINT));
    }
    
    public function get_feeds() {
        return array(
            'selected_feeds' => $this->selected_feeds,
            'default_feeds' => $this->default_feeds
        );
    }
    
    public function update_feeds($feeds) {
        $this->selected_feeds = $feeds;
        $this->save_settings();
    }
    
    public function get_stopwords() {
        return array(
            'custom_stopwords' => $this->custom_stopwords,
            'default_count' => count($this->default_stopwords)
        );
    }
    
    public function update_stopwords($stopwords) {
        $this->custom_stopwords = $stopwords;
        $this->save_settings();
    }
    
    private function fetch_feed($feed_name, $feed_url) {
        $articles = array();
        
        try {
            $context = stream_context_create(array(
                'http' => array(
                    'timeout' => 30,
                    'user_agent' => 'Mozilla/5.0 (RSS Analyzer PHP)',
                    'header' => 'Accept: application/rss+xml, application/xml, text/xml',
                    'ignore_errors' => true,
                    'verify_peer' => false,
                    'verify_peer_name' => false
                )
            ));
            
            $xml_content = @file_get_contents($feed_url, false, $context);
            
            if ($xml_content === false) {
                error_log("Failed to fetch feed: $feed_name");
                return array();
            }
            
            // Try SimpleXML first (if available)
            if (function_exists('simplexml_load_string')) {
                $xml = @simplexml_load_string($xml_content);
                if ($xml !== false) {
                    return $this->parse_with_simplexml($xml, $feed_name);
                }
            }
            
            // Fallback to regex parsing if SimpleXML is not available
            return $this->parse_with_regex($xml_content, $feed_name);
            
        } catch (Exception $e) {
            error_log("Error fetching $feed_name: " . $e->getMessage());
        }
        
        return array();
    }
    
    private function parse_with_simplexml($xml, $feed_name) {
        $articles = array();
        $items = array();
        
        // RSS 2.0 format
        if (isset($xml->channel->item)) {
            $items = $xml->channel->item;
        }
        // Atom format
        elseif (isset($xml->entry)) {
            $items = $xml->entry;
        }
        
        $count = 0;
        foreach ($items as $item) {
            if ($count >= 25) break;
            
            $title = '';
            $description = '';
            $link = '';
            $pub_date = '';
            
            if (isset($item->title)) {
                $title = (string)$item->title;
                $description = isset($item->description) ? (string)$item->description : '';
                if (isset($item->summary) && empty($description)) {
                    $description = (string)$item->summary;
                }
                $link = isset($item->link) ? (string)$item->link : '';
                $pub_date = isset($item->pubDate) ? (string)$item->pubDate : '';
                if (isset($item->published) && empty($pub_date)) {
                    $pub_date = (string)$item->published;
                }
            }
            
            $description = strip_tags($description);
            
            $articles[] = array(
                'title' => $title,
                'description' => $description,
                'link' => $link,
                'published' => $pub_date,
                'feed_name' => $feed_name
            );
            
            $count++;
        }
        
        return $articles;
    }
    
    private function parse_with_regex($xml_content, $feed_name) {
        $articles = array();
        
        try {
            // Clean up the XML content
            $xml_content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $xml_content);
            
            // More comprehensive patterns for different RSS formats
            $item_patterns = array(
                // RSS 2.0 items (case insensitive, multiline)
                '/<item\b[^>]*>(.*?)<\/item>/is',
                // Atom entries
                '/<entry\b[^>]*>(.*?)<\/entry>/is'
            );
            
            $items_found = array();
            
            foreach ($item_patterns as $pattern) {
                if (preg_match_all($pattern, $xml_content, $matches)) {
                    $items_found = $matches[1];
                    error_log("Found " . count($items_found) . " items using pattern for $feed_name");
                    break;
                }
            }
            
            if (empty($items_found)) {
                error_log("No items found with any pattern for $feed_name");
                // Debug: show first 500 chars of content
                error_log("Content preview: " . substr($xml_content, 0, 500));
                return array();
            }
            
            $count = 0;
            foreach ($items_found as $item_content) {
                if ($count >= 25) break;
                
                $article = $this->extract_article_data($item_content, $feed_name);
                
                if (!empty($article['title'])) {
                    $articles[] = $article;
                    $count++;
                }
            }
            
            error_log("Successfully parsed " . count($articles) . " articles for $feed_name");
            
        } catch (Exception $e) {
            error_log("Regex parsing error for $feed_name: " . $e->getMessage());
        }
        
        return $articles;
    }
    
    private function extract_article_data($item_content, $feed_name) {
        // Extract title
        $title = $this->extract_xml_tag($item_content, 'title');
        
        // Extract description (try multiple fields)
        $description = '';
        $desc_fields = array('description', 'summary', 'content', 'content:encoded');
        foreach ($desc_fields as $field) {
            $description = $this->extract_xml_tag($item_content, $field);
            if (!empty($description)) break;
        }
        
        // Extract link
        $link = $this->extract_xml_tag($item_content, 'link');
        if (empty($link)) {
            // Try to extract href attribute from link tag
            if (preg_match('/<link[^>]*href=["\']([^"\']*)["\'][^>]*>/i', $item_content, $link_matches)) {
                $link = $link_matches[1];
            } elseif (preg_match('/<link[^>]*>([^<]*)<\/link>/i', $item_content, $link_matches)) {
                $link = trim($link_matches[1]);
            }
        }
        
        // Extract publication date
        $pub_date = '';
        $date_fields = array('pubDate', 'published', 'updated', 'dc:date');
        foreach ($date_fields as $field) {
            $pub_date = $this->extract_xml_tag($item_content, $field);
            if (!empty($pub_date)) break;
        }
        
        // Clean up the extracted data
        $title = $this->clean_text($title);
        $description = $this->clean_text($description);
        $link = trim($link);
        $pub_date = trim($pub_date);
        
        return array(
            'title' => $title,
            'description' => $description,
            'link' => $link,
            'published' => $pub_date,
            'feed_name' => $feed_name
        );
    }
    
    private function extract_xml_tag($content, $tag) {
        // Handle namespaced tags
        $tag_escaped = preg_quote($tag, '/');
        $patterns = array(
            // Standard tag
            '/<' . $tag_escaped . '(?:\s[^>]*)?>[\s]*<!\[CDATA\[(.*?)\]\]>[\s]*<\/' . $tag_escaped . '>/is',
            '/<' . $tag_escaped . '(?:\s[^>]*)?>([^<]*)<\/' . $tag_escaped . '>/is',
            // Self-closing or empty tag
            '/<' . $tag_escaped . '(?:\s[^>]*)?\/>/',
            // Tag with attributes but no content
            '/<' . $tag_escaped . '(?:\s[^>]*)?>[\s]*<\/' . $tag_escaped . '>/is'
        );
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                if (isset($matches[1])) {
                    return trim($matches[1]);
                }
            }
        }
        
        return '';
    }
    
    private function clean_text($text) {
        if (empty($text)) return '';
        
        // Remove HTML tags
        $text = strip_tags($text);
        
        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Clean up whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        
        return trim($text);
    }
    
    private function extract_words($text) {
        if (empty($text)) {
            return array();
        }
        
        $words = array();
        if (preg_match_all('/\b[a-zA-Z]{3,}\b/', strtolower($text), $matches)) {
            $words = $matches[0];
        }
        
        return $words;
    }
    
    public function analyze_feeds() {
        try {
            error_log("Starting feed analysis...");
            
            $all_articles = array();
            $feed_word_counts = array();
            $feed_word_sources = array();
            
            if (empty($this->selected_feeds)) {
                error_log("No feeds selected");
                throw new Exception("No feeds selected for analysis");
            }
            
            foreach ($this->selected_feeds as $feed_name => $feed_url) {
                error_log("Processing feed: $feed_name");
                
                try {
                    $articles = $this->fetch_feed($feed_name, $feed_url);
                    error_log("Fetched " . count($articles) . " articles from $feed_name");
                    
                    if (empty($articles)) {
                        error_log("No articles found for $feed_name");
                        continue;
                    }
                    
                    $all_articles = array_merge($all_articles, $articles);
                    
                    $feed_words = array();
                    $word_to_articles = array();
                    
                    foreach ($articles as $article) {
                        if (!is_array($article) || !isset($article['title']) || !isset($article['description'])) {
                            error_log("Invalid article structure in $feed_name");
                            continue;
                        }
                        
                        $title_words = $this->extract_words($article['title']);
                        $desc_words = $this->extract_words($article['description']);
                        $article_words = array_unique(array_merge($title_words, $desc_words));
                        
                        $feed_words = array_merge($feed_words, $title_words, $desc_words);
                        
                        foreach ($article_words as $word) {
                            if (!isset($word_to_articles[$word])) {
                                $word_to_articles[$word] = array();
                            }
                            $word_to_articles[$word][] = array(
                                'title' => isset($article['title']) ? $article['title'] : 'Untitled',
                                'link' => isset($article['link']) ? $article['link'] : '',
                                'published' => isset($article['published']) ? $article['published'] : ''
                            );
                        }
                    }
                    
                    if (empty($feed_words)) {
                        error_log("No words extracted from $feed_name");
                        continue;
                    }
                    
                    $all_stopwords = array_merge($this->default_stopwords, $this->custom_stopwords);
                    $filtered_feed_words = array_filter($feed_words, function($word) use ($all_stopwords) {
                        return !in_array($word, $all_stopwords);
                    });
                    
                    if (empty($filtered_feed_words)) {
                        error_log("No words remaining after filtering stopwords for $feed_name");
                        continue;
                    }
                    
                    $word_counts = array_count_values($filtered_feed_words);
                    arsort($word_counts);
                    
                    $feed_word_counts[$feed_name] = array();
                    foreach ($word_counts as $word => $count) {
                        $feed_word_counts[$feed_name][] = array(
                            'word' => $word,
                            'frequency' => $count
                        );
                    }
                    
                    $feed_word_sources[$feed_name] = array();
                    foreach ($word_counts as $word => $count) {
                        if (isset($word_to_articles[$word])) {
                            $feed_word_sources[$feed_name][$word] = array_slice($word_to_articles[$word], 0, 5);
                        }
                    }
                    
                    error_log("Successfully processed $feed_name with " . count($word_counts) . " unique words");
                    
                } catch (Exception $e) {
                    error_log("Error processing feed $feed_name: " . $e->getMessage());
                    continue; // Skip this feed and continue with others
                }
            }
            
            if (empty($all_articles)) {
                error_log("No articles found from any feed");
                return array(
                    'articles' => array(),
                    'word_frequency' => array(),
                    'feed_word_counts' => array(),
                    'feed_word_sources' => array(),
                    'total_articles' => 0,
                    'total_unique_words' => 0,
                    'timestamp' => date('c'),
                    'debug' => 'No articles found from any selected feeds'
                );
            }
            
            error_log("Processing " . count($all_articles) . " total articles");
            
            $all_words = array();
            foreach ($all_articles as $article) {
                if (!is_array($article)) continue;
                
                $title_words = $this->extract_words(isset($article['title']) ? $article['title'] : '');
                $desc_words = $this->extract_words(isset($article['description']) ? $article['description'] : '');
                $all_words = array_merge($all_words, $title_words, $desc_words);
            }
            
            $all_stopwords = array_merge($this->default_stopwords, $this->custom_stopwords);
            $filtered_words = array_filter($all_words, function($word) use ($all_stopwords) {
                return !in_array($word, $all_stopwords);
            });
            
            if (empty($filtered_words)) {
                error_log("No words remaining after global filtering");
                return array(
                    'articles' => $all_articles,
                    'word_frequency' => array(),
                    'feed_word_counts' => $feed_word_counts,
                    'feed_word_sources' => $feed_word_sources,
                    'total_articles' => count($all_articles),
                    'total_unique_words' => 0,
                    'timestamp' => date('c'),
                    'debug' => 'No words remaining after stopword filtering'
                );
            }
            
            $word_counts = array_count_values($filtered_words);
            arsort($word_counts);
            
            $word_frequency = array();
            $count = 0;
            foreach ($word_counts as $word => $frequency) {
                if ($count >= 100) break;
                $word_frequency[] = array(
                    'word' => $word,
                    'frequency' => $frequency
                );
                $count++;
            }
            
            error_log("Analysis completed successfully with " . count($word_frequency) . " top words");
            
            return array(
                'articles' => $all_articles,
                'word_frequency' => $word_frequency,
                'feed_word_counts' => $feed_word_counts,
                'feed_word_sources' => $feed_word_sources,
                'total_articles' => count($all_articles),
                'total_unique_words' => count($word_counts),
                'timestamp' => date('c')
            );
            
        } catch (Exception $e) {
            error_log("Fatal error in analyze_feeds: " . $e->getMessage());
            throw $e;
        }
    }
}

// Initialize the analyzer
$analyzer = new RSSWordAnalyzer();

// Handle API requests
if (isset($_GET['api'])) {
    header('Content-Type: application/json');
    
    $api_endpoint = $_GET['api'];
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($api_endpoint) {
        case 'feeds':
            if ($method === 'GET') {
                echo json_encode($analyzer->get_feeds());
            } elseif ($method === 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);
                if (isset($input['feeds'])) {
                    $analyzer->update_feeds($input['feeds']);
                    echo json_encode(array('status' => 'success'));
                } else {
                    echo json_encode(array('error' => 'No feeds provided'));
                }
            }
            break;
            
        case 'stopwords':
            if ($method === 'GET') {
                echo json_encode($analyzer->get_stopwords());
            } elseif ($method === 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);
                if (isset($input['stopwords'])) {
                    $analyzer->update_stopwords($input['stopwords']);
                    echo json_encode(array('status' => 'success'));
                } else {
                    echo json_encode(array('error' => 'No stopwords provided'));
                }
            }
            break;
            
        case 'analyze':
            try {
                error_log("Starting analysis API call");
                
                // Enable error reporting for debugging
                ini_set('display_errors', 1);
                ini_set('display_startup_errors', 1);
                error_reporting(E_ALL);
                
                $results = $analyzer->analyze_feeds();
                
                if (empty($results)) {
                    throw new Exception("Analysis returned empty results");
                }
                
                error_log("Analysis completed, returning results");
                echo json_encode($results);
                
            } catch (Exception $e) {
                error_log("API Error in analyze: " . $e->getMessage());
                error_log("Stack trace: " . $e->getTraceAsString());
                
                http_response_code(500);
                echo json_encode(array(
                    'error' => $e->getMessage(),
                    'debug_info' => array(
                        'selected_feeds' => count($analyzer->get_feeds()['selected_feeds']),
                        'timestamp' => date('c'),
                        'php_version' => PHP_VERSION
                    )
                ));
            }
            break;
            
        default:
            http_response_code(404);
            echo json_encode(array('error' => 'API endpoint not found'));
            break;
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RSS Word Frequency Analyzer - PHP</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 { font-size: 2.5rem; margin-bottom: 10px; }
        .header p { font-size: 1.1rem; opacity: 0.9; }
        .content { padding: 30px; }
        .section {
            margin-bottom: 30px;
            padding: 25px;
            border: 1px solid #e1e5e9;
            border-radius: 8px;
            background: #f8f9fa;
        }
        .section h2 { color: #2c3e50; margin-bottom: 20px; font-size: 1.4rem; }
        .feed-controls {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .feed-item {
            background: white;
            padding: 15px;
            border-radius: 6px;
            border: 1px solid #dee2e6;
            margin-bottom: 10px;
        }
        .feed-item label { display: flex; align-items: center; cursor: pointer; }
        .feed-item input { margin-right: 10px; }
        .custom-feed { display: flex; gap: 10px; margin-top: 15px; }
        .custom-feed input {
            flex: 1;
            padding: 10px;
            border: 1px solid #ced4da;
            border-radius: 4px;
        }
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1rem;
            transition: transform 0.2s;
        }
        .btn:hover { transform: translateY(-2px); }
        .btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
        .btn-secondary {
            background: none;
            border: 1px solid #667eea;
            color: #667eea;
        }
        .btn-secondary:hover {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
        }
        .stopwords-input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            min-height: 100px;
            font-family: inherit;
        }
        .results-section { display: none; }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        .stat-number { font-size: 2rem; font-weight: bold; margin-bottom: 5px; }
        .word-cloud { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 30px; }
        .word-tag {
            background: #e9ecef;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
            border: 1px solid #dee2e6;
        }
        .word-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .word-table th, .word-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        .word-table th { background: #f8f9fa; font-weight: 600; }
        .loading { text-align: center; padding: 40px; color: #6c757d; }
        .error {
            color: #dc3545;
            background: #f8d7da;
            padding: 15px;
            border-radius: 4px;
            margin: 15px 0;
        }
        .success {
            color: #155724;
            background: #d4edda;
            padding: 15px;
            border-radius: 4px;
            margin: 15px 0;
        }
        .feed-breakdown { margin-top: 30px; }
        .feed-breakdown h3 { color: #2c3e50; margin-bottom: 20px; font-size: 1.3rem; }
        .feed-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            margin-bottom: 20px;
            border-bottom: 1px solid #dee2e6;
        }
        .feed-tab {
            padding: 10px 16px;
            border: none;
            background: none;
            color: #6c757d;
            cursor: pointer;
            border-radius: 6px 6px 0 0;
            font-size: 0.9rem;
            transition: all 0.2s;
        }
        .feed-tab.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .feed-content { display: none; }
        .feed-content.active { display: block; }
        .feed-word-cloud { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 20px; }
        .feed-word-tag {
            background: #e3f2fd;
            border: 1px solid #90caf9;
            padding: 4px 10px;
            border-radius: 16px;
            font-size: 0.85rem;
            color: #1565c0;
            cursor: pointer;
            transition: all 0.2s;
        }
        .feed-word-tag:hover { background: #90caf9; color: white; }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 8px;
            width: 80%;
            max-width: 800px;
            max-height: 80vh;
            overflow-y: auto;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #dee2e6;
        }
        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
        }
        .close:hover { color: #667eea; }
        .sources-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 10px;
        }
        .source-link {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 4px;
            border: 1px solid #dee2e6;
            transition: all 0.2s;
        }
        .source-link:hover { background: #e9ecef; }
        .source-link a {
            text-decoration: none;
            color: #495057;
            font-weight: 500;
            display: block;
            margin-bottom: 4px;
        }
        .source-link a:hover { color: #667eea; }
        .source-date { font-size: 0.85rem; color: #6c757d; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>RSS Word Frequency Analyzer</h1>
            <p>Analyze word frequency from RSS feeds - Clean PHP Version</p>
        </div>
        
        <div class="content">
            <div class="section">
                <h2>RSS Feed Selection</h2>
                <div class="feed-controls">
                    <button class="btn btn-secondary" onclick="selectAllFeeds()">Select All</button>
                    <button class="btn btn-secondary" onclick="deselectAllFeeds()">Deselect All</button>
                </div>
                <div id="feeds-container"></div>
                <div class="custom-feed">
                    <input type="text" id="custom-feed-name" placeholder="Feed Name">
                    <input type="url" id="custom-feed-url" placeholder="RSS Feed URL">
                    <button class="btn" onclick="addCustomFeed()">Add Feed</button>
                </div>
            </div>
            
            <div class="section">
                <h2>Custom Stop Words</h2>
                <textarea id="stopwords" class="stopwords-input" 
                         placeholder="Enter words to exclude, one per line..."></textarea>
                <button class="btn" onclick="saveStopwords()" style="margin-top: 15px;">Save Stop Words</button>
            </div>
            
            <div class="section">
                <h2>Analysis</h2>
                <button class="btn" id="analyze-btn" onclick="performAnalysis()">Analyze Word Frequency</button>
                <div id="loading" class="loading" style="display: none;">
                    <p>Fetching and analyzing RSS feeds...</p>
                </div>
            </div>
            
            <div class="section results-section" id="results-section">
                <h2>Results</h2>
                <div id="stats" class="stats"></div>
                <div id="word-cloud" class="word-cloud"></div>
                <table class="word-table">
                    <thead>
                        <tr><th>Rank</th><th>Word</th><th>Frequency</th></tr>
                    </thead>
                    <tbody id="word-table-body"></tbody>
                </table>
                
                <div class="feed-breakdown" id="feed-breakdown">
                    <h3>Word Frequency by Feed</h3>
                    <div class="feed-tabs" id="feed-tabs"></div>
                    <div id="feed-contents"></div>
                </div>
            </div>
        </div>
    </div>

    <div id="word-sources-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modal-word-title">Articles containing word</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div id="modal-sources-content"></div>
        </div>
    </div>

    <script>
        let currentFeeds = {};
        let currentResults = null;
        let currentWordSources = null;
        
        document.addEventListener('DOMContentLoaded', function() {
            loadFeeds();
            loadStopwords();
        });
        
        async function makeAjaxCall(endpoint, method, data = null) {
            const options = {
                method: method,
                headers: { 'Content-Type': 'application/json' }
            };
            
            if (data && method !== 'GET') {
                options.body = JSON.stringify(data);
            }
            
            const apiParam = endpoint.replace('/api/', '');
            const url = `?api=${apiParam}`;
            
            const response = await fetch(url, options);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            return await response.json();
        }
        
        async function loadFeeds() {
            try {
                const data = await makeAjaxCall('/api/feeds', 'GET');
                currentFeeds = data.selected_feeds;
                renderFeeds(data.selected_feeds, data.default_feeds);
            } catch (error) {
                console.error('Error loading feeds:', error);
                showError('Error loading feeds: ' + error.message);
                initializeDefaultFeeds();
            }
        }
        
        function initializeDefaultFeeds() {
            const defaultFeeds = {
                'BBC News': 'http://feeds.bbci.co.uk/news/rss.xml',
                'TechCrunch': 'http://feeds.feedburner.com/TechCrunch',
                'Hacker News': 'https://hnrss.org/frontpage'
            };
            currentFeeds = defaultFeeds;
            renderFeeds(defaultFeeds, defaultFeeds);
        }
        
        function renderFeeds(selectedFeeds, defaultFeeds) {
            const container = document.getElementById('feeds-container');
            container.innerHTML = '';
            
            for (const [name, url] of Object.entries(defaultFeeds)) {
                const feedItem = document.createElement('div');
                feedItem.className = 'feed-item';
                feedItem.innerHTML = `<label><input type="checkbox" ${
                    selectedFeeds[name] ? 'checked' : ''
                } onchange="toggleFeed('${name.replace(/'/g, "\\'")}', '${url.replace(/'/g, "\\'")}', this.checked)"><div><strong>${
                    name
                }</strong><br><small style="color: #6c757d;">${
                    url.length > 50 ? url.substring(0, 50) + '...' : url
                }</small></div></label>`;
                container.appendChild(feedItem);
            }
        }
        
        function selectAllFeeds() {
            document.querySelectorAll('#feeds-container input[type="checkbox"]').forEach(cb => {
                if (!cb.checked) {
                    cb.checked = true;
                    cb.dispatchEvent(new Event('change'));
                }
            });
        }
        
        function deselectAllFeeds() {
            document.querySelectorAll('#feeds-container input[type="checkbox"]').forEach(cb => {
                if (cb.checked) {
                    cb.checked = false;
                    cb.dispatchEvent(new Event('change'));
                }
            });
        }
        
        function toggleFeed(name, url, checked) {
            if (checked) {
                currentFeeds[name] = url;
            } else {
                delete currentFeeds[name];
            }
            saveFeeds();
        }
        
        async function saveFeeds() {
            try {
                await makeAjaxCall('/api/feeds', 'POST', {feeds: currentFeeds});
            } catch (error) {
                console.error('Error saving feeds:', error);
                showError('Error saving feeds: ' + error.message);
            }
        }
        
        function addCustomFeed() {
            const name = document.getElementById('custom-feed-name').value.trim();
            const url = document.getElementById('custom-feed-url').value.trim();
            
            if (!name || !url) {
                showError('Please enter both feed name and URL');
                return;
            }
            
            currentFeeds[name] = url;
            saveFeeds();
            loadFeeds();
            
            document.getElementById('custom-feed-name').value = '';
            document.getElementById('custom-feed-url').value = '';
            
            showSuccess('Custom feed added successfully!');
        }
        
        async function loadStopwords() {
            try {
                const data = await makeAjaxCall('/api/stopwords', 'GET');
                document.getElementById('stopwords').value = data.custom_stopwords.join('\\n');
            } catch (error) {
                console.error('Error loading stopwords:', error);
                showError('Error loading stopwords: ' + error.message);
            }
        }
        
        async function saveStopwords() {
            const stopwordsText = document.getElementById('stopwords').value;
            const stopwords = stopwordsText.split('\\n')
                .map(word => word.trim().toLowerCase())
                .filter(word => word.length > 0);
            
            try {
                await makeAjaxCall('/api/stopwords', 'POST', {stopwords: stopwords});
                showSuccess('Stop words saved successfully!');
            } catch (error) {
                console.error('Error saving stopwords:', error);
                showError('Error saving stopwords: ' + error.message);
            }
        }
        
        async function performAnalysis() {
            if (Object.keys(currentFeeds).length === 0) {
                showError('Please select at least one RSS feed');
                return;
            }
            
            const analyzeBtn = document.getElementById('analyze-btn');
            const loading = document.getElementById('loading');
            const resultsSection = document.getElementById('results-section');
            
            analyzeBtn.disabled = true;
            loading.style.display = 'block';
            resultsSection.style.display = 'none';
            
            try {
                const data = await makeAjaxCall('/api/analyze', 'GET');
                
                if (data.error) {
                    showError('Analysis error: ' + data.error);
                    return;
                }
                
                displayResults(data);
                resultsSection.style.display = 'block';
                
            } catch (error) {
                console.error('Error performing analysis:', error);
                showError('Error performing analysis: ' + error.message);
            } finally {
                analyzeBtn.disabled = false;
                loading.style.display = 'none';
            }
        }
        
        function displayResults(data) {
            currentResults = data;
            currentWordSources = data.feed_word_sources || {};
            
            document.getElementById('stats').innerHTML = 
                `<div class="stat-card"><div class="stat-number">${data.total_articles || 0}</div><div>Articles Analyzed</div></div>` +
                `<div class="stat-card"><div class="stat-number">${data.total_unique_words || 0}</div><div>Unique Words</div></div>` +
                `<div class="stat-card"><div class="stat-number">${Object.keys(currentFeeds).length}</div><div>RSS Feeds</div></div>`;
            
            if (data.word_frequency && data.word_frequency.length > 0) {
                displayWordCloud(data.word_frequency.slice(0, 30));
                displayWordTable(data.word_frequency);
            }
            
            if (data.feed_word_counts) {
                displayFeedBreakdown(data.feed_word_counts);
            }
        }
        
        function displayWordCloud(wordFrequency) {
            const wordCloud = document.getElementById('word-cloud');
            wordCloud.innerHTML = '';
            
            wordFrequency.forEach((item, index) => {
                const wordTag = document.createElement('span');
                wordTag.className = 'word-tag';
                const fontSize = Math.max(0.8, 1.2 - (index / 30) * 0.4);
                wordTag.style.fontSize = fontSize + 'rem';
                wordTag.textContent = `${item.word} (${item.frequency})`;
                wordCloud.appendChild(wordTag);
            });
        }
        
        function displayWordTable(wordFrequency) {
            const tableBody = document.getElementById('word-table-body');
            tableBody.innerHTML = '';
            
            wordFrequency.slice(0, 50).forEach((item, index) => {
                const row = document.createElement('tr');
                row.innerHTML = `<td>${index + 1}</td><td><strong>${item.word}</strong></td><td>${item.frequency}</td>`;
                tableBody.appendChild(row);
            });
        }
        
        function displayFeedBreakdown(feedWordCounts) {
            const feedTabs = document.getElementById('feed-tabs');
            const feedContents = document.getElementById('feed-contents');
            
            feedTabs.innerHTML = '';
            feedContents.innerHTML = '';
            
            const feedNames = Object.keys(feedWordCounts);
            
            feedNames.forEach((feedName, index) => {
                const tab = document.createElement('button');
                tab.className = 'feed-tab' + (index === 0 ? ' active' : '');
                tab.textContent = feedName;
                tab.onclick = () => showFeedContent(feedName);
                feedTabs.appendChild(tab);
                
                const content = document.createElement('div');
                content.className = 'feed-content' + (index === 0 ? ' active' : '');
                content.id = 'feed-content-' + index;
                
                const words = feedWordCounts[feedName];
                if (words && words.length > 0) {
                    const feedWordCloud = document.createElement('div');
                    feedWordCloud.className = 'feed-word-cloud';
                    
                    words.slice(0, 15).forEach(item => {
                        const wordTag = document.createElement('span');
                        wordTag.className = 'feed-word-tag';
                        wordTag.textContent = `${item.word} (${item.frequency})`;
                        wordTag.onclick = () => showWordSources(item.word, feedName);
                        wordTag.title = 'Click to see source articles';
                        feedWordCloud.appendChild(wordTag);
                    });
                    
                    content.appendChild(feedWordCloud);
                    
                    const table = document.createElement('table');
                    table.className = 'word-table';
                    table.innerHTML = '<thead><tr><th>Rank</th><th>Word</th><th>Frequency</th><th>Sources</th></tr></thead>';
                    
                    const tbody = document.createElement('tbody');
                    words.slice(0, 20).forEach((item, i) => {
                        const row = document.createElement('tr');
                        row.innerHTML = `<td>${i + 1}</td><td><strong>${item.word}</strong></td><td>${item.frequency}</td><td>` +
                            `<button class="btn btn-secondary" style="font-size: 0.8rem; padding: 4px 8px;" onclick="showWordSources('${
                                item.word.replace(/'/g, "\\'")
                            }', '${feedName.replace(/'/g, "\\'")}')">View Sources</button></td>`;
                        tbody.appendChild(row);
                    });
                    table.appendChild(tbody);
                    content.appendChild(table);
                } else {
                    content.innerHTML = '<p style="color: #6c757d; padding: 20px;">No words found for this feed.</p>';
                }
                
                feedContents.appendChild(content);
            });
        }
        
        function showFeedContent(feedName) {
            document.querySelectorAll('.feed-tab').forEach(tab => {
                tab.classList.toggle('active', tab.textContent === feedName);
            });
            
            document.querySelectorAll('.feed-content').forEach((content, index) => {
                const tabText = document.querySelectorAll('.feed-tab')[index].textContent;
                content.classList.toggle('active', tabText === feedName);
            });
        }
        
        function showWordSources(word, feedName) {
            const modal = document.getElementById('word-sources-modal');
            const modalTitle = document.getElementById('modal-word-title');
            const modalContent = document.getElementById('modal-sources-content');
            
            modalTitle.textContent = `Articles containing "${word}" from ${feedName}`;
            
            const sources = currentWordSources[feedName] && currentWordSources[feedName][word] || [];
            
            if (sources.length === 0) {
                modalContent.innerHTML = '<p style="color: #6c757d;">No source articles found for this word.</p>';
            } else {
                let sourcesHtml = '<div class="sources-grid">';
                sources.forEach(source => {
                    const publishedDate = source.published ? new Date(source.published).toLocaleDateString() : 'Unknown date';
                    sourcesHtml += '<div class="source-link">';
                    if (source.link) {
                        sourcesHtml += `<a href="${source.link}" target="_blank" rel="noopener noreferrer">${
                            source.title || 'Untitled Article'
                        }</a>`;
                    } else {
                        sourcesHtml += `<span>${source.title || 'Untitled Article'}</span>`;
                    }
                    sourcesHtml += `<div class="source-date">${publishedDate}</div>`;
                    sourcesHtml += '</div>';
                });
                sourcesHtml += '</div>';
                modalContent.innerHTML = sourcesHtml;
            }
            
            modal.style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('word-sources-modal').style.display = 'none';
        }
        
        window.onclick = function(event) {
            const modal = document.getElementById('word-sources-modal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        }
        
        function showError(message) {
            const existing = document.querySelector('.error');
            if (existing) existing.remove();
            
            const error = document.createElement('div');
            error.className = 'error';
            error.textContent = message;
            document.querySelector('.content').insertBefore(error, document.querySelector('.content').firstChild);
            
            setTimeout(() => error.remove(), 8000);
        }
        
        function showSuccess(message) {
            const existing = document.querySelector('.success');
            if (existing) existing.remove();
            
            const success = document.createElement('div');
            success.className = 'success';
            success.textContent = message;
            document.querySelector('.content').insertBefore(success, document.querySelector('.content').firstChild);
            
            setTimeout(() => success.remove(), 3000);
        }
    </script>
</body>
</html>