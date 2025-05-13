# Eurovision Vote App - Spécifications

## Vue d'ensemble

Application simple pour permettre à des amis de voter sur les prestations Eurovision.
Architecture minimaliste basée sur des fichiers JSON, sans authentification complexe.

## Fonctionnalités

### Utilisateur (joueur)

- Saisie d'un pseudo (pas d'authentification)
- Choix d'une équipe parmi une liste prédéfinie
- Vote pour chaque prestation (note de 0 à 10)
- Possibilité de modifier son vote

### Vote

- Un utilisateur peut voter plusieurs fois (mise à jour du vote précédent)
- Pas de limitation IP ou tracking
- Vote non-obligatoire sur toutes les prestations

### Résultats

Dashboard publique d'affichage des résultats

- Classement général des prestations
- Possibilité de filtrer/grouper par équipe
- Calcul automatique des moyennes et classements

## Spécifications techniques

### Stack Back

- PHP: 8.2+
- Symfony: 7.2+
- Stockage: Fichiers JSON (pas de base de données)

### Stack front

- Twig
- AlpineJS
- JavaScript: Vanilla ES6+
- Tailwind CSS

### Architecture des données

#### Configuration (`config/eurovision.json`)

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
      }
    }
  }
}
```

#### Votes (`storage/votes.json`)

```json
{
  "votes": {
    "pseudo1": {
      "team": "Team Baguette",
      "scores": {
        "FRA": 8,
        "SWE": 7,
        "ITA": 9
      }
    }
  }
}
```

### Structure Symfony

```
src/
├── Controller/
│   ├── HomeController.php      # Page d'accueil (saisie pseudo/équipe)
│   ├── VoteController.php      # Interface de vote
│   └── ResultsController.php   # Page des résultats
├── Service/
│   ├── ConfigService.php       # Lecture config Eurovision
│   └── VoteService.php         # Gestion votes (lecture/écriture JSON)
└── Form/
└── VoteFormType.php        # Formulaire de vote
```

## Contraintes et principes

### KISS (Keep It Simple, Stupid)

- Pas de complexité inutile
- Fichiers JSON pour la persistence
- Pas d'authentification complexe
- Interface simple et fonctionnelle

### Sécurité

- Validation basique des données côté serveur

### Performance

- Application prévue pour un usage limité (groupe d'amis)
- Pas d'optimisation particulière requise
- Chargement complet des données acceptable
- Utilisation du localstorage côté client pour une "session" persistente

## Règles métier

- Un vote par pseudo (écrasement si re-vote)
- Pas d'obligation de voter pour tous les pays
- Calcul de moyenne pour le classement
- Configuration réutilisable d'une année sur l'autre

## Workflow joueur (mobile)

### 1. Connexion
- Saisie pseudo + choix équipe
- Stockage dans LocalStorage pour persistence
- Redirection vers interface de vote

### 2. Interface de vote
- Affichage visuel des performances (design mobile-first)
- Le joueur voit uniquement ses propres votes (pas ceux des autres)
- Interface tactile pour noter de 0 à 10
- Soumission immédiate à chaque vote modifié

### 3. Après vote
- Possibilité de revenir modifier ses votes
- Pas d'accès direct aux résultats pour les joueurs

## Workflow écran partagé (grand écran)

### Page résultats dédiée
- URL spécifique type `/results`
- Accès public (pas d'authentification nécessaire)
- Design adapté grand écran (lisible à distance)
- Auto-refresh pour mise à jour en temps réel
- Affichage des moyennes et classements par pays
- Possibilité de filtrer/grouper par équipe

## Workflow admin

### Page admin dédiée

- URL `/admin`
- Basic auth
- Gestion des utilisateurs (suppression, renommage)
- Gestion des équipes (ajout/modification/suppression)
- Reset des votes (vider le fichier JSON)

## Flux technique
```
Joueur mobile -> Vote -> Serveur (JSON) -> Écran résultats (auto-refresh)
↑              ↑                           ↓
LocalStorage   Pas de session              Calculs moyennes
```

## Séparation des interfaces

- **Interface joueur** : focus mobile, affichage minimal (ses votes uniquement)
- **Interface résultats** : focus grand écran, vue d'ensemble enrichie
- **Interface admin** : page publique pour reset votes, gestion des utilisateurs, gestion des équipes
- **Pas de mélange** : pas de redirection entre les interfaces

