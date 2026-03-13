# Go-Live Security Checklist

Checklist pre-deploiement production (version courte, operationnelle).

## 0) Statut verification (2026-03-10)
- [x] Mecanismes de securite principaux implementes dans le repo (headers, CSP mode, trusted proxy/hosts, cache security_state, runbooks, smoke targets).
- [ ] Validation infra/runtime de production completee (secrets, HTTPS/LB, Redis partage, alerting, smoke pre-go-live).

## 1) Secrets et configuration (infra runtime)
- [ ] `APP_SECRET` fort, unique, configure hors repo.
- [ ] `OIDC_GOOGLE_CLIENT_SECRET` configure hors repo.
- [ ] `TURNSTILE_SECRET_KEY` configure hors repo.

## 2) Reverse proxy et HTTPS
- [x] Support app pour `TRUSTED_PROXIES` et `TRUSTED_HOSTS` present.
- [ ] `TRUSTED_PROXIES` configure avec les IP/LB de prod.
- [ ] `TRUSTED_HOSTS` configure avec les domaines autorises en prod.
- [ ] Application servie uniquement en HTTPS.
- [ ] `Strict-Transport-Security` observe en reponse (env prod + HTTPS).

## 3) CSP et headers
- [x] Subscriber headers de securite en place (`X-Frame-Options`, `nosniff`, `Referrer-Policy`, `Permissions-Policy`, `COOP`, `CORP`, `no-store` sur pages sensibles).
- [x] `SECURITY_CSP_MODE=off|report_only|enforce` supporte.
- [ ] `SECURITY_CSP_MODE=report_only` execute en pre-prod avec observation.
- [ ] Rapport erreurs CSP analyse pendant la phase d observation.
- [ ] Basculer en `SECURITY_CSP_MODE=enforce` apres validation.

## 4) Stockage securite (multi-instance)
- [x] Pool `cache.security_state` dedie et configure.
- [x] Support Redis en prod (`REDIS_URL`) configure.
- [ ] `REDIS_URL` pointe vers Redis partage de prod.
- [ ] Rate-limiting et sessions actives verifies sur plusieurs instances.

## 5) Auth et comptes
- [ ] Un compte admin nominal cree, test de connexion OK (env cible).
- [ ] Flows critiques verifies: login/logout, forgot/reset password, verify email, OIDC callback.
- [ ] Pages sensibles retournent bien `Cache-Control: no-store` (verification e2e sur env cible).

## 6) Observabilite et exploitation
- [x] Commandes et runbook ops disponibles (`audit-retention-run`, `smoke`, triage incidents).
- [ ] Alertes configurees pour anomalies auth (rate-limited, login failed, OIDC failures).
- [ ] Job retention logs actif (cron/scheduler en env cible).
- [ ] Runbook incident auth/minerva relu et valide par l equipe exploit.

## 7) Validation finale (avant go-live)
- [ ] `make phpstan`
- [ ] `make phpunit-unit`
- [ ] `make phpunit-integration`
- [ ] `make phpunit-functional`
- [ ] Smoke manuel front/admin (themes, locale, auth, admin actions sensibles).
