# RSS Word Frequency Analyzer

A PHP-based web application that analyzes word frequency from RSS feeds with source tracking, customizable filtering, and feed-specific result filtering. Built to work on shared hosting environments without external dependencies.

## ✨ Features

- **📊 Configurable Top Words**: Select anywhere from 10 to 1000 top words to display and analyze
- **📰 Multi-feed analysis**: Select from default feeds or add custom RSS/Atom feeds
- **🔍 Feed-Specific Filtering**: View results for all feeds combined or filter by individual RSS feed
- **🔍 Word frequency tracking**: Analyze most common words across all feeds with visual word cloud
- **📖 Source attribution**: Click on words to see which articles contain them via interactive modal
- **📈 Feed-specific breakdown**: View detailed statistics and word frequency per individual feed
- **🚫 Custom stopword filtering**: Add your own words to exclude from analysis
- **📱 Responsive design**: Modern, mobile-friendly interface with gradient styling
- **⚡ Zero dependencies**: Pure PHP with no external libraries required
- **🔧 Enhanced analytics**: Real-time statistics dashboard with feed performance metrics

## 🆕 What's New in v8

- **🎯 Feed-Specific Results**: New "View Results By" dropdown to filter results by individual RSS feeds
- **📊 Feed Comparison**: Easily compare word patterns between different news sources
- **🔍 Targeted Analysis**: Focus on specific feeds to see their unique vocabulary and themes
- **💡 Smart Filtering**: Switch between "All Feeds Combined" and individual feed views
- **📈 Dynamic Statistics**: Stats automatically update based on your selected filter
- **🏷️ Context-Aware Sources**: Word sources filtered by current view selection

## 🌟 Previous Updates (v7)

- **Configurable Word Count**: Choose exactly how many top words to analyze (10-1000 range)
- **Modern UI Redesign**: Completely refreshed interface with gradient backgrounds and improved UX
- **Enhanced Statistics**: Comprehensive analytics dashboard showing feed performance
- **Interactive Word Cloud**: Dynamic sizing based on word frequency with hover effects
- **AJAX-Powered Modals**: Smooth, fast source viewing without page reloads
- **Better Mobile Support**: Fully responsive design optimized for all devices
- **Performance Improvements**: Optimized memory usage and faster processing

## 🛠 Requirements

- **PHP 7.0 or higher**
- **Web server** (Apache, Nginx, or PHP development server)
- **Write permissions** for settings storage

**Note**: This application does not require the SimpleXML PHP extension. It includes a fallback regex-based RSS parser for maximum compatibility.

## 🚀 Quick Start

### Local Development

1. **Download or clone** the repository
2. **Navigate** to the directory containing `index.php`
3. **Start** the PHP development server:
   ```bash
   php -S localhost:8001
   ```
4. **Open** your browser to `http://localhost:8001`
5. **Select RSS feeds** and **set your desired number of top words**
6. **Click** "Analyze Word Frequency"

### Shared Hosting Deployment

1. **Upload** `index.php` to your web hosting directory (e.g., `public_html/rss-analyzer/`)
2. **Set directory permissions** to allow file writing:
   ```bash
   chmod 755 /path/to/rss-analyzer/
   ```
3. **Access** via web browser at your domain
4. **Configure** and analyze!

## 📋 How to Use

### Basic Analysis
1. **📰 Select Feeds**: Choose from the default feed list or add custom RSS/Atom URLs
2. **🔢 Set Word Count**: Choose how many top words to analyze (10-1000)
3. **📊 Choose View Filter**: Select "All Feeds Combined" or a specific feed to analyze
4. **🚫 Add Stopwords**: Exclude common words like "said", "according", "reported"
5. **🔍 Analyze**: Click "Analyze Word Frequency" to process selected feeds

### Advanced Features

#### Feed-Specific Analysis
- **Individual Feed Focus**: Use the "View Results By" dropdown to see results for just one RSS feed
- **Feed Comparison**: Switch between feeds to compare their unique word patterns and themes
- **Source-Specific Insights**: Understand what topics and vocabulary each news source emphasizes
- **Contextual Sources**: When viewing a specific feed, clicking words shows sources from only that feed

#### Interactive Word Exploration
- **📊 View Statistics**: See comprehensive analytics for each feed
- **🏷️ Interactive Word Cloud**: Click any word to view source articles
- **📈 Feed Breakdown**: Monitor individual feed performance and contribution
- **⚙️ Custom Configuration**: Add your own feeds and stopwords that persist between sessions

### Results Interface
- **Statistics Dashboard**: Overview of analysis results with key metrics that update based on your filter
- **Visual Word Cloud**: Words sized by frequency with click-to-explore functionality
- **Feed Filter Controls**: Easy switching between combined view and individual feeds
- **Source Modal**: Detailed view of articles containing specific words, filtered by current selection
- **Feed Statistics Table**: Performance metrics for each analyzed feed

