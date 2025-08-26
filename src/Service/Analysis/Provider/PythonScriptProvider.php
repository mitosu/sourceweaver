<?php

namespace App\Service\Analysis\Provider;

use App\Entity\ApiConfiguration;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PythonScriptProvider extends AbstractApiProvider
{
    private string $scriptsPath;
    
    public function __construct(
        HttpClientInterface $httpClient,
        ApiConfiguration $apiConfiguration,
        string $projectDir
    ) {
        parent::__construct($httpClient, $apiConfiguration);
        $this->scriptsPath = $projectDir . '/python_scripts';
    }
    
    public function analyze(string $targetType, string $targetValue): array
    {
        $scriptName = $this->getScriptForTarget($targetType);
        
        if (!$scriptName) {
            return $this->formatResult('PythonScript', [], 'error', 'No script available for target type');
        }
        
        $scriptPath = $this->scriptsPath . '/' . $scriptName;
        
        if (!file_exists($scriptPath)) {
            return $this->formatResult('PythonScript', [], 'error', "Script not found: {$scriptName}");
        }
        
        try {
            $result = $this->executeScript($scriptPath, $targetValue);
            return $this->formatResult('PythonScript', $result, 'success');
        } catch (\Exception $e) {
            return $this->formatResult('PythonScript', [], 'error', $e->getMessage());
        }
    }
    
    public function getSupportedTypes(): array
    {
        return ['ip', 'domain', 'url', 'email', 'hash'];
    }
    
    private function getScriptForTarget(string $targetType): ?string
    {
        $scriptMapping = [
            'ip' => 'ip_analysis.py',
            'domain' => 'domain_analysis.py', 
            'url' => 'url_analysis.py',
            'email' => 'email_analysis.py',
            'hash' => 'hash_analysis.py'
        ];
        
        return $scriptMapping[$targetType] ?? null;
    }
    
    private function executeScript(string $scriptPath, string $targetValue): array
    {
        $command = [
            'python3',
            $scriptPath,
            $targetValue,
            '--format=json'
        ];
        
        // Add API keys as environment variables if available
        $env = [];
        foreach ($this->config as $key => $value) {
            $env[strtoupper($key)] = $value;
        }
        
        $process = new Process($command, null, $env);
        $process->setTimeout(120); // 2 minutes timeout
        
        try {
            $process->mustRun();
            $output = $process->getOutput();
            
            // Try to decode JSON output
            $data = json_decode($output, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON output from Python script: ' . json_last_error_msg());
            }
            
            return $data;
            
        } catch (ProcessFailedException $e) {
            throw new \Exception('Python script execution failed: ' . $e->getMessage());
        }
    }
}