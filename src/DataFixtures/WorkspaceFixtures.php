<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Factory\WorkspaceFactory;
use App\Factory\WorkspaceMembershipFactory;
use App\Factory\DashboardFactory;
use App\Factory\TabFactory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class WorkspaceFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $user = $manager->getRepository(User::class)->findOneBy(['email' => 'miguel@mail.com']);

        if (!$user) {
            throw new \RuntimeException("Se requiere al menos un usuario para asignar el Workspace");
        }

        $workspace = WorkspaceFactory::create('Demo Workspace', $user);
        $manager->persist($workspace);

        $membership = WorkspaceMembershipFactory::create($workspace, $user, 'ADMIN');
        $manager->persist($membership);

        $dashboard = DashboardFactory::create('Main Dashboard', $workspace);
        $manager->persist($dashboard);

        $tab1 = TabFactory::mainTable('Main Table', $dashboard, 1);
        $manager->persist($tab1);

        $tab2 = TabFactory::kanban('Kanban View', $dashboard, 2);
        $manager->persist($tab2);

        $tab3 = TabFactory::calendar('Calendar', $dashboard, 3);
        $manager->persist($tab3);

        $manager->flush();
    }
}
