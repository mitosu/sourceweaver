<?php
namespace App\DataFixtures;

use App\Entity\User;
use App\Factory\WorkspaceFactory;
use App\Factory\WorkspaceMembershipFactory;
use App\Factory\DashboardFactory;
use App\Factory\TabFactory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class ExtendedWorkspaceFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Suponemos que ya existen al menos 2 usuarios
        $users = $manager->getRepository(User::class)->findBy([], null, 2);

        if (count($users) < 2) {
            throw new \RuntimeException("Se requieren al menos 2 usuarios para ejecutar esta fixture.");
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
