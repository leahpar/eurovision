<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;

class VoteService
{
    private string $votesFilePath;
    /** @var array<string, mixed>|null */
    private ?array $votes = null;

    public function __construct(
        private readonly ParameterBagInterface $parameterBag,
        private readonly Filesystem $filesystem,
        private readonly ConfigService $configService
    ) {
        $this->votesFilePath = $this->parameterBag->get('kernel.project_dir') . '/var/storage/votes.json';
    }

    /**
     * Récupère tous les votes.
     * 
     * @return array<string, mixed>
     */
    public function getAllVotes(): array
    {
        if ($this->votes === null) {
            $this->loadVotes();
        }

        return $this->votes['votes'] ?? [];
    }

    /**
     * Récupère les votes d'un utilisateur spécifique.
     * 
     * @return array{team: string, scores: array<string, int>}|null
     */
    public function getUserVotes(string $pseudo): ?array
    {
        $votes = $this->getAllVotes();
        return $votes[$pseudo] ?? null;
    }

    /**
     * Enregistre les votes d'un utilisateur.
     * 
     * @param string $pseudo Pseudo de l'utilisateur
     * @param string $team Équipe de l'utilisateur
     * @param array<string, int> $scores Scores attribués (code pays => score)
     * @return bool True si le vote a été enregistré avec succès
     */
    public function saveUserVotes(string $pseudo, string $team, array $scores): bool
    {
        // Validation des données
        if (empty($pseudo) || empty($team)) {
            throw new \InvalidArgumentException('Le pseudo et l\'équipe sont obligatoires.');
        }

        // Validation de l'équipe
        $validTeams = $this->configService->getTeams();
        if (!in_array($team, $validTeams, true)) {
            throw new \InvalidArgumentException(sprintf('L\'équipe "%s" n\'est pas valide.', $team));
        }

        // Validation des scores
        $validPerformances = array_keys($this->configService->getPerformances());
        foreach ($scores as $countryCode => $score) {
            if (!in_array($countryCode, $validPerformances, true)) {
                throw new \InvalidArgumentException(sprintf('Le pays "%s" n\'est pas valide.', $countryCode));
            }

            // Score doit être un entier entre 0 et 10
            if (!is_int($score) || $score < 0 || $score > 10) {
                throw new \InvalidArgumentException(sprintf('Le score pour le pays "%s" doit être un entier entre 0 et 10.', $countryCode));
            }
        }

        // Chargement des votes existants
        if ($this->votes === null) {
            $this->loadVotes();
        }

        // Récupération des données existantes de l'utilisateur
        $existingUserData = $this->votes['votes'][$pseudo] ?? null;
        $existingScores = [];

        // Conserver les scores existants s'ils existent
        if ($existingUserData !== null && isset($existingUserData['scores']) && is_array($existingUserData['scores'])) {
            $existingScores = $existingUserData['scores'];
        }

        // Fusion des scores existants avec les nouveaux scores (les nouveaux remplacent les anciens pour chaque pays concerné)
        $mergedScores = array_merge($existingScores, $scores);

        // Préparation des données de vote mises à jour
        $userData = [
            'team' => $team,  // On met à jour l'équipe au cas où elle aurait changé
            'scores' => $mergedScores,
        ];

        // Mise à jour des votes
        $this->votes['votes'][$pseudo] = $userData;

        // Sauvegarde des votes
        return $this->saveVotes();
    }

    /**
     * Calcule le classement des performances.
     * 
     * @return array<string, array{
     *     countryCode: string,
     *     name: string,
     *     artist: string,
     *     song: string,
     *     flag: string,
     *     averageScore: float,
     *     totalVotes: int
     * }>
     */
    public function calculateRanking(): array
    {
        $votes = $this->getAllVotes();
        $performances = $this->configService->getPerformances();
        $ranking = [];

        // Initialisation du classement
        foreach ($performances as $countryCode => $performance) {
            $ranking[$countryCode] = [
                'countryCode' => $countryCode,
                'name' => $performance['name'],
                'artist' => $performance['artist'],
                'song' => $performance['song'],
                'flag' => $performance['flag'],
                'averageScore' => 0,
                'totalVotes' => 0,
            ];
        }

        // Calcul des scores moyens
        foreach ($votes as $userVotes) {
            if (!isset($userVotes['scores']) || !is_array($userVotes['scores'])) {
                continue;
            }

            foreach ($userVotes['scores'] as $countryCode => $score) {
                if (!isset($ranking[$countryCode])) {
                    continue;
                }

                $ranking[$countryCode]['totalVotes']++;
                // Mise à jour incrémentale de la moyenne
                $currentTotal = $ranking[$countryCode]['averageScore'] * ($ranking[$countryCode]['totalVotes'] - 1);
                $ranking[$countryCode]['averageScore'] = ($currentTotal + $score) / $ranking[$countryCode]['totalVotes'];
            }
        }

        // Tri par score moyen décroissant
        uasort($ranking, function ($a, $b) {
            return $b['averageScore'] <=> $a['averageScore'];
        });

        return $ranking;
    }

    /**
     * Filtre le classement par équipe.
     * 
     * @param string $team Équipe à filtrer
     * @return array<string, array{
     *     countryCode: string,
     *     name: string,
     *     artist: string,
     *     song: string,
     *     flag: string,
     *     averageScore: float,
     *     totalVotes: int
     * }>
     */
    public function getTeamRanking(string $team): array
    {
        $votes = $this->getAllVotes();
        $performances = $this->configService->getPerformances();
        $ranking = [];

        // Initialisation du classement
        foreach ($performances as $countryCode => $performance) {
            $ranking[$countryCode] = [
                'countryCode' => $countryCode,
                'name' => $performance['name'],
                'artist' => $performance['artist'],
                'song' => $performance['song'],
                'flag' => $performance['flag'],
                'averageScore' => 0,
                'totalVotes' => 0,
            ];
        }

        // Calcul des scores moyens pour l'équipe spécifiée
        foreach ($votes as $userVotes) {
            if (!isset($userVotes['team']) || $userVotes['team'] !== $team || !isset($userVotes['scores']) || !is_array($userVotes['scores'])) {
                continue;
            }

            foreach ($userVotes['scores'] as $countryCode => $score) {
                if (!isset($ranking[$countryCode])) {
                    continue;
                }

                $ranking[$countryCode]['totalVotes']++;
                // Mise à jour incrémentale de la moyenne
                $currentTotal = $ranking[$countryCode]['averageScore'] * ($ranking[$countryCode]['totalVotes'] - 1);
                $ranking[$countryCode]['averageScore'] = ($currentTotal + $score) / $ranking[$countryCode]['totalVotes'];
            }
        }

        // Tri par score moyen décroissant
        uasort($ranking, function ($a, $b) {
            return $b['averageScore'] <=> $a['averageScore'];
        });

        return $ranking;
    }

    /**
     * Identifie le joueur le plus sévère (moyenne de votes la plus basse)
     * 
     * @param string|null $team Équipe à filtrer (optionnel)
     * @return array{pseudo: string, team: string, averageScore: float}|null
     */
    public function getHarshestVoter(?string $team = null): ?array
    {
        $votes = $this->getAllVotes();
        if (empty($votes)) {
            return null;
        }
        
        $voterScores = [];
        
        foreach ($votes as $pseudo => $userData) {
            // Filtrer par équipe si demandé
            if ($team !== null && (!isset($userData['team']) || $userData['team'] !== $team)) {
                continue;
            }
            
            if (!isset($userData['scores']) || !is_array($userData['scores']) || empty($userData['scores'])) {
                continue;
            }
            
            $totalScore = 0;
            $voteCount = count($userData['scores']);
            
            foreach ($userData['scores'] as $score) {
                $totalScore += $score;
            }
            
            $voterScores[$pseudo] = [
                'pseudo' => $pseudo,
                'team' => $userData['team'] ?? 'Inconnue',
                'averageScore' => $totalScore / $voteCount
            ];
        }
        
        if (empty($voterScores)) {
            return null;
        }
        
        // Tri par moyenne croissante
        uasort($voterScores, function ($a, $b) {
            return $a['averageScore'] <=> $b['averageScore'];
        });
        
        // Retourne le joueur avec la moyenne la plus basse
        return reset($voterScores);
    }
    
    /**
     * Identifie le joueur le plus généreux (moyenne de votes la plus haute)
     * 
     * @param string|null $team Équipe à filtrer (optionnel)
     * @return array{pseudo: string, team: string, averageScore: float}|null
     */
    public function getGenerousVoter(?string $team = null): ?array
    {
        $votes = $this->getAllVotes();
        if (empty($votes)) {
            return null;
        }
        
        $voterScores = [];
        
        foreach ($votes as $pseudo => $userData) {
            // Filtrer par équipe si demandé
            if ($team !== null && (!isset($userData['team']) || $userData['team'] !== $team)) {
                continue;
            }
            
            if (!isset($userData['scores']) || !is_array($userData['scores']) || empty($userData['scores'])) {
                continue;
            }
            
            $totalScore = 0;
            $voteCount = count($userData['scores']);
            
            foreach ($userData['scores'] as $score) {
                $totalScore += $score;
            }
            
            $voterScores[$pseudo] = [
                'pseudo' => $pseudo,
                'team' => $userData['team'] ?? 'Inconnue',
                'averageScore' => $totalScore / $voteCount
            ];
        }
        
        if (empty($voterScores)) {
            return null;
        }
        
        // Tri par moyenne décroissante
        uasort($voterScores, function ($a, $b) {
            return $b['averageScore'] <=> $a['averageScore'];
        });
        
        // Retourne le joueur avec la moyenne la plus haute
        return reset($voterScores);
    }
    
    /**
     * Identifie le pays le plus clivant (écart-type des scores le plus élevé)
     * 
     * @param string|null $team Équipe à filtrer (optionnel)
     * @return array{countryCode: string, name: string, flag: string, stdDeviation: float}|null
     */
    public function getMostDivisiveCountry(?string $team = null): ?array
    {
        $votes = $this->getAllVotes();
        $performances = $this->configService->getPerformances();
        
        if (empty($votes) || empty($performances)) {
            return null;
        }
        
        /** @var array<string, array{scores: list<int>, countryCode: string, name: string, flag: string}> $countryScores */
        $countryScores = [];
        
        // Initialiser les tableaux de scores pour chaque pays
        foreach ($performances as $countryCode => $performance) {
            $countryScores[$countryCode] = [
                'scores' => [],
                'countryCode' => $countryCode,
                'name' => $performance['name'],
                'flag' => $performance['flag']
            ];
        }
        
        // Collecter tous les scores par pays
        foreach ($votes as $userData) {
            // Filtrer par équipe si demandé
            if ($team !== null && (!isset($userData['team']) || $userData['team'] !== $team)) {
                continue;
            }
            
            if (!isset($userData['scores']) || !is_array($userData['scores'])) {
                continue;
            }
            
            foreach ($userData['scores'] as $countryCode => $score) {
                if (isset($countryScores[$countryCode])) {
                    $countryScores[$countryCode]['scores'][] = $score;
                }
            }
        }
        
        $divisiveCountries = [];
        
        // Calculer l'écart-type pour chaque pays
        foreach ($countryScores as $countryCode => $data) {
            if (count($data['scores']) < 2) {
                continue;
            }
            
            $mean = array_sum($data['scores']) / count($data['scores']);
            $variance = 0;
            
            foreach ($data['scores'] as $score) {
                $variance += pow($score - $mean, 2);
            }
            
            $variance /= count($data['scores']);
            $stdDeviation = sqrt($variance);
            
            $divisiveCountries[$countryCode] = [
                'countryCode' => $countryCode,
                'name' => $data['name'],
                'flag' => $data['flag'],
                'stdDeviation' => $stdDeviation
            ];
        }
        
        if (empty($divisiveCountries)) {
            return null;
        }
        
        // Tri par écart-type décroissant
        uasort($divisiveCountries, function ($a, $b) {
            return $b['stdDeviation'] <=> $a['stdDeviation'];
        });
        
        // Retourne le pays avec l'écart-type le plus élevé
        return reset($divisiveCountries);
    }
    
