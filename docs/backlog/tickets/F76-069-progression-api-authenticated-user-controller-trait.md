# F76-069 - Progression API Authenticated User Controller Trait

## Contexte
Les controllers API progression dupliquaient un helper `getAuthenticatedUser()` identique.

## Scope
- Introduire un trait partage pour la resolution de l'utilisateur authentifie.
- L'appliquer aux controllers API progression concernes.
- Verifier phpstan/unit/integration.

## Avancement
- [x] Ajouter `ProgressionAuthenticatedUserControllerTrait`.
- [x] Migrer `PlayerController`, `PlayerItemKnowledgeController`, `PlayerKnowledgeTransferController`, `PlayerStatsController`.
- [x] Verifier phpstan/unit/integration.
