<?php
/**
 * Test script for cURL fallback system
 * This script tests the direct Google search fallback functionality
 */

require_once 'vendor/autoload.php';

use Symfony\Component\HttpClient\CurlHttpClient;
use Psr\Log\LoggerInterface;

// Simple logger implementation for testing
class TestLogger implements LoggerInterface
{
    public function emergency($message, array $context = []): void
    {
        echo "[EMERGENCY] $message " . json_encode($context) . "\n";
    }
    
    public function alert($message, array $context = []): void
    {
        echo "[ALERT] $message " . json_encode($context) . "\n";
    }
    
    public function critical($message, array $context = []): void
    {
        echo "[CRITICAL] $message " . json_encode($context) . "\n";
    }
    
    public function error($message, array $context = []): void
    {
        echo "[ERROR] $message " . json_encode($context) . "\n";
    }
    
    public function warning($message, array $context = []): void
    {
        echo "[WARNING] $message " . json_encode($context) . "\n";
    }
    
    public function notice($message, array $context = []): void
    {
        echo "[NOTICE] $message " . json_encode($context) . "\n";
    }
    
    public function info($message, array $context = []): void
    {
        echo "[INFO] $message " . json_encode($context) . "\n";
    }
    
    public function debug($message, array $context = []): void
    {
        echo "[DEBUG] $message " . json_encode($context) . "\n";
    }
    
    public function log($level, $message, array $context = []): void
    {
        echo "[$level] $message " . json_encode($context) . "\n";
    }
}

function parseWithDom(string $htmlContent, string $query): array
{
    $results = [];
    
    try {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($htmlContent);
        libxml_clear_errors();
        
        $xpath = new DOMXPath($dom);
        
        // Try to find Google search result containers
        $selectors = [
            '//div[@class="g"]',
            '//div[contains(@class,"g ")]',
            '//div[@class="MjjYud"]',  // Another common Google result class
            '//div[contains(@class,"result")]'
        ];
        
        foreach ($selectors as $selector) {
            $resultNodes = $xpath->query($selector);
            echo "Selector '$selector' found " . $resultNodes->length . " nodes\n";
            
            if ($resultNodes->length > 0) {
                foreach ($resultNodes as $node) {
                    // Try to extract title and link from various patterns
                    $linkNodes = $xpath->query('.//h3//a | .//a[h3]', $node);
                    
                    if ($linkNodes->length > 0) {
                        $linkNode = $linkNodes->item(0);
                        $href = $linkNode->getAttribute('href');
                        
                        // Get the title text
                        $titleNodes = $xpath->query('.//h3', $node);
                        $title = $titleNodes->length > 0 ? trim($titleNodes->item(0)->textContent) : '';
                        
                        if (!empty($title) && !empty($href) && filter_var($href, FILTER_VALIDATE_URL)) {
                            $results[] = [
                                'title' => $title,
                                'link' => $href,
                                'snippet' => 'Extracted with DOM parsing',
                                'display_link' => parse_url($href, PHP_URL_HOST) ?? $href
                            ];
                            
                            if (count($results) >= 5) break 2;
                        }
                    }
                }
            }
        }
        
    } catch (Exception $e) {
        echo "DOM parsing error: " . $e->getMessage() . "\n";
    }
    
    return $results;
}

// Test the cURL Google search directly
function testCurlGoogleSearch(string $query): array
{
    $logger = new TestLogger();
    
    echo "Testing cURL Google Search for query: $query\n";
    echo str_repeat("-", 50) . "\n";
    
    // Encode the query for URL
    $encodedQuery = urlencode($query);
    
    // Google search URL with parameters for better results
    $googleUrl = "https://www.google.com/search?q={$encodedQuery}&num=10&hl=en&safe=off";
    
    echo "URL: $googleUrl\n";
    
    // Setup cURL with headers to mimic a real browser
    $curlHeaders = [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
        'Accept-Language: en-US,en;q=0.5',
        'Accept-Encoding: gzip, deflate',
        'Connection: keep-alive',
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $googleUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER => $curlHeaders,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_ENCODING => 'gzip,deflate',
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        throw new Exception("cURL Error: {$curlError}");
    }
    
    if ($httpCode !== 200) {
        throw new Exception("HTTP Error: {$httpCode}");
    }
    
    if (empty($response)) {
        throw new Exception("Empty response from Google");
    }
    
    echo "Response received, length: " . strlen($response) . " bytes\n";
    
    // Try to parse basic results with multiple patterns (Google's HTML structure varies)
    $results = [];
    
    // Save response to file for debugging
    file_put_contents('/tmp/google_response.html', $response);
    echo "Response saved to /tmp/google_response.html for debugging\n";
    
    // Try different patterns for modern Google results
    $patterns = [
        // Pattern 1: Direct h3 with link
        '/<h3[^>]*><a[^>]*href="([^"]*)"[^>]*[^<]*>(.*?)<\/a><\/h3>/is',
        // Pattern 2: Link with h3 inside
        '/<a[^>]*href="([^"]*)"[^>]*><h3[^>]*>(.*?)<\/h3>/is',
        // Pattern 3: More flexible pattern
        '/<h3[^>]*[^<]*>.*?href="([^"]*)"[^>]*>(.*?)<\/a>/is',
    ];
    
    foreach ($patterns as $patternIndex => $pattern) {
        $matches = [];
        if (preg_match_all($pattern, $response, $matches, PREG_SET_ORDER)) {
            echo "Pattern $patternIndex found " . count($matches) . " matches\n";
            
            foreach ($matches as $match) {
                if (count($results) >= 5) break;
                
                $link = html_entity_decode($match[1]);
                $title = strip_tags(html_entity_decode($match[2]));
                
                // Clean title and link
                $title = trim(preg_replace('/\s+/', ' ', $title));
                
                // Skip if title or link is empty or if it's a Google internal link
                if (empty($title) || empty($link) || 
                    strpos($link, 'google.com') !== false ||
                    strpos($link, 'javascript:') === 0 ||
                    strlen($title) < 5) {
                    continue;
                }
                
                $results[] = [
                    'title' => $title,
                    'link' => $link,
                    'snippet' => "Extracted with pattern $patternIndex",
                    'display_link' => parse_url($link, PHP_URL_HOST) ?? $link
                ];
            }
            
            if (count($results) > 0) break; // Stop if we found results with this pattern
        }
    }
    
    // If no results with regex, try DOM parsing
    if (empty($results)) {
        echo "Regex parsing failed, trying DOM parsing...\n";
        $results = parseWithDom($response, $query);
    }
    
    echo "Results found: " . count($results) . "\n";
    
    return [
        'query' => $query,
        'results' => $results,
        'total_results' => count($results),
        'search_method' => 'curl_test',
        'http_code' => $httpCode
    ];
}

// Test queries
$testQueries = [
    'github',  // Simple query that should return results
    'site:github.com python',
    'site:x.com "mikes_torres"'
];

foreach ($testQueries as $query) {
    try {
        $result = testCurlGoogleSearch($query);
        
        echo "\nResults for '$query':\n";
        foreach ($result['results'] as $i => $r) {
            echo "  " . ($i + 1) . ". {$r['title']}\n";
            echo "     {$r['link']}\n";
            echo "     {$r['snippet']}\n\n";
        }
        
        echo str_repeat("=", 80) . "\n\n";
        
        // Add delay between requests to be respectful
        sleep(2);
        
    } catch (Exception $e) {
        echo "ERROR: " . $e->getMessage() . "\n\n";
    }
}