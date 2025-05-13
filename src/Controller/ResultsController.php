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
            
            // Agréger quelques statistiques 
            $stats = [
                'totalVotes' => 0,
                'totalPerformances' => count($ranking),
                'highestScore' => 0,
                'lowestScore' => 10,
            ];
            
            $votes = $this->voteService->getAllVotes();
            $stats['totalVoters'] = count($votes);
            
            foreach ($ranking as $data) {
                $stats['totalVotes'] += $data['totalVotes'];
                if ($data['averageScore'] > $stats['highestScore']) {
                    $stats['highestScore'] = $data['averageScore'];
                }
                if ($data['averageScore'] < $stats['lowestScore'] && $data['totalVotes'] > 0) {
                    $stats['lowestScore'] = $data['averageScore'];
                }
            }
            
            return new JsonResponse([
                'success' => true,
                'ranking' => array_values($ranking), // Convertir en tableau indexé pour faciliter l'utilisation
                'stats' => $stats
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur lors du calcul des résultats: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
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
                    'totalVotes' => 0,
                ];
            }
            
            // Calculer les statistiques par équipe
            foreach ($votes as $pseudo => $userData) {
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
                'message' => 'Erreur lors du calcul des statistiques par équipe: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}