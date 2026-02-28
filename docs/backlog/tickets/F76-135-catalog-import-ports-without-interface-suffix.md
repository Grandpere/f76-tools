# F76-135 - Catalog import ports without Interface suffix

## Contexte
La convention DDD du projet retire le suffixe `Interface` sur les ports applicatifs.
Les ports d import du contexte `Catalog` etaient encore suffixes.

## Scope
- Renommer les ports d import:
  - `ItemImportItemRepositoryInterface` -> `ItemImportItemRepository`
  - `ItemImportPersistenceInterface` -> `ItemImportPersistence`
  - `ItemImportSourceReaderInterface` -> `ItemImportSourceReader`
- Propager les renommages dans:
  - service applicatif d import,
  - repositories/infra d import,
  - wiring DI (`config/services.yaml`),
  - tests unitaires associes.
- Garder `Item` (domaine metier) inchange.

## Criteres d acceptance
- Aucun usage des 3 anciens noms `*Interface` ne subsiste dans le code.
- Les checks qualite habituels restent verts.

## Tests
- `make phpstan`
- `make phpunit-unit`
- `make phpunit-integration`
- `make php-cs-fixer-check`
- `make phpunit-functional` (execute manuellement par l utilisateur)

