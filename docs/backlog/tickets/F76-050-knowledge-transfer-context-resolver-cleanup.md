# F76-050 - Knowledge Transfer Context Resolver Cleanup

## Contexte
`PlayerKnowledgeTransferController::importLike()` rassemble encore plusieurs etapes (resolve player + decode payload) avant branching mode.

## Scope
- Extraire un helper de contexte `player + payload` avec gestion d'erreur player.
- Reutiliser ce helper dans `importLike`.
- Aucun changement comportemental.

## Avancement
- [x] Extraire helper contexte import.
- [x] Integrer dans importLike.
- [x] Verifier phpstan/unit/integration.
