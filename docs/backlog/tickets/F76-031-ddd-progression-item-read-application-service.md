# F76-031 - DDD Slice 21: Progression Item Read Application Service

## Contexte
La lecture d'item par `publicId` est encore exposee via `PlayerKnowledgeApplicationService`, qui est oriente ecriture (mark/unmark learned). Cela melange les responsabilites.

## Scope
- Introduire un service applicatif de lecture `ItemReadApplicationService`.
- Introduire le contrat repository associe pour la lecture d'item.
- Brancher `PlayerItemKnowledgeController` sur ce service read.
- Retirer la lecture d'item de `PlayerKnowledgeApplicationService`.

## Avancement
- [x] Ajouter contrat `ItemReadRepository`.
- [x] Ajouter `ItemReadApplicationService`.
- [x] Mettre a jour repository + wiring controller.
- [x] Ajouter/adapter tests unitaires.

## Criteres d acceptance
- Responsabilites read/write separees pour les items en progression.
- Aucun changement de comportement HTTP/API.

## Tests
- Unit + integration.
- Functional: campagne globale finale.

## Risques / rollback
- Risque faible, refacto de wiring sans changement de logique metier.
