# F76-094 - Security Anti-Bot Components

## Contexte
Les pages security qui utilisent la protection anti-bot dupliquaient les memes blocs Twig: champ honeypot, widget Turnstile et script Turnstile.

## Scope
- Extraire des composants Twig partages pour honeypot et Turnstile.
- Integrer ces composants dans les formulaires security concernes.
- Conserver le comportement existant (widget + script uniquement si `captchaSiteKey` est configuree).

## Criteres d acceptance
- Un composant dedie rend le honeypot.
- Un composant dedie rend le widget Turnstile.
- Un composant dedie rend le script Turnstile.
- Register/forgot/resend/contact utilisent les composants partages sans regression fonctionnelle.

## Avancement
- [x] Composants crees:
  - [x] `templates/security/_honeypot_field.html.twig`
  - [x] `templates/security/_turnstile.html.twig`
  - [x] `templates/security/_turnstile_script.html.twig`
- [x] Integrations effectuees:
  - [x] `templates/security/register.html.twig`
  - [x] `templates/security/forgot_password.html.twig`
  - [x] `templates/security/resend_verification.html.twig`
  - [x] `templates/security/contact.html.twig`

## Statut
- Done - 2026-02-28
