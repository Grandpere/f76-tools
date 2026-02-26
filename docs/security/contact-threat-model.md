# Contact Threat Model (Light)

## Menaces
- Spam bot
- Flood de requetes
- Injection de contenu HTML/script
- Perte de messages en cas de panne SMTP

## Controles en place
- CSRF
- Honeypot
- Rate-limit
- Turnstile optionnel
- Sanitization cote rendu

## Gap principal
- Source de verite DB manquante (ticket F76-001).
