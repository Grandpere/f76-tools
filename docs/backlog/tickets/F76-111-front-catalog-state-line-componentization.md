# F76-111 - Front Catalog State Line Componentization

## Contexte
La ligne d'etat front (`catalog-state`, aria-live, target Stimulus `state`) etait dupliquee sur plusieurs pages.

## Scope
- Extraire un composant Twig partage pour la ligne d'etat.
- Integrer ce composant dans dashboard, minerva et progression.

## Criteres d acceptance
- Le markup de la ligne d'etat est centralise.
- Les trois pages utilisent le composant avec leur target Stimulus.
- Aucun changement fonctionnel de feedback et accessibilite.

## Avancement
- [x] Composant cree: `templates/_catalog_state_line.html.twig`.
- [x] Integrations effectuees:
  - [x] `templates/dashboard/index.html.twig`
  - [x] `templates/minerva/rotation.html.twig`
  - [x] `templates/progression/index.html.twig`

## Statut
- Done - 2026-02-28