## 🎯 Use Cases

### Content Strategy & Analysis
- **Editorial Focus Analysis**: Compare how different news sources cover topics
- **Trend Identification**: Spot unique themes and vocabulary patterns per feed
- **Content Gaps**: Identify topics covered by some sources but not others
- **Brand Voice Analysis**: Understand the language patterns of specific publications

### Research & Journalism
- **Source Comparison**: Analyze bias and focus differences between news outlets
- **Topic Tracking**: Monitor how specific feeds cover particular subjects
- **Language Patterns**: Study vocabulary differences across news sources
- **Content Discovery**: Find unique stories and perspectives from individual feeds

### Marketing & SEO
- **Keyword Research**: Identify trending terms in your industry's news sources
- **Content Ideas**: Discover topics that specific publications focus on
- **Competitive Analysis**: Compare content themes across industry publications
- **Trend Monitoring**: Track emerging vocabulary in your sector

## 🌐 Shared Hosting Compatibility

### WebHostingHub.com Compatibility

This application should work on WebHostingHub.com shared hosting with minimal or no modifications. However, be aware of these potential considerations:

✅ **Should Work Out-of-Box:**
- Single PHP file deployment
- No special PHP extensions required
- File-based configuration storage
- Standard HTTP operations

⚠️ **Potential Issues & Solutions:**

#### External URL Access
Some shared hosts restrict external URL fetching. Test with this simple script:
```php
<?php
$test_url = 'http://feeds.bbci.co.uk/news/rss.xml';
$content = @file_get_contents($test_url);
echo $content ? "External URLs work" : "External URLs may be blocked";
?>
```
If blocked, you may need to contact WebHostingHub support to enable external URL access.

#### File Permissions
Ensure the application directory has write permissions for `settings.json`:
```bash
chmod 755 /public_html/rss-analyzer/
```

#### Script Execution Limits
Shared hosting typically limits script execution to 30-60 seconds. The application is optimized for speed, but if you encounter timeouts:
- Reduce the number of selected feeds
- Lower the top words count for faster processing
- Focus on individual feeds rather than analyzing all feeds at once
- Contact hosting support about time limits

### Deployment Steps

1. **Create directory**: `/public_html/rss-analyzer/`
2. **Upload files**: Place `index.php` in the directory
3. **Test basic functionality**: Visit the URL and ensure the page loads
4. **Test feed fetching**: Try analysis with one feed first
5. **Test feed filtering**: Verify the "View Results By" dropdown works correctly
6. **Monitor error logs**: Check cPanel error logs if issues occur

### Common Troubleshooting

- **"Failed to fetch feed" errors**: External URLs may be blocked - contact hosting support
- **"Permission denied" for settings**: Directory needs write permissions - check file/folder permissions in cPanel
- **Timeout errors**: Reduce number of feeds or words analyzed simultaneously, or focus on individual feeds
- **Memory errors**: Contact support about memory limits (rare with current optimizations)
- **Filter not working**: Ensure JavaScript is enabled in your browser

## ⚙️ Technical Details

### RSS Feed Parsing
The application uses a two-tier parsing approach:
- **Primary**: SimpleXML (if available)
- **Fallback**: Regex-based parsing

This ensures compatibility even when PHP extensions are limited.

### Supported Feed Formats
- RSS 2.0
- RSS 1.0  
- Atom feeds
- Most standard XML-based news feeds

### Data Storage & Processing
- **Settings**: Stored in `settings.json` (created automatically)
- **Feed-Specific Data**: Per-feed word frequencies stored in memory during analysis
- **No database required**: All analysis data is processed in-memory
- **Privacy-focused**: No external data transmission except RSS fetching

### Processing Limits
- **Articles per feed**: Limited to 25 for optimal speed
- **Top words**: User configurable from 10 to 1000
- **Source tracking**: Maximum 5 source articles tracked per word
- **Memory optimization**: Efficient array filtering and per-feed data storage

### Security Features
- Input validation on custom feed URLs
- HTML stripping from RSS content
- No user data persistence beyond settings
- Safe file operations with error handling
- AJAX request validation and filtering

## 🌐 Shared Hosting Compatibility

### WebHostingHub.com Compatibility

This application should work on WebHostingHub.com shared hosting with minimal or no modifications. However, be aware of these potential considerations:

✅ **Should Work Out-of-Box:**
- Single PHP file deployment
- No special PHP extensions required
- File-based configuration storage
- Standard HTTP operations

⚠️ **Potential Issues & Solutions:**

#### External URL Access
Some shared hosts restrict external URL fetching. Test with this simple script:
```php
<?php
$test_url = 'http://feeds.bbci.co.uk/news/rss.xml';
$content = @file_get_contents($test_url);
echo $content ? "External URLs work" : "External URLs may be blocked";
?>
```
If blocked, you may need to contact WebHostingHub support to enable external URL access.

