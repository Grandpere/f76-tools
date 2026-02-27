# F76-073 - User Management Controller POST Guard Helper

## Contexte
`UserManagementController` repetait le meme pattern dans chaque action POST admin (deny access + CSRF invalid + redirect).

## Scope
- Extraire un helper prive de garde POST admin.
- Extraire un helper prive de redirection vers la liste users.
- Simplifier les actions `toggleActive`, `toggleAdmin`, `generateResetLink`.
- Verifier phpstan/unit/integration.

## Avancement
- [x] Ajouter `guardAdminPostOrFailure`.
- [x] Ajouter `redirectToUsers`.
- [x] Simplifier les 3 actions POST.
- [x] Verifier phpstan/unit/integration.
