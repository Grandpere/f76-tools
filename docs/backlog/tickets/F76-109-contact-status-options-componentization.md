# F76-109 - Contact Status Options Componentization

## Contexte
La liste des options de statut contact etait dupliquee dans la page admin des messages de contact:
- filtre global,
- formulaire de mise a jour par ligne.

## Scope
- Extraire un composant Twig partage pour les options de statut contact.
- Integrer ce composant sur les deux emplacements.

## Criteres d acceptance
- Les options de statut contact sont centralisees dans un seul template.
- Le filtre status et la mise a jour par ligne reutilisent ce template.
- Aucun changement fonctionnel de mise a jour/filtrage.

## Avancement
- [x] Composant cree: `templates/admin/_contact_status_options.html.twig`.
- [x] Integrations effectuees:
  - [x] filtre status dans `templates/admin/contact_messages.html.twig`
  - [x] select status du formulaire ligne dans `templates/admin/contact_messages.html.twig`

## Statut
- Done - 2026-02-28
