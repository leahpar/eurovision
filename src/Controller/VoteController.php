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
        // Vérifier le Content-Type
        if (!$this->isValidJsonRequest($request)) {
            return $this->jsonError('Content-Type doit être application/json', Response::HTTP_BAD_REQUEST);
        }
        
        // Décoder le JSON
        $data = $this->decodeJsonRequest($request);
        if ($data === null) {
            return $this->jsonError('Impossible de décoder le JSON', Response::HTTP_BAD_REQUEST);
        }
        
        // Valider la structure de la requête
        $errors = $this->validateVoteRequest($data);
        if (!empty($errors)) {
            return new JsonResponse([
                'success' => false,
                'errors' => $errors
            ], Response::HTTP_BAD_REQUEST);
        }
        
        $pseudo = $data['pseudo'];
        $team = $data['team'];
        $scores = $data['scores'];
        
        // Validation des scores
        $scoreErrors = $this->validateScores($scores);
        if (!empty($scoreErrors)) {
            return new JsonResponse([
                'success' => false,
                'errors' => $scoreErrors
            ], Response::HTTP_BAD_REQUEST);
        }
        
        try {
            // Enregistrer les votes
            $this->voteService->saveUserVotes($pseudo, $team, $scores);
            
            return new JsonResponse([
                'success' => true,
                'message' => 'Votes enregistrés avec succès'
            ]);
        } catch (\InvalidArgumentException $e) {
            // Erreur de validation métier
            return $this->jsonError($e->getMessage(), Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            // Log l'erreur côté serveur
            error_log('Erreur lors de l\'enregistrement des votes: ' . $e->getMessage());
            
            return $this->jsonError('Erreur serveur lors de l\'enregistrement des votes', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/user-votes', name: 'api_user_votes', methods: ['GET'])]
    public function getUserVotes(Request $request): JsonResponse
    {
        $pseudo = $request->query->get('pseudo');
        
        if (empty($pseudo)) {
            return $this->jsonError('Pseudo obligatoire', Response::HTTP_BAD_REQUEST);
        }
        
        // Valider le pseudo (pas de caractères spéciaux dangereux)
        $validator = Validation::createValidator();
        $violations = $validator->validate($pseudo, [
            new Assert\Regex([
                'pattern' => '/^[a-zA-Z0-9_\-\.]+$/',
                'message' => 'Le pseudo ne doit contenir que des lettres, chiffres, tirets, points ou underscores'
            ])
        ]);
        
        if (count($violations) > 0) {
            return $this->jsonError($violations[0]->getMessage(), Response::HTTP_BAD_REQUEST);
        }
        
        try {
            $userVotes = $this->voteService->getUserVotes($pseudo);
            
            return new JsonResponse([
                'success' => true,
                'votes' => $userVotes
            ]);
        } catch (\Exception $e) {
            return $this->jsonError('Erreur lors de la récupération des votes: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
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
     * Valide la structure de la requête de vote.
     * 
     * @param array|null $data Les données à valider
     * @return array Les erreurs de validation
     */
    private function validateVoteRequest(?array $data): array
    {
        $errors = [];
        
        if ($data === null) {
            $errors['global'] = 'Données manquantes';
            return $errors;
        }
        
        // Vérifier la présence des champs obligatoires
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
        
        if (!isset($data['team']) || empty($data['team'])) {
            $errors['team'] = 'L\'équipe est obligatoire';
        } else {
            // Vérifier que l'équipe existe
            $validTeams = $this->configService->getTeams();
            if (!in_array($data['team'], $validTeams, true)) {
                $errors['team'] = 'L\'équipe sélectionnée n\'est pas valide';
            }
        }
        
        if (!isset($data['scores']) || !is_array($data['scores'])) {
            $errors['scores'] = 'Les scores sont obligatoires et doivent être un objet';
        } elseif (empty($data['scores'])) {
            $errors['scores'] = 'Au moins un vote est requis';
        }
        
        return $errors;
    }
    
    /**
     * Valide les scores soumis.
     * 
     * @param array $scores Les scores à valider
     * @return array Les erreurs de validation
     */
    private function validateScores(array $scores): array
    {
        $errors = [];
        $performances = $this->configService->getPerformances();
        
        foreach ($scores as $countryCode => $score) {
            // Valider le code pays
            if (!isset($performances[$countryCode])) {
                $errors["country_{$countryCode}"] = sprintf('Pays non valide: %s', $countryCode);
                continue;
            }
            
            // Valider le score
            if (!is_numeric($score)) {
                $errors["score_{$countryCode}"] = sprintf('Le score pour %s doit être un nombre', $countryCode);
            } elseif (intval($score) != $score) {
                $errors["score_{$countryCode}"] = sprintf('Le score pour %s doit être un entier', $countryCode);
            } elseif ($score < 0 || $score > 10) {
                $errors["score_{$countryCode}"] = sprintf('Le score pour %s doit être entre 0 et 10', $countryCode);
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