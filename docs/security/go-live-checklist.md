# Go-Live Security Checklist

Checklist pre-deploiement production (version courte, operationnelle).

## 1) Secrets et configuration
- [ ] `APP_SECRET` fort, unique, configure hors repo.
- [ ] `OIDC_GOOGLE_CLIENT_SECRET` configure hors repo.
- [ ] `TURNSTILE_SECRET_KEY` configure hors repo.
- [ ] `ROADMAP_OCR_SPACE_API_KEY` configure hors repo (si OCR.space actif).

## 2) Reverse proxy et HTTPS
- [ ] `TRUSTED_PROXIES` configure avec les IP/LB de prod.
- [ ] `TRUSTED_HOSTS` configure avec les domaines autorises en prod.
- [ ] Application servie uniquement en HTTPS.
- [ ] `Strict-Transport-Security` observe en reponse (env prod + HTTPS).

## 3) CSP et headers
- [ ] `SECURITY_CSP_MODE=report_only` en pre-prod pour observation.
- [ ] Rapport erreurs CSP analyse pendant la phase d observation.
- [ ] Basculer en `SECURITY_CSP_MODE=enforce` apres validation.
- [ ] Verifier presence des headers: `X-Frame-Options`, `nosniff`, `Referrer-Policy`, `Permissions-Policy`, `COOP`, `CORP`.

## 4) Stockage securite (multi-instance)
- [ ] `REDIS_URL` pointe vers Redis partage de prod.
- [ ] Pool `cache.security_state` valide en prod.
- [ ] Rate-limiting et sessions actives verifies sur plusieurs instances.

## 5) Auth et comptes
- [ ] Un compte admin nominal cree, test de connexion OK.
- [ ] Flows critiques verifies: login/logout, forgot/reset password, verify email, OIDC callback.
- [ ] Pages sensibles retournent bien `Cache-Control: no-store`.

## 6) Observabilite et exploitation
- [ ] Alertes configurees pour anomalies auth (rate-limited, login failed, OIDC failures).
- [ ] Job retention logs actif (`audit-retention-run`).
- [ ] Runbook incident auth/minerva relu et valide.

## 7) Validation finale
- [ ] `make phpstan`
- [ ] `make phpunit-unit`
- [ ] `make phpunit-integration`
- [ ] `make phpunit-functional`
- [ ] Smoke manuel front/admin (themes, locale, auth, admin actions sensibles).
