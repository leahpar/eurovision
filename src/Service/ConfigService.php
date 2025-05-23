<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;

class ConfigService
{
    private string $configFilePath;
    /** @var array{eurovision: array{edition: string, teams: list<string>, performances: array<string, array{name: string, artist: string, song: string, flag: string}>}}|null */
    private ?array $config = null;

    public function __construct(
        private readonly ParameterBagInterface $parameterBag,
        private readonly Filesystem $filesystem
    ) {
        $this->configFilePath = $this->parameterBag->get('kernel.project_dir') . '/config/data/eurovision.json';
    }

    /**
     * Récupère toute la configuration.
     * 
     * @return array{eurovision: array{edition: string, teams: list<string>, performances: array<string, array{name: string, artist: string, song: string, flag: string}>}}
     */
    public function getConfig(): array
    {
        if ($this->config === null) {
            $this->loadConfig();
        }

        return $this->config;
    }

    /**
     * Récupère l'édition actuelle de l'Eurovision.
     */
    public function getEdition(): string
    {
        return $this->getConfig()['eurovision']['edition'] ?? '';
    }

    /**
     * Récupère la liste des équipes.
     * 
     * @return list<string>
     */
    public function getTeams(): array
    {
        return $this->getConfig()['eurovision']['teams'] ?? [];
    }

    /**
     * Récupère la liste des performances.
     * 
     * @return array<string, array{name: string, artist: string, song: string, flag: string}>
     */
    public function getPerformances(): array
    {
        return $this->getConfig()['eurovision']['performances'] ?? [];
    }

    /**
     * Récupère une performance spécifique par son code pays.
     * 
     * @return array{name: string, artist: string, song: string, flag: string}|null
     */
    public function getPerformance(string $countryCode): ?array
    {
        $performances = $this->getPerformances();
        return $performances[$countryCode] ?? null;
    }

    /**
     * Charge la configuration depuis le fichier JSON.
     * 
     * @throws \RuntimeException Si le fichier n'existe pas, n'est pas lisible ou n'est pas un JSON valide
     */
    private function loadConfig(): void
    {
        if (!$this->filesystem->exists($this->configFilePath)) {
            throw new \RuntimeException(sprintf('Le fichier de configuration %s n\'existe pas.', $this->configFilePath));
        }

        $configContent = file_get_contents($this->configFilePath);
        if ($configContent === false) {
            throw new \RuntimeException(sprintf('Impossible de lire le fichier de configuration %s.', $this->configFilePath));
        }

        $config = json_decode($configContent, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException(sprintf('Le fichier de configuration %s n\'est pas un JSON valide: %s', $this->configFilePath, json_last_error_msg()));
        }

        $this->config = $config;
    }
}