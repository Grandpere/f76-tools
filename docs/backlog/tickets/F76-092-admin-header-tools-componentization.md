# F76-092 - Admin Header Tools Componentization

## Contexte
Le bloc header admin (retour dashboard + selecteur de langue) etait duplique dans plusieurs vues admin, avec gestion des params de filtre re-implementee a chaque fois.

## Scope
- Extraire un composant Twig admin pour le header tools.
- Supporter les champs hidden du formulaire locale pour conserver les filtres/pagination.
- Integrer le composant sur les pages admin principales.

## Criteres d acceptance
- Le header tools admin est centralise dans un unique template partage.
- Les pages admin conservent les filtres/pagination apres changement de langue.
- Aucun changement fonctionnel sur les routes/labellisations du lien retour dashboard.

## Avancement
- [x] Composant partage cree: `templates/admin/_header_tools.html.twig`.
- [x] Integration sur:
  - [x] `templates/admin/users.html.twig`
  - [x] `templates/admin/item_translations.html.twig`
  - [x] `templates/admin/contact_messages.html.twig`
  - [x] `templates/admin/audit_logs.html.twig`
  - [x] `templates/admin/minerva_rotation.html.twig`

## Statut
- Done - 2026-02-28
