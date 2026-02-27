# F76-076 - Admin User Typed Actor And Auth Context

## Contexte
Les services admin user utilisaient encore `mixed $actor` avec des statuts `ACTOR_REQUIRED`, alors que les actions admin exigent deja un utilisateur authentifie.

## Scope
- Introduire `AdminAuthenticatedUserContext` pour resoudre explicitement l'actor.
- Taper les services admin user avec `UserEntity $actor`.
- Supprimer les branches/statuts `ACTOR_REQUIRED` devenues mortes.
- Adapter `UserManagementController`, mappers et tests.
- Verifier phpstan/unit/integration.

## Avancement
- [x] Ajouter `AdminAuthenticatedUserContext`.
- [x] Taper `ToggleUserActiveApplicationService`, `ToggleUserAdminApplicationService`, `GenerateResetLinkApplicationService`.
- [x] Supprimer `ACTOR_REQUIRED` dans les resultats/mappers associes.
- [x] Adapter le controller et la couverture unitaire.
- [x] Verifier phpstan/unit/integration.
