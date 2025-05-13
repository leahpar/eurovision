# Tâches de développement

Les tâches sont regroupées par catégories pour faciliter la gestion du projet.
En l'occurrence ici, le chapitre 1 contient 3 tâches distinctes.

Chaque tâche doit être suivie des lints, tests, et d'un commit. Et validation avant de passer à la suivante.

## 1. Configuration et base
- [x] **Setup Symfony** : Nouveau projet Symfony 7.2+, config PHP 8.2+
- [x] **Fichier config** : Créer `var/storage/eurovision.json` avec structure complète
- [x] **Sample data** : Ajouter quelques pays/équipes pour tester

## 2. Services backend
- [x] **ConfigService** : Lecture du fichier `eurovision.json`
- [x] **VoteService** : Gestion lecture/écriture `var/storage/votes.json`
- [x] **Tests unitaires** : Pour ConfigService et VoteService
- [x] **Gestion erreurs** : Validation JSON, fichiers manquants

## 3. Controllers et API
- [ ] **ConnectionController** : Page saisie pseudo/équipe
- [ ] **VoteController** : Interface vote + API `/api/vote` (POST)
- [ ] **ResultsController** : Page résultats + API `/api/results` (GET)
- [ ] **Configuration routes** : Mapping URLs propres
- [ ] **Validation API** : Contrôles côté serveur pour les votes
- [ ] **Réponses HTTP** : Gestion erreurs API appropriées

## 4. Frontend + AlpineJS
- [ ] **CDN Alpine** : Ajout script tag dans layout Twig
- [ ] **Templates Twig** : Layouts mobile-first + grand écran
- [ ] **Page connexion** : Formulaire pseudo/équipe
- [ ] **Interface vote** : Affichage visual + AlpineJS x-data
- [ ] **VoteApp component** : Logique vote + soumission async
- [ ] **Interface résultats** : Design grand écran
- [ ] **ResultsApp component** : Auto-refresh + filtres équipes
- [ ] **LocalStorage** : Persistance pseudo/équipe/votes
- [ ] **Responsive design** : Focus mobile/tactile

## 5. Finitions
- [ ] **CSS/Design** : Cohérence visuelle entre pages
- [ ] **Favicon/Meta** : Tags HTML appropriés
- [ ] **Tests manuels** : Scénarios complets
- [ ] **Performance** : Vérification chargement
- [ ] **Documentation** : README avec instructions

## 6. Déploiement
- [ ] **Config prod** : Variables environnement
- [ ] **Permissions** : Fichiers storage en écriture
- [ ] **Test déployé** : Vérification environnement final
- [ ] **Backup strategy** : Sauvegarde votes.json
