<?php

namespace App\Tests\Service;

use App\Service\ConfigService;
use App\Service\VoteService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;

class VoteServiceTest extends TestCase
{
    private \PHPUnit\Framework\MockObject\MockObject $parameterBagMock;
    private \PHPUnit\Framework\MockObject\MockObject $filesystemMock;
    private \PHPUnit\Framework\MockObject\MockObject $configServiceMock;
    private VoteService $voteService;
    private string $votesFilePath;
    /** @var array<string, array<string, string>> */
    private array $testPerformances;
    /** @var array<int, string> */
    private array $testTeams;

    protected function setUp(): void
    {
        $this->votesFilePath = sys_get_temp_dir() . '/votes-test.json';
        
        // Données de test
        $this->testTeams = [
            'Team Test 1',
            'Team Test 2'
        ];
        
        $this->testPerformances = [
            'TST' => [
                'name' => 'Test Country',
                'artist' => 'Test Artist',
                'song' => 'Test Song',
                'flag' => '🏁'
            ],
            'DUM' => [
                'name' => 'Dummy Country',
                'artist' => 'Dummy Artist',
                'song' => 'Dummy Song',
                'flag' => '🏴'
            ]
        ];
        
        // Créer le fichier de test initial
        $initialData = [
            'votes' => [
                'user1' => [
                    'team' => 'Team Test 1',
                    'scores' => [
                        'TST' => 8,
                        'DUM' => 5
                    ]
                ]
            ]
        ];
        file_put_contents($this->votesFilePath, json_encode($initialData));
        
        // Mocks
        $this->parameterBagMock = $this->createMock(ParameterBagInterface::class);
        $this->parameterBagMock->method('get')
            ->with('kernel.project_dir')
            ->willReturn(sys_get_temp_dir());
            
        $this->filesystemMock = $this->createMock(Filesystem::class);
        $this->filesystemMock->method('exists')
            ->willReturn(true);
            
        $this->configServiceMock = $this->createMock(ConfigService::class);
        $this->configServiceMock->method('getTeams')
            ->willReturn($this->testTeams);
        $this->configServiceMock->method('getPerformances')
            ->willReturn($this->testPerformances);
            
        // Création du service à tester avec des dépendances mockées
        $this->voteService = new VoteService($this->parameterBagMock, $this->filesystemMock, $this->configServiceMock);
        
        // Modification du chemin du fichier de votes pour le test
        $reflectionClass = new \ReflectionClass(VoteService::class);
        $reflectionProperty = $reflectionClass->getProperty('votesFilePath');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($this->voteService, $this->votesFilePath);
        
        // Forcer le rechargement des votes
        $reflectionProperty = $reflectionClass->getProperty('votes');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($this->voteService, null);
    }
    
    protected function tearDown(): void
    {
        if (file_exists($this->votesFilePath)) {
            unlink($this->votesFilePath);
        }
    }
    
    public function testGetAllVotes(): void
    {
        $votes = $this->voteService->getAllVotes();
        
        $this->assertIsArray($votes);
        $this->assertArrayHasKey('user1', $votes);
        $this->assertEquals('Team Test 1', $votes['user1']['team']);
        $this->assertEquals(8, $votes['user1']['scores']['TST']);
    }
    
    public function testGetUserVotes(): void
    {
        $userVotes = $this->voteService->getUserVotes('user1');
        
        $this->assertIsArray($userVotes);
        $this->assertEquals('Team Test 1', $userVotes['team']);
        $this->assertEquals(8, $userVotes['scores']['TST']);
        
        // Test utilisateur inexistant
        $nonExistentUser = $this->voteService->getUserVotes('nonexistentuser');
        $this->assertNull($nonExistentUser);
    }
    
    public function testSaveUserVotes(): void
    {
        // Paramètres de test
        $pseudo = 'testUser';
        $team = 'Team Test 2';
        $scores = [
            'TST' => 9,
            'DUM' => 7
        ];
        
        // Appel de la méthode à tester
        $result = $this->voteService->saveUserVotes($pseudo, $team, $scores);
        
        // Vérification du résultat
        $this->assertTrue($result);
        
        // Vérification que les votes ont été enregistrés
        $savedVotes = $this->voteService->getUserVotes($pseudo);
        $this->assertNotNull($savedVotes);
        $this->assertEquals($team, $savedVotes['team']);
        $this->assertEquals($scores, $savedVotes['scores']);
    }
    
    public function testSaveUserVotesWithInvalidData(): void
    {
        // Test avec pseudo vide
        $this->expectException(\InvalidArgumentException::class);
        $this->voteService->saveUserVotes('', 'Team Test 1', ['TST' => 8]);
    }
    
    public function testSaveUserVotesWithInvalidTeam(): void
    {
        // Test avec équipe invalide
        $this->expectException(\InvalidArgumentException::class);
        $this->voteService->saveUserVotes('user2', 'Invalid Team', ['TST' => 8]);
    }
    
