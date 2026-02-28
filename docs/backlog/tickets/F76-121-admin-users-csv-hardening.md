# F76-121 - Admin users CSV hardening

## Contexte
L export CSV users fonctionnait, mais restait exposable a:
- des soucis d affichage UTF-8 sur certains outils tableur,
- des interpretations de formules si une cellule commence par `=`, `+`, `-` ou `@`.

## Scope
- Ajouter un BOM UTF-8 au debut du CSV exporte.
- Sanitizer les valeurs CSV exportees contre l injection de formules:
  - prefixer d une apostrophe les valeurs commencant par `=`, `+`, `-`, `@`.
- Ajouter un test fonctionnel cible sur:
  - presence du BOM,
  - sanitation d un email "formula-like".

## Criteres d acceptance
- Le CSV users est ouvert proprement en UTF-8 dans les tableurs courants.
- Les cellules potentiellement interpretees comme formules sont neutralisees.
- La couverture fonctionnelle protege ce comportement.

## Tests
- `make phpstan`
- `make phpunit-unit`
- `make phpunit-integration`
- `make php-cs-fixer-check`
- `make phpunit-functional` (execute manuellement par l utilisateur)
