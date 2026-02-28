# F76-137 - PHPat port naming guardrails

## Contexte
Apres la suppression des suffixes `*Interface` sur les ports applicatifs, il faut verrouiller cette convention pour eviter les regressions.

## Scope
- Ajouter des regles PHPat dans `tests/Architecture/CleanArchitectureTest.php`:
  - interdire les classes suffixees `*Interface` dans `Application`, `Domain` et `UI`,
  - imposer que les contrats UI suffixes `*Port` soient des interfaces,
  - interdire les legacy `*Interface` dans la couche `UI`.

## Criteres d acceptance
- Les nouvelles regles sont presentes et lisibles dans la suite architecture.
- Les checks qualite standards passent.

## Notes
- Le binaire `vendor/bin/phpat` n est pas present dans l image actuelle; les regles sont donc validees via compilation/phpstan et seront executees quand PHPat sera expose dans l outillage projet.

## Tests
- `make phpstan`
- `make phpunit-unit`
- `make php-cs-fixer-check`
- `make phpunit-functional` (execute manuellement par l utilisateur)

