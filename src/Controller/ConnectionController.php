<?php

namespace App\Controller;

use App\Service\ConfigService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

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
        $data = json_decode($request->getContent(), true);
        $pseudo = $data['pseudo'] ?? '';
        $team = $data['team'] ?? '';
        
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
        
        return new JsonResponse([
            'success' => true,
            'redirect' => $this->generateUrl('app_vote')
        ]);
    }
}