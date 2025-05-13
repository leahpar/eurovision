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

        return $this->render('vote/index.html.twig', [
            'edition' => $edition,
            'performances' => $performances,
        ]);
    }

    #[Route('/api/vote', name: 'api_vote', methods: ['POST'])]
    public function vote(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        $pseudo = $data['pseudo'] ?? null;
        $team = $data['team'] ?? null;
        $scores = $data['scores'] ?? [];
        
        // Validation basique
        if (empty($pseudo) || empty($team)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Pseudo et équipe sont obligatoires'
            ], Response::HTTP_BAD_REQUEST);
        }
        
        // Validation des scores
        $performances = $this->configService->getPerformances();
        foreach ($scores as $countryCode => $score) {
            if (!isset($performances[$countryCode])) {
                return new JsonResponse([
                    'success' => false,
                    'message' => sprintf('Pays non valide: %s', $countryCode)
                ], Response::HTTP_BAD_REQUEST);
            }
            
            if (!is_numeric($score) || intval($score) != $score || $score < 0 || $score > 10) {
                return new JsonResponse([
                    'success' => false,
                    'message' => sprintf('Score non valide pour %s: %s. Le score doit être un entier entre 0 et 10.', 
                        $countryCode, $score)
                ], Response::HTTP_BAD_REQUEST);
            }
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
                'message' => 'Erreur lors de l\'enregistrement des votes: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
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
            ], Response::HTTP_BAD_REQUEST);
        }
        
        $userVotes = $this->voteService->getUserVotes($pseudo);
        
        return new JsonResponse([
            'success' => true,
            'votes' => $userVotes
        ]);
    }
}