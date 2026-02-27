# F76-044 - Knowledge Transfer Import Mode Enum

## Contexte
Le helper `importLike()` utilise un booleen `preview` peu explicite.

## Scope
- Introduire un enum `PlayerKnowledgeImportMode`.
- Remplacer le booleen par cet enum dans le controller.
- Aucun changement comportemental.

## Avancement
- [x] Ajouter enum.
- [x] Adapter controller.
- [x] Verifier phpstan/unit/integration.
