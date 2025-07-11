<?php
namespace App\DataFixtures;

use App\Entity\User;
use App\Factory\WorkspaceFactory;
use App\Factory\WorkspaceMembershipFactory;
use App\Factory\DashboardFactory;
use App\Factory\TabFactory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Doctrine\DBAL\Connection; // Import Connection

class ExtendedWorkspaceFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        /** @var Connection $connection */
        $connection = $manager->getConnection();
        $queryBuilder = $connection->createQueryBuilder();

        $queryBuilder
            ->select('u.id')
            ->from('user', 'u')
            ->where('JSON_VALID(u.roles) = 1 AND JSON_CONTAINS(u.roles, :role) = 1')
            ->setParameter('role', '"ADMIN"')
            ->setMaxResults(2);

        $statement = $queryBuilder->executeQuery();
        $userIds = $statement->fetchFirstColumn();

        if (count($userIds) < 2) {
            // Try to fetch at least one admin if two are not available.
            // Or, if the requirement is strictly two admins, this exception is appropriate.
            // For now, let's adjust the message slightly if any admins are found but less than 2.
            if (empty($userIds)) {
                 throw new \RuntimeException("No se encontraron usuarios con el rol 'ADMIN' para ejecutar esta fixture.");
            }
            // If we want to proceed with fewer than 2 admins, we can, otherwise the original check is fine.
            // For this example, let's stick to the requirement of 2, or throw.
            // To be more flexible, one might fetch up to 2 and proceed if at least 1 is found.
            // However, the original logic implies a need for a certain number (2).
            // Let's ensure the message is accurate for the strict check.
             throw new \RuntimeException("Se requieren al menos 2 usuarios con rol 'ADMIN' para ejecutar esta fixture. Encontrados: " . count($userIds));
        }

        // Fetch user entities using the IDs
        $users = $manager->getRepository(User::class)->findBy(['id' => $userIds]);

        // Ensure users are found for the fetched IDs (should always be true if IDs came from the DB)
        if (count($users) < count($userIds)) {
            throw new \RuntimeException("No se pudieron cargar todos los usuarios ADMIN desde la base de datos.");
        }


        foreach ($users as $index => $user) {
            $workspace = WorkspaceFactory::create("Workspace del usuario {$user->getEmail()}", $user);
            $manager->persist($workspace);

            $membership = WorkspaceMembershipFactory::create($workspace, $user, 'ADMIN');
            $manager->persist($membership);

            foreach (range(1, 2) as $d) {
                $dashboard = DashboardFactory::create("Dashboard {$d} para {$user->getEmail()}", $workspace);
                $manager->persist($dashboard);

                foreach (range(1, 3) as $i) {
                    $type = ['mainTable', 'kanban', 'calendar'][($i - 1) % 3];
                    $tab = match ($type) {
                        'mainTable' => TabFactory::mainTable("Main Table {$i}", $dashboard, $i),
                        'kanban' => TabFactory::kanban("Kanban {$i}", $dashboard, $i),
                        'calendar' => TabFactory::calendar("Calendar {$i}", $dashboard, $i),
                    };
                    $manager->persist($tab);
                }
            }
        }

        $manager->flush();
    }
}
