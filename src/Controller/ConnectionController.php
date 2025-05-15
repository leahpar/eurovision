<?php

namespace App\Controller;

use App\Service\ConfigService;
use App\Service\VoteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class ConnectionController extends AbstractController
{
    public function __construct(
        private readonly ConfigService $configService,
        private readonly VoteService $voteService
    ) {
    }

    #[Route('/', name: 'app_home')]
    public function index(Request $request): Response
    {
        // Vérifier si un userId est présent dans le localStorage via JS
        // La vérification réelle sera effectuée côté client
        
        // Récupérer les données nécessaires pour la page d'accueil
        $edition = $this->configService->getEdition();
        $teams = $this->configService->getTeams();

        return $this->render('connection/index.html.twig', [
            'edition' => $edition,
            'teams' => $teams,
            'checkUserSession' => true
        ]);
    }
    
    #[Route('/reconnexion/{userId}', name: 'app_reconnexion')]
    public function reconnexion(string $userId): Response
    {
        // Récupérer les données utilisateur
        $userInfo = $this->voteService->getUserByUserId($userId);
        
        // Si l'utilisateur n'existe pas, rediriger vers la page d'accueil
        if ($userInfo === null) {
            $this->addFlash('error', 'Utilisateur non trouvé. Veuillez vous reconnecter.');
            return $this->redirectToRoute('app_home');
        }
        
        // Récupérer les données nécessaires pour la page de connexion
        $edition = $this->configService->getEdition();
        $teams = $this->configService->getTeams();
        
        // Rendre la vue avec les données pré-remplies
        return $this->render('connection/index.html.twig', [
            'edition' => $edition,
            'teams' => $teams,
            'userInfo' => [
                'userId' => $userInfo['userId'],
                'userData' => [
                    'pseudo' => $userInfo['userData']['pseudo'],
                    'team' => $userInfo['userData']['team']
                ]
            ]
        ]);
    }

    #[Route('/api/validate-connection', name: 'api_validate_connection', methods: ['POST'])]
    public function validateConnection(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $pseudo = $data['pseudo'] ?? '';
        $team = $data['team'] ?? '';
        $userId = $data['userId'] ?? null; // Récupérer l'ID s'il est fourni
        
        // Validation basique
        $errors = [];
        
        if (empty($pseudo)) {
            $errors['pseudo'] = 'Le pseudo est obligatoire';
        }
        
        if (empty($team)) {
            $errors['team'] = 'L\'équipe est obligatoire';
        } else {
            // Vérifier que l'équipe existe
            $validTeams = $this->configService->getTeams();
            if (!in_array($team, $validTeams, true)) {
                $errors['team'] = 'L\'équipe sélectionnée n\'est pas valide';
            }
        }
        
        if (!empty($errors)) {
            return new JsonResponse(['success' => false, 'errors' => $errors], 400);
        }
        
        try {
            // L'ID sera automatiquement généré par VoteService à la première connexion
            
            // Sauvegarder les données de l'utilisateur même si pas de votes
            // pour s'assurer qu'il a un ID unique
            $result = $this->voteService->saveUserVotes($pseudo, $team, [], $userId);
            
            if (!$result['success']) {
                return new JsonResponse([
                    'success' => false,
                    'errors' => ['general' => 'Erreur lors de la création/mise à jour du profil utilisateur']
                ], 500);
            }
            
            return new JsonResponse([
                'success' => true,
                'userId' => $result['userId'],
                'redirect' => $this->generateUrl('app_vote')
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false, 
                'errors' => ['general' => 'Erreur: ' . $e->getMessage()]
            ], 500);
        }
    }
    
    #[Route('/api/get-user-by-id/{userId}', name: 'api_get_user_by_id', methods: ['GET'])]
    public function getUserById(string $userId): JsonResponse
    {
        $userInfo = $this->voteService->getUserByUserId($userId);
        
        if ($userInfo === null) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Utilisateur non trouvé'
            ], 404);
        }
        
        return new JsonResponse([
            'success' => true,
            'pseudo' => $userInfo['userData']['pseudo'],
            'team' => $userInfo['userData']['team']
        ]);
    }
}