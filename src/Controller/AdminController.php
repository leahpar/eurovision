<?php

namespace App\Controller;

use App\Service\ConfigService;
use App\Service\VoteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class AdminController extends AbstractController
{
    public function __construct(
        private readonly ConfigService $configService,
        private readonly VoteService $voteService
    ) {
    }

    #[Route('/admin', name: 'app_admin')]
    public function index(): Response
    {
        // Récupérer les votes pour afficher la liste des joueurs
        $votes = $this->voteService->getAllVotes();
        $edition = $this->configService->getEdition();
        
        return $this->render('admin/index.html.twig', [
            'edition' => $edition,
            'votes' => $votes,
        ]);
    }

    #[Route('/api/admin/reset-votes', name: 'api_admin_reset_votes', methods: ['POST'])]
    public function resetVotes(): JsonResponse
    {
        try {
            // Créer une nouvelle structure de votes vide
            $emptyVotes = ['votes' => []];
            
            // Sauvegarder via le service
            $result = $this->resetVotesInService();
            
            if ($result) {
                return new JsonResponse([
                    'success' => true,
                    'message' => 'Tous les votes ont été réinitialisés avec succès.'
                ]);
            } else {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Erreur lors de la réinitialisation des votes.'
                ], 500);
            }
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/admin/delete-player/{pseudo}', name: 'api_admin_delete_player', methods: ['DELETE'])]
    public function deletePlayer(string $pseudo): JsonResponse
    {
        try {
            $result = $this->deletePlayerInService($pseudo);
            
            if ($result) {
                return new JsonResponse([
                    'success' => true,
                    'message' => 'Le joueur a été supprimé avec succès.'
                ]);
            } else {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Erreur lors de la suppression du joueur.'
                ], 500);
            }
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Réinitialise tous les votes en créant un fichier vide.
     * Cette méthode est séparée pour faciliter les tests unitaires.
     */
    private function resetVotesInService(): bool
    {
        // Puisque VoteService n'a pas de méthode pour reset les votes, 
        // nous utilisons un chemin direct pour créer un fichier vide
        $votesFilePath = $this->getParameter('kernel.project_dir') . '/var/storage/votes.json';
        
        $emptyVotes = ['votes' => []];
        $content = json_encode($emptyVotes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        return file_put_contents($votesFilePath, $content) !== false;
    }

    /**
     * Supprime un joueur des votes.
     * Cette méthode est séparée pour faciliter les tests unitaires.
     */
    private function deletePlayerInService(string $pseudo): bool
    {
        // Charger les votes actuels
        $votes = $this->voteService->getAllVotes();
        
        // Vérifier si le joueur existe
        if (!isset($votes[$pseudo])) {
            throw new \InvalidArgumentException(sprintf('Le joueur "%s" n\'existe pas.', $pseudo));
        }
        
        // Ici nous devons manipuler le fichier directement car VoteService n'a pas de méthode pour supprimer un joueur
        $votesFilePath = $this->getParameter('kernel.project_dir') . '/var/storage/votes.json';
        
        // Lire le fichier JSON complet
        $votesContent = file_get_contents($votesFilePath);
        if ($votesContent === false) {
            throw new \RuntimeException('Impossible de lire le fichier de votes.');
        }
        
        $votesData = json_decode($votesContent, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Le fichier de votes n\'est pas un JSON valide: ' . json_last_error_msg());
        }
        
        // Supprimer le joueur
        unset($votesData['votes'][$pseudo]);
        
        // Enregistrer le fichier mis à jour
        $content = json_encode($votesData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        return file_put_contents($votesFilePath, $content) !== false;
    }
}