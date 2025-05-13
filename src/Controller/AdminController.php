<?php

namespace App\Controller;

use App\Service\ConfigService;
use App\Service\VoteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
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
        // Forcer le rafraîchissement pour s'assurer d'avoir la dernière version
        $this->voteService->refreshVotes();
        $votes = $this->voteService->getAllVotes();
        $edition = $this->configService->getEdition();
        $teams = $this->configService->getTeams();
        
        return $this->render('admin/index.html.twig', [
            'edition' => $edition,
            'votes' => $votes,
            'teams' => $teams,
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

    #[Route('/api/admin/update-player/{userId}', name: 'api_admin_update_player', methods: ['POST'])]
    public function updatePlayer(Request $request, string $userId): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $newPseudo = $data['newPseudo'] ?? '';
            $team = $data['team'] ?? '';
            
            // Validation basique
            if (empty($newPseudo) || empty($team)) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Le pseudo et l\'équipe sont obligatoires'
                ], 400);
            }
            
            // Validation de l'équipe
            $validTeams = $this->configService->getTeams();
            if (!in_array($team, $validTeams, true)) {
                return new JsonResponse([
                    'success' => false,
                    'message' => sprintf('L\'équipe "%s" n\'est pas valide.', $team)
                ], 400);
            }
            
            $result = $this->updatePlayerInService($userId, $newPseudo, $team);
            
            if ($result) {
                return new JsonResponse([
                    'success' => true,
                    'message' => 'Les informations du joueur ont été mises à jour avec succès.'
                ]);
            } else {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Erreur lors de la mise à jour du joueur.'
                ], 500);
            }
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }
    
    #[Route('/api/admin/delete-player/{userId}', name: 'api_admin_delete_player', methods: ['DELETE'])]
    public function deletePlayer(string $userId): JsonResponse
    {
        try {
            $result = $this->deletePlayerInService($userId);
            
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
     * Met à jour les informations d'un joueur.
     * Cette méthode est séparée pour faciliter les tests unitaires.
     */
    private function updatePlayerInService(string $userId, string $newPseudo, string $team): bool
    {
        // Charger les votes actuels
        $votes = $this->voteService->getAllVotes();
        
        // Vérifier si le joueur existe
        if (!isset($votes[$userId])) {
            throw new \InvalidArgumentException(sprintf('Le joueur avec l\'ID "%s" n\'existe pas.', $userId));
        }
        
        // Récupérer l'ancien pseudo
        $oldPseudo = $votes[$userId]['pseudo'] ?? '';
        
        // Vérifier que le nouveau pseudo n'est pas déjà utilisé
        if ($oldPseudo !== $newPseudo) {
            foreach ($votes as $id => $data) {
                if ($id !== $userId && isset($data['pseudo']) && $data['pseudo'] === $newPseudo) {
                    throw new \InvalidArgumentException(sprintf('Le pseudo "%s" est déjà utilisé par un autre joueur.', $newPseudo));
                }
            }
        }
        
        // Lire le fichier directement
        $votesFilePath = $this->getParameter('kernel.project_dir') . '/var/storage/votes.json';
        
        $votesContent = file_get_contents($votesFilePath);
        if ($votesContent === false) {
            throw new \RuntimeException('Impossible de lire le fichier de votes.');
        }
        
        $votesData = json_decode($votesContent, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Le fichier de votes n\'est pas un JSON valide: ' . json_last_error_msg());
        }
        
        // Mettre à jour les données du joueur
        if (isset($votesData['votes'][$userId])) {
            $votesData['votes'][$userId]['pseudo'] = $newPseudo;
            $votesData['votes'][$userId]['team'] = $team;
            
            // Enregistrer le fichier mis à jour
            $content = json_encode($votesData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            return file_put_contents($votesFilePath, $content) !== false;
        }
        
        return false;
    }

    /**
     * Supprime un joueur des votes.
     * Cette méthode est séparée pour faciliter les tests unitaires.
     */
    private function deletePlayerInService(string $userId): bool
    {
        // Charger les votes actuels
        $votes = $this->voteService->getAllVotes();
        
        // Vérifier si le joueur existe
        if (!isset($votes[$userId])) {
            throw new \InvalidArgumentException(sprintf('Le joueur avec l\'ID "%s" n\'existe pas.', $userId));
        }
        
        // Manipuler le fichier directement
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
        unset($votesData['votes'][$userId]);
        
        // Enregistrer le fichier mis à jour
        $content = json_encode($votesData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        return file_put_contents($votesFilePath, $content) !== false;
    }
}