# F76-117 - Admin users export CSV (filtered view)

## Contexte
Le backoffice users ne proposait pas d export des donnees, contrairement aux logs d audit.

## Scope
- Ajouter une route admin `GET /admin/users/export` qui exporte un CSV.
- Reutiliser les filtres/tri de la vue users (`q`, `google`, `active`, `role`, `verified`, `localPassword`, `sort`, `dir`).
- Ajouter le lien `Export CSV` dans la toolbar de filtres.
- Ajouter les traductions FR/EN/DE.
- Ajouter tests fonctionnels:
  - acces refuse non-admin,
  - export admin avec filtres et en-tete CSV attendu.

## Critere d acceptance
- L export contient uniquement les users de la vue filtree.
- Le CSV inclut l en-tete et les colonnes de securite/identite attendues.
- Un non-admin recoit 403.

## Tests
- `make phpstan`
- `make phpunit-unit`
- `make phpunit-integration`
- `make php-cs-fixer-check`
- `make phpunit-functional` (execute manuellement par l utilisateur)
