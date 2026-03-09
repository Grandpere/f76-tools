# URL Hardening

## En place
- IDs publics opaques (player/item) au lieu d IDs incrementaux en API front.
- URLs temporaires signees pour liens sensibles.
- Query param `player` retire du dashboard (etat localStorage).
- `trusted_proxies`/`trusted_headers` explicites pour fiabiliser schema/IP derriere reverse proxy.

## Reste a faire
- Audit complet des actions sensibles pour forcer POST + CSRF si necessaire.
