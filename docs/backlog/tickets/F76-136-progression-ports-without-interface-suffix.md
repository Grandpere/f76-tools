# F76-136 - Progression ports without Interface suffix

## Contexte
Le contexte `Progression` utilisait encore des ports suffixes `Interface`.
La convention projet impose des noms de ports sans ce suffixe.

## Scope
- Renommer:
  - `ItemReadRepositoryInterface` -> `ItemReadRepository`
  - `PlayerReadRepositoryInterface` -> `PlayerReadRepository`
  - `ProgressionOwnedPlayerReadResolverInterface` -> `ProgressionOwnedPlayerReadPort`
- Propager les renommages dans:
  - services applicatifs,
  - repositories et resolvers,
  - tests unitaires,
  - references docs impactees.
- Gerer la collision de nom sur `ProgressionOwnedPlayerReadResolver` (port et implementation en namespace UI) via un nom de port distinct (`...ReadPort`).

## Criteres d acceptance
- Plus de reference aux anciens noms `*Interface` pour ces trois ports.
- Le resolver concret reste `ProgressionOwnedPlayerReadResolver`.
- Les checks qualite standard restent verts.

## Tests
- `make phpstan`
- `make phpunit-unit`
- `make phpunit-integration`
- `make php-cs-fixer-check`
- `make phpunit-functional` (execute manuellement par l utilisateur)

