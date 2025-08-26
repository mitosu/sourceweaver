<?php

namespace App\DataFixtures;

use App\Entity\Investigation;
use App\Entity\Target;
use App\Entity\User;
use App\Entity\Workspace;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class InvestigationTestFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $adminUser = $this->getReference('admin-user', User::class);
        $miguelUser = $this->getReference('miguel-user', User::class);
        
        // Get the first workspace available
        $workspace = $manager->getRepository(Workspace::class)->findOneBy([]);
        if (!$workspace) {
            throw new \RuntimeException('No workspace found. Make sure WorkspaceFixtures are loaded first.');
        }

        $investigationsData = [
            [
                'name' => 'Análisis de Infraestructura Crítica',
                'description' => 'Investigación de la infraestructura de red de organizaciones críticas para identificar vulnerabilidades.',
                'status' => 'active',
                'priority' => 'high',
                'daysAgo' => 1
            ],
            [
                'name' => 'Threat Intelligence Collection',
                'description' => 'Recopilación sistemática de inteligencia de amenazas desde fuentes OSINT públicas.',
                'status' => 'completed',
                'priority' => 'medium',
                'daysAgo' => 2
            ],
            [
                'name' => 'Domain Investigation Campaign',
                'description' => 'Análisis exhaustivo de dominios sospechosos reportados por el equipo de seguridad.',
                'status' => 'active',
                'priority' => 'critical',
                'daysAgo' => 5
            ],
            [
                'name' => 'IP Geolocation Study',
                'description' => 'Estudio de patrones de geolocalización de IPs identificadas como maliciosas.',
                'status' => 'draft',
                'priority' => 'low',
                'daysAgo' => 7
            ],
            [
                'name' => 'URL Malware Detection',
                'description' => 'Detección y análisis de malware en URLs sospechosas utilizando múltiples engines.',
                'status' => 'active',
                'priority' => 'high',
                'daysAgo' => 1
            ],
            [
                'name' => 'Social Media Investigation',
                'description' => 'Investigación de perfiles y actividades sospechosas en redes sociales.',
                'status' => 'completed',
                'priority' => 'medium',
                'daysAgo' => 10
            ],
            [
                'name' => 'APT Group Tracking',
                'description' => 'Seguimiento de indicators of compromise asociados a grupos APT conocidos.',
                'status' => 'active',
                'priority' => 'critical',
                'daysAgo' => 3
            ],
            [
                'name' => 'Phishing Campaign Analysis',
                'description' => 'Análisis de campañas de phishing dirigidas contra la organización.',
                'status' => 'active',
                'priority' => 'high',
                'daysAgo' => 4
            ]
        ];

        foreach ($investigationsData as $index => $data) {
            $investigation = new Investigation();
            $investigation->setName($data['name']);
            $investigation->setDescription($data['description']);
            $investigation->setStatus($data['status']);
            $investigation->setPriority($data['priority']);
            
            // Assign all investigations to miguel user for testing
            $user = $miguelUser;
            $investigation->setCreatedBy($user);
            $investigation->setWorkspace($workspace);
            
            // Set creation date in the past
            $createdAt = new \DateTimeImmutable();
            $createdAt = $createdAt->modify('-' . $data['daysAgo'] . ' days');
            
            // Use reflection to set the createdAt field since it's normally auto-set
            $reflection = new \ReflectionClass($investigation);
            $property = $reflection->getProperty('createdAt');
            $property->setAccessible(true);
            $property->setValue($investigation, $createdAt);

            $manager->persist($investigation);

            // Add some sample targets to a few investigations
            if (in_array($data['name'], ['Análisis de Infraestructura Crítica', 'Domain Investigation Campaign', 'URL Malware Detection'])) {
                // Add targets for more realistic data
                $targetsData = [
                    'Análisis de Infraestructura Crítica' => [
                        ['type' => 'ip', 'value' => '1.1.1.1'],
                        ['type' => 'ip', 'value' => '8.8.8.8'],
                        ['type' => 'domain', 'value' => 'google.com']
                    ],
                    'Domain Investigation Campaign' => [
                        ['type' => 'domain', 'value' => 'suspicious-domain.com'],
                        ['type' => 'url', 'value' => 'https://malicious-site.net/payload']
                    ],
                    'URL Malware Detection' => [
                        ['type' => 'url', 'value' => 'https://example-malware.com/dropper'],
                        ['type' => 'ip', 'value' => '192.168.1.100']
                    ]
                ];

                if (isset($targetsData[$data['name']])) {
                    foreach ($targetsData[$data['name']] as $targetData) {
                        $target = new Target();
                        $target->setType($targetData['type']);
                        $target->setValue($targetData['value']);
                        $target->setDescription('Target automático para testing');
                        $target->setInvestigation($investigation);

                        $manager->persist($target);
                    }
                }
            }
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            AppFixtures::class,
            WorkspaceFixtures::class,
            ExtendedWorkspaceFixtures::class,
        ];
    }
}