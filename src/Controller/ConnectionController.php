<?php

namespace App\Controller;

use App\Service\ConfigService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validation;

class ConnectionController extends AbstractController
{
    public function __construct(
        private readonly ConfigService $configService
    ) {
    }

    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        // Récupérer les données nécessaires pour la page d'accueil
        $edition = $this->configService->getEdition();
        $teams = $this->configService->getTeams();

        return $this->render('connection/index.html.twig', [
            'edition' => $edition,
            'teams' => $teams,
        ]);
    }

    #[Route('/api/validate-connection', name: 'api_validate_connection', methods: ['POST'])]
    public function validateConnection(Request $request): JsonResponse
    {
        // Vérifier le Content-Type
        if (!$this->isValidJsonRequest($request)) {
            return $this->jsonError('Content-Type doit être application/json', Response::HTTP_BAD_REQUEST);
        }
        
        // Décoder le JSON
        $data = $this->decodeJsonRequest($request);
        if ($data === null) {
            return $this->jsonError('Impossible de décoder le JSON', Response::HTTP_BAD_REQUEST);
        }
        
        // Validation des données
        $errors = $this->validateConnectionData($data);
        
        if (!empty($errors)) {
            return new JsonResponse(['success' => false, 'errors' => $errors], Response::HTTP_BAD_REQUEST);
        }
        
        return new JsonResponse([
            'success' => true,
            'redirect' => $this->generateUrl('app_vote')
        ]);
    }
    
    /**
     * Vérifie si la requête est une requête JSON valide.
     */
    private function isValidJsonRequest(Request $request): bool
    {
        $contentType = $request->headers->get('Content-Type');
        return $contentType === 'application/json' || str_starts_with($contentType, 'application/json;');
    }
    
    /**
     * Décode le JSON de la requête.
     * 
     * @return array|null Le contenu décodé ou null en cas d'erreur
     */
    private function decodeJsonRequest(Request $request): ?array
    {
        $content = $request->getContent();
        if (empty($content)) {
            return null;
        }
        
        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
        
        return $data;
    }
    
    /**
     * Valide les données de connexion.
     * 
     * @param array|null $data Les données à valider
     * @return array Les erreurs de validation
     */
    private function validateConnectionData(?array $data): array
    {
        $errors = [];
        
        if ($data === null) {
            $errors['global'] = 'Données manquantes';
            return $errors;
        }
        
        // Validation du pseudo
        if (!isset($data['pseudo']) || empty($data['pseudo'])) {
            $errors['pseudo'] = 'Le pseudo est obligatoire';
        } else {
            // Valider le format du pseudo
            $validator = Validation::createValidator();
            $violations = $validator->validate($data['pseudo'], [
                new Assert\Length([
                    'min' => 2,
                    'max' => 50,
                    'minMessage' => 'Le pseudo doit contenir au moins {{ limit }} caractères',
                    'maxMessage' => 'Le pseudo ne peut pas dépasser {{ limit }} caractères'
                ]),
                new Assert\Regex([
                    'pattern' => '/^[a-zA-Z0-9_\-\.]+$/',
                    'message' => 'Le pseudo ne doit contenir que des lettres, chiffres, tirets, points ou underscores'
                ])
            ]);
            
            if (count($violations) > 0) {
                $errors['pseudo'] = $violations[0]->getMessage();
            }
        }
        
        // Validation de l'équipe
        if (!isset($data['team']) || empty($data['team'])) {
            $errors['team'] = 'L\'équipe est obligatoire';
        } else {
            // Vérifier que l'équipe existe
            $validTeams = $this->configService->getTeams();
            if (!in_array($data['team'], $validTeams, true)) {
                $errors['team'] = 'L\'équipe sélectionnée n\'est pas valide';
            }
        }
        
        return $errors;
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