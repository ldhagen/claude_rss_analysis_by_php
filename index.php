<?php
/**
 * RSS Word Frequency Analyzer with Top Words Selection
 * Enhanced version with configurable number of top words to display
 * 
 * Features:
 * - Multi-feed RSS/Atom analysis
 * - Configurable number of top words (10-1000)
 * - Source tracking and attribution
 * - Custom stopwords filtering
 * - Feed-specific breakdowns
 * - Responsive design
 * - Zero external dependencies
 */

// Handle AJAX request for word sources FIRST, before any output
if (isset($_POST['action']) && $_POST['action'] === 'get_sources') {
    header('Content-Type: application/json');
    
    $word = $_POST['word'] ?? '';
    $selected_feeds = $_POST['feeds'] ?? [];
    $custom_stopwords_input = trim($_POST['custom_stopwords'] ?? '');
    $top_words_count = max(10, min(1000, (int)($_POST['top_words_count'] ?? 50)));
    $view_filter = $_POST['view_filter'] ?? 'all';
    
    // Load saved settings
    $custom_feeds = [];
    $saved_custom_stopwords = [];
    if (file_exists('settings.json')) {
        $settings = json_decode(file_get_contents('settings.json'), true);
        if ($settings) {
            $saved_custom_stopwords = $settings['custom_stopwords'] ?? [];
            $custom_feeds = $settings['custom_feeds'] ?? [];
        }
    }
    
    // Process custom stopwords
    $custom_stopwords = $saved_custom_stopwords;
    if ($custom_stopwords_input) {
        $words = array_map('trim', explode(',', strtolower($custom_stopwords_input)));
        $custom_stopwords = array_merge($custom_stopwords, array_filter($words));
        $custom_stopwords = array_unique($custom_stopwords);
    }
    
    // Re-run analysis to get sources
    if (!empty($selected_feeds)) {
        try {
            $analyzer = new RSSWordAnalyzer();
            $analyzer->processFeeds($selected_feeds, $custom_feeds, $custom_stopwords, $top_words_count);
            $sources = $analyzer->getWordSources($word);
            
            // Filter sources by selected feed if not showing all
            if ($view_filter !== 'all') {
                $sources = array_filter($sources, function($source) use ($view_filter) {
                    return $source['feed'] === $view_filter;
                });
            }
            
            echo json_encode(['sources' => array_values($sources)]);
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['sources' => []]);
    }
    exit;
}

class RSSWordAnalyzer {
    private $default_feeds = [
        'BBC News' => 'http://feeds.bbci.co.uk/news/rss.xml',
        'Reuters' => 'https://feeds.reuters.com/reuters/topNews',
        'AP News' => 'https://apnews.com/apf-topnews',
        'CNN' => 'http://rss.cnn.com/rss/edition.rss',
        'TechCrunch' => 'https://techcrunch.com/feed/',
        'The Guardian' => 'https://www.theguardian.com/world/rss',
        'NPR' => 'https://feeds.npr.org/1001/rss.xml'
    ];
    
    private $default_stopwords = [
        'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by',
        'from', 'up', 'about', 'into', 'through', 'during', 'before', 'after', 'above', 'below',
        'between', 'among', 'under', 'over', 'within', 'without', 'against', 'toward', 'upon',
        'is', 'am', 'are', 'was', 'were', 'being', 'been', 'be', 'have', 'has', 'had', 'do',
        'does', 'did', 'will', 'would', 'could', 'should', 'may', 'might', 'must', 'can', 'shall',
        'i', 'you', 'he', 'she', 'it', 'we', 'they', 'me', 'him', 'her', 'us', 'them', 'my',
        'your', 'his', 'her', 'its', 'our', 'their', 'mine', 'yours', 'ours', 'theirs', 'this',
        'that', 'these', 'those', 'who', 'what', 'where', 'when', 'why', 'how', 'which', 'whose',
        'said', 'says', 'told', 'tells', 'asked', 'asks', 'according', 'reported', 'reports',
        'also', 'just', 'only', 'even', 'still', 'now', 'then', 'here', 'there', 'more', 'most',
        'some', 'any', 'all', 'each', 'every', 'both', 'either', 'neither', 'none', 'one', 'two',
        'three', 'four', 'five', 'first', 'second', 'third', 'last', 'next', 'previous', 'new',
        'old', 'good', 'bad', 'big', 'small', 'large', 'little', 'long', 'short', 'high', 'low'
    ];
    
