# RSS Word Frequency Analyzer

A PHP-based web application that analyzes word frequency from RSS feeds with source tracking and customizable filtering. Built to work on shared hosting environments without external dependencies.

## Features

- **Multi-feed analysis** - Select from default feeds or add custom RSS/Atom feeds
- **Word frequency tracking** - Analyze most common words across all feeds
- **Source attribution** - Click on words to see which articles contain them
- **Feed-specific breakdown** - View word frequency per individual feed
- **Custom stopword filtering** - Add your own words to exclude from analysis
- **Responsive design** - Works on desktop and mobile devices
- **Zero dependencies** - Pure PHP with no external libraries required

## Requirements

- PHP 7.0 or higher
- Web server (Apache, Nginx, or PHP development server)
- Write permissions for settings storage

**Note:** This application does **not** require the SimpleXML PHP extension. It includes a fallback regex-based RSS parser for maximum compatibility.

## Quick Start

### Local Development

1. **Download or clone** the repository
2. **Navigate to the directory** containing `index.php`
3. **Start the PHP development server:**
   ```bash
   php -S localhost:8001
   ```
4. **Open your browser** to `http://localhost:8001`
5. **Select RSS feeds** and click "Analyze Word Frequency"

### Shared Hosting Deployment

1. **Upload `index.php`** to your web hosting directory (e.g., `public_html/rss-analyzer/`)
2. **Set directory permissions** to allow file writing:
   ```bash
   chmod 755 /path/to/rss-analyzer/
   ```
3. **Access via web browser** at your domain

## Usage

1. **Feed Selection**: Choose from default feeds or add custom RSS/Atom URLs
2. **Custom Stopwords**: Add words to exclude (e.g., "said", "according", "reported")
3. **Analysis**: Click "Analyze Word Frequency" to process selected feeds
4. **Results**: View overall word frequency and per-feed breakdowns
5. **Source Tracking**: Click word tags or "View Sources" to see originating articles

## WebHostingHub.com Deployment

This application should work on WebHostingHub.com shared hosting with minimal or no modifications. However, be aware of these potential considerations:

### Compatibility Checklist

**✅ Should Work Out-of-Box:**
- Single PHP file deployment
- No special PHP extensions required
- File-based configuration storage
- Standard HTTP operations

**⚠️ Potential Issues:**

#### External URL Access
Some shared hosts restrict external URL fetching. Test with this simple script:

```php
<?php
$test_url = 'http://feeds.bbci.co.uk/news/rss.xml';
$content = @file_get_contents($test_url);
echo $content ? "External URLs work" : "External URLs may be blocked";
?>
```

If blocked, you may need to contact WebHostingHub support to enable external URL access or modify the code to use cURL.

#### File Permissions
Ensure the application directory has write permissions for `settings.json`:
```bash
chmod 755 /public_html/rss-analyzer/
```

#### Execution Time
Shared hosting typically limits script execution to 30-60 seconds. The application is optimized for speed, but if you encounter timeouts:
- Reduce the number of selected feeds
- Contact hosting support about time limits

### Recommended Deployment Steps

1. **Create directory**: `/public_html/rss-analyzer/`
2. **Upload files**: Place `index.php` in the directory
3. **Test basic functionality**: Visit the URL and ensure the page loads
4. **Test feed fetching**: Try analysis with one feed first
5. **Monitor error logs**: Check cPanel error logs if issues occur

### Troubleshooting on Shared Hosting

**"Failed to fetch feed" errors:**
- External URLs may be blocked
- Contact hosting support or implement cURL fallback

**"Permission denied" for settings:**
- Directory needs write permissions
- Check file/folder permissions in cPanel

**Timeout errors:**
- Reduce number of feeds analyzed simultaneously
- Contact support about execution time limits

**Memory errors:**
- Rare with current optimizations
- Contact support about memory limits

## Technical Details

### RSS Parsing Strategy

The application uses a two-tier parsing approach:

1. **Primary**: SimpleXML (if available)
2. **Fallback**: Regex-based parsing

This ensures compatibility even when PHP extensions are limited.

### Supported Feed Formats

- RSS 2.0
- RSS 1.0
- Atom feeds
- Most standard XML-based news feeds

### Data Storage

- **Settings**: Stored in `settings.json` (created automatically)
- **No database required**: All data is processed in-memory
- **Privacy-focused**: No external data transmission except RSS fetching

### Performance Optimizations

- Limited to 25 articles per feed for speed
- Top 100 words globally, 50 in display tables
- Maximum 5 source articles tracked per word
- Efficient memory usage with array filtering

## Security Considerations

- Input validation on custom feed URLs
- HTML stripping from RSS content
- No user data persistence beyond settings
- Safe file operations with error handling

## Customization

### Adding Default Feeds

Edit the `$default_feeds` array in the `RSSWordAnalyzer` class:

```php
'Feed Name' => 'https://example.com/feed.rss'
```

### Modifying Stopwords

The default stopword list can be extended in the `$default_stopwords` array or through the web interface.

### Styling

CSS is embedded in the HTML. Modify the `<style>` section to customize appearance.

## Limitations

- Requires external URL access for RSS fetching
- Processing time increases with number of feeds
- Limited to text content analysis (no images or multimedia)
- Some RSS feeds may have access restrictions or rate limiting

## Browser Support

- Modern browsers (Chrome, Firefox, Safari, Edge)
- JavaScript required for full functionality
- Mobile responsive design

## License

This project is open source. Feel free to modify and distribute according to your needs.

## Contributing

Contributions are welcome. Focus areas include:
- Additional RSS feed format support
- Performance improvements
- Enhanced error handling
- UI/UX improvements

## Version History

- **v6**: Added source tracking, regex-based RSS parsing, WebHostingHub compatibility
- **Earlier versions**: Basic word frequency analysis, Flask/Python implementation

## Support

For issues specific to shared hosting deployment, consult your hosting provider's documentation or support team. For application bugs or feature requests, use the GitHub issue tracker.
