# F76

Application Symfony 8 (Twig + Symfony UX) pour le suivi de progression Fallout 76:
- plans Minerva
- mods légendaires
- calendrier communautaire
- codes nucléaires
- backoffice admin (traductions, utilisateurs, logs, roadmap, Minerva)

Le projet est 100% dockerisé pour éviter toute installation locale (hors Docker/Compose).

## Sommaire
- [Stack](#stack)
- [Architecture](#architecture)
- [Prerequis](#prerequis)
- [Installation rapide](#installation-rapide)
- [Acces locaux](#acces-locaux)
- [Configuration (.env.local)](#configuration-envlocal)
- [Commandes utiles](#commandes-utiles)
- [Donnees metier (import/sync)](#donnees-metier-importsync)
- [Qualite et tests](#qualite-et-tests)
- [Exploitation et securite](#exploitation-et-securite)
- [Troubleshooting](#troubleshooting)
- [Documentation projet](#documentation-projet)

## Stack
- PHP 8.4+
- Symfony 8
- Twig + Stimulus + Symfony UX Turbo
- PostgreSQL 16
- Nginx (reverse proxy local)
- Mailpit (emails dev)
- Docker Compose + Makefile

## Architecture
Architecture pragmatique Symfony en migration progressive vers des frontières DDD:
- `src/Catalog`
- `src/Progression`
- `src/Identity`
- `src/Support`

Chaque feature est organisée autour de couches `Domain`, `Application`, `Infrastructure`, `UI`.

## Prerequis
- Docker
- Docker Compose v2
- GNU Make

Aucun PHP/Composer/PostgreSQL n'est requis sur ta machine.

## Installation rapide
1. Demarrer la stack:
```bash
make up
```
2. Initialiser la base:
```bash
make db-migrate
```
Si c'est un premier setup complet (reset DB):
```bash
make db-init
```
3. (Optionnel) Synchroniser et importer les donnees metier:
```bash
make data-sync
docker compose -f compose.yaml exec -T app php bin/console app:items:import data
```
4. Creer un utilisateur local:
```bash
docker compose -f compose.yaml exec -T app php bin/console app:user:create user@example.com --password='ChangeMe123!'
```
5. Promouvoir admin (si besoin backoffice):
```bash
docker compose -f compose.yaml exec -T app php bin/console app:user:promote-admin user@example.com
```

## Acces locaux
- App: [http://localhost:8000/en/](http://localhost:8000/en/)
- Mailpit: [http://localhost:8025](http://localhost:8025)
- PostgreSQL (expose pour IDE):
  - Host: `127.0.0.1`
  - Port: `5434`
  - DB: `app`
  - User: `app`
  - Password: `!ChangeMe!`

## Configuration `.env.local`
Ne jamais stocker de secrets dans `.env` versionné.

Exemple minimal:
```dotenv
APP_SECRET=change-me-local-secret

# Captcha Cloudflare Turnstile
TURNSTILE_SITE_KEY=
TURNSTILE_SECRET_KEY=

# Google OIDC
OIDC_GOOGLE_ENABLED=0
OIDC_GOOGLE_ISSUER=https://accounts.google.com
OIDC_GOOGLE_CLIENT_ID=
OIDC_GOOGLE_CLIENT_SECRET=

```

## Commandes utiles
### Conteneurs
```bash
make up
make down
make restart-app
make logs
make ps
make shell
```
`make restart-app` attend automatiquement que l'URL de login reponde avant de rendre la main.

### Base de donnees
```bash
make db-init
make db-migrate
make db-diff
make db-test-init
```

### Donnees metier
```bash
make data-sync
make nuke-codes-warmup
docker compose -f compose.yaml exec -T app php bin/console app:data:sync:fandom

make minerva-refresh-dry-run
make minerva-refresh-check
make minerva-refresh-check-json
make minerva-refresh-run
```

Roadmap saison (workflow recommande):
1. Backoffice roadmap: importer les 3 snapshots JSON (FR, EN, DE).
2. Verifier/corriger chaque snapshot, puis approuver les 3.
3. Merge FR/EN/DE (la saison fusionnee devient active automatiquement).

### Audit / retention
```bash
make audit-retention-dry-run
make audit-retention-run
```

### Smoke checks
```bash
make smoke-ops
make smoke-app
make smoke
```

## Donnees metier (import/sync)
Flux recommande:
1. Recuperer les JSON upstream:
```bash
make data-sync
```
2. Simuler l'import:
```bash
docker compose -f compose.yaml exec -T app php bin/console app:items:import data --dry-run
```
3. Import reel:
```bash
docker compose -f compose.yaml exec -T app php bin/console app:items:import data
```

Notes:
- L'import est idempotent (mise à jour + creation).
- Les traductions UI restent dans `translations/`.
- Les evenements roadmap canoniques sont scopes par saison (pas d'ecrasement global inter-saisons).
- La sync Fandom ecrit des fichiers par page dans `data/plan_recipes_pages/` (`recipes.json`, `plans_*.json`) + `index.json`.

## Qualite et tests
Commandes standard:
```bash
make phpstan
make phpunit-unit
make phpunit-integration
make php-cs-fixer-check
```

Suite fonctionnelle:
```bash
make phpunit-functional
```

Correction style:
```bash
make php-cs-fixer
```

## Exploitation et securite
Points importants:
- Les URLs front sont préfixées par locale (`/en/...`, `/fr/...`, `/de/...`).
- Les secrets vont dans `.env.local` (dev) ou variables d'environnement (prod).
- Config proxy/host/CSP à adapter en production.

Runbooks:
- Ops: `docs/ops/ops-runbook.md`
- Gouvernance Minerva: `docs/ops/minerva-governance.md`
- Securite: `docs/security/readme.md`
- Go-live checklist: `docs/security/go-live-checklist.md`

## Troubleshooting
### `could not translate host name "database"`
Tu as lancé une commande Symfony hors conteneur. Utilise :
```bash
docker compose -f compose.yaml exec -T app php bin/console ...
```

### `There is no existing directory at /var/www/html/var/log`
Problème de permissions/volume `var`. Relancer :
```bash
make restart-app
```

### Mail non recu
Verifier `MAILER_DSN` et consulter Mailpit: [http://localhost:8025](http://localhost:8025)

## Documentation projet
- Cartographie docs: `docs/README.md`
- Backlog: `docs/backlog/readme.md`
- Focus courant: `docs/backlog/current-focus.md`
- Tickets: `docs/backlog/tickets/`
- Memo agent: `docs/ai/memory.md`
- Checklists livraison: `docs/ai/checklists.md`

---

Licence: propriétaire (voir `composer.json`).
