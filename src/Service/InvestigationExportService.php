<?php

namespace App\Service;

use App\Entity\Investigation;
use App\Entity\Target;
use Twig\Environment;

class InvestigationExportService
{
    public function __construct(
        private Environment $twig
    ) {}

    public function generateHtmlReport(Investigation $investigation): string
    {
        $targetsData = [];
        
        foreach ($investigation->getTargets() as $target) {
            $targetsData[] = [
                'target' => $target,
                'analysisResults' => $this->formatAnalysisResults($target),
                'summary' => $this->generateTargetSummary($target)
            ];
        }

        return $this->twig->render('investigation/export.html.twig', [
            'investigation' => $investigation,
            'targetsData' => $targetsData,
            'exportDate' => new \DateTimeImmutable(),
            'totalTargets' => count($targetsData),
            'analyzedTargets' => count(array_filter($targetsData, fn($data) => $data['target']->getStatus() === 'analyzed')),
            'pendingTargets' => count(array_filter($targetsData, fn($data) => $data['target']->getStatus() === 'pending')),
            'errorTargets' => count(array_filter($targetsData, fn($data) => $data['target']->getStatus() === 'error'))
        ]);
    }

    private function formatAnalysisResults(Target $target): array
    {
        $formattedResults = [];
        
        foreach ($target->getAnalysisResults() as $result) {
            $formattedResults[] = [
                'source' => $result->getSource(),
                'status' => $result->getStatus(),
                'data' => $this->formatResultData($result->getData()),
                'analyzedAt' => $result->getAnalyzedAt(),
                'errorMessage' => $result->getErrorMessage()
            ];
        }

        return $formattedResults;
    }

    private function formatResultData(?array $data): array
    {
        if (!$data) {
            return [];
        }

        // For now, return data as-is for debugging, format later in template
        return $data;
    }

    private function formatVirusTotalData(array $data): array
    {
        $vt = $data['virustotal'] ?? $data;
        
        // Handle different VirusTotal API response formats
        $reputation = $vt['reputation'] ?? $vt['positives'] ?? 0;
        
        // Try different possible structures for total engines
        $totalEngines = 0;
        if (isset($vt['stats']['malicious'])) {
            $totalEngines = $vt['stats']['malicious'];
        } elseif (isset($vt['stats'])) {
            $totalEngines = array_sum($vt['stats']); // Sum all stats
        } elseif (isset($vt['total'])) {
            $totalEngines = $vt['total'];
        } elseif (isset($vt['engines'])) {
            $totalEngines = count($vt['engines']); // Count engines
        }
        
        $scanDate = $vt['analysis_date'] ?? $vt['scan_date'] ?? null;
        $permalink = $vt['permalink'] ?? null;
        
        $result = [
            'source' => 'VirusTotal',
            'reputation' => $reputation,
            'total_engines' => $totalEngines,
            'summary' => $reputation . ' de ' . $totalEngines . ' motores analizaron el recurso',
            'raw_data' => $vt // Keep raw data for debugging
        ];
        
        // Only add fields if they exist
        if ($scanDate) {
            $result['scan_date'] = $scanDate;
        }
        
        if ($permalink) {
            $result['permalink'] = $permalink;
        }
        
        return $result;
    }

    private function formatGoogleSearchData(array $data): array
    {
        $results = $data['google_search'] ?? [];
        
        return [
            'source' => 'Google Search',
            'total_results' => $results['searchInformation']['totalResults'] ?? 0,
            'results' => array_slice($results['items'] ?? [], 0, 10), // First 10 results
            'search_time' => $results['searchInformation']['searchTime'] ?? 0
        ];
    }

    private function formatAliasSearchData(array $data): array
    {
        $alias = $data['alias_search'] ?? [];
        
        return [
            'source' => 'Alias Search',
            'total_platforms' => count($alias['platforms'] ?? []),
            'found_platforms' => count(array_filter($alias['platforms'] ?? [], fn($p) => !empty($p['results']))),
            'platforms' => $alias['platforms'] ?? [],
            'recommendations' => $alias['recommendations'] ?? []
        ];
    }

    private function formatHaveIBeenPwnedData(array $data): array
    {
        $hibp = $data['haveibeenpwned'] ?? [];
        
        return [
            'source' => 'HaveIBeenPwned',
            'breaches_found' => count($hibp['breaches'] ?? []),
            'breaches' => $hibp['breaches'] ?? [],
            'pastes_found' => count($hibp['pastes'] ?? []),
            'pastes' => $hibp['pastes'] ?? []
        ];
    }

    private function formatDorkingSearchData(array $data): array
    {
        $dorking = $data['dorking_search'] ?? [];
        
        return [
            'source' => 'Google Dorking',
            'queries_executed' => count($dorking['queries'] ?? []),
            'total_results' => array_sum(array_map(fn($q) => count($q['results'] ?? []), $dorking['queries'] ?? [])),
            'queries' => $dorking['queries'] ?? []
        ];
    }

    private function generateTargetSummary(Target $target): array
    {
        $summary = [
            'status' => $target->getStatus(),
            'type' => $target->getType(),
            'value' => $target->getValue(),
            'tools_used' => $target->getOsintTools(),
            'total_results' => count($target->getAnalysisResults()),
            'successful_analyses' => 0,
            'failed_analyses' => 0,
            'threat_indicators' => [],
            'recommendations' => []
        ];

        foreach ($target->getAnalysisResults() as $result) {
            if ($result->getStatus() === 'success') {
                $summary['successful_analyses']++;
                $summary['threat_indicators'] = array_merge(
                    $summary['threat_indicators'], 
                    $this->extractThreatIndicators($result->getData())
                );
            } else {
                $summary['failed_analyses']++;
            }
        }

        $summary['recommendations'] = $this->generateRecommendations($target, $summary);

        return $summary;
    }

    private function extractThreatIndicators(?array $data): array
    {
        $indicators = [];
        
        if (!$data) return $indicators;

        // Extract from VirusTotal
        if (isset($data['virustotal']['positives']) && $data['virustotal']['positives'] > 0) {
            $indicators[] = 'Detectado por ' . $data['virustotal']['positives'] . ' motores antivirus';
        }

        // Extract from HaveIBeenPwned
        if (isset($data['haveibeenpwned']['breaches']) && count($data['haveibeenpwned']['breaches']) > 0) {
            $indicators[] = 'Encontrado en ' . count($data['haveibeenpwned']['breaches']) . ' brechas de seguridad';
        }

        return $indicators;
    }

    private function generateRecommendations(Target $target, array $summary): array
    {
        $recommendations = [];

        // Based on threat indicators
        if (!empty($summary['threat_indicators'])) {
            $recommendations[] = 'ALERTA: Este target presenta indicadores de amenaza. Revisar análisis detallado.';
        }

        // Based on failed analyses
        if ($summary['failed_analyses'] > 0) {
            $recommendations[] = 'Algunos análisis fallaron. Considerar repetir análisis más tarde.';
        }

        // Based on target type
        switch ($target->getType()) {
            case 'domain':
                $recommendations[] = 'Considerar análisis adicional de subdominios y DNS.';
                break;
            case 'ip':
                $recommendations[] = 'Revisar geolocalización y puertos abiertos.';
                break;
            case 'email':
                $recommendations[] = 'Verificar alias asociados y presencia en redes sociales.';
                break;
            case 'hash':
                $recommendations[] = 'Correlacionar con bases de datos de malware adicionales.';
                break;
        }

        // If no results
        if ($summary['successful_analyses'] === 0) {
            $recommendations[] = 'No se obtuvieron resultados. Verificar validez del target.';
        }

        return $recommendations;
    }
}