    public function testSaveUserVotesWithInvalidCountry(): void
    {
        // Test avec pays invalide
        $this->expectException(\InvalidArgumentException::class);
        $this->voteService->saveUserVotes('user2', 'Team Test 1', ['XXX' => 8]);
    }
    
    public function testSaveUserVotesWithInvalidScore(): void
    {
        // Test avec score invalide (trop élevé)
        $this->expectException(\InvalidArgumentException::class);
        $this->voteService->saveUserVotes('user2', 'Team Test 1', ['TST' => 11]);
    }
    
    public function testCalculateRanking(): void
    {
        // Préparation des données supplémentaires
        $initialData = [
            'votes' => [
                'user1' => [
                    'team' => 'Team Test 1',
                    'scores' => [
                        'TST' => 8,
                        'DUM' => 5
                    ]
                ],
                'user2' => [
                    'team' => 'Team Test 2',
                    'scores' => [
                        'TST' => 6,
                        'DUM' => 9
                    ]
                ]
            ]
        ];
        file_put_contents($this->votesFilePath, json_encode($initialData));
        
        // Forcer le rechargement des votes
        $reflectionClass = new \ReflectionClass(VoteService::class);
        $reflectionProperty = $reflectionClass->getProperty('votes');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($this->voteService, null);
        
        // Appel de la méthode à tester
        $ranking = $this->voteService->calculateRanking();
        
        // Vérifications
        $this->assertIsArray($ranking);
        $this->assertCount(2, $ranking);
        
        // Vérification de l'ordre (par score moyen décroissant)
        $keys = array_keys($ranking);
        
        // TST doit avoir une moyenne de 7 (8+6)/2
        $this->assertEqualsWithDelta(7.0, $ranking['TST']['averageScore'], 0.01);
        $this->assertEquals(2, $ranking['TST']['totalVotes']);
        
        // DUM doit avoir une moyenne de 7 (5+9)/2
        $this->assertEqualsWithDelta(7.0, $ranking['DUM']['averageScore'], 0.01);
        $this->assertEquals(2, $ranking['DUM']['totalVotes']);
    }
    
    public function testGetTeamRanking(): void
    {
        // Préparation des données supplémentaires
        $initialData = [
            'votes' => [
                'user1' => [
                    'team' => 'Team Test 1',
                    'scores' => [
                        'TST' => 8,
                        'DUM' => 5
                    ]
                ],
                'user2' => [
                    'team' => 'Team Test 2',
                    'scores' => [
                        'TST' => 6,
                        'DUM' => 9
                    ]
                ],
                'user3' => [
                    'team' => 'Team Test 1',
                    'scores' => [
                        'TST' => 10,
                        'DUM' => 3
                    ]
                ]
            ]
        ];
        file_put_contents($this->votesFilePath, json_encode($initialData));
        
        // Forcer le rechargement des votes
        $reflectionClass = new \ReflectionClass(VoteService::class);
        $reflectionProperty = $reflectionClass->getProperty('votes');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($this->voteService, null);
        
        // Appel de la méthode à tester pour Team Test 1
        $teamRanking = $this->voteService->getTeamRanking('Team Test 1');
        
        // Vérifications
        $this->assertIsArray($teamRanking);
        
        // TST doit avoir une moyenne de 9 (8+10)/2 pour Team Test 1
        $this->assertEqualsWithDelta(9.0, $teamRanking['TST']['averageScore'], 0.01);
        $this->assertEquals(2, $teamRanking['TST']['totalVotes']);
        
        // DUM doit avoir une moyenne de 4 (5+3)/2 pour Team Test 1
        $this->assertEqualsWithDelta(4.0, $teamRanking['DUM']['averageScore'], 0.01);
        $this->assertEquals(2, $teamRanking['DUM']['totalVotes']);
    }
    
    public function testPartialVoteUpdate(): void
    {
        // Configurer les données initiales
        $initialData = [
            'votes' => [
                'testUser' => [
                    'team' => 'Team Test 1',
                    'scores' => [
                        'TST' => 8,
                        'DUM' => 5
                    ]
                ]
            ]
        ];
        file_put_contents($this->votesFilePath, json_encode($initialData));
        
        // Forcer le rechargement des votes
        $reflectionClass = new \ReflectionClass(VoteService::class);
        $reflectionProperty = $reflectionClass->getProperty('votes');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($this->voteService, null);
        
        // Update seulement un pays (TST)
        $partialScores = [
            'TST' => 10
        ];
        
        // Sauvegarder les votes partiels
        $result = $this->voteService->saveUserVotes('testUser', 'Team Test 1', $partialScores);
        $this->assertTrue($result);
        
        // Vérifier que les deux pays sont toujours présents
        $savedVotes = $this->voteService->getUserVotes('testUser');
        $this->assertNotNull($savedVotes);
        
        // TST a été mis à jour
        $this->assertEquals(10, $savedVotes['scores']['TST']);
        
        // DUM a été conservé
        $this->assertEquals(5, $savedVotes['scores']['DUM']);
    }
}