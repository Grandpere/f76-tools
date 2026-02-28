# F76-128 - Admin auth events filters

## Contexte
La page admin d activite securite utilisateur affichait les evenements mais sans filtres, ce qui degradait l investigation quand le volume augmentait.

## Scope
- Ajouter filtres sur la page admin auth events:
  - filtre de niveau (`info`/`warning`),
  - recherche texte (`event` ou `IP`).
- Etendre le reader `AuthAuditLogReader` pour supporter ces filtres.
- Ajouter couverture fonctionnelle ciblee sur le filtrage.

## Criteres d acceptance
- Un admin peut filtrer les evenements securite par niveau et recherche texte.
- Les resultats affiches respectent les filtres saisis.

## Tests
- `make phpstan`
- `make phpunit-unit`
- `make phpunit-integration`
- `make php-cs-fixer-check`
- `make phpunit-functional` (execute manuellement par l utilisateur)