    private $settings_file = 'settings.json';
    private $word_frequency = [];
    private $word_sources = [];
    private $feed_stats = [];
    
    public function __construct() {
        $this->loadSettings();
    }
    
    private function loadSettings() {
        if (file_exists($this->settings_file)) {
            $settings = json_decode(file_get_contents($this->settings_file), true);
            if ($settings && isset($settings['custom_stopwords'])) {
                $this->default_stopwords = array_merge(
                    $this->default_stopwords, 
                    $settings['custom_stopwords']
                );
            }
        }
    }
    
    private function saveSettings($custom_stopwords, $custom_feeds) {
        $settings = [
            'custom_stopwords' => $custom_stopwords,
            'custom_feeds' => $custom_feeds,
            'last_updated' => date('Y-m-d H:i:s')
        ];
        file_put_contents($this->settings_file, json_encode($settings, JSON_PRETTY_PRINT));
    }
    
    public function fetchFeed($url) {
        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'user_agent' => 'RSS Word Analyzer/1.0'
            ]
        ]);
        
        $content = @file_get_contents($url, false, $context);
        if ($content === false) {
            return false;
        }
        
        // Try SimpleXML first
        if (extension_loaded('simplexml')) {
            libxml_use_internal_errors(true);
            $xml = @simplexml_load_string($content);
            if ($xml !== false) {
                return $this->parseXMLFeed($xml);
            }
        }
        
        // Fallback to regex parsing
        return $this->parseRegexFeed($content);
    }
    
    private function parseXMLFeed($xml) {
        $articles = [];
        $namespaces = $xml->getNamespaces(true);
        
        // RSS 2.0
        if (isset($xml->channel->item)) {
            foreach ($xml->channel->item as $item) {
                $articles[] = [
                    'title' => (string)$item->title,
                    'description' => (string)$item->description,
                    'link' => (string)$item->link,
                    'content' => isset($item->children($namespaces['content'] ?? '')->encoded) 
                        ? (string)$item->children($namespaces['content'])->encoded 
                        : (string)$item->description
                ];
            }
        }
        // Atom
        elseif (isset($xml->entry)) {
            foreach ($xml->entry as $entry) {
                $articles[] = [
                    'title' => (string)$entry->title,
                    'description' => isset($entry->summary) ? (string)$entry->summary : '',
                    'link' => (string)$entry->link['href'],
                    'content' => isset($entry->content) ? (string)$entry->content : (string)$entry->summary
                ];
            }
        }
        
        return array_slice($articles, 0, 25); // Limit to 25 articles
    }
    
    private function parseRegexFeed($content) {
        $articles = [];
        
        // RSS item parsing
        if (preg_match_all('/<item[^>]*>(.*?)<\/item>/s', $content, $items)) {
            foreach ($items[1] as $item) {
                $article = [];
                
                if (preg_match('/<title[^>]*>(.*?)<\/title>/s', $item, $title)) {
                    $article['title'] = html_entity_decode(strip_tags($title[1]));
                }
                
                if (preg_match('/<description[^>]*>(.*?)<\/description>/s', $item, $desc)) {
                    $article['description'] = html_entity_decode(strip_tags($desc[1]));
                }
                
                if (preg_match('/<link[^>]*>(.*?)<\/link>/s', $item, $link)) {
                    $article['link'] = trim($link[1]);
                }
                
                // Try content:encoded for full content
                if (preg_match('/<content:encoded[^>]*>(.*?)<\/content:encoded>/s', $item, $content)) {
                    $article['content'] = html_entity_decode(strip_tags($content[1]));
                } else {
                    $article['content'] = $article['description'] ?? '';
                }
                
                if (!empty($article['title'])) {
                    $articles[] = $article;
                }
            }
        }
        
        // Atom entry parsing
        if (empty($articles) && preg_match_all('/<entry[^>]*>(.*?)<\/entry>/s', $content, $entries)) {
            foreach ($entries[1] as $entry) {
                $article = [];
                
                if (preg_match('/<title[^>]*>(.*?)<\/title>/s', $entry, $title)) {
                    $article['title'] = html_entity_decode(strip_tags($title[1]));
                }
                
                if (preg_match('/<summary[^>]*>(.*?)<\/summary>/s', $entry, $summary)) {
                    $article['description'] = html_entity_decode(strip_tags($summary[1]));
                }
                
                if (preg_match('/<link[^>]*href=["\']([^"\']*)["\'][^>]*>/s', $entry, $link)) {
                    $article['link'] = $link[1];
                }
                
                if (preg_match('/<content[^>]*>(.*?)<\/content>/s', $entry, $content)) {
                    $article['content'] = html_entity_decode(strip_tags($content[1]));
                } else {
                    $article['content'] = $article['description'] ?? '';
                }
                
                if (!empty($article['title'])) {
                    $articles[] = $article;
                }
            }
        }
        
        return array_slice($articles, 0, 25);
    }
    
    public function analyzeWords($articles, $feed_name, $stopwords) {
        $words = [];
        $source_count = 0;
        
        foreach ($articles as $article) {
            $text = $article['title'] . ' ' . $article['content'];
            $text = strtolower(preg_replace('/[^\w\s]/', ' ', $text));
            $text_words = array_filter(explode(' ', $text), function($word) use ($stopwords) {
                return strlen($word) > 2 && !in_array($word, $stopwords) && !is_numeric($word);
            });
            
            foreach ($text_words as $word) {
                if (!isset($words[$word])) {
                    $words[$word] = 0;
                }
                $words[$word]++;
                
                // Track sources (limit to 5 per word)
                if (!isset($this->word_sources[$word])) {
                    $this->word_sources[$word] = [];
                }
                if (count($this->word_sources[$word]) < 5) {
                    $this->word_sources[$word][] = [
                        'title' => $article['title'],
                        'link' => $article['link'] ?? '',
                        'feed' => $feed_name
                    ];
                }
            }
            $source_count++;
        }
        
        $this->feed_stats[$feed_name] = [
            'articles' => $source_count,
            'unique_words' => count($words),
            'top_words' => array_slice(array_keys(arsort($words) ? $words : []), 0, 10)
        ];
        
        return $words;
    }
    
    public function processFeeds($selected_feeds, $custom_feeds, $custom_stopwords, $top_words_count = 50) {
        $stopwords = array_merge($this->default_stopwords, $custom_stopwords);
        $all_feeds = array_merge($this->default_feeds, $custom_feeds);
        
        $this->word_frequency = [];
        $this->word_sources = [];
        $this->feed_stats = [];
        $this->feed_word_frequency = []; // Store per-feed word frequencies
        
        foreach ($selected_feeds as $feed_name) {
            if (isset($all_feeds[$feed_name])) {
                $articles = $this->fetchFeed($all_feeds[$feed_name]);
                if ($articles) {
                    $words = $this->analyzeWords($articles, $feed_name, $stopwords);
                    
                    // Store feed-specific word frequency
                    arsort($words);
                    $this->feed_word_frequency[$feed_name] = array_slice($words, 0, $top_words_count, true);
                    
                    // Add to global frequency
                    foreach ($words as $word => $count) {
                        if (!isset($this->word_frequency[$word])) {
                            $this->word_frequency[$word] = 0;
                        }
                        $this->word_frequency[$word] += $count;
                    }
                }
            }
        }
        
        // Sort by frequency and limit to requested number
        arsort($this->word_frequency);
        $this->word_frequency = array_slice($this->word_frequency, 0, $top_words_count, true);
        
        // Save settings
        $this->saveSettings($custom_stopwords, $custom_feeds);
        
        return $this->word_frequency;
    }
    
    public function getFeedWordFrequency($feed_name = null) {
        if ($feed_name && isset($this->feed_word_frequency[$feed_name])) {
            return $this->feed_word_frequency[$feed_name];
        }
        return $this->feed_word_frequency ?? [];
    }
    
    public function getWordSources($word) {
        return $this->word_sources[$word] ?? [];
    }
    
    public function getFeedStats() {
        return $this->feed_stats;
    }
}

