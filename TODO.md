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
5. [ ] **Validation API** : Contrôles côté serveur pour les votes
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
1. [ ] **CSS/Design** : Cohérence visuelle entre pages
2. [x] **Favicon/Meta** : Tags HTML appropriés
3. [ ] **Tests manuels** : Scénarios complets
4. [ ] **Performance** : Vérification chargement
5. [ ] **Documentation** : README avec instructions

## Déploiement
1. [ ] **Config prod** : Variables environnement
2. [ ] **Permissions** : Fichiers storage en écriture
3. [ ] **Test déployé** : Vérification environnement final
4. [ ] **Backup strategy** : Sauvegarde votes.json