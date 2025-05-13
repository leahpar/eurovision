# Eurovision Vote App

Application web simple pour organiser des votes entre amis lors des soirées Eurovision.

> **DISCLAIMER** : Ce projet a été entièrement développé par Claude (Anthropic), un modèle de langage IA. Il a été créé dans le but d'explorer les capacités des LLM dans le développement de code. Aucun développeur humain n'a écrit une seule ligne de code dans ce projet, à l'exception des instructions et des retours fournis.

## Description

Cette application Symfony permet de :
- S'identifier avec un pseudo et une équipe
- Voter pour chaque prestation de l'Eurovision (notes de 0 à 10)
- Visualiser les résultats en temps réel sur un écran partagé
- Filtrer les résultats par équipe

## Prérequis

- PHP 8.2+
- Composer
- Symfony 7.2+
- Navigateur moderne (avec support de JavaScript et localStorage)

## Installation

1. Cloner le dépôt :
```bash
git clone <url-du-depot>
cd eurovision
```

2. Installer les dépendances :
```bash
composer install
```

3. Configurer le fichier des prestations :
```bash
mkdir -p var/storage
```

4. Créer le fichier de configuration `var/storage/eurovision.json` avec les prestations :
```json
{
  "eurovision": {
    "edition": "Eurovision 2025",
    "teams": [
      "Team Baguette",
      "Team Abba",
      "Team Pizza"
    ],
    "performances": {
      "FRA": {
        "name": "France",
        "artist": "Nom Artiste",
        "song": "Titre Chanson",
        "flag": "🇫🇷"
      },
      "SWE": {
        "name": "Suède",
        "artist": "Artiste Suédois",
        "song": "Chanson Suédoise",
        "flag": "🇸🇪"
      }
    }
  }
}
```

5. S'assurer que les permissions sont correctes :
```bash
chmod -R 777 var/storage
```

## Démarrage

1. Lancer le serveur de développement Symfony :
```bash
symfony server:start
```

2. Accéder à l'application à l'adresse : `http://localhost:8000`

## Architecture

- **Controllers** :
  - `ConnectionController` : Gestion de la connexion (pseudo/équipe)
  - `VoteController` : Interface et API de vote
  - `ResultsController` : Affichage et API des résultats

- **Services** :
  - `ConfigService` : Lecture du fichier de configuration JSON
  - `VoteService` : Lecture/écriture des votes

- **Stockage** :
  - `var/storage/eurovision.json` : Configuration des prestations
  - `var/storage/votes.json` : Enregistrement des votes

## Utilisation

### Page de connexion
- Entrez votre pseudo
- Sélectionnez votre équipe
- Les informations sont sauvegardées dans le localStorage du navigateur

### Page de vote
- Votez pour chaque pays (note de 0 à 10)
- Les votes sont enregistrés instantanément
- Vous pouvez modifier vos votes à tout moment

### Page de résultats
- Affichage du classement général
- Statistiques de participation
- Filtrage par équipe
- Rafraîchissement automatique (30 secondes)

## Déploiement en production

1. Configurer les variables d'environnement de production :
```bash
APP_ENV=prod
APP_SECRET=your_secret
```

2. Vider le cache :
```bash
php bin/console cache:clear --env=prod
```

3. S'assurer des permissions en écriture :
```bash
chmod -R 777 var/storage
```

## PWA (Progressive Web App)

L'application est configurée comme une PWA et peut être installée sur l'écran d'accueil des appareils mobiles.

## Personnalisation

### Modification des équipes
Modifiez le fichier `var/storage/eurovision.json` pour ajouter/supprimer des équipes.

### Modification des pays
Modifiez le fichier `var/storage/eurovision.json` pour ajouter/supprimer/modifier les pays participants.

## Licence

[WTFPL](https://en.wikipedia.org/wiki/WTFPL)
