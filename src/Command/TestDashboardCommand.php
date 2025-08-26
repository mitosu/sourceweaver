<?php

namespace App\Command;

use App\Repository\InvestigationRepository;
use App\Repository\ActivityLogRepository;
use App\Repository\UserRepository;
use App\Service\Workspace\GetUserWorkspaces;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-dashboard',
    description: 'Test dashboard data and templates',
)]
class TestDashboardCommand extends Command
{
    public function __construct(
        private InvestigationRepository $investigationRepository,
        private ActivityLogRepository $activityLogRepository,
        private UserRepository $userRepository,
        private GetUserWorkspaces $getUserWorkspaces
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Testing Dashboard Data');

        try {
            // Get miguel user and refresh from database
            $user = $this->userRepository->findOneBy(['email' => 'miguel@mail.com']);
            if (!$user) {
                $io->error('Miguel user not found');
                return Command::FAILURE;
            }
            
            // Refresh the user entity from database to avoid cache issues
            $this->userRepository->getEntityManager()->refresh($user);

            $io->info("Found user: {$user->getEmail()}");

            // Test workspaces
            $workspaces = ($this->getUserWorkspaces)($user);
            $io->info("User workspaces: " . count($workspaces));

            // Test investigations with updated repository method
            $io->info("User ID: " . $user->getId());
            $allInvestigations = $this->investigationRepository->findByUser($user);
            $io->info("Total investigations for user (updated method): " . count($allInvestigations));

            // Test recent investigations
            $recentInvestigations = $this->investigationRepository->findRecentByUser($user, 5);
            $io->info("Recent investigations: " . count($recentInvestigations));

            // Test statistics
            $stats = [
                'total_investigations' => count($allInvestigations),
                'active_investigations' => count(array_filter($allInvestigations, fn($i) => $i->getStatus() === 'active')),
                'completed_investigations' => count(array_filter($allInvestigations, fn($i) => $i->getStatus() === 'completed')),
                'total_targets' => array_sum(array_map(fn($i) => count($i->getTargets()), $allInvestigations)),
            ];

            $io->section('Dashboard Statistics');
            $io->table(
                ['Metric', 'Value'],
                [
                    ['Total Investigations', $stats['total_investigations']],
                    ['Active Investigations', $stats['active_investigations']],
                    ['Completed Investigations', $stats['completed_investigations']],
                    ['Total Targets', $stats['total_targets']]
                ]
            );

            $io->section('Recent Investigations');
            if (count($recentInvestigations) > 0) {
                $rows = [];
                foreach ($recentInvestigations as $investigation) {
                    $rows[] = [
                        $investigation->getName(),
                        $investigation->getStatus(),
                        $investigation->getPriority(),
                        count($investigation->getTargets()),
                        $investigation->getCreatedAt()->format('Y-m-d H:i')
                    ];
                }
                $io->table(['Name', 'Status', 'Priority', 'Targets', 'Created'], $rows);
            } else {
                $io->warning('No recent investigations found');
            }

            // Test recent activity
            $recentActivity = $this->activityLogRepository->findRecentActivity(7, 10);
            $io->info("Recent activity entries: " . count($recentActivity));

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error("Test failed: {$e->getMessage()}");
            $io->writeln("Error details: " . $e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}