<?php

namespace App\Command;

use App\Repository\ApiConfigurationRepository;
use App\Service\Analysis\ApiAnalysisService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:debug-api',
    description: 'Debug API configurations and services',
)]
class DebugApiCommand extends Command
{
    public function __construct(
        private ApiConfigurationRepository $apiConfigurationRepository,
        private ?ApiAnalysisService $apiAnalysisService = null
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('API Configuration Debug');

        // Check active API configurations
        $configs = $this->apiConfigurationRepository->findBy(['isActive' => true]);
        $io->section('Active API Configurations');
        
        if (empty($configs)) {
            $io->warning('No active API configurations found!');
            return Command::FAILURE;
        }

        foreach ($configs as $config) {
            $io->writeln("- {$config->getName()}");
            $io->writeln("  Description: {$config->getDescription()}");
            $io->writeln("  Options: " . count($config->getOptions()));
            
            foreach ($config->getOptions() as $option) {
                $value = $option->getOptionValue();
                if (stripos($option->getOptionName(), 'key') !== false) {
                    $value = str_repeat('*', min(strlen($value), 10));
                }
                $io->writeln("    {$option->getOptionName()}: {$value}");
            }
            $io->writeln('');
        }

        // Check if ApiAnalysisService is available
        $io->section('API Analysis Service');
        if ($this->apiAnalysisService) {
            $io->success('ApiAnalysisService is available');
            
            $providers = $this->apiAnalysisService->getAvailableProviders();
            $io->writeln("Available providers: " . count($providers));
            
            foreach ($providers as $provider) {
                $io->writeln("- {$provider['name']}: " . implode(', ', $provider['supported_types']));
            }
        } else {
            $io->error('ApiAnalysisService is not available');
        }

        return Command::SUCCESS;
    }
}
