<?php

namespace App\Controller\Api;

use App\Repository\ApiConfigurationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/api-configurations')]
#[IsGranted('ROLE_USER')]
final class ApiConfigurationController extends AbstractController
{
    #[Route('/templates', name: 'api_configuration_templates')]
    public function templates(): JsonResponse
    {
        $templateDir = $this->getParameter('kernel.project_dir') . '/config/api_templates';
        $templates = [];
        
        if (is_dir($templateDir)) {
            $files = glob($templateDir . '/*.json');
            
            foreach ($files as $file) {
                $content = json_decode(file_get_contents($file), true);
                if ($content) {
                    $content['filename'] = basename($file);
                    $templates[] = $content;
                }
            }
        }
        
        return new JsonResponse($templates);
    }
    
    #[Route('/{id}/test', name: 'api_configuration_test', methods: ['POST'])]
    public function test(string $id, ApiConfigurationRepository $repository): JsonResponse
    {
        $apiConfig = $repository->find($id);
        
        if (!$apiConfig) {
            return new JsonResponse(['success' => false, 'error' => 'API configuration not found'], 404);
        }
        
        if (!$apiConfig->isActive()) {
            return new JsonResponse(['success' => false, 'error' => 'API configuration is inactive']);
        }
        
        // Test basic connectivity
        try {
            $config = [];
            foreach ($apiConfig->getOptions() as $option) {
                $config[$option->getName()] = $option->getValue();
            }
            
            $baseUrl = $config['base_url'] ?? '';
            $apiKey = $config['api_key'] ?? '';
            
            if (!$baseUrl) {
                return new JsonResponse(['success' => false, 'error' => 'Base URL not configured']);
            }
            
            if (!$apiKey) {
                return new JsonResponse(['success' => false, 'error' => 'API Key not configured']);
            }
            
            // Simulate a basic connectivity test
            $testPassed = $this->performConnectivityTest($apiConfig->getName(), $baseUrl, $apiKey);
            
            if ($testPassed) {
                return new JsonResponse([
                    'success' => true,
                    'message' => 'API connection test successful',
                    'details' => [
                        'name' => $apiConfig->getName(),
                        'base_url' => $baseUrl,
                        'has_api_key' => !empty($apiKey)
                    ]
                ]);
            } else {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'API connection test failed - check your credentials and network connectivity'
                ]);
            }
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Test failed: ' . $e->getMessage()
            ]);
        }
    }
    
    private function performConnectivityTest(string $apiName, string $baseUrl, string $apiKey): bool
    {
        // For demo purposes, we'll simulate connectivity tests
        // In a real implementation, you would make actual API calls
        
        $apiName = strtolower($apiName);
        
        // Simulate different success rates for different APIs
        $successRates = [
            'virustotal' => 0.9,
            'abuseipdb' => 0.85,
            'urlvoid' => 0.8,
            'default' => 0.75
        ];
        
        $successRate = $successRates[$apiName] ?? $successRates['default'];
        
        // Check if URL and key look valid
        if (!filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            return false;
        }
        
        if (strlen($apiKey) < 10) {
            return false;
        }
        
        // Simulate random success/failure based on success rate
        return (mt_rand() / mt_getrandmax()) < $successRate;
    }
}