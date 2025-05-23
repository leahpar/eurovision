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
     * Force le rechargement des votes depuis le disque.
     */
    public function refreshVotes(): void
    {
        $this->votes = null;
        $this->loadVotes();
    }

    /**
     * Récupère les votes d'un utilisateur spécifique par son pseudo.
     * 
     * @return array{pseudo: string, team: string, scores: array<string, int>}|null
     */
    public function getUserVotes(string $pseudo): ?array
    {
        $votes = $this->getAllVotes();
        
        // Chercher l'utilisateur par pseudo
        foreach ($votes as $id => $userData) {
            if (isset($userData['pseudo']) && $userData['pseudo'] === $pseudo) {
                // On a trouvé l'utilisateur
                return $userData;
            }
        }
        
        return null;
    }
    
    /**
     * Recherche un utilisateur par son ID.
     * 
     * @return array{userId: string, userData: array{pseudo: string, team: string, scores: array<string, int>}}|null
     */
    public function getUserByUserId(string $userId): ?array
    {
        $votes = $this->getAllVotes();
        
        // Avec la nouvelle structure, l'ID est la clé
        if (isset($votes[$userId])) {
            return [
                'userId' => $userId,
                'userData' => $votes[$userId]
            ];
        }
        
        return null;
    }

    /**
     * Génère un ID unique pour un utilisateur.
     */
    private function generateUserId(): string
    {
        // Génère un ID unique avec préfixe
        return 'usr_' . uniqid('', true);
    }
    
    /**
     * Enregistre les votes d'un utilisateur.
     * 
     * @param string $pseudo Pseudo de l'utilisateur
     * @param string $team Équipe de l'utilisateur
     * @param array<string, int> $scores Scores attribués (code pays => score)
     * @param string|null $userId ID de l'utilisateur (généré si non fourni)
     * @return array{userId: string, success: bool} ID de l'utilisateur et statut de l'opération
     */
    public function saveUserVotes(string $pseudo, string $team, array $scores, ?string $userId = null): array
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
            if (!is_numeric($score) || (int)$score != $score || $score < 0 || $score > 10) {
                throw new \InvalidArgumentException(sprintf('Le score pour le pays "%s" doit être un entier entre 0 et 10.', $countryCode));
            }
        }

        // Chargement des votes existants
        if ($this->votes === null) {
            $this->loadVotes();
        }

        // Recherche de l'utilisateur existant par pseudo d'abord
        $foundUserId = null;
        $existingUserData = null;

        // Parcourir les votes pour chercher un utilisateur avec ce pseudo
        foreach ($this->votes['votes'] as $id => $data) {
            if (isset($data['pseudo']) && $data['pseudo'] === $pseudo) {
                $foundUserId = $id;
                $existingUserData = $data;
                break;
            }
        }

        // Utiliser l'ID fourni ou trouvé, ou générer un nouveau
        if ($userId !== null && !empty(trim($userId))) {
            // ID fourni explicitement, on le garde
        } elseif ($foundUserId !== null && !empty(trim($foundUserId))) {
            // ID trouvé pour ce pseudo
            $userId = $foundUserId;
        } else {
            // Nouveau utilisateur ou ID invalide - générer un nouvel ID
            $userId = $this->generateUserId();
        }

        // Récupérer les scores existants s'il y en a
        $existingScores = [];
        if ($existingUserData !== null && isset($existingUserData['scores']) && is_array($existingUserData['scores'])) {
            $existingScores = $existingUserData['scores'];
        }

        // Fusion des scores existants avec les nouveaux scores
        $mergedScores = array_merge($existingScores, $scores);

        // Préparation des données de vote mises à jour
        $userData = [
            'pseudo' => $pseudo,
            'team' => $team,
            'scores' => $mergedScores,
        ];

        // Mise à jour des votes en utilisant l'ID comme clé
        $this->votes['votes'][$userId] = $userData;

        // Sauvegarde des votes
        $success = $this->saveVotes();
        
        return [
            'userId' => $userId,
            'success' => $success
        ];
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
        foreach ($votes as $userId => $userVotes) {
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
        foreach ($votes as $userId => $userVotes) {
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
        
        foreach ($votes as $userId => $userData) {
            // Filtrer par équipe si demandé
            if ($team !== null && (!isset($userData['team']) || $userData['team'] !== $team)) {
                continue;
            }
            
            if (!isset($userData['scores']) || !is_array($userData['scores']) || empty($userData['scores']) || !isset($userData['pseudo'])) {
                continue;
            }
            
            $totalScore = 0;
            $voteCount = count($userData['scores']);
            
            foreach ($userData['scores'] as $score) {
                $totalScore += $score;
            }
            
            $voterScores[$userId] = [
                'pseudo' => $userData['pseudo'],
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
        
        foreach ($votes as $userId => $userData) {
            // Filtrer par équipe si demandé
            if ($team !== null && (!isset($userData['team']) || $userData['team'] !== $team)) {
                continue;
            }
            
            if (!isset($userData['scores']) || !is_array($userData['scores']) || empty($userData['scores']) || !isset($userData['pseudo'])) {
                continue;
            }
            
            $totalScore = 0;
            $voteCount = count($userData['scores']);
            
            foreach ($userData['scores'] as $score) {
                $totalScore += $score;
            }
            
            $voterScores[$userId] = [
                'pseudo' => $userData['pseudo'],
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
     * Identifie le pays le plus clivant (plus grande différence entre les notes min et max)
     * 
     * @param string|null $team Équipe à filtrer (optionnel)
     * @return array{countryCode: string, name: string, flag: string, stdDeviation: float, minScore: int, maxScore: int, scoreDifference: int}|null
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
        foreach ($votes as $userId => $userData) {
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
            
            $scores = $data['scores'];
            $minScore = min($scores);
            $maxScore = max($scores);
            
            $divisiveCountries[$countryCode] = [
                'countryCode' => $countryCode,
                'name' => $data['name'],
                'flag' => $data['flag'],
                'stdDeviation' => $stdDeviation,
                'minScore' => $minScore,
                'maxScore' => $maxScore,
                'scoreDifference' => $maxScore - $minScore
            ];
        }
        
        if (empty($divisiveCountries)) {
            return null;
        }
        
        // Tri par différence de score décroissante
        uasort($divisiveCountries, function ($a, $b) {
            return $b['scoreDifference'] <=> $a['scoreDifference'];
        });
        
        // Retourne le pays avec la plus grande différence entre notes
        return reset($divisiveCountries);
    }
    
    /**
     * Identifie le pays le plus consensuel (plus petite différence entre les notes min et max)
     * 
     * @param string|null $team Équipe à filtrer (optionnel)
     * @return array{countryCode: string, name: string, flag: string, stdDeviation: float, minScore: int, maxScore: int, scoreDifference: int}|null
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
        foreach ($votes as $userId => $userData) {
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
            
            $scores = $data['scores'];
            $minScore = min($scores);
            $maxScore = max($scores);
            
            $consensualCountries[$countryCode] = [
                'countryCode' => $countryCode,
                'name' => $data['name'],
                'flag' => $data['flag'],
                'stdDeviation' => $stdDeviation,
                'minScore' => $minScore,
                'maxScore' => $maxScore,
                'scoreDifference' => $maxScore - $minScore
            ];
        }
        
        if (empty($consensualCountries)) {
            return null;
        }
        
        // Tri par différence de score croissante
        uasort($consensualCountries, function ($a, $b) {
            return $a['scoreDifference'] <=> $b['scoreDifference'];
        });
        
        // Retourne le pays avec la plus petite différence entre notes
        return reset($consensualCountries);
    }
    
    /**
     * Identifie le votant le plus constant (écart-type le plus bas dans ses votes)
     * 
     * @param string|null $team Équipe à filtrer (optionnel)
     * @return array{pseudo: string, team: string, stdDeviation: float}|null
     */
    public function getMostConsistentVoter(?string $team = null): ?array
    {
        $votes = $this->getAllVotes();
        if (empty($votes)) {
            return null;
        }
        
        $voterStats = [];
        
        foreach ($votes as $userId => $userData) {
            // Filtrer par équipe si demandé
            if ($team !== null && (!isset($userData['team']) || $userData['team'] !== $team)) {
                continue;
            }
            
            if (!isset($userData['scores']) || !is_array($userData['scores']) || count($userData['scores']) < 2 || !isset($userData['pseudo'])) {
                continue;
            }
            
            $scores = array_values($userData['scores']);
            $mean = array_sum($scores) / count($scores);
            $variance = 0;
            
            foreach ($scores as $score) {
                $variance += pow($score - $mean, 2);
            }
            
            $variance /= count($scores);
            $stdDeviation = sqrt($variance);
            
            $voterStats[$userId] = [
                'pseudo' => $userData['pseudo'],
                'team' => $userData['team'] ?? 'Inconnue',
                'stdDeviation' => $stdDeviation
            ];
        }
        
        if (empty($voterStats)) {
            return null;
        }
        
        // Tri par écart-type croissant (le plus constant d'abord)
        uasort($voterStats, function ($a, $b) {
            return $a['stdDeviation'] <=> $b['stdDeviation'];
        });
        
        // Retourne le votant avec l'écart-type le plus bas
        return reset($voterStats);
    }
    
    /**
     * Identifie le votant le plus radical (écart-type le plus élevé dans ses votes)
     * 
     * @param string|null $team Équipe à filtrer (optionnel)
     * @return array{pseudo: string, team: string, stdDeviation: float}|null
     */
    public function getMostVariedVoter(?string $team = null): ?array
    {
        $votes = $this->getAllVotes();
        if (empty($votes)) {
            return null;
        }
        
        $voterStats = [];
        
        foreach ($votes as $userId => $userData) {
            // Filtrer par équipe si demandé
            if ($team !== null && (!isset($userData['team']) || $userData['team'] !== $team)) {
                continue;
            }
            
            if (!isset($userData['scores']) || !is_array($userData['scores']) || count($userData['scores']) < 2 || !isset($userData['pseudo'])) {
                continue;
            }
            
            $scores = array_values($userData['scores']);
            $mean = array_sum($scores) / count($scores);
            $variance = 0;
            
            foreach ($scores as $score) {
                $variance += pow($score - $mean, 2);
            }
            
            $variance /= count($scores);
            $stdDeviation = sqrt($variance);
            
            $voterStats[$userId] = [
                'pseudo' => $userData['pseudo'],
                'team' => $userData['team'] ?? 'Inconnue',
                'stdDeviation' => $stdDeviation
            ];
        }
        
        if (empty($voterStats)) {
            return null;
        }
        
        // Tri par écart-type décroissant (le plus radical d'abord)
        uasort($voterStats, function ($a, $b) {
            return $b['stdDeviation'] <=> $a['stdDeviation'];
        });
        
        // Retourne le votant avec l'écart-type le plus élevé
        return reset($voterStats);
    }
    
    /**
     * Identifie les deux votants avec les votes les plus similaires (jumeaux)
     * 
     * @param string|null $team Équipe à filtrer (optionnel)
     * @return array{voter1: array{pseudo: string, team: string}, voter2: array{pseudo: string, team: string}, similarity: float}|null
     */
    public function getMostSimilarVoters(?string $team = null): ?array
    {
        $votes = $this->getAllVotes();
        if (empty($votes) || count($votes) < 2) {
            return null;
        }
        
        $voterScores = [];
        
        // Étape 1: Collecter tous les scores par votant
        foreach ($votes as $userId => $userData) {
            // Filtrer par équipe si demandé
            if ($team !== null && (!isset($userData['team']) || $userData['team'] !== $team)) {
                continue;
            }
            
            if (!isset($userData['scores']) || !is_array($userData['scores']) || count($userData['scores']) < 3 || !isset($userData['pseudo'])) {
                continue;
            }
            
            $voterScores[$userId] = [
                'pseudo' => $userData['pseudo'],
                'team' => $userData['team'] ?? 'Inconnue',
                'scores' => $userData['scores']
            ];
        }
        
        if (count($voterScores) < 2) {
            return null;
        }
        
        $highestSimilarity = -1;
        $mostSimilarPair = null;
        
        // Étape 2: Comparer chaque paire de votants
        $voterIds = array_keys($voterScores);
        for ($i = 0; $i < count($voterIds) - 1; $i++) {
            for ($j = $i + 1; $j < count($voterIds); $j++) {
                $voter1Id = $voterIds[$i];
                $voter2Id = $voterIds[$j];
                
                $voter1 = $voterScores[$voter1Id];
                $voter2 = $voterScores[$voter2Id];
                
                // Calculer la similarité
                $similarity = $this->calculateVoterSimilarity($voter1['scores'], $voter2['scores']);
                
                if ($similarity > $highestSimilarity) {
                    $highestSimilarity = $similarity;
                    $mostSimilarPair = [
                        'voter1' => [
                            'pseudo' => $voter1['pseudo'],
                            'team' => $voter1['team']
                        ],
                        'voter2' => [
                            'pseudo' => $voter2['pseudo'],
                            'team' => $voter2['team']
                        ],
                        'similarity' => $similarity
                    ];
                }
            }
        }
        
        return $mostSimilarPair;
    }
    
    /**
     * Identifie les deux votants avec les votes les plus différents (opposés)
     * 
     * @param string|null $team Équipe à filtrer (optionnel)
     * @return array{voter1: array{pseudo: string, team: string}, voter2: array{pseudo: string, team: string}, similarity: float}|null
     */
    public function getMostDifferentVoters(?string $team = null): ?array
    {
        $votes = $this->getAllVotes();
        if (empty($votes) || count($votes) < 2) {
            return null;
        }
        
        $voterScores = [];
        
        // Étape 1: Collecter tous les scores par votant
        foreach ($votes as $userId => $userData) {
            // Filtrer par équipe si demandé
            if ($team !== null && (!isset($userData['team']) || $userData['team'] !== $team)) {
                continue;
            }
            
            if (!isset($userData['scores']) || !is_array($userData['scores']) || count($userData['scores']) < 3 || !isset($userData['pseudo'])) {
                continue;
            }
            
            $voterScores[$userId] = [
                'pseudo' => $userData['pseudo'],
                'team' => $userData['team'] ?? 'Inconnue',
                'scores' => $userData['scores']
            ];
        }
        
        if (count($voterScores) < 2) {
            return null;
        }
        
        $lowestSimilarity = 2; // Une valeur supérieure à 1 pour garantir qu'elle sera remplacée
        $mostDifferentPair = null;
        
        // Étape 2: Comparer chaque paire de votants
        $voterIds = array_keys($voterScores);
        for ($i = 0; $i < count($voterIds) - 1; $i++) {
            for ($j = $i + 1; $j < count($voterIds); $j++) {
                $voter1Id = $voterIds[$i];
                $voter2Id = $voterIds[$j];
                
                $voter1 = $voterScores[$voter1Id];
                $voter2 = $voterScores[$voter2Id];
                
                // Calculer la similarité
                $similarity = $this->calculateVoterSimilarity($voter1['scores'], $voter2['scores']);
                
                if ($similarity < $lowestSimilarity) {
                    $lowestSimilarity = $similarity;
                    $mostDifferentPair = [
                        'voter1' => [
                            'pseudo' => $voter1['pseudo'],
                            'team' => $voter1['team']
                        ],
                        'voter2' => [
                            'pseudo' => $voter2['pseudo'],
                            'team' => $voter2['team']
                        ],
                        'similarity' => $similarity
                    ];
                }
            }
        }
        
        return $mostDifferentPair;
    }
    
    /**
     * Calcule la similarité entre deux ensembles de votes
     * Plus le résultat est proche de 1, plus les votes sont similaires
     * 
     * @param array<string, int> $scores1 Premier ensemble de scores
     * @param array<string, int> $scores2 Deuxième ensemble de scores
     * @return float Indice de similarité entre 0 et 1
     */
    private function calculateVoterSimilarity(array $scores1, array $scores2): float
    {
        // Trouver les pays communs votés par les deux utilisateurs
        $commonCountries = array_intersect(array_keys($scores1), array_keys($scores2));
        
        if (empty($commonCountries)) {
            return 0; // Aucun pays en commun
        }
        
        // Calculer la similarité basée sur la différence de notes
        $totalDifference = 0;
        $maxPossibleDifference = count($commonCountries) * 10; // 10 est la différence maximale possible (0 vs 10)
        
        foreach ($commonCountries as $countryCode) {
            $difference = abs($scores1[$countryCode] - $scores2[$countryCode]);
            $totalDifference += $difference;
        }
        
        // Transformer en similarité: 1 - (différence / différence maximale possible)
        return 1 - ($totalDifference / $maxPossibleDifference);
    }
    
    /**
     * Identifie le votant le plus mainstream (notes les plus proches de la moyenne)
     * 
     * @param string|null $team Équipe à filtrer (optionnel)
     * @return array{pseudo: string, team: string, deviation: float}|null
     */
    public function getMostMainstreamVoter(?string $team = null): ?array
    {
        $votes = $this->getAllVotes();
        if (empty($votes)) {
            return null;
        }
        
        // Obtenir le classement moyen pour avoir les scores moyens par pays
        $ranking = $this->calculateRanking();
        
        $voterDeviations = [];
        
        foreach ($votes as $userId => $userData) {
            // Filtrer par équipe si demandé
            if ($team !== null && (!isset($userData['team']) || $userData['team'] !== $team)) {
                continue;
            }
            
            if (!isset($userData['scores']) || !is_array($userData['scores']) || count($userData['scores']) < 3 || !isset($userData['pseudo'])) {
                continue;
            }
            
            $totalDeviation = 0;
            $countedScores = 0;
            
            foreach ($userData['scores'] as $countryCode => $score) {
                if (isset($ranking[$countryCode]) && $ranking[$countryCode]['totalVotes'] > 0) {
                    $avgScore = $ranking[$countryCode]['averageScore'];
                    $deviation = abs($score - $avgScore);
                    $totalDeviation += $deviation;
                    $countedScores++;
                }
            }
            
            // Seulement calculer si l'utilisateur a voté pour des pays avec des moyennes
            if ($countedScores > 0) {
                $avgDeviation = $totalDeviation / $countedScores;
                
                $voterDeviations[$userId] = [
                    'pseudo' => $userData['pseudo'],
                    'team' => $userData['team'] ?? 'Inconnue',
                    'deviation' => $avgDeviation
                ];
            }
        }
        
        if (empty($voterDeviations)) {
            return null;
        }
        
        // Tri par écart moyen croissant (le moins déviant = le plus mainstream)
        uasort($voterDeviations, function ($a, $b) {
            return $a['deviation'] <=> $b['deviation'];
        });
        
        // Retourne le votant avec l'écart moyen le plus faible
        return reset($voterDeviations);
    }
    
    /**
     * Identifie le votant le plus underground (notes les plus éloignées de la moyenne)
     * 
     * @param string|null $team Équipe à filtrer (optionnel)
     * @return array{pseudo: string, team: string, deviation: float}|null
     */
    public function getMostUndergroundVoter(?string $team = null): ?array
    {
        $votes = $this->getAllVotes();
        if (empty($votes)) {
            return null;
        }
        
        // Obtenir le classement moyen pour avoir les scores moyens par pays
        $ranking = $this->calculateRanking();
        
        $voterDeviations = [];
        
        foreach ($votes as $userId => $userData) {
            // Filtrer par équipe si demandé
            if ($team !== null && (!isset($userData['team']) || $userData['team'] !== $team)) {
                continue;
            }
            
            if (!isset($userData['scores']) || !is_array($userData['scores']) || count($userData['scores']) < 3 || !isset($userData['pseudo'])) {
                continue;
            }
            
            $totalDeviation = 0;
            $countedScores = 0;
            
            foreach ($userData['scores'] as $countryCode => $score) {
                if (isset($ranking[$countryCode]) && $ranking[$countryCode]['totalVotes'] > 0) {
                    $avgScore = $ranking[$countryCode]['averageScore'];
                    $deviation = abs($score - $avgScore);
                    $totalDeviation += $deviation;
                    $countedScores++;
                }
            }
            
            // Seulement calculer si l'utilisateur a voté pour des pays avec des moyennes
            if ($countedScores > 0) {
                $avgDeviation = $totalDeviation / $countedScores;
                
                $voterDeviations[$userId] = [
                    'pseudo' => $userData['pseudo'],
                    'team' => $userData['team'] ?? 'Inconnue',
                    'deviation' => $avgDeviation
                ];
            }
        }
        
        if (empty($voterDeviations)) {
            return null;
        }
        
        // Tri par écart moyen décroissant (le plus déviant = le plus underground)
        uasort($voterDeviations, function ($a, $b) {
            return $b['deviation'] <=> $a['deviation'];
        });
        
        // Retourne le votant avec l'écart moyen le plus élevé
        return reset($voterDeviations);
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
        
        // Migration du format ancien (pseudo => data) vers nouveau (id => data avec pseudo)
        $needsMigration = false;
        $newVotes = [];
        
        foreach ($votes['votes'] as $key => $data) {
            // Si la clé est vide, c'est une erreur de migration précédente - à nettoyer
            if (empty(trim($key))) {
                $needsMigration = true;
                continue;
            }
            
            // Si la première partie de la clé n'est pas "usr_" et qu'il n'y a pas de champ pseudo
            // c'est l'ancien format où le pseudo est la clé
            if (!str_starts_with($key, 'usr_') && !isset($data['pseudo'])) {
                $needsMigration = true;
                $newId = $this->generateUserId();
                $newVotes[$newId] = array_merge(['pseudo' => $key], $data);
            } else {
                $newVotes[$key] = $data;
            }
        }
        
        // Si on a fait une migration, mettre à jour et sauvegarder
        if ($needsMigration) {
            $votes['votes'] = $newVotes;
            
            // Sauvegarder le fichier migré
            $content = json_encode($votes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            file_put_contents($this->votesFilePath, $content);
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