<?php

namespace App\Command;

use App\Repository\ApiConfigurationRepository;
use App\Service\Analysis\Provider\FastApiProvider;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:test-fastapi',
    description: 'Test FastAPI provider directly',
)]
class TestFastApiCommand extends Command
{
    public function __construct(
        private ApiConfigurationRepository $apiConfigurationRepository,
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('target_value', InputArgument::REQUIRED, 'Target to analyze (e.g., 1.1.1.1)')
            ->addArgument('target_type', InputArgument::OPTIONAL, 'Target type (ip, domain, url)', 'ip')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $targetValue = $input->getArgument('target_value');
        $targetType = $input->getArgument('target_type');

        $io->title('Testing FastAPI Provider Directly');

        try {
            // Get FastAPI configuration
            $config = $this->apiConfigurationRepository->findOneBy(['name' => 'FastAPI-Python', 'isActive' => true]);
            if (!$config) {
                $io->error('FastAPI-Python configuration not found');
                return Command::FAILURE;
            }

            $io->info("Found configuration: {$config->getName()}");

            // Create FastAPI provider
            $provider = new FastApiProvider($this->httpClient, $config, $this->logger);

            $io->section('Provider Configuration');
            $io->writeln("Supported types: " . implode(', ', $provider->getSupportedTypes()));

            $io->section('Analyzing Target');
            $io->info("Target: {$targetType} = {$targetValue}");

            // Perform analysis
            $result = $provider->analyze($targetType, $targetValue);

            $io->success("Analysis completed!");
            
            $io->section('Result');
            $io->writeln("Source: {$result['source']}");
            $io->writeln("Status: {$result['status']}");
            
            if ($result['status'] === 'error') {
                $io->error("Error: {$result['error']}");
            } else {
                $dataCount = count($result['data'] ?? []);
                $io->writeln("Data points: {$dataCount}");
                
                if (isset($result['data']['_metadata'])) {
                    $io->writeln("Execution time: {$result['data']['_metadata']['execution_time']}s");
                }
                
                $io->section('Sample Data');
                $io->writeln(json_encode($result['data'], JSON_PRETTY_PRINT));
            }
            
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error("Test failed: {$e->getMessage()}");
            $io->writeln("Error details: " . $e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}