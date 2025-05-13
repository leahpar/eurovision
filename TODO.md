# Tâches de développement

Les tâches sont regroupées par catégories pour faciliter la gestion du projet.
En l'occurrence ici, le chapitre 1 contient 3 tâches distinctes.

Chaque tâche doit être suivie des lints, tests, et d'un commit. Et validation avant de passer à la suivante.

## Configuration et base
1. [x] **Setup Symfony** : Nouveau projet Symfony 7.2+, config PHP 8.2+
2. [x] **Fichier config** : Créer `var/storage/eurovision.json` avec structure complète
3. [x] **Sample data** : Ajouter quelques pays/équipes pour tester

## Services backend
1. [x] **ConfigService** : Lecture du fichier `eurovision.json`
2. [x] **VoteService** : Gestion lecture/écriture `var/storage/votes.json`
3. [x] **Tests unitaires** : Pour ConfigService et VoteService
4. [x] **Gestion erreurs** : Validation JSON, fichiers manquants

## Controllers et API
1. [x] **ConnectionController** : Page saisie pseudo/équipe
2. [x] **VoteController** : Interface vote + API `/api/vote` (POST)
3. [x] **ResultsController** : Page résultats + API `/api/results` (GET)
4. [x] **Configuration routes** : Mapping URLs propres
5. [x] **Validation API** : Contrôles côté serveur pour les votes
6. [x] **Réponses HTTP** : Gestion erreurs API appropriées

## Frontend + AlpineJS
1. [x] **CDN Alpine** : Ajout script tag dans layout Twig
2. [x] **Templates Twig** : Layouts mobile-first + grand écran
3. [x] **Page connexion** : Formulaire pseudo/équipe
4. [x] **Interface vote** : Affichage visual + AlpineJS x-data
5. [x] **VoteApp component** : Logique vote + soumission async
6. [x] **Interface résultats** : Design grand écran
7. [x] **ResultsApp component** : Auto-refresh + filtres équipes
8. [x] **LocalStorage** : Persistance pseudo/équipe/votes
9. [x] **Responsive design** : Focus mobile/tactile

## Finitions
1. [x] **CSS/Design** : Cohérence visuelle entre pages
2. [x] **Favicon/Meta** : Tags HTML appropriés + PWA
3. [x] **Documentation** : README avec instructions

## Retours clients

### Divers
- [x] Readme.md => Ajouter un disclaimer indiquant que tout le code a été écrit par Claude, que le développement de ce projet a pour but de jouer avec les LLM

### Page de résultats
- [x] Supprimer l'entête de la page
- [x] Refresh automatique toutes les 10 secondes
- [x] Ajouter un bouton de refresh manuel

### Page de votes
- [x] Trier les pays par ordre alphabétique
- [x] Enlever le bouton de déconnexion
- [x] Distinguer visuellement les pays non notés
- Header :
  - [x] Titre = "Eurovision 2025" seulement
  - [x] Pas de retour à la ligne au milieur du pseudo / équipe
- Améliorer la card des pays : 
  - [x] Boutons des votes sur une seule ligne SANS scroll vertical
  - [x] Drapeau plus visible
  - [x] Noms de la chanson et de l'artiste sur la même ligne
  - [x] Utilise plus la largeur de l'écran
