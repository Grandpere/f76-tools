# F76-075 - Item Translation Explicit Admin Guard

## Contexte
`ItemTranslationController` etait le seul controller admin sans garde explicite dans son action.

## Scope
- Appliquer le trait de garde admin partage.
- Ajouter la garde explicite dans l'action principale.
- Verifier phpstan/unit/integration.

## Avancement
- [x] Ajouter `AdminRoleGuardControllerTrait` dans `ItemTranslationController`.
- [x] Appeler `ensureAdminAccess()` en entree d'action.
- [x] Verifier phpstan/unit/integration.
