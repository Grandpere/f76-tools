# F76-141 - Minerva reliability check JSON + admin consistency status

## Contexte
Le check de couverture Minerva existait deja, mais la sortie etait principalement texte.  
Pour un monitoring cron fiable, il faut une sortie machine-readable.  
Cote admin, un statut simple de coherence doit etre visible rapidement.

## Objectif
- Exposer une sortie JSON du check Minerva (avec status + range + metriques).
- Garder le code retour non-zero en dry-run strict quand il y a des trous.
- Afficher un statut de coherence simple en backoffice Minerva.

## Scope
- [x] Option `--format=text|json` sur `app:minerva:refresh-rotation`.
- [x] Nouveau target `make minerva-refresh-check-json`.
- [x] Ligne de statut coherence dans la carte freshness admin Minerva.
- [x] Mise a jour runbook/gouvernance ops.
- [x] Tests unitaires commande + assertion functional admin ciblee.

## Hors scope
- Aucun changement de logique metier de generation/refresh.
- Pas de nouveau provider externe.

## Verification
- `make phpstan`
- `make phpunit-unit`
- `make phpunit-integration`
- `make php-cs-fixer-check`
- `make phpunit-functional` (manuel utilisateur)

