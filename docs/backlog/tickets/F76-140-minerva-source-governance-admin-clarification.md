# F76-140 - Minerva source governance admin clarification

## Contexte
La gouvernance Minerva etait documentee, mais une partie de la doc indiquait encore "override non actif" alors que le backoffice supporte deja les overrides manuels.

## Objectif
- Clarifier la regle operationnelle: genere par defaut, override exceptionnel et temporaire.
- Rendre cette regle visible uniquement pour les admins dans la page Minerva backoffice.
- Aligner runbook/gouvernance avec cette pratique.

## Scope
- [x] Encart de gouvernance dans `admin/minerva_rotation.html.twig`.
- [x] Traductions EN/FR/DE associees.
- [x] Ajustement doc `docs/ops/minerva-governance.md`.
- [x] Ajustement runbook `docs/ops/ops-runbook.md`.
- [x] Assertion functional admin sur presence de l encart.

## Hors scope
- Aucun changement de logique de generation/refresh.
- Aucune exposition de cette gouvernance sur le front joueur.

## Verification
- `make phpstan`
- `make phpunit-unit`
- `make phpunit-integration`
- `make php-cs-fixer-check`
- `make phpunit-functional` (manuel utilisateur)

