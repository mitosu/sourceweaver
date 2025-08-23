<?php
namespace App\DataFixtures;

use App\Entity\User;
use App\Factory\WorkspaceFactory;
use App\Factory\WorkspaceMembershipFactory;
use App\Factory\DashboardFactory;
use App\Factory\TabFactory;
use App\Entity\ValueObject\DashboardName;
use App\Entity\ValueObject\TabName;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface; // Import DependentFixtureInterface
use Doctrine\Persistence\ObjectManager;
use Doctrine\DBAL\Connection; // Import Connection
use App\Entity\ValueObject\WorkspaceName;

class ExtendedWorkspaceFixtures extends Fixture implements DependentFixtureInterface // Implement DependentFixtureInterface
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
            if (empty($userIds)) {
                 throw new \RuntimeException("No se encontraron usuarios con el rol 'ADMIN' para ejecutar esta fixture.");
            }
            
             throw new \RuntimeException("Se requieren al menos 2 usuarios con rol 'ADMIN' para ejecutar esta fixture. Encontrados: " . count($userIds));
        }

        // Fetch user entities using the IDs
        $users = $manager->getRepository(User::class)->findBy(['id' => $userIds]);

        // Ensure users are found for the fetched IDs (should always be true if IDs came from the DB)
        if (count($users) < count($userIds)) {
            throw new \RuntimeException("No se pudieron cargar todos los usuarios ADMIN desde la base de datos.");
        }


        foreach ($users as $index => $user) {
            $workspaceName = new WorkspaceName('Logitec');
            $workspace = WorkspaceFactory::create($workspaceName, $user);
            $manager->persist($workspace);

            $membership = WorkspaceMembershipFactory::create($workspace, $user, 'ADMIN');
            $manager->persist($membership);

            foreach (range(1, 2) as $d) {
                $dashboard = DashboardFactory::create(new DashboardName("Dashboard {$d} para {$user->getId()}"), $workspace);
                $manager->persist($dashboard);

                foreach (range(1, 3) as $i) {
                    $type = ['mainTable', 'kanban', 'calendar'][($i - 1) % 3];
                    $tab = match ($type) {
                        'mainTable' => TabFactory::mainTable(new TabName("Main Table {$i}"), $dashboard, $i),
                        'kanban' => TabFactory::kanban(new TabName("Kanban {$i}"), $dashboard, $i),
                        'calendar' => TabFactory::calendar(new TabName("Calendar {$i}"), $dashboard, $i),
                    };
                    $manager->persist($tab);
                }
            }
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            AppFixtures::class,
        ];
    }
}
