<?php

namespace App\Command;

use App\Entity\Investigation;
use App\Entity\Target;
use App\Repository\InvestigationRepository;
use App\Service\Analysis\AnalysisService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-analysis',
    description: 'Test analysis workflow with Python microservice',
)]
class TestAnalysisCommand extends Command
{
    public function __construct(
        private AnalysisService $analysisService,
        private InvestigationRepository $investigationRepository,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('target_value', InputArgument::REQUIRED, 'Target to analyze (e.g., 8.8.8.8)')
            ->addArgument('target_type', InputArgument::OPTIONAL, 'Target type (ip, domain, url)', 'ip')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $targetValue = $input->getArgument('target_value');
        $targetType = $input->getArgument('target_type');

        $io->title('Testing OSINT Analysis Workflow');
        $io->info("Analyzing {$targetType}: {$targetValue}");

        try {
            // Get the first investigation for testing
            $investigation = $this->investigationRepository->findOneBy([]);
            if (!$investigation) {
                $io->error('No investigation found. Please create an investigation first.');
                return Command::FAILURE;
            }

            $io->info("Using investigation: {$investigation->getName()}");

            // Create a test target
            $target = new Target();
            $target->setType($targetType);
            $target->setValue($targetValue);
            $target->setDescription("Test target created by analysis command");
            $target->setInvestigation($investigation);

            $this->entityManager->persist($target);
            $this->entityManager->flush();

            $io->info("Created target with ID: {$target->getId()}");

            // Perform analysis
            $io->section('Starting Analysis');
            $results = $this->analysisService->analyzeTarget($target);

            $io->success("Analysis completed! Generated " . count($results) . " results.");

            // Display results summary
            $io->section('Analysis Results Summary');
            foreach ($results as $result) {
                $io->writeln("- Source: {$result->getSource()}");
                $io->writeln("  Status: {$result->getStatus()}");
                if ($result->getErrorMessage()) {
                    $io->writeln("  Error: {$result->getErrorMessage()}");
                } else {
                    $dataCount = count($result->getData() ?: []);
                    $io->writeln("  Data points: {$dataCount}");
                }
                $io->writeln('');
            }

            $io->note("You can view detailed results at: /investigations/{$investigation->getId()}/targets/{$target->getId()}/results");
            
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error("Analysis failed: {$e->getMessage()}");
            $io->writeln("Error details: " . $e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}
