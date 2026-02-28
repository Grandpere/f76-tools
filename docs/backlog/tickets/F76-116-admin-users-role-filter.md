# F76-116 - Admin users role filter

## Contexte
Le backoffice users ne permettait pas de filtrer rapidement les comptes admin vs non-admin.

## Scope
- Ajouter un filtre `role` (`admin|user`) sur `/admin/users`.
- Integrer ce filtre dans:
  - la liste filtree,
  - la preservation des query params (POST redirects),
  - les formulaires d action,
  - le switch de langue dans le header.
- Ajouter les traductions FR/EN/DE.
- Ajouter des tests fonctionnels cibles (filtrage + preservation en redirect).

## Critere d acceptance
- `?role=admin` affiche seulement les comptes admin.
- `?role=user` affiche seulement les comptes non-admin.
- Une action admin conserve le filtre role en redirection.

## Tests
- `make phpstan`
- `make phpunit-unit`
- `make phpunit-integration`
- `make php-cs-fixer-check`
- `make phpunit-functional` (execute manuellement par l utilisateur)