// Initialize analyzer
$analyzer = new RSSWordAnalyzer();
$results = [];
$selected_feeds = [];
$custom_feeds = [];
$custom_stopwords = [];
$top_words_count = 50; // Default value
$view_filter = 'all'; // New: all feeds or specific feed
$error_messages = [];

// Load saved settings
if (file_exists('settings.json')) {
    $settings = json_decode(file_get_contents('settings.json'), true);
    if ($settings) {
        $custom_stopwords = $settings['custom_stopwords'] ?? [];
        $custom_feeds = $settings['custom_feeds'] ?? [];
    }
}

// Process form submission
if ($_POST) {
    $selected_feeds = $_POST['feeds'] ?? [];
    $new_feed_name = trim($_POST['new_feed_name'] ?? '');
    $new_feed_url = trim($_POST['new_feed_url'] ?? '');
    $custom_stopwords_input = trim($_POST['custom_stopwords'] ?? '');
    $top_words_count = max(10, min(1000, (int)($_POST['top_words_count'] ?? 50)));
    $view_filter = $_POST['view_filter'] ?? 'all';
    
    // Add new custom feed
    if ($new_feed_name && $new_feed_url) {
        if (filter_var($new_feed_url, FILTER_VALIDATE_URL)) {
            $custom_feeds[$new_feed_name] = $new_feed_url;
        } else {
            $error_messages[] = "Invalid URL for custom feed: $new_feed_url";
        }
    }
    
    // Process custom stopwords
    if ($custom_stopwords_input) {
        $words = array_map('trim', explode(',', strtolower($custom_stopwords_input)));
        $custom_stopwords = array_merge($custom_stopwords, array_filter($words));
        $custom_stopwords = array_unique($custom_stopwords);
    }
    
    // Process feeds if any are selected
    if (!empty($selected_feeds)) {
        try {
            $results = $analyzer->processFeeds($selected_feeds, $custom_feeds, $custom_stopwords, $top_words_count);
        } catch (Exception $e) {
            $error_messages[] = "Error processing feeds: " . $e->getMessage();
        }
    } else {
        $error_messages[] = "Please select at least one feed to analyze.";
    }
}