    /**
     * Identifie le pays le plus consensuel (écart-type des scores le plus faible)
     * 
     * @param string|null $team Équipe à filtrer (optionnel)
     * @return array{countryCode: string, name: string, flag: string, stdDeviation: float}|null
     */
    public function getMostConsensualCountry(?string $team = null): ?array
    {
        $votes = $this->getAllVotes();
        $performances = $this->configService->getPerformances();
        
        if (empty($votes) || empty($performances)) {
            return null;
        }
        
        /** @var array<string, array{scores: list<int>, countryCode: string, name: string, flag: string}> $countryScores */
        $countryScores = [];
        
        // Initialiser les tableaux de scores pour chaque pays
        foreach ($performances as $countryCode => $performance) {
            $countryScores[$countryCode] = [
                'scores' => [],
                'countryCode' => $countryCode,
                'name' => $performance['name'],
                'flag' => $performance['flag']
            ];
        }
        
        // Collecter tous les scores par pays
        foreach ($votes as $userData) {
            // Filtrer par équipe si demandé
            if ($team !== null && (!isset($userData['team']) || $userData['team'] !== $team)) {
                continue;
            }
            
            if (!isset($userData['scores']) || !is_array($userData['scores'])) {
                continue;
            }
            
            foreach ($userData['scores'] as $countryCode => $score) {
                if (isset($countryScores[$countryCode])) {
                    $countryScores[$countryCode]['scores'][] = $score;
                }
            }
        }
        
        $consensualCountries = [];
        
        // Calculer l'écart-type pour chaque pays
        foreach ($countryScores as $countryCode => $data) {
            if (count($data['scores']) < 2) {
                continue;
            }
            
            $mean = array_sum($data['scores']) / count($data['scores']);
            $variance = 0;
            
            foreach ($data['scores'] as $score) {
                $variance += pow($score - $mean, 2);
            }
            
            $variance /= count($data['scores']);
            $stdDeviation = sqrt($variance);
            
            $consensualCountries[$countryCode] = [
                'countryCode' => $countryCode,
                'name' => $data['name'],
                'flag' => $data['flag'],
                'stdDeviation' => $stdDeviation
            ];
        }
        
        if (empty($consensualCountries)) {
            return null;
        }
        
        // Tri par écart-type croissant
        uasort($consensualCountries, function ($a, $b) {
            return $a['stdDeviation'] <=> $b['stdDeviation'];
        });
        
        // Retourne le pays avec l'écart-type le plus faible
        return reset($consensualCountries);
    }

    /**
     * Charge les votes depuis le fichier JSON.
     */
    private function loadVotes(): void
    {
        // Si le fichier n'existe pas, on crée une structure vide
        if (!$this->filesystem->exists($this->votesFilePath)) {
            $this->votes = ['votes' => []];
            return;
        }

        $votesContent = file_get_contents($this->votesFilePath);
        if ($votesContent === false) {
            throw new \RuntimeException(sprintf('Impossible de lire le fichier de votes %s.', $this->votesFilePath));
        }

        $votes = json_decode($votesContent, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException(sprintf('Le fichier de votes %s n\'est pas un JSON valide: %s', $this->votesFilePath, json_last_error_msg()));
        }

        // S'assurer que la structure du fichier est correcte
        if (!isset($votes['votes'])) {
            $votes = ['votes' => []];
        }

        $this->votes = $votes;
    }

    /**
     * Sauvegarde les votes dans le fichier JSON avec un mécanisme de verrouillage.
     */
    private function saveVotes(): bool
    {
        // Utiliser un verrouillage de fichier pour éviter les conflits d'écriture
        $store = new FlockStore(sys_get_temp_dir());
        $lockFactory = new LockFactory($store);
        $lock = $lockFactory->createLock('votes.json');

        if (!$lock->acquire()) {
            // Impossible d'obtenir le verrouillage
            return false;
        }

        try {
            // Assurer que le répertoire existe
            $directory = dirname($this->votesFilePath);
            if (!$this->filesystem->exists($directory)) {
                $this->filesystem->mkdir($directory, 0755);
            }

            // Écrire les données
            $content = json_encode($this->votes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            if ($content === false) {
                throw new \RuntimeException('Impossible d\'encoder les votes en JSON.');
            }

            file_put_contents($this->votesFilePath, $content);
            return true;
        } finally {
            // Toujours libérer le verrou
            $lock->release();
        }
    }
}