# Security Audit - 2026-03-09

Contexte:
- audit "etat actuel" du projet en local (non deploye, pas de HTTPS local),
- objectif: connaitre le niveau de risque reel et prioriser les actions avant mise en production.

Perimetre verifie:
- authentication, reset password, verification email, OIDC Google,
- protections anti-abus (CSRF, captcha, rate limiting),
- routes/admin access control,
- appels HTTP externes (Turnstile, OIDC, OCR, Nuke codes),
- hygiene HTTP/session/cookies.

## Synthese executive
- Critique: 0
- Eleve: 1
- Moyen: 4
- Faible: 2

Conclusion:
- la base auth est solide (CSRF present, tokens random+hash, guard anti-bot, controle ROLE_ADMIN explicite),
- mais il reste des points de durcissement indispensables avant prod:
  - secret applicatif obligatoire,
  - headers HTTP de securite,
  - durcissement OIDC (discovery/issuer),
  - configuration cache/limiting en mode multi-instance.

## Correctifs rapides appliques (2026-03-09)
- `api_feed_controller`:
  - suppression de l'injection `innerHTML` basee sur payload,
  - construction DOM avec `textContent` (XSS hardening).
- `TurnstileVerifier`:
  - timeout explicite ajoute (`TURNSTILE_TIMEOUT_SECONDS`, defaut `5`).
- sanitize HTML front (`item_catalog` / `minerva_knowledge`):
  - normalisation du `src` avant controle de schema,
  - restriction des images a `/assets/icons/`.
- session/cookies:
  - policy explicite (`cookie_secure`, `cookie_httponly`, `cookie_samesite`).
- headers HTTP:
  - ajout de `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`,
  - `HSTS` active uniquement en `prod` + HTTPS.
- guard secret:
  - blocage explicite en `prod` si `APP_SECRET` vide (HTTP + console).

## Findings

### [HIGH] `APP_SECRET` vide par defaut dans `.env`
- Fichier: `.env`
- Constat: `APP_SECRET=` est vide dans le fichier versionne.
- Impact:
  - signature d'URL (UriSigner) faible/invalide si l'environnement herite de cette valeur,
  - affaiblit les mecanismes relies au secret framework.
- Recommandation:
  - imposer un secret non vide en toutes circonstances (check au boot),
  - conserver les vrais secrets uniquement hors repo (`.env.local` / variables runtime),
  - ajouter une validation explicite "fail fast" en env non-test si secret vide.

### [MEDIUM] Headers HTTP de securite absents
- Fichiers: `config/packages/` (pas de config headers dediee detectee)
- Constat: pas de politique explicite CSP/HSTS/X-Frame-Options/Referrer-Policy.
- Impact:
  - surface XSS/clickjacking plus large,
  - posture "browser hardening" insuffisante pour prod.
- Recommandation:
  - ajouter un subscriber/reverse-proxy policy avec au minimum:
    - `Content-Security-Policy` (progressive),
    - `X-Frame-Options: DENY` (ou CSP frame-ancestors),
    - `X-Content-Type-Options: nosniff`,
    - `Referrer-Policy`,
    - `Strict-Transport-Security` (uniquement en HTTPS prod).

### [MEDIUM] Durcissement cookies/session non explicite
- Fichier: `config/packages/framework.yaml`
- Constat: `session: true` sans options de cookie explicites.
- Impact:
  - dependance aux defaults/runtime proxy,
  - risque de config incoherente entre local/staging/prod (secure/samesite).
- Recommandation:
  - expliciter en prod:
    - `cookie_secure: auto|true` (true derriere TLS),
    - `cookie_httponly: true`,
    - `cookie_samesite: lax` (ou `strict` selon UX),
    - config `trusted_proxies`/`trusted_headers` si reverse proxy.

### [MEDIUM] OIDC Google: confiance implicite de la discovery
- Fichiers:
  - `src/Identity/Infrastructure/Oidc/GoogleOidcHttpClient.php`
  - `src/Identity/UI/Security/Controller/GoogleOidcController.php`
- Constat:
  - l'issuer vient de l'env, puis les endpoints discovery sont utilises tels quels,
  - pas de verification explicite stricte du host attendu.
- Impact:
  - en cas de mauvaise config env ou empoisonnement config, fuite possible de `client_secret` vers endpoint tiers.
- Recommandation:
  - forcer `issuer` HTTPS et whitelist host Google attendu (`accounts.google.com`),
  - verifier que `authorization/token/userinfo` sont HTTPS et host coherent avec la politique attendue.

### [MEDIUM] Limiting/etat session base sur cache local filesystem
- Fichiers:
  - `config/packages/cache.yaml`
  - `src/Identity/Infrastructure/Guard/AuthRequestThrottler.php`
  - `src/Identity/Infrastructure/Security/CacheActiveUserSessionRegistry.php`
- Constat: rate limiting et registre des sessions actives reposent sur `cache.app` (filesystem par defaut).
- Impact:
  - en prod multi-instance: limitation non globale (contournable entre pods/nodes),
  - vision partielle des sessions actives.
- Recommandation:
  - basculer `cache.app` (ou pools dedies) sur Redis partage en prod.

### [LOW] Insertion HTML non echappee dans un controller Stimulus secondaire
- Fichier: `assets/controllers/api_feed_controller.js`
- Constat: `innerHTML` construit depuis payload JSON sans escaping.
- Impact:
  - XSS possible si la source JSON devient un jour non fiable.
- Recommandation:
  - preferer `textContent` + creation de noeuds DOM,
  - ou sanitization stricte avant injection HTML.

### [LOW] Verification Turnstile sans timeout explicite
- Fichier: `src/Identity/Infrastructure/Guard/TurnstileVerifier.php`
- Constat: requete HTTP Cloudflare sans timeout configure localement.
- Impact:
  - latence anormale potentielle sur les formulaires lors d'incidents reseau.
- Recommandation:
  - configurer timeout court + retry policy maitrisee.

### [LOW] Sanitize HTML cote front: controle `src` ameliorable
- Fichiers:
  - `assets/controllers/item_catalog_controller.js`
  - `assets/controllers/minerva_knowledge_controller.js`
- Constat:
  - filtrage present, mais test de protocoles `src` est sensible a la casse/variantes.
- Impact:
  - risque faible dans le contexte actuel (sources connues), mais durcissement possible.
- Recommandation:
  - normaliser (`trim().toLowerCase()`) avant verification de schema,
  - limiter `src` a une whitelist de prefixes internes (`/assets/icons/`).

## Points positifs (deja solides)
- CSRF present sur login/logout et actions POST sensibles.
- Tokens email/reset:
  - generation `random_bytes`,
  - stockage hash (`sha256`) en DB,
  - TTL et cooldown.
- Guards anti-abus en entree (csrf + honeypot + turnstile + throttling).
- Controle role admin explicite sur routes admin.
- OIDC:
  - state+TTL,
  - PKCE,
  - email Google verifie requis.

## Priorisation recommandee
1. Bloquant avant prod:
   - secret obligatoire non vide,
   - headers HTTP de securite,
   - cookie/session policy explicite.
2. Durcissement identite:
   - OIDC discovery host/https guardrails.
3. Exploitation prod:
   - Redis partage pour cache de securite (rate-limit/sessions).
4. Hygiene defense-in-depth:
   - `api_feed_controller` sans `innerHTML`,
   - timeout explicite Turnstile,
   - sanitize `src` plus strict.

## Notes de contexte local
- Absence de HTTPS en local: acceptable pour dev.
- HSTS et cookies `secure=true` doivent etre actives en prod HTTPS, pas forcees en local.
