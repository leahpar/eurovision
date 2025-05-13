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
        
        // Validation basique
        if (empty($pseudo) || empty($team) || empty($scores)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Pseudo, équipe et scores sont obligatoires'
            ], 400);
        }
        
        try {
            // Enregistrer les votes
            $this->voteService->saveUserVotes($pseudo, $team, $scores);
            
            return new JsonResponse([
                'success' => true,
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
        
        if (empty($pseudo)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Pseudo obligatoire'
            ], 400);
        }
        
        $userVotes = $this->voteService->getUserVotes($pseudo);
        
        return new JsonResponse([
            'success' => true,
            'votes' => $userVotes
        ]);
    }
}