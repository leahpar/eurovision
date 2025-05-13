<?php

namespace App\Tests\Service;

use App\Service\ConfigService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;

class ConfigServiceTest extends TestCase
{
    private $parameterBagMock;
    private $filesystemMock;
    private $configService;
    private $configFilePath;
    private $testConfigData;

    protected function setUp(): void
    {
        $this->configFilePath = sys_get_temp_dir() . '/eurovision-test.json';
        
        // Données de test
        $this->testConfigData = [
            'eurovision' => [
                'edition' => 'Eurovision Test Edition',
                'teams' => [
                    'Team Test 1',
                    'Team Test 2'
                ],
                'performances' => [
                    'TST' => [
                        'name' => 'Test Country',
                        'artist' => 'Test Artist',
                        'song' => 'Test Song',
                        'flag' => '🏁'
                    ]
                ]
            ]
        ];
        
        // Créer le fichier de test
        file_put_contents($this->configFilePath, json_encode($this->testConfigData));
        
        // Mocks
        $this->parameterBagMock = $this->createMock(ParameterBagInterface::class);
        $this->parameterBagMock->method('get')
            ->with('kernel.project_dir')
            ->willReturn(sys_get_temp_dir());
            
        $this->filesystemMock = $this->createMock(Filesystem::class);
        $this->filesystemMock->method('exists')
            ->willReturn(true);
            
        // Création du service à tester avec des dépendances mockées
        $this->configService = new ConfigService($this->parameterBagMock, $this->filesystemMock);
        
        // Modification du chemin du fichier de configuration pour le test
        $reflectionClass = new \ReflectionClass(ConfigService::class);
        $reflectionProperty = $reflectionClass->getProperty('configFilePath');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($this->configService, $this->configFilePath);
    }
    
    protected function tearDown(): void
    {
        if (file_exists($this->configFilePath)) {
            unlink($this->configFilePath);
        }
    }
    
    public function testGetConfig(): void
    {
        $config = $this->configService->getConfig();
        
        $this->assertIsArray($config);
        $this->assertArrayHasKey('eurovision', $config);
        $this->assertEquals($this->testConfigData, $config);
    }
    
    public function testGetEdition(): void
    {
        $edition = $this->configService->getEdition();
        
        $this->assertEquals('Eurovision Test Edition', $edition);
    }
    
    public function testGetTeams(): void
    {
        $teams = $this->configService->getTeams();
        
        $this->assertIsArray($teams);
        $this->assertCount(2, $teams);
        $this->assertEquals(['Team Test 1', 'Team Test 2'], $teams);
    }
    
    public function testGetPerformances(): void
    {
        $performances = $this->configService->getPerformances();
        
        $this->assertIsArray($performances);
        $this->assertArrayHasKey('TST', $performances);
        $this->assertEquals($this->testConfigData['eurovision']['performances'], $performances);
    }
    
    public function testGetPerformance(): void
    {
        $performance = $this->configService->getPerformance('TST');
        
        $this->assertIsArray($performance);
        $this->assertArrayHasKey('name', $performance);
        $this->assertEquals('Test Country', $performance['name']);
        
        // Test avec un code pays inexistant
        $nonExistentPerformance = $this->configService->getPerformance('XXX');
        $this->assertNull($nonExistentPerformance);
    }
}