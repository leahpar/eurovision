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
    public function index(Request $request): Response
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
        
        $scores = $data['scores'] ?? [];
        $userId = $data['userId'] ?? null;
        
        // Validation basique
        if (empty($scores) || empty($userId)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Scores et userId obligatoires'
            ], 400);
        }
        
        try {
            $userInfo = $this->voteService->getUserByUserId($userId);
            
            // Vérifier que l'utilisateur existe toujours
            if ($userInfo === null) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Votre compte a été supprimé',
                    'userDeleted' => true
                ], 401);
            }
            
            $pseudo = $userInfo['userData']['pseudo'];
            $team = $userInfo['userData']['team'];

            // Enregistrer les votes
            $result = $this->voteService->saveUserVotes($pseudo, $team, $scores, $userId);
            
            return new JsonResponse([
                'success' => true,
                'userId' => $result['userId'],
                'pseudo' => $pseudo,
                'team' => $team,
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
            
            if ($userInfo !== null) {
                $pseudo = $userInfo['userData']['pseudo'];
                $team = $userInfo['userData']['team'];
                
                return new JsonResponse([
                    'success' => true,
                    'pseudo' => $pseudo,
                    'team' => $team,
                    'votes' => $userInfo['userData'],
                    'userId' => $userId
                ]);
            } else {
                // L'utilisateur a été supprimé
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Votre compte a été supprimé',
                    'userDeleted' => true
                ], 401);
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
        
        // Si on a trouvé l'utilisateur, on inclut son pseudo et son équipe
        if ($userVotes !== null) {
            return new JsonResponse([
                'success' => true,
                'votes' => $userVotes,
                'pseudo' => $userVotes['pseudo'],
                'team' => $userVotes['team']
            ]);
        }
        
        return new JsonResponse([
            'success' => true,
            'votes' => $userVotes,
            'pseudo' => $pseudo
        ]);
    }
}
