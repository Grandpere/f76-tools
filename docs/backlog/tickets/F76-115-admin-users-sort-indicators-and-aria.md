# F76-115 - Admin users sort indicators and aria-sort

## Contexte
La table users etait triable mais sans indication visuelle explicite du tri actif ni attribut `aria-sort` sur les en-tetes.

## Scope
- Ajouter `aria-sort` sur les 3 colonnes triables (`email`, `created_at`, `active`).
- Ajouter un indicateur visuel (fleche haut/bas) sur la colonne actuellement triee.
- Ajouter un style dedie pour liens/indicateurs de tri.
- Renforcer un test fonctionnel existant pour verifier la presence du marqueur de tri.

## Critere d acceptance
- Le tri actif est visible immediatement dans le tableau.
- Les technologies d assistance recoivent l etat de tri via `aria-sort`.

## Tests
- `make phpstan`
- `make phpunit-unit`
- `make phpunit-integration`
- `make php-cs-fixer-check`
- `make phpunit-functional` (execute manuellement par l utilisateur)
