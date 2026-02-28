# F76-096 - Security Links Componentization

## Contexte
Les pages security dupliquaient la structure du bloc `auth-links`, avec risque de divergence visuelle/ordre des liens.

## Scope
- Extraire un composant Twig partage pour les liens secondaires des pages security.
- Integrer le composant dans toutes les pages security.
- Conserver les memes routes/libelles et le meme ordre de liens.

## Criteres d acceptance
- Le rendu `auth-links` est centralise dans un seul composant.
- Toutes les pages security utilisent ce composant.
- Aucun changement fonctionnel des destinations de navigation.

## Avancement
- [x] Composant cree: `templates/security/_links.html.twig`.
- [x] Integrations effectuees:
  - [x] `templates/security/login.html.twig`
  - [x] `templates/security/register.html.twig`
  - [x] `templates/security/forgot_password.html.twig`
  - [x] `templates/security/resend_verification.html.twig`
  - [x] `templates/security/contact.html.twig`
  - [x] `templates/security/reset_password.html.twig`

## Statut
- Done - 2026-02-28
