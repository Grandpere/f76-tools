# F76-039 - Progression Controller Read-Helper Cleanup

## Contexte
Les controllers progression repetent le pattern `resolveOrNotFound` + `instanceof JsonResponse`.

## Scope
- Extraire des helpers prives dans les controllers pour ce pattern.
- Aucun changement de logique metier ou de payload.

## Avancement
- [x] Nettoyer `PlayerController`.
- [x] Nettoyer `PlayerKnowledgeTransferController`.
- [x] Nettoyer `PlayerStatsController`.
- [x] Verifier phpstan/unit/integration.
