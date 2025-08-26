<?php

namespace App\DataFixtures;

use App\Entity\ApiConfiguration;
use App\Entity\ApiConfigurationOption;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ApiConfigurationFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $user = $this->getReference('admin-user', User::class);

        // Create FastAPI Python microservice configuration
        $fastApiConfig = new ApiConfiguration();
        $fastApiConfig->setName('FastAPI-Python');
        $fastApiConfig->setDescription('Python microservice for OSINT analysis using multiple scripts and APIs');
        $fastApiConfig->setDocumentationUrl('http://localhost:8001/docs');
        $fastApiConfig->setCreatedBy($user);
        $fastApiConfig->setIsActive(true);

        // Add configuration options
        $urlOption = new ApiConfigurationOption();
        $urlOption->setOptionName('fast_api_url');
        $urlOption->setOptionValue('http://python-osint:8001');
        $fastApiConfig->addOption($urlOption);

        $timeoutOption = new ApiConfigurationOption();
        $timeoutOption->setOptionName('timeout');
        $timeoutOption->setOptionValue('120');
        $fastApiConfig->addOption($timeoutOption);

        $manager->persist($fastApiConfig);
        
        // Create VirusTotal configuration example
        $virusTotalConfig = new ApiConfiguration();
        $virusTotalConfig->setName('VirusTotal');
        $virusTotalConfig->setDescription('VirusTotal API for malware and URL analysis');
        $virusTotalConfig->setDocumentationUrl('https://developers.virustotal.com/reference');
        $virusTotalConfig->setCreatedBy($user);
        $virusTotalConfig->setIsActive(false); // Disabled by default until API key is added

        $vtApiKeyOption = new ApiConfigurationOption();
        $vtApiKeyOption->setOptionName('virustotal_api_key');
        $vtApiKeyOption->setOptionValue('YOUR_API_KEY_HERE');
        $virusTotalConfig->addOption($vtApiKeyOption);

        $manager->persist($virusTotalConfig);

        // Create AbuseIPDB configuration example
        $abuseConfig = new ApiConfiguration();
        $abuseConfig->setName('AbuseIPDB');
        $abuseConfig->setDescription('AbuseIPDB API for IP reputation checking');
        $abuseConfig->setDocumentationUrl('https://docs.abuseipdb.com/');
        $abuseConfig->setCreatedBy($user);
        $abuseConfig->setIsActive(false); // Disabled by default until API key is added

        $abuseApiKeyOption = new ApiConfigurationOption();
        $abuseApiKeyOption->setOptionName('abuseipdb_api_key');
        $abuseApiKeyOption->setOptionValue('YOUR_API_KEY_HERE');
        $abuseConfig->addOption($abuseApiKeyOption);

        $manager->persist($abuseConfig);

        $manager->flush();

        // Set references for other fixtures
        $this->setReference('fastapi-config', $fastApiConfig);
        $this->setReference('virustotal-config', $virusTotalConfig);
        $this->setReference('abuseipdb-config', $abuseConfig);
    }

    public function getDependencies(): array
    {
        return [
            AppFixtures::class,
        ];
    }
}
