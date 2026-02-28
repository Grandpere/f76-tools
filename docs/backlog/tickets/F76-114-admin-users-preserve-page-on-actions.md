# F76-114 - Admin users preserve current page after actions

## Contexte
Sur `/admin/users`, une action POST (toggle active/admin, reset, resend, unlink) renvoyait vers la page 1, meme depuis une page paginee differente.

## Scope
- Preserver `page` dans les formulaires d action.
- Preserver `page` dans la redirection controller apres action.
- Ajouter un test fonctionnel de preservation.

## Critere d acceptance
- Depuis `/admin/users?...&page=2`, une action reste sur `page=2` apres redirect.
- Le comportement des autres filtres/sorts/perPage reste intact.

## Tests
- `make phpstan`
- `make phpunit-unit`
- `make phpunit-integration`
- `make php-cs-fixer-check`
- `make phpunit-functional` (execute manuellement par l utilisateur)

## Risques / rollback
- Risque: valeur `page` invalide en POST.
- Mitigation: normalisation serveur `page >= 1`.
