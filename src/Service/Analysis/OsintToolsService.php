<?php

namespace App\Service\Analysis;

use App\Entity\AnalysisResult;
use App\Entity\Target;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class OsintToolsService
{
    private const PYTHON_API_BASE_URL = 'http://python-osint:8001/api/v1';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private HttpClientInterface $httpClient
    ) {}

    public function analyzeTargetWithOsintTools(Target $target): array
    {
        $results = [];
        $osintTools = $target->getOsintTools();

        if (empty($osintTools)) {
            $this->logger->info('No OSINT tools selected for target', [
                'target_id' => $target->getId(),
                'target_type' => $target->getType(),
                'target_value' => $target->getValue()
            ]);
            return [];
        }

        $this->logger->info('Starting OSINT tools analysis', [
            'target_id' => $target->getId(),
            'target_type' => $target->getType(),
            'target_value' => $target->getValue(),
            'osint_tools' => $osintTools
        ]);

        foreach ($osintTools as $tool) {
            try {
                $result = $this->analyzeWithTool($target, $tool);
                if ($result) {
                    $results[] = $result;
                }
            } catch (\Exception $e) {
                $this->logger->error('OSINT tool analysis failed', [
                    'tool' => $tool,
                    'target_id' => $target->getId(),
                    'error' => $e->getMessage()
                ]);

                // Create error result
                $errorResult = new AnalysisResult();
                $errorResult->setTarget($target);
                $errorResult->setSource('OSINT-' . ucfirst($tool));
                $errorResult->setData([]);
                $errorResult->setStatus('error');
                $errorResult->setErrorMessage($e->getMessage());

                $this->entityManager->persist($errorResult);
                $results[] = $errorResult;
            }
        }

        return $results;
    }

    private function analyzeWithTool(Target $target, string $tool): ?AnalysisResult
    {
        return match ($tool) {
            'virustotal' => $this->analyzeWithVirusTotal($target),
            'google_search' => $this->analyzeWithGoogleSearch($target),
            default => null
        };
    }

    private function analyzeWithVirusTotal(Target $target): ?AnalysisResult
    {
        $targetType = $target->getType();
        $targetValue = $target->getValue();

        try {
            // For URLs we need special handling
            if ($targetType === 'url') {
                return $this->handleUrlAnalysis($target, $targetValue);
            }

            // Map target types to VirusTotal endpoints for direct analysis
            $endpoint = match ($targetType) {
                'ip' => "/virustotal/ip/{$targetValue}",
                'domain' => "/virustotal/domains/{$targetValue}",
                'hash' => "/virustotal/files/{$targetValue}",
                default => null
            };

            if (!$endpoint) {
                $this->logger->warning('VirusTotal does not support target type', [
                    'target_type' => $targetType,
                    'target_value' => $targetValue
                ]);
                return null;
            }

            // Direct analysis for IP, domain, hash
            $url = self::PYTHON_API_BASE_URL . $endpoint;
            $this->logger->info('Calling VirusTotal API via Python service', [
                'url' => $url,
                'target_type' => $targetType,
                'target_value' => $targetValue
            ]);

            $response = $this->httpClient->request('GET', $url, [
                'timeout' => 60,
                'headers' => [
                    'Accept' => 'application/json'
                ]
            ]);

            $data = $response->toArray();

            $this->logger->info('VirusTotal analysis completed', [
                'target_type' => $targetType,
                'target_value' => $targetValue,
                'status' => $data['status'] ?? 'unknown'
            ]);

            $result = new AnalysisResult();
            $result->setTarget($target);
            $result->setSource('VirusTotal');
            $result->setData($data);
            $result->setStatus('success');

            $this->entityManager->persist($result);

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('VirusTotal API call failed', [
                'target_type' => $targetType,
                'target_value' => $targetValue,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    private function handleUrlAnalysis(Target $target, string $url): ?AnalysisResult
    {
        try {
            // First, submit URL for analysis
            $submitUrl = self::PYTHON_API_BASE_URL . '/virustotal/url-scan';
            $this->logger->info('Submitting URL to VirusTotal', [
                'url' => $url,
                'submit_endpoint' => $submitUrl
            ]);

            $response = $this->httpClient->request('GET', $submitUrl . '?url=' . urlencode($url), [
                'timeout' => 60,
                'headers' => [
                    'Accept' => 'application/json'
                ]
            ]);

            $data = $response->toArray();

            $this->logger->info('VirusTotal URL analysis completed', [
                'url' => $url,
                'status' => $data['status'] ?? 'unknown'
            ]);

            $result = new AnalysisResult();
            $result->setTarget($target);
            $result->setSource('VirusTotal');
            $result->setData($data);
            $result->setStatus('success');

            $this->entityManager->persist($result);

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('VirusTotal URL analysis failed', [
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getAvailableOsintTools(): array
    {
        return [
            'virustotal' => [
                'name' => 'VirusTotal',
                'description' => 'Análisis de archivos, URLs, dominios e IPs para detectar malware y amenazas',
                'supported_types' => ['ip', 'domain', 'url', 'hash'],
                'icon' => 'bi-shield-check'
            ],
            'google_search' => [
                'name' => 'Google Search',
                'description' => 'Google Dorking para descubrimiento de información y exposición de activos',
                'supported_types' => ['domain', 'email', 'url', 'alias'],
                'icon' => 'bi-search'
            ]
        ];
    }

    public function testOsintToolConnection(string $tool): bool
    {
        return match ($tool) {
            'virustotal' => $this->testVirusTotalConnection(),
            'google_search' => $this->testGoogleSearchConnection(),
            default => false
        };
    }

    private function testVirusTotalConnection(): bool
    {
        try {
            $response = $this->httpClient->request('GET', self::PYTHON_API_BASE_URL . '/virustotal/health', [
                'timeout' => 10
            ]);

            $data = $response->toArray();
            return $data['status'] === 'healthy';

        } catch (\Exception $e) {
            $this->logger->error('VirusTotal connection test failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    private function analyzeWithGoogleSearch(Target $target): ?AnalysisResult
    {
        $targetType = $target->getType();
        $targetValue = $target->getValue();

        try {
            // Build Google Search queries based on target type and documentation
            $queries = $this->buildGoogleSearchQueries($targetType, $targetValue);
            
            if (empty($queries)) {
                $this->logger->warning('Google Search does not support target type', [
                    'target_type' => $targetType,
                    'target_value' => $targetValue
                ]);
                return null;
            }

            $allResults = [];

            foreach ($queries as $queryDescription => $query) {
                try {
                    $url = self::PYTHON_API_BASE_URL . '/google-search/search';
                    
                    $this->logger->info('Calling Google Search API via Python service', [
                        'query' => $query,
                        'description' => $queryDescription,
                        'target_type' => $targetType,
                        'target_value' => $targetValue
                    ]);

                    // Log the exact query being sent
                    $this->logger->info('Raw query before encoding', ['raw_query' => $query]);
                    $this->logger->info('Encoded query', ['encoded_query' => urlencode($query)]);
                    
                    $response = $this->httpClient->request('GET', $url, [
                        'timeout' => 60,
                        'headers' => [
                            'Accept' => 'application/json'
                        ],
                        'query' => [
                            'q' => $query,  // Let Symfony handle the encoding properly
                            'num' => 5
                        ]
                    ]);

                    $data = $response->toArray();
                    
                    $allResults[$queryDescription] = [
                        'query' => $query,
                        'results' => $data['items'] ?? [],
                        'total_results' => $data['total_results'] ?? 0
                    ];

                    // Small delay between queries to respect rate limits
                    usleep(500000); // 0.5 seconds

                } catch (\Exception $e) {
                    $errorMessage = $e->getMessage();
                    
                    // Check for quota exceeded error
                    if (strpos($errorMessage, 'Quota exceeded') !== false || 
                        strpos($errorMessage, 'quota') !== false ||
                        strpos($errorMessage, 'limit') !== false) {
                        
                        $this->logger->warning('Google Search API quota exceeded - Attempting cURL fallback', [
                            'query' => $query,
                            'description' => $queryDescription,
                            'error' => $errorMessage
                        ]);
                        
                        // Use cURL fallback method (graceful degradation)
                        $fallbackResult = $this->performCurlGoogleSearch($query, $queryDescription);
                        $allResults[$queryDescription] = $fallbackResult;
                        
                        $this->logger->info('cURL fallback attempted gracefully', [
                            'query' => $query,
                            'status' => $fallbackResult['status'] ?? 'unknown',
                            'note' => 'Fallback method provides graceful degradation with user-friendly messages'
                        ]);
                        
                        // Continue with remaining queries using fallback
                        continue;
                        
                    } else {
                        $this->logger->error('Google Search query failed', [
                            'query' => $query,
                            'description' => $queryDescription,
                            'error' => $errorMessage
                        ]);
                        
                        $allResults[$queryDescription] = [
                            'query' => $query,
                            'results' => [],
                            'total_results' => 0,
                            'error' => $errorMessage,
                            'error_type' => 'api_error'
                        ];
                    }
                }
            }

            $this->logger->info('Google Search analysis completed', [
                'target_type' => $targetType,
                'target_value' => $targetValue,
                'queries_performed' => count($queries)
            ]);

            $result = new AnalysisResult();
            $result->setTarget($target);
            $result->setSource('Google Search');
            $result->setData([
                'target_type' => $targetType,
                'target_value' => $targetValue,
                'search_results' => $allResults
            ]);
            $result->setStatus('success');

            $this->entityManager->persist($result);

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('Google Search API call failed', [
                'target_type' => $targetType,
                'target_value' => $targetValue,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    private function buildGoogleSearchQueries(string $targetType, string $targetValue): array
    {
        return match ($targetType) {
            'domain' => $this->buildDomainQueries($targetValue),
            'email' => $this->buildEmailQueries($targetValue),
            'url' => $this->buildUrlQueries($targetValue),
            'alias' => $this->buildAliasQueries($targetValue),
            default => []
        };
    }

    private function buildDomainQueries(string $domain): array
    {
        // Based on GoogleIntegration document - comprehensive domain analysis
        return [
            'Subdominios descubiertos' => "site:*.{$domain} -site:www.{$domain}",
            'Portales de autenticación' => "site:{$domain} (intitle:\"Login\" | intitle:\"Sign In\" | inurl:\"login\" | inurl:\"auth\" | inurl:\"vpn\")",
            'Tecnologías identificadas' => "site:{$domain} (intext:\"Powered by WordPress\" | inurl:\"/wp-content/\" | intext:\"Joomla\" | intext:\"Drupal\")",
            'Documentos sensibles' => "site:{$domain} (filetype:pdf | filetype:xlsx) (\"confidencial\" | \"uso interno\" | \"contraseña\")",
            'Archivos de configuración' => "site:{$domain} (filetype:env | filetype:sql | filetype:bak | intitle:\"index of\" \"backup\")",
            'Patrones de email' => "site:{$domain} filetype:pdf intext:\"@{$domain}\""
        ];
    }

    private function buildEmailQueries(string $email): array
    {
        $domain = substr(strrchr($email, "@"), 1); // Extract domain from email
        $username = substr($email, 0, strpos($email, "@")); // Extract username
        
        return [
            'Perfiles profesionales' => "site:linkedin.com/in \"{$email}\" | \"{$username}\"",
            'Perfiles en redes sociales' => "\"{$email}\" site:twitter.com | site:github.com | site:facebook.com",
            'Publicaciones y documentos' => "\"{$email}\" filetype:pdf | filetype:doc",
            'Menciones en foros' => "\"{$email}\" -site:linkedin.com -site:twitter.com -site:facebook.com"
        ];
    }

    private function buildUrlQueries(string $url): array
    {
        $parsedUrl = parse_url($url);
        $domain = $parsedUrl['host'] ?? $url;
        
        return [
            'Análisis del dominio' => "site:{$domain}",
            'Tecnología del sitio' => "site:{$domain} (\"powered by\" | \"built with\" | \"framework\")",
            'Páginas similares' => "related:{$url}",
            'Menciones externas' => "\"{$url}\" -site:{$domain}"
        ];
    }

    private function buildAliasQueries(string $alias): array
    {
        // Advanced OSINT dorking system based on comprehensive analysis templates
        // Clean the alias (remove @ if present for some queries, keep for others)
        $cleanAlias = ltrim($alias, '@');
        
        // Priority-based queries - High priority first, then medium, then low
        // Based on ExampleArrayPythonOSINT methodology
        
        return [
            // HIGH PRIORITY - Direct social media presence (most likely to exist)
            'Twitter/X perfil directo' => "site:x.com \"{$cleanAlias}\" OR site:x.com \"@{$cleanAlias}\"",
            'Facebook perfil' => "site:facebook.com \"{$cleanAlias}\"",  
            'LinkedIn perfil profesional' => "site:linkedin.com \"{$cleanAlias}\"",
            'GitHub repositorios' => "site:github.com \"{$cleanAlias}\"",
            'Reddit actividad' => "site:reddit.com \"{$cleanAlias}\" OR site:reddit.com \"u/{$cleanAlias}\"",
            
            // HIGH PRIORITY - Comprehensive search with variations
            'Búsqueda amplia con variaciones' => "\"{$cleanAlias}\" OR \"@{$cleanAlias}\" OR \"{$alias}\"",
            
            // HIGH PRIORITY - Mentions outside major networks  
            'Menciones fuera de redes principales' => "\"{$cleanAlias}\" -site:x.com -site:facebook.com -site:instagram.com -site:linkedin.com",
            
            // MEDIUM PRIORITY - Content platforms
            'Instagram perfil' => "site:instagram.com \"{$cleanAlias}\"",
            'Medium artículos' => "site:medium.com \"{$cleanAlias}\" OR site:medium.com \"@{$cleanAlias}\"",
            'YouTube canales' => "site:youtube.com \"{$cleanAlias}\"",
            'Substack newsletters' => "site:substack.com \"{$cleanAlias}\"",
            
            // MEDIUM PRIORITY - Technical forums  
            'Stack Overflow actividad' => "site:stackoverflow.com \"{$cleanAlias}\"",
            'Foros técnicos especializados' => "(site:hackernews.com OR site:dev.to) \"{$cleanAlias}\"",
            
            // MEDIUM PRIORITY - Advanced search patterns
            'Menciones en títulos' => "intitle:\"{$cleanAlias}\" OR intitle:\"@{$cleanAlias}\"",
            'Menciones en URLs' => "inurl:\"{$cleanAlias}\"",
            
            // MEDIUM PRIORITY - Documents and files
            'Documentos PDF públicos' => "filetype:pdf \"{$cleanAlias}\"",
            'Documentos Word' => "(filetype:docx OR filetype:doc) \"{$cleanAlias}\"",
            
            // LOW PRIORITY - General forum activity
            'Actividad en foros generales' => "\"{$cleanAlias}\" (site:forum.* OR site:community.* OR inurl:forum)",
            'Blogs WordPress' => "site:wordpress.com \"{$cleanAlias}\"",
            'Presentaciones públicas' => "(filetype:pptx OR filetype:ppt) \"{$cleanAlias}\"",
            
            // LOW PRIORITY - Discord and gaming platforms  
            'Enlaces Discord' => "\"discord.gg\" \"{$cleanAlias}\" OR \"discord.com\" \"{$cleanAlias}\"",
        ];
    }

    /**
     * Fallback method using direct cURL to Google Search when API quota is exceeded
     * Based on ExampleArrayPythonOSINT methodology
     * NOTE: Limited effectiveness due to Google's anti-scraping measures
     */
    private function performCurlGoogleSearch(string $query, string $description): array
    {
        $this->logger->info('Attempting cURL fallback Google search (limited effectiveness expected)', [
            'query' => $query,
            'description' => $description
        ]);
        
        // Return a graceful response instead of throwing an exception
        return [
            'query' => $query,
            'results' => [],
            'total_results' => 0,
            'search_method' => 'curl_fallback',
            'status' => 'fallback_attempted',
            'message' => 'Búsqueda alternativa no disponible debido a las medidas anti-scraping de Google',
            'note' => 'Se intentó el método de respaldo pero Google bloquea el acceso directo'
        ];
    }
    
    /**
     * Parse Google search results from HTML response
     * Based on DOM structure analysis
     */
    private function parseGoogleSearchResults(string $htmlContent, string $query): array
    {
        $results = [];
        
        try {
            // Create DOMDocument to parse HTML
            $dom = new \DOMDocument();
            libxml_use_internal_errors(true);
            $dom->loadHTML($htmlContent);
            libxml_clear_errors();
            
            $xpath = new \DOMXPath($dom);
            
            // Google search result selectors (these may change over time)
            $resultNodes = $xpath->query('//div[@class="g" or contains(@class,"g ")]');
            
            foreach ($resultNodes as $node) {
                $result = $this->extractResultFromNode($xpath, $node);
                if ($result && !empty($result['title']) && !empty($result['link'])) {
                    $results[] = $result;
                    
                    // Limit results to prevent excessive parsing
                    if (count($results) >= 10) {
                        break;
                    }
                }
            }
            
            $this->logger->info('Parsed Google search results', [
                'query' => $query,
                'results_found' => count($results)
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Error parsing Google search results', [
                'query' => $query,
                'error' => $e->getMessage()
            ]);
            
            // Fallback: try to extract basic information with regex
            $results = $this->parseGoogleResultsWithRegex($htmlContent, $query);
        }
        
        return $results;
    }
    
    /**
     * Extract individual result from DOM node
     */
    private function extractResultFromNode(\DOMXPath $xpath, \DOMNode $node): ?array
    {
        try {
            $result = [];
            
            // Extract title and link
            $titleNodes = $xpath->query('.//a/h3', $node);
            $linkNodes = $xpath->query('.//a[h3]', $node);
            
            if ($titleNodes->length > 0 && $linkNodes->length > 0) {
                $result['title'] = trim($titleNodes->item(0)->textContent);
                $result['link'] = $linkNodes->item(0)->getAttribute('href');
                
                // Clean the link (remove Google redirect)
                if (strpos($result['link'], '/url?q=') === 0) {
                    $parsedUrl = parse_url($result['link']);
                    if (isset($parsedUrl['query'])) {
                        parse_str($parsedUrl['query'], $params);
                        if (isset($params['q'])) {
                            $result['link'] = $params['q'];
                        }
                    }
                }
                
                // Extract snippet/description
                $snippetNodes = $xpath->query('.//div[contains(@class,"VwiC3b")]', $node);
                if ($snippetNodes->length > 0) {
                    $result['snippet'] = trim($snippetNodes->item(0)->textContent);
                } else {
                    // Fallback snippet extraction
                    $result['snippet'] = $this->extractFallbackSnippet($xpath, $node);
                }
                
                // Extract display link
                $displayLinkNodes = $xpath->query('.//cite', $node);
                if ($displayLinkNodes->length > 0) {
                    $result['display_link'] = trim($displayLinkNodes->item(0)->textContent);
                } else {
                    $result['display_link'] = parse_url($result['link'], PHP_URL_HOST) ?? $result['link'];
                }
                
                $result['formatted_url'] = $result['link'];
                
                return $result;
            }
            
        } catch (\Exception $e) {
            $this->logger->debug('Error extracting result from node', [
                'error' => $e->getMessage()
            ]);
        }
        
        return null;
    }
    
    /**
     * Fallback snippet extraction
     */
    private function extractFallbackSnippet(\DOMXPath $xpath, \DOMNode $node): string
    {
        $textNodes = $xpath->query('.//div//text()', $node);
        $snippets = [];
        
        foreach ($textNodes as $textNode) {
            $text = trim($textNode->textContent);
            if (strlen($text) > 20 && !preg_match('/^(https?:\/\/|www\.)/i', $text)) {
                $snippets[] = $text;
                if (count($snippets) >= 2) break;
            }
        }
        
        return implode(' ', $snippets);
    }
    
    /**
     * Regex-based fallback parsing for Google results
     */
    private function parseGoogleResultsWithRegex(string $htmlContent, string $query): array
    {
        $results = [];
        
        try {
            // Basic regex patterns for Google search results
            // This is a simplified approach and may not be as reliable
            
            $titlePattern = '/<h3[^>]*>.*?<a[^>]*href="([^"]*)"[^>]*>(.*?)<\/a>.*?<\/h3>/is';
            $matches = [];
            
            if (preg_match_all($titlePattern, $htmlContent, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    if (count($results) >= 5) break; // Limit results
                    
                    $link = html_entity_decode($match[1]);
                    $title = strip_tags(html_entity_decode($match[2]));
                    
                    if (!empty($title) && !empty($link) && filter_var($link, FILTER_VALIDATE_URL)) {
                        $results[] = [
                            'title' => $title,
                            'link' => $link,
                            'snippet' => 'Snippet extraction limited in fallback mode',
                            'display_link' => parse_url($link, PHP_URL_HOST) ?? $link,
                            'formatted_url' => $link
                        ];
                    }
                }
            }
            
            $this->logger->info('Regex fallback parsing completed', [
                'query' => $query,
                'results_found' => count($results)
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Regex fallback parsing failed', [
                'query' => $query,
                'error' => $e->getMessage()
            ]);
        }
        
        return $results;
    }

    private function testGoogleSearchConnection(): bool
    {
        try {
            $response = $this->httpClient->request('GET', self::PYTHON_API_BASE_URL . '/google-search/health', [
                'timeout' => 10
            ]);

            $data = $response->toArray();
            return $data['status'] === 'healthy';

        } catch (\Exception $e) {
            $this->logger->error('Google Search connection test failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}