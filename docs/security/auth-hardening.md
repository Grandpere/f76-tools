# Auth Hardening

## En place
- Verification email obligatoire.
- Forgot password avec reponse anti-enumeration.
- URLs temporaires signees (`verify-email`, `reset-password`).
- Rate-limit register/login/forgot/resend.
- Honeypot + Turnstile (optionnel via config).
- Journalisation d evenements auth sensibles (email hash + IP).
- Politique TTL/cooldown centralisee (`TemporaryLinkPolicy`).

## A surveiller
- Rotation secret APP_SECRET.
- Revue periodique seuils rate-limit.
- Monitoring des erreurs auth.
