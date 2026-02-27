# F76-051 - Player Item Action Context Object

## Contexte
`PlayerItemKnowledgeController` transporte le contexte `player + item` dans un tableau associe.

## Scope
- Introduire un objet de contexte immuable dedie.
- Remplacer le tableau par cet objet.
- Aucun changement comportemental.

## Avancement
- [x] Ajouter objet de contexte.
- [x] Brancher le controller.
- [x] Verifier phpstan/unit/integration.