#### File Permissions
Ensure the application directory has write permissions for `settings.json`:
```bash
chmod 755 /public_html/rss-analyzer/
```

#### Script Execution Limits
Shared hosting typically limits script execution to 30-60 seconds. The application is optimized for speed, but if you encounter timeouts:
- Reduce the number of selected feeds
- Lower the top words count for faster processing
- Contact hosting support about time limits

### Deployment Steps

1. **Create directory**: `/public_html/rss-analyzer/`
2. **Upload files**: Place `index.php` in the directory
3. **Test basic functionality**: Visit the URL and ensure the page loads
4. **Test feed fetching**: Try analysis with one feed first
5. **Monitor error logs**: Check cPanel error logs if issues occur

### Common Troubleshooting

- **"Failed to fetch feed" errors**: External URLs may be blocked - contact hosting support
- **"Permission denied" for settings**: Directory needs write permissions - check file/folder permissions in cPanel
- **Timeout errors**: Reduce number of feeds or words analyzed simultaneously
- **Memory errors**: Contact support about memory limits (rare with current optimizations)

## ⚙️ Technical Details

### RSS Feed Parsing
The application uses a two-tier parsing approach:
- **Primary**: SimpleXML (if available)
- **Fallback**: Regex-based parsing

This ensures compatibility even when PHP extensions are limited.

### Supported Feed Formats
- RSS 2.0
- RSS 1.0  
- Atom feeds
- Most standard XML-based news feeds

### Data Storage
- **Settings**: Stored in `settings.json` (created automatically)
- **No database required**: All analysis data is processed in-memory
- **Privacy-focused**: No external data transmission except RSS fetching

### Processing Limits
- **Articles per feed**: Limited to 25 for optimal speed
- **Top words**: User configurable from 10 to 1000
- **Source tracking**: Maximum 5 source articles tracked per word
- **Memory optimization**: Efficient array filtering and processing

### Security Features
- Input validation on custom feed URLs
- HTML stripping from RSS content
- No user data persistence beyond settings
- Safe file operations with error handling

## 🎨 Customization

### Default Feeds
Edit the `$default_feeds` array in the `RSSWordAnalyzer` class:
```php
'Feed Name' => 'https://example.com/feed.rss'
```

### Stopwords
The default stopword list can be extended in the `$default_stopwords` array or through the web interface.

### Styling
CSS is embedded in the HTML. Modify the `<style>` section to customize appearance. The design uses:
- **Gradient backgrounds** for modern appeal
- **Flexbox layouts** for responsive design
- **CSS Grid** for organized form sections
- **Smooth transitions** for interactive elements
- **Dynamic filtering controls** for feed-specific views

## 🔧 System Requirements

### Server Requirements
- **Modern browsers**: Chrome, Firefox, Safari, Edge
- **JavaScript required**: For full functionality, interactive features, and feed filtering
- **Mobile responsive**: Optimized for all screen sizes

### Hosting Requirements
- Requires external URL access for RSS fetching
- Processing time increases with number of feeds and words analyzed
- Limited to text content analysis (no images or multimedia)
- Some RSS feeds may have access restrictions or rate limiting

## 🚨 Known Limitations

- **External URL dependency**: Requires external URL access for RSS fetching
- **Processing time**: Scales with number of feeds and top words selected  
- **Content scope**: Limited to text content analysis (no multimedia)
- **Feed restrictions**: Some RSS feeds may have access restrictions or rate limiting
- **Shared hosting limits**: Subject to hosting provider's execution time and memory limits
- **Browser dependency**: Feed filtering requires JavaScript enabled

## 📝 Version History

- **v8**: Added feed-specific result filtering, enhanced feed comparison, targeted analysis capabilities
- **v7**: Added configurable top words selection, modern UI redesign, enhanced analytics, AJAX modals
- **v6**: Added source tracking, regex-based RSS parsing, WebHostingHub compatibility
- **Earlier versions**: Basic word frequency analysis, Flask/Python implementation

## 🤝 Contributing

This project is open source. Feel free to modify and distribute according to your needs.

**Contribution focus areas:**
- Additional RSS feed format support
- Performance improvements for large-scale analysis
- Enhanced feed comparison and filtering features
- Better error handling and user feedback
- UI/UX improvements and accessibility
- Mobile optimization enhancements
- Advanced analytics and visualization features

## 📞 Support

For issues specific to shared hosting deployment, consult your hosting provider's documentation or support team. 

For application bugs, feature requests, or general questions, use the GitHub issue tracker.

## 📄 License

This project is open source and available under the MIT License. Feel free to use, modify, and distribute as needed.

---

**🌟 Star this repository if you find it useful!**
