<?php

namespace App\DataFixtures;

use App\Entity\Investigation;
use App\Entity\Target;
use App\Entity\User;
use App\Entity\Workspace;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class InvestigationFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        // Get existing user and workspace
        $user = $manager->getRepository(User::class)->findOneBy(['email' => 'admin@ironwhisper.com']);
        $workspace = $manager->getRepository(Workspace::class)->findOneBy([]);

        if (!$user || !$workspace) {
            return;
        }

        // Create sample investigations
        $investigation1 = new Investigation();
        $investigation1->setName('Análisis de Dominio Sospechoso');
        $investigation1->setDescription('Investigación de un dominio reportado por usuarios como posible phishing');
        $investigation1->setStatus('active');
        $investigation1->setPriority('high');
        $investigation1->setCreatedBy($user);
        $investigation1->setWorkspace($workspace);
        $manager->persist($investigation1);

        // Add targets to investigation 1
        $target1 = new Target();
        $target1->setType('domain');
        $target1->setValue('suspicious-bank.com');
        $target1->setDescription('Dominio reportado como phishing bancario');
        $target1->setInvestigation($investigation1);
        $manager->persist($target1);

        $target2 = new Target();
        $target2->setType('ip');
        $target2->setValue('192.0.2.123');
        $target2->setDescription('IP asociada al dominio sospechoso');
        $target2->setInvestigation($investigation1);
        $manager->persist($target2);

        $target3 = new Target();
        $target3->setType('email');
        $target3->setValue('admin@suspicious-bank.com');
        $target3->setDescription('Email de contacto del dominio');
        $target3->setInvestigation($investigation1);
        $manager->persist($target3);

        // Create second investigation
        $investigation2 = new Investigation();
        $investigation2->setName('Análisis de Malware');
        $investigation2->setDescription('Análisis de archivo malicioso detectado en red corporativa');
        $investigation2->setStatus('active');
        $investigation2->setPriority('urgent');
        $investigation2->setCreatedBy($user);
        $investigation2->setWorkspace($workspace);
        $manager->persist($investigation2);

        // Add targets to investigation 2
        $target4 = new Target();
        $target4->setType('hash');
        $target4->setValue('d41d8cd98f00b204e9800998ecf8427e');
        $target4->setDescription('Hash MD5 del archivo sospechoso');
        $target4->setInvestigation($investigation2);
        $manager->persist($target4);

        $target5 = new Target();
        $target5->setType('url');
        $target5->setValue('https://evil-download.com/malware.exe');
        $target5->setDescription('URL de descarga del malware');
        $target5->setInvestigation($investigation2);
        $manager->persist($target5);

        // Create completed investigation
        $investigation3 = new Investigation();
        $investigation3->setName('Reconocimiento de Red Externa');
        $investigation3->setDescription('Análisis de IPs escaneando nuestra red');
        $investigation3->setStatus('completed');
        $investigation3->setPriority('medium');
        $investigation3->setCreatedBy($user);
        $investigation3->setWorkspace($workspace);
        $manager->persist($investigation3);

        // Add target to investigation 3
        $target6 = new Target();
        $target6->setType('ip');
        $target6->setValue('198.51.100.42');
        $target6->setDescription('IP origen de los escaneos');
        $target6->setStatus('analyzed');
        $target6->setLastAnalyzed(new \DateTimeImmutable('-1 day'));
        $target6->setInvestigation($investigation3);
        $manager->persist($target6);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            AppFixtures::class,
            ExtendedWorkspaceFixtures::class,
        ];
    }
}