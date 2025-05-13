<?php

namespace App\Controller;

use App\Service\ConfigService;
use App\Service\VoteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validation;

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
        
        // Valider l'équipe si elle est fournie
        if (!empty($team)) {
            $validTeams = $this->configService->getTeams();
            if (!in_array($team, $validTeams, true)) {
                return $this->jsonError('Équipe non valide', Response::HTTP_BAD_REQUEST);
            }
        }
        
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
            $stats = $this->calculateStats($ranking);
            
            return new JsonResponse([
                'success' => true,
                'ranking' => array_values($ranking), // Convertir en tableau indexé pour faciliter l'utilisation
                'stats' => $stats
            ]);
        } catch (\Exception $e) {
            // Log l'erreur côté serveur
            error_log('Erreur lors du calcul des résultats: ' . $e->getMessage());
            
            return $this->jsonError('Erreur lors du calcul des résultats', Response::HTTP_INTERNAL_SERVER_ERROR);
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
                    'averageVotesPerVoter' => 0,
                ];
            }
            
            // Calculer les statistiques par équipe
            foreach ($votes as $pseudo => $userData) {
                if (isset($userData['team']) && isset($teamStats[$userData['team']])) {
                    $team = $userData['team'];
                    $teamStats[$team]['totalVoters']++;
                    
                    if (isset($userData['scores']) && is_array($userData['scores'])) {
                        $voteCount = count($userData['scores']);
                        $teamStats[$team]['totalVotes'] += $voteCount;
                    }
                }
            }
            
            // Calculer les moyennes
            foreach ($teamStats as $team => $stats) {
                if ($stats['totalVoters'] > 0) {
                    $teamStats[$team]['averageVotesPerVoter'] = round($stats['totalVotes'] / $stats['totalVoters'], 1);
                }
            }
            
            return new JsonResponse([
                'success' => true,
                'teams' => array_values($teamStats)
            ]);
        } catch (\Exception $e) {
            // Log l'erreur côté serveur
            error_log('Erreur lors du calcul des statistiques par équipe: ' . $e->getMessage());
            
            return $this->jsonError('Erreur lors du calcul des statistiques par équipe', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Calcule les statistiques globales à partir du classement.
     * 
     * @param array $ranking Le classement des performances
     * @return array Les statistiques calculées
     */
    private function calculateStats(array $ranking): array
    {
        $stats = [
            'totalVotes' => 0,
            'totalPerformances' => count($ranking),
            'highestScore' => 0,
            'lowestScore' => 10,
            'averageAllScores' => 0,
        ];
        
        $votes = $this->voteService->getAllVotes();
        $stats['totalVoters'] = count($votes);
        
        $sumScores = 0;
        $countScores = 0;
        
        foreach ($ranking as $data) {
            $stats['totalVotes'] += $data['totalVotes'];
            
            if ($data['totalVotes'] > 0) {
                if ($data['averageScore'] > $stats['highestScore']) {
                    $stats['highestScore'] = $data['averageScore'];
                }
                
                if ($data['averageScore'] < $stats['lowestScore']) {
                    $stats['lowestScore'] = $data['averageScore'];
                }
                
                $sumScores += $data['averageScore'] * $data['totalVotes'];
                $countScores += $data['totalVotes'];
            }
        }
        
        if ($countScores > 0) {
            $stats['averageAllScores'] = round($sumScores / $countScores, 2);
        }
        
        // Si aucun vote, on réinitialise la plus basse note
        if ($stats['totalVotes'] === 0) {
            $stats['lowestScore'] = 0;
        }
        
        return $stats;
    }
    
    /**
     * Crée une réponse JSON d'erreur.
     */
    private function jsonError(string $message, int $status = Response::HTTP_BAD_REQUEST): JsonResponse
    {
        return new JsonResponse([
            'success' => false,
            'message' => $message
        ], $status);
    }
}