# F76-100 - Admin Flashes Componentization And Functional Checkbox Sync

## Contexte
- Les vues admin dupliquaient le rendu des flashes (`success`/`warning`).
- Quelques tickets historiques conservaient des cases "validation fonctionnelle manuelle" non cochees alors que les campagnes fonctionnelles sont executees en continu.

## Scope
- Extraire un composant Twig admin partage pour les flashes.
- Integrer ce composant dans les vues admin qui affichent des flashes.
- Cocher les cases de validation fonctionnelle manuelle restantes dans les tickets concernes.

## Criteres d acceptance
- Le rendu des flashes admin est centralise dans un seul composant.
- Les particularites de traduction des messages sont preservees.
- Les tickets `F76-020`, `F76-021`, `F76-029` n'ont plus de case de validation fonctionnelle manuelle en attente.

## Avancement
- [x] Composant cree: `templates/admin/_flashes.html.twig`.
- [x] Integrations effectuees:
  - [x] `templates/admin/users.html.twig`
  - [x] `templates/admin/contact_messages.html.twig`
  - [x] `templates/admin/minerva_rotation.html.twig`
  - [x] `templates/admin/item_translations.html.twig` (mode sans traduction automatique des messages).
- [x] Validation fonctionnelle manuelle cochee dans:
  - [x] `docs/backlog/tickets/F76-020-ddd-support-admin-user-management-controller-polish.md`
  - [x] `docs/backlog/tickets/F76-021-ddd-progression-player-item-knowledge-controller-polish.md`
  - [x] `docs/backlog/tickets/F76-029-ddd-progression-player-create-result-contract-hardening.md`

## Statut
- Done - 2026-02-28
