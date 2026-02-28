# F76-102 - Security Primary Actions Componentization

## Contexte
Les pages security dupliquaient le bloc d'actions primaires (`submit` + bouton secondaire `register`) sur plusieurs formulaires.

## Scope
- Extraire un composant Twig partage pour les actions primaires security.
- Integrer ce composant dans les formulaires concernes.
- Conserver les libelles/routages actuels.

## Criteres d acceptance
- Le bloc d'actions primaires est centralise dans un composant unique.
- Login, forgot, resend, contact, reset utilisent ce composant.
- Aucun changement fonctionnel de navigation.

## Avancement
- [x] Composant cree: `templates/security/_primary_actions.html.twig`.
- [x] Integrations effectuees:
  - [x] `templates/security/login.html.twig`
  - [x] `templates/security/forgot_password.html.twig`
  - [x] `templates/security/resend_verification.html.twig`
  - [x] `templates/security/contact.html.twig`
  - [x] `templates/security/reset_password.html.twig`

## Statut
- Done - 2026-02-28
