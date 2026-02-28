# F76-093 - Security Flashes Componentization

## Contexte
Les pages security dupliquaient le rendu des flash messages (`success`/`warning`) et, pour la page login, le rendu de l'erreur d'authentification.

## Scope
- Extraire un composant Twig partage pour le rendu des flashes security.
- Supporter l'erreur d'authentification login de facon optionnelle.
- Integrer le composant dans toutes les pages security.

## Criteres d acceptance
- Le rendu des flashes security est centralise dans un seul fichier.
- La page login continue d'afficher l'erreur d'authentification.
- Aucun changement fonctionnel sur les messages affiches.

## Avancement
- [x] Composant cree: `templates/security/_flashes.html.twig`.
- [x] Integrations effectuees:
  - [x] `templates/security/login.html.twig`
  - [x] `templates/security/register.html.twig`
  - [x] `templates/security/forgot_password.html.twig`
  - [x] `templates/security/resend_verification.html.twig`
  - [x] `templates/security/contact.html.twig`
  - [x] `templates/security/reset_password.html.twig`

## Statut
- Done - 2026-02-28