$all_feeds = array_merge($analyzer->default_feeds ?? [], $custom_feeds);
$feed_stats = $analyzer->getFeedStats();
$feed_word_frequencies = $analyzer->getFeedWordFrequency();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RSS Word Frequency Analyzer</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .header p {
            font-size: 1.1em;
            opacity: 0.9;
        }
        
        .content {
            padding: 30px;
        }
        
        .form-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 30px;
            border-left: 4px solid #4facfe;
        }
        
        .form-section h3 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 1.3em;
            display: flex;
            align-items: center;
        }
        
        .form-section h3:before {
            content: "⚙️";
            margin-right: 10px;
            font-size: 1.2em;
        }
        
        .form-grid {
            display: grid;
            gap: 20px;
        }
        
        @media (min-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr 1fr;
            }
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #2c3e50;
        }
        
        .feed-selection {
            max-height: 250px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            background: white;
        }
        
        .feed-item {
            display: flex;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        
        .feed-item:last-child {
            border-bottom: none;
        }
        
        .feed-item input[type="checkbox"] {
            margin-right: 10px;
            transform: scale(1.2);
        }
        
        .feed-item label {
            flex: 1;
            margin-bottom: 0;
            cursor: pointer;
            font-weight: normal;
        }
        
        input[type="text"], input[type="url"], input[type="number"], textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }
        
        input[type="text"]:focus, input[type="url"]:focus, input[type="number"]:focus, textarea:focus {
            outline: none;
            border-color: #4facfe;
            box-shadow: 0 0 0 3px rgba(79, 172, 254, 0.1);
        }
        
        .number-input-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .number-input-group input[type="number"] {
            width: 120px;
        }
        
        .number-input-group .range-info {
            color: #6c757d;
            font-size: 0.9em;
        }
        
        .btn {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 25px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(79, 172, 254, 0.3);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .error {
            background: #fff5f5;
            border: 1px solid #fed7d7;
            color: #c53030;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .results {
            margin-top: 30px;
        }
        
        .results h2 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 1.8em;
            text-align: center;
        }
        
        .stats-grid {
            display: grid;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        @media (min-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        
        .stat-card h4 {
            font-size: 2em;
            margin-bottom: 5px;
        }
        
        .stat-card p {
            font-size: 0.9em;
            opacity: 0.9;
        }
        
        .word-cloud {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .word-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
        }
        
        .word-tag {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .word-tag:hover {
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(79, 172, 254, 0.3);
        }
        
        .word-tag.large { font-size: 1.2em; padding: 10px 20px; }
        .word-tag.medium { font-size: 1.1em; }
        .word-tag.small { font-size: 0.9em; }
        
        .sources-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }
        
        .sources-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            border-radius: 15px;
            padding: 30px;
            max-width: 600px;
            max-height: 70vh;
            overflow-y: auto;
            width: 90%;
        }
        
        .sources-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .close-btn {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #999;
        }
        
        .source-item {
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }
        
        .source-item:last-child {
            border-bottom: none;
        }
        
        .source-title {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .source-feed {
            color: #666;
            font-size: 0.9em;
        }
        
        .source-link {
            color: #4facfe;
            text-decoration: none;
        }
        
        .source-link:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            .container {
                margin: 10px;
                border-radius: 10px;
            }
            
            .header {
                padding: 20px;
            }
            
            .header h1 {
                font-size: 2em;
            }
            
            .content {
                padding: 20px;
            }
            
            .form-section {
                padding: 20px;
            }
            
            .sources-content {
                width: 95%;
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 RSS Word Frequency Analyzer</h1>
            <p>Analyze word patterns across multiple RSS feeds with customizable top words selection</p>
        </div>
        
        <div class="content">
            <form method="post">
                <div class="form-section">
                    <h3>Feed Selection & Configuration</h3>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>📰 Select RSS Feeds:</label>
                            <div class="feed-selection">
                                <?php foreach ($all_feeds as $name => $url): ?>
                                    <div class="feed-item">
                                        <input type="checkbox" name="feeds[]" value="<?= htmlspecialchars($name) ?>" 
                                               id="feed_<?= md5($name) ?>"
                                               <?= in_array($name, $selected_feeds) ? 'checked' : '' ?>>
                                        <label for="feed_<?= md5($name) ?>">
                                            <?= htmlspecialchars($name) ?>
                                            <?php if (isset($custom_feeds[$name])): ?>
                                                <small>(Custom)</small>
                                            <?php endif; ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>🔢 Number of Top Words:</label>
                            <div class="number-input-group">
                                <input type="number" name="top_words_count" 
                                       value="<?= $top_words_count ?>" 
                                       min="10" max="1000" step="5">
                                <span class="range-info">(10-1000)</span>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>📊 View Results By:</label>
                            <select name="view_filter" style="width: 100%; padding: 12px; border: 2px solid #e1e5e9; border-radius: 8px; font-size: 14px;">
                                <option value="all" <?= $view_filter === 'all' ? 'selected' : '' ?>>All Feeds Combined</option>
                                <?php if (!empty($selected_feeds)): ?>
                                    <?php foreach ($selected_feeds as $feed_name): ?>
                                        <option value="<?= htmlspecialchars($feed_name) ?>" 
                                                <?= $view_filter === $feed_name ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($feed_name) ?> Only
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>➕ Add Custom Feed:</label>
                            <input type="text" name="new_feed_name" placeholder="Feed Name" 
                                   value="<?= htmlspecialchars($_POST['new_feed_name'] ?? '') ?>">
                            <input type="url" name="new_feed_url" placeholder="https://example.com/feed.xml" 
                                   value="<?= htmlspecialchars($_POST['new_feed_url'] ?? '') ?>" style="margin-top: 10px;">
                        </div>
                        
                        <div class="form-group">
                            <label>🚫 Custom Stopwords (comma-separated):</label>
                            <textarea name="custom_stopwords" rows="3" 
                                      placeholder="said, according, reported, news, today"><?= htmlspecialchars(implode(', ', $custom_stopwords)) ?></textarea>
                        </div>
                    </div>
                </div>
                
                <div style="text-align: center;">
                    <button type="submit" class="btn">🔍 Analyze Word Frequency</button>
                </div>
            </form>
            
            <?php if (!empty($error_messages)): ?>
                <div class="error">
                    <strong>⚠️ Errors:</strong>
                    <ul style="margin: 10px 0 0 20px;">
                        <?php foreach ($error_messages as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($results)): ?>
                <?php
                // Determine which results to display based on view filter
                $display_results = $results;
                $display_title = "All Feeds Combined";
                
                if ($view_filter !== 'all' && isset($feed_word_frequencies[$view_filter])) {
                    $display_results = $feed_word_frequencies[$view_filter];
                    $display_title = htmlspecialchars($view_filter) . " Only";
                }
                ?>
                
                <div class="results">
                    <h2>📈 Analysis Results - <?= $display_title ?> (Top <?= count($display_results) ?> Words)</h2>
                    
                    <div class="stats-grid">
                        <div class="stat-card">
                            <h4><?= count($display_results) ?></h4>
                            <p>Unique Words Found</p>
                        </div>
                        <div class="stat-card">
                            <h4><?= $view_filter === 'all' ? count($selected_feeds) : '1' ?></h4>
                            <p><?= $view_filter === 'all' ? 'Feeds Analyzed' : 'Feed Selected' ?></p>
                        </div>
                        <div class="stat-card">
                            <h4><?= array_sum($display_results) ?></h4>
                            <p>Total Word Occurrences</p>
                        </div>
                        <div class="stat-card">
                            <h4><?= $view_filter === 'all' ? array_sum(array_column($feed_stats, 'articles')) : ($feed_stats[$view_filter]['articles'] ?? 0) ?></h4>
                            <p>Articles Processed</p>
                        </div>
                    </div>
                    
                    <?php if ($view_filter !== 'all' && count($selected_feeds) > 1): ?>
                        <div class="word-cloud" style="background: #e8f4fd; border-left: 4px solid #2196f3;">
                            <div style="text-align: center; margin-bottom: 15px;">
                                <strong>🔍 Filtered View Active</strong><br>
                                <span style="color: #666; font-size: 0.9em;">
                                    Showing results for "<strong><?= htmlspecialchars($view_filter) ?></strong>" only. 
                                    <a href="#" onclick="document.getElementsByName('view_filter')[0].value='all'; document.querySelector('form').submit(); return false;" 
                                       style="color: #2196f3; text-decoration: none;">View all feeds</a>
                                </span>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="word-cloud">
                        <h3 style="text-align: center; margin-bottom: 20px; color: #2c3e50;">
                            🏷️ Word Frequency Cloud - <?= $display_title ?>
                        </h3>
                        <div class="word-tags">
                            <?php
                            if (!empty($display_results)) {
                                $max_count = max($display_results);
                                $min_count = min($display_results);
                                $range = $max_count - $min_count;
                                
                                foreach ($display_results as $word => $count):
                                    // Calculate size class based on frequency
                                    if ($range > 0) {
                                        $ratio = ($count - $min_count) / $range;
                                        if ($ratio > 0.7) $size_class = 'large';
                                        elseif ($ratio > 0.4) $size_class = 'medium';
                                        else $size_class = 'small';
                                    } else {
                                        $size_class = 'medium';
                                    }
                            ?>
                                <span class="word-tag <?= $size_class ?>" 
                                      onclick="showWordSources('<?= htmlspecialchars($word) ?>', <?= $count ?>, '<?= htmlspecialchars($view_filter) ?>')"
                                      title="<?= $count ?> occurrences">
                                    <?= htmlspecialchars($word) ?> (<?= $count ?>)
                                </span>
                            <?php 
                                endforeach;
                            } else {
                                echo '<p style="text-align: center; color: #666;">No words found for the selected filter.</p>';
                            }
                            ?>
                        </div>
                    </div>
                    
                    <?php if (!empty($feed_stats)): ?>
                        <div class="word-cloud">
                            <h3 style="text-align: center; margin-bottom: 20px; color: #2c3e50;">
                                📊 Feed Statistics
                            </h3>
                            <div style="overflow-x: auto;">
                                <table style="width: 100%; border-collapse: collapse;">
                                    <thead>
                                        <tr style="background: #f8f9fa;">
                                            <th style="padding: 12px; border: 1px solid #ddd; text-align: left;">Feed Name</th>
                                            <th style="padding: 12px; border: 1px solid #ddd; text-align: center;">Articles</th>
                                            <th style="padding: 12px; border: 1px solid #ddd; text-align: center;">Unique Words</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($feed_stats as $feed_name => $stats): ?>
                                            <tr>
                                                <td style="padding: 12px; border: 1px solid #ddd; font-weight: 600;">
                                                    <?= htmlspecialchars($feed_name) ?>
                                                </td>
                                                <td style="padding: 12px; border: 1px solid #ddd; text-align: center;">
                                                    <?= $stats['articles'] ?>
                                                </td>
                                                <td style="padding: 12px; border: 1px solid #ddd; text-align: center;">
                                                    <?= $stats['unique_words'] ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Sources Modal -->
    <div id="sourcesModal" class="sources-modal">
        <div class="sources-content">
            <div class="sources-header">
                <h3 id="modalTitle">Word Sources</h3>
                <button type="button" class="close-btn" onclick="closeSourcesModal()">&times;</button>
            </div>
            <div id="modalContent">
                <!-- Sources will be loaded here -->
            </div>
        </div>
    </div>
    
    <script>
        function showWordSources(word, count, viewFilter = 'all') {
            const modal = document.getElementById('sourcesModal');
            const title = document.getElementById('modalTitle');
            const content = document.getElementById('modalContent');
            
            const filterText = viewFilter !== 'all' ? ` (from ${viewFilter})` : '';
            title.textContent = `"${word}" - ${count} occurrences${filterText}`;
            content.innerHTML = '<p style="text-align: center; padding: 20px;">Loading sources...</p>';
            
            modal.style.display = 'block';
            
            // Make AJAX request to get sources
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_sources&word=${encodeURIComponent(word)}&view_filter=${encodeURIComponent(viewFilter)}&${getFormData()}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.sources && data.sources.length > 0) {
                    let html = '';
                    data.sources.forEach(source => {
                        html += `
                            <div class="source-item">
                                <div class="source-title">
                                    ${source.link ? 
                                        `<a href="${source.link}" target="_blank" class="source-link">${source.title}</a>` : 
                                        source.title
                                    }
                                </div>
                                <div class="source-feed">From: ${source.feed}</div>
                            </div>
                        `;
                    });
                    content.innerHTML = html;
                } else {
                    content.innerHTML = '<p style="text-align: center; padding: 20px; color: #666;">No sources available for this word.</p>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                content.innerHTML = '<p style="text-align: center; padding: 20px; color: #c53030;">Error loading sources.</p>';
            });
        }
        
        function closeSourcesModal() {
            document.getElementById('sourcesModal').style.display = 'none';
        }
        
        function getFormData() {
            const form = document.querySelector('form');
            const formData = new FormData(form);
            return new URLSearchParams(formData).toString();
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('sourcesModal');
            if (event.target === modal) {
                closeSourcesModal();
            }
        }
        
        // Keyboard navigation
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeSourcesModal();
            }
        });
    </script>
</body>
</html>