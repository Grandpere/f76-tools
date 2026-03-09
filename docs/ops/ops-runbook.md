# Ops Runbook

## Contexte
Ce document regroupe les commandes d exploitation courantes pour ce projet Symfony (Docker).

## Prerequis
- Stack Docker disponible.
- Execution depuis la racine du projet.

## Reverse proxy (prod)
- Configurer `TRUSTED_PROXIES` (ex: IP/LB internes) dans l'environnement de production.
- Ne pas conserver la valeur de fallback dev (`127.0.0.1,REMOTE_ADDR`) en production.
- Cette config est necessaire pour des IP client/scheme HTTPS fiables via `X-Forwarded-*`.
- CSP:
  - `SECURITY_CSP_MODE=report_only` par defaut.
  - Basculer en `SECURITY_CSP_MODE=enforce` apres periode d'observation.

## Commandes de base
- Demarrer la stack:
  - `make up`
- Arreter la stack:
  - `make down`
- Ouvrir un shell dans le conteneur app:
  - `make shell`

## Exemple production (compose)
- Fichier: `compose.prod.example.yaml`
- Objectif: base de depart sans secrets versionnes (variables requises via env).
- Lancement type:
  - `docker compose -f compose.prod.example.yaml up -d`

## Base de donnees
- Initialiser/reinitialiser la DB (drop + create + migrations):
  - `make db-init`
- Appliquer les migrations:
  - `make db-migrate`

## Import des items
- Sync des sources JSON (Nukaknights):
  - `make data-sync`
- Dry-run:
  - `docker compose -f compose.yaml exec -T app php bin/console app:items:import data --dry-run`
- Import reel:
  - `docker compose -f compose.yaml exec -T app php bin/console app:items:import data`

## Utilisateurs
- Creer un utilisateur:
  - `docker compose -f compose.yaml exec -T app php bin/console app:user:create user@example.com --password='secret123'`
- Promouvoir admin:
  - `docker compose -f compose.yaml exec -T app php bin/console app:user:promote-admin user@example.com`

## Audit logs
- Purge unifiee dry-run (auth + admin):
  - `make audit-retention-dry-run`
- Purge unifiee reelle (auth + admin):
  - `make audit-retention-run`
- Purge dediee admin audit dry-run:
  - `docker compose -f compose.yaml exec -T app php bin/console app:admin:audit:purge --days=90 --dry-run`
- Purge dediee auth audit dry-run:
  - `docker compose -f compose.yaml exec -T app php bin/console app:auth:audit:purge --days=90 --dry-run`
- Export CSV via UI:
  - Backoffice > Logs d audit > `Export CSV`
- Export CSV auth par utilisateur via UI:
  - Backoffice > Utilisateurs > Activite securite > `Export CSV`

## Planification cron
- Exemple de cron quotidien (02:15) depuis l hote Docker:
  - `15 2 * * * cd /chemin/vers/f76 && make audit-retention-run >> var/log/audit-retention.log 2>&1`
- Minerva: ordre recommande (02:25 check, 02:30 run):
  - `25 2 * * * cd /chemin/vers/f76 && make minerva-refresh-check >> var/log/minerva-refresh-check.log 2>&1`
  - `30 2 * * * cd /chemin/vers/f76 && make minerva-refresh-run >> var/log/minerva-refresh.log 2>&1`
- Variante exploitable machine (JSON + code retour):
  - `25 2 * * * cd /chemin/vers/f76 && make minerva-refresh-check-json >> var/log/minerva-refresh-check.json.log 2>&1`
- Recommandation:
  - lancer d abord quelques jours en `audit-retention-dry-run` pour verifier les volumes.
  - lancer quelques jours `minerva-refresh-dry-run` avant activation cron.

## Rotation Minerva
- Generation dry-run:
  - `docker compose -f compose.yaml exec -T app php bin/console app:minerva:generate-rotation --from=2026-01-01 --to=2026-12-31 --dry-run`
- Generation reelle:
  - `docker compose -f compose.yaml exec -T app php bin/console app:minerva:generate-rotation --from=2026-01-01 --to=2026-12-31`
- Refresh couverture dry-run (horizon glissant):
  - `make minerva-refresh-dry-run`
- Refresh couverture dry-run strict (exit code non-zero si trous):
  - `make minerva-refresh-check`
- Refresh couverture dry-run strict en JSON (monitoring/log parser):
  - `make minerva-refresh-check-json`
- Refresh couverture reelle (horizon glissant):
  - `make minerva-refresh-run`
- Override manuel exceptionnel (incident):
  - creer via `GET /admin/minerva-rotation` > section `Manual overrides`,
  - limiter la plage au strict necessaire,
  - apres resolution: regenerer la plage et supprimer l override.
- Backoffice admin:
  - `GET /admin/minerva-rotation` (formulaire de regeneration + visualisation timeline).
- Page publique authentifiee:
  - `GET /minerva-rotation`.
- Gouvernance source:
  - `docs/ops/minerva-governance.md`.

## Monitoring thresholds
- Minerva (signal machine):
  - Commande: `make minerva-refresh-check-json`.
  - Le JSON contient `status`, `missingWindows`, `covered`, `performed`.
  - Seuil `OK`:
    - `status=ok` et `missingWindows=0`.
  - Seuil `WARNING`:
    - `status=drift_detected` et `missingWindows` entre 1 et 2.
  - Seuil `CRITICAL`:
    - `status=drift_detected` et `missingWindows >= 3`.
  - Alerte automatique:
    - utiliser le code retour non-zero de `make minerva-refresh-check-json` (active avec `--fail-on-missing`) comme condition d alerte.
- Auth (signal smoke):
  - Commande: `make phpunit-functional-smoke`.
  - Seuil `OK`: retour 0.
  - Seuil `CRITICAL`: retour non-zero (regression sur auth/front/api/admin critiques).

## Smoke suite (rapide)
- Smoke ops:
  - `make smoke-ops`
- Smoke app (fonctionnel critique):
  - `make smoke-app`
- Smoke complet:
  - `make smoke`
- Couverture smoke app:
  - `tests/Functional/Security/LoginLogoutTest.php`
  - `tests/Functional/DashboardControllerTest.php`
  - `tests/Functional/ProgressionControllerTest.php`
  - `tests/Functional/MinervaRotationControllerTest.php`
  - `tests/Functional/Api/PlayerControllerTest.php`
  - `tests/Functional/Admin/UserManagementControllerTest.php`

## Triage incidents (Minerva/Auth)
- Incident Minerva (alerte drift):
  - 1. Lancer `make minerva-refresh-check-json` et relever `missingWindows`.
  - 2. Lancer `make minerva-refresh-run`.
  - 3. Relancer `make minerva-refresh-check-json` pour confirmer retour a `status=ok`.
  - 4. Si drift persiste, appliquer un override manuel minimal via `/admin/minerva-rotation`, puis planifier correction source et suppression override.
- Incident auth/smoke:
  - 1. Lancer `make smoke-app` pour reproduire.
  - 2. Identifier le premier test en echec (auth/front/api/admin) et corriger en priorite.
  - 3. Relancer `make smoke-app` puis `make phpunit-functional` complet.
  - 4. Si echec lie a volume de donnees test, relancer `make db-test-init` puis `make smoke-app`.

## Qualite
- Analyse statique:
  - `make phpstan`
- Tests unitaires:
  - `make phpunit-unit`
- Tests fonctionnels:
  - `make phpunit-functional`

## Notes
- Les tests fonctionnels et integration recreent la DB de test.
- En pratique, lancer `make phpstan` + `make phpunit-unit` avant les suites plus couteuses.
