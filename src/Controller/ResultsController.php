<?php

namespace App\Controller;

use App\Service\ConfigService;
use App\Service\VoteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class ResultsController extends AbstractController
{
    public function __construct(
        private readonly ConfigService $configService,
        private readonly VoteService $voteService
    ) {
    }

    #[Route('/results', name: 'app_results')]
    public function index(): Response
    {
        // Récupérer les données nécessaires pour la page de résultats
        $edition = $this->configService->getEdition();
        $teams = $this->configService->getTeams();

        return $this->render('results/index.html.twig', [
            'edition' => $edition,
            'teams' => $teams,
        ]);
    }

    #[Route('/api/results', name: 'api_results', methods: ['GET'])]
    public function getResults(Request $request): JsonResponse
    {
        $team = $request->query->get('team');
        
        try {
            $ranking = [];
            
            if (!empty($team)) {
                // Filtrer les résultats par équipe si demandé
                $ranking = $this->voteService->getTeamRanking($team);
            } else {
                // Résultats globaux sinon
                $ranking = $this->voteService->calculateRanking();
            }
            
            // Ajouter le rang à chaque entrée
            $rank = 1;
            foreach ($ranking as $countryCode => $data) {
                $ranking[$countryCode]['rank'] = $rank++;
            }
            
            // Agréger quelques statistiques simples
            $stats = [
                'totalVotes' => 0,
                'totalVoters' => count($this->voteService->getAllVotes())
            ];
            
            foreach ($ranking as $data) {
                $stats['totalVotes'] += $data['totalVotes'];
            }
            
            // Calculer les statistiques supplémentaires
            $highestScore = 0;
            $lowestScore = 10;
            $highestScoreCountry = null;
            $lowestScoreCountry = null;
            
            foreach ($ranking as $data) {
                if ($data['totalVotes'] > 0) {
                    if ($data['averageScore'] > $highestScore) {
                        $highestScore = $data['averageScore'];
                        $highestScoreCountry = [
                            'name' => $data['name'],
                            'flag' => $data['flag'],
                            'countryCode' => $data['countryCode']
                        ];
                    }
                    
                    if ($data['averageScore'] < $lowestScore) {
                        $lowestScore = $data['averageScore'];
                        $lowestScoreCountry = [
                            'name' => $data['name'],
                            'flag' => $data['flag'],
                            'countryCode' => $data['countryCode']
                        ];
                    }
                }
            }
            
            $stats['highestScore'] = $highestScore;
            $stats['lowestScore'] = $lowestScore;
            
            if ($highestScoreCountry) {
                $stats['highestScoreCountry'] = $highestScoreCountry;
            }
            
            if ($lowestScoreCountry) {
                $stats['lowestScoreCountry'] = $lowestScoreCountry;
            }
            
            // Fun stats - filtrer par équipe si demandé
            $harshestVoter = $this->voteService->getHarshestVoter(!empty($team) ? $team : null);
            if ($harshestVoter) {
                $stats['harshestVoter'] = $harshestVoter;
            }
            
            $generousVoter = $this->voteService->getGenerousVoter(!empty($team) ? $team : null);
            if ($generousVoter) {
                $stats['generousVoter'] = $generousVoter;
            }
            
            $divisiveCountry = $this->voteService->getMostDivisiveCountry(!empty($team) ? $team : null);
            if ($divisiveCountry) {
                $stats['divisiveCountry'] = $divisiveCountry;
            }
            
            $consensualCountry = $this->voteService->getMostConsensualCountry(!empty($team) ? $team : null);
            if ($consensualCountry) {
                $stats['consensualCountry'] = $consensualCountry;
            }
            
            // Ajout des nouvelles statistiques sur les votants
            $consistentVoter = $this->voteService->getMostConsistentVoter(!empty($team) ? $team : null);
            if ($consistentVoter) {
                $stats['consistentVoter'] = $consistentVoter;
            }
            
            $variedVoter = $this->voteService->getMostVariedVoter(!empty($team) ? $team : null);
            if ($variedVoter) {
                $stats['variedVoter'] = $variedVoter;
            }
            
            // Ajout des nouvelles statistiques fun
            $twinVoters = $this->voteService->getMostSimilarVoters(!empty($team) ? $team : null);
            if ($twinVoters) {
                $stats['twinVoters'] = $twinVoters;
            }
            
            $oppositeVoters = $this->voteService->getMostDifferentVoters(!empty($team) ? $team : null);
            if ($oppositeVoters) {
                $stats['oppositeVoters'] = $oppositeVoters;
            }
            
            return new JsonResponse([
                'success' => true,
                'ranking' => array_values($ranking),
                'stats' => $stats
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 400);
        }
    }

    #[Route('/api/teams-stats', name: 'api_teams_stats', methods: ['GET'])]
    public function getTeamsStats(): JsonResponse
    {
        try {
            $teams = $this->configService->getTeams();
            $votes = $this->voteService->getAllVotes();
            
            $teamStats = [];
            foreach ($teams as $team) {
                $teamStats[$team] = [
                    'name' => $team,
                    'totalVoters' => 0,
                    'totalVotes' => 0
                ];
            }
            
            // Calculer les statistiques par équipe
            foreach ($votes as $userData) {
                if (isset($userData['team']) && isset($teamStats[$userData['team']])) {
                    $teamStats[$userData['team']]['totalVoters']++;
                    
                    if (isset($userData['scores']) && is_array($userData['scores'])) {
                        $teamStats[$userData['team']]['totalVotes'] += count($userData['scores']);
                    }
                }
            }
            
            return new JsonResponse([
                'success' => true,
                'teams' => array_values($teamStats)
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 400);
        }
    }
}