# F76-095 - Security Card Header Componentization

## Contexte
Le header visuel des pages security (`Fallout 76` + titre de page) etait duplique dans tous les templates du flow auth/contact.

## Scope
- Extraire un composant Twig partage pour le header de carte security.
- Integrer ce composant dans toutes les pages security.
- Ne pas modifier le contenu visible ni les traductions.

## Criteres d acceptance
- Le header security est centralise dans un composant unique.
- Toutes les pages security l'utilisent.
- Aucun changement fonctionnel du flow auth/contact.

## Avancement
- [x] Composant cree: `templates/security/_card_header.html.twig`.
- [x] Integrations effectuees:
  - [x] `templates/security/login.html.twig`
  - [x] `templates/security/register.html.twig`
  - [x] `templates/security/forgot_password.html.twig`
  - [x] `templates/security/resend_verification.html.twig`
  - [x] `templates/security/contact.html.twig`
  - [x] `templates/security/reset_password.html.twig`

## Statut
- Done - 2026-02-28
