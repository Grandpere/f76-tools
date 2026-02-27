# F76-052 - Knowledge Transfer Import Context Object

## Contexte
`PlayerKnowledgeTransferController` transporte `player + payload` dans un tableau associatif.

## Scope
- Introduire un objet de contexte immuable pour l'import.
- Remplacer le tableau dans le controller.
- Aucun changement de comportement.

## Avancement
- [x] Ajouter objet de contexte.
- [x] Brancher le controller.
- [x] Verifier phpstan/unit/integration.
