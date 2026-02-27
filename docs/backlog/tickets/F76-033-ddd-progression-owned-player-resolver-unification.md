# F76-033 - DDD Slice 23: Progression Owned Player Resolver Unification

## Contexte
Deux chemins existent encore pour resoudre le `player` possede: un resolver read-side et un resolver branche sur `PlayerKnowledgeApplicationService` (write-side).

## Scope
- Basculer les controllers progression restants sur `ProgressionOwnedPlayerReadResolver`.
- Supprimer le resolver write-side devenu inutile.
- Simplifier `PlayerKnowledgeApplicationService` en retirant la responsabilite de resolve player.

## Avancement
- [x] Migrer `PlayerItemKnowledgeController`.
- [x] Migrer `PlayerKnowledgeTransferController`.
- [x] Migrer `PlayerStatsController`.
- [x] Retirer resolver/interface obsoletes + tests associes.

## Criteres d acceptance
- Un seul chemin de resolution du player possede cote API progression.
- Aucun changement de comportement HTTP.

## Tests
- Unit + integration.
- Functional: campagne globale finale.

## Risques / rollback
- Risque faible de wiring DI, logique metier inchangee.
