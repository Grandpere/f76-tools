# F76-099 - Admin Page Header Componentization

## Contexte
Les pages admin continuaient a dupliquer la structure complete du header (tools, kicker, titre, description, nav), meme apres factorisation partielle des tools.

## Scope
- Extraire un composant Twig partage pour le header complet des pages admin.
- Integrer ce composant sur les vues admin principales.
- Conserver la preservation des filtres/pagination via `localeHiddenFields`.

## Criteres d acceptance
- Un composant unique centralise la structure de header admin.
- Les 5 pages admin principales utilisent ce composant.
- Aucun changement fonctionnel sur navigation, locale et filtres persistants.

## Avancement
- [x] Composant cree: `templates/admin/_page_header.html.twig`.
- [x] Integrations effectuees:
  - [x] `templates/admin/users.html.twig`
  - [x] `templates/admin/item_translations.html.twig`
  - [x] `templates/admin/contact_messages.html.twig`
  - [x] `templates/admin/audit_logs.html.twig`
  - [x] `templates/admin/minerva_rotation.html.twig`

## Statut
- Done - 2026-02-28
