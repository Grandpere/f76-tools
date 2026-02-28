# F76-104 - Admin Pager Componentization

## Contexte
Le bloc de pagination admin (`translations-pager`) etait duplique dans plusieurs vues (`translations`, `contact messages`, `audit logs`).

## Scope
- Extraire un composant Twig partage pour le pager admin.
- Integrer ce composant dans les vues admin paginees.
- Conserver les URLs et le comportement de navigation.

## Criteres d acceptance
- Le HTML de pagination est centralise dans un composant unique.
- Les vues admin paginees utilisent ce composant.
- Aucun changement fonctionnel de navigation prev/next.

## Avancement
- [x] Composant cree: `templates/admin/_pager.html.twig`.
- [x] Integrations effectuees:
  - [x] `templates/admin/item_translations.html.twig`
  - [x] `templates/admin/contact_messages.html.twig`
  - [x] `templates/admin/audit_logs.html.twig`

## Statut
- Done - 2026-02-28
