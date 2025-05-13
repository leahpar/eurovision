# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Context

Ce projet est une petite appli entre potes pour voter les prestations de l'eurovision.
Il y a une page d'accueil avec les prestations et un bouton pour voter.
Chaque utilisateur peut voter pour chaque prestation.
Il y a une page de résultats avec le classement des prestations.

## KISS

- This repository is a small personnal Symfony for a side project.
- Don't bother with complexity, it's a simple project.
- KISS is the key.
- Don't hesitate to ask for more details if needed.

## Language, Framework & tools

- **PHP**: 8.2+
- **Symfony**: 7.2+
- **Javascript**: vanilla, ES6+

## Lint & Testing

- `php bin/console lint:twig /templates/xxx`: Lint Twig templates
- `php bin/console lint:yaml /config/xxx`: Lint YAML files
- `php vendor/bin/phpstan analyse -c phpstan.neon -l 6`: PhpStan static analysis

## Code Style Guidelines

- **Naming**: Use camelCase for methods/variables, PascalCase for classes
- **Imports**: Group Symfony components first, then other dependencies, then project classes
- **Types**: Include PHP 8 type hints for parameters and return values
- **Error handling**: Use try/catch blocks with specific exception types
- **Controllers**: Make controllers thin, move business logic to services
- **Entities**: Implement LoggableEntity interface for entities that need logging
- **Repository**: Use SearchableEntityRepositoryTrait for searchable entities
- **Templates**: Follow Twig best practices with {% block %} structure

## Code Organization (src/*)

- **Controller/**: Contains all application controllers; one controller per entity/feature
- **Entity/**: Domain models with Doctrine ORM annotations
- **Form/**: Form type definitions and extensions
- **Repository/**: Doctrine repositories with custom query methods
- **Service/**: Business logic and application services
- **Twig/**: Custom Twig extensions and components

## Git

- Les messages de commits doivent être en français.
- Les messages de commits doivent commencer par le picto 🤖 et garder un message concernant les modifications seulement.
