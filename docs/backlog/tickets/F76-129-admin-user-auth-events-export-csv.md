# F76-129 - Admin user auth events export CSV

## Contexte
La page admin d activite securite utilisateur permettait le filtrage mais pas l export CSV cible pour audit externe.

## Scope
- Ajouter une route d export CSV sur la page `admin/users/{id}/auth-events`.
- Reprendre les filtres courants (`level`, `q`) dans l export.
- Ajouter BOM UTF-8 + sanitation anti formula CSV.
- Ajouter couverture fonctionnelle ciblee (403 non-admin + contenu export admin).

## Criteres d acceptance
- Un admin peut exporter les evenements auth d un utilisateur en CSV.
- Le CSV respecte les filtres saisis et contient un en-tete stable.
- Un non-admin recoit `403` sur la route export.

## Tests
- `make phpstan`
- `make phpunit-unit`
- `make phpunit-integration`
- `make php-cs-fixer-check`
- `make phpunit-functional` (execute manuellement par l utilisateur)

