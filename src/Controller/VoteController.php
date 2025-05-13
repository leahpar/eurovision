<?php

namespace App\Controller;

use App\Service\ConfigService;
use App\Service\VoteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class VoteController extends AbstractController
{
    public function __construct(
        private readonly ConfigService $configService,
        private readonly VoteService $voteService
    ) {
    }

    #[Route('/vote', name: 'app_vote')]
    public function index(): Response
    {
        // Récupérer les données nécessaires pour la page de vote
        $edition = $this->configService->getEdition();
        $performances = $this->configService->getPerformances();

        // Trier les performances par ordre alphabétique du nom de pays
        uasort($performances, function ($a, $b) {
            return $a['name'] <=> $b['name'];
        });

        return $this->render('vote/index.html.twig', [
            'edition' => $edition,
            'performances' => $performances,
        ]);
    }

    #[Route('/api/vote', name: 'api_vote', methods: ['POST'])]
    public function vote(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        $pseudo = $data['pseudo'] ?? '';
        $team = $data['team'] ?? '';
        $scores = $data['scores'] ?? [];
        $userId = $data['userId'] ?? null;
        
        // Validation basique
        if (empty($pseudo) || empty($team) || empty($scores)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Pseudo, équipe et scores sont obligatoires'
            ], 400);
        }
        
        try {
            // Si un userId est fourni mais le pseudo ou l'équipe ne correspond pas, c'est qu'il y a eu un changement
            if ($userId !== null) {
                $userInfo = $this->voteService->getUserByUserId($userId);
                
                if ($userInfo !== null && !empty($userInfo['userData'])) {
                    // Si l'ID est valide mais le pseudo a changé, mettre à jour l'ancien pseudo
                    if (isset($userInfo['userData']['pseudo']) && $userInfo['userData']['pseudo'] !== $pseudo) {
                        // Le pseudo a été changé par l'admin - utiliser le nouveau pseudo mais conserver l'ID
                        $pseudo = $userInfo['userData']['pseudo'];
                    }
                    
                    // Si l'ID est valide mais l'équipe a changé, mettre à jour l'ancienne équipe
                    if (isset($userInfo['userData']['team']) && $userInfo['userData']['team'] !== $team) {
                        // L'équipe a été changée par l'admin - utiliser la nouvelle équipe mais conserver l'ID
                        $team = $userInfo['userData']['team'];
                    }
                }
            }
            
            // Enregistrer les votes
            $result = $this->voteService->saveUserVotes($pseudo, $team, $scores, $userId);
            
            return new JsonResponse([
                'success' => true,
                'userId' => $result['userId'],
                'message' => 'Votes enregistrés avec succès'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 400);
        }
    }

    #[Route('/api/user-votes', name: 'api_user_votes', methods: ['GET'])]
    public function getUserVotes(Request $request): JsonResponse
    {
        $pseudo = $request->query->get('pseudo');
        $userId = $request->query->get('userId');
        
        // Si on a un ID utilisateur, on privilégie la recherche par ID
        if (!empty($userId)) {
            $userInfo = $this->voteService->getUserByUserId($userId);
            
            if ($userInfo !== null && !empty($userInfo['userData'])) {
                $pseudo = $userInfo['userData']['pseudo'] ?? '';
                $team = $userInfo['userData']['team'] ?? '';
                
                return new JsonResponse([
                    'success' => true,
                    'pseudo' => $pseudo,
                    'team' => $team,
                    'votes' => $userInfo['userData']
                ]);
            }
        }
        
        // Sinon, on fait une recherche classique par pseudo
        if (empty($pseudo)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Pseudo ou userId obligatoire'
            ], 400);
        }
        
        $userVotes = $this->voteService->getUserVotes($pseudo);
        
        return new JsonResponse([
            'success' => true,
            'votes' => $userVotes
        ]);
    }
}