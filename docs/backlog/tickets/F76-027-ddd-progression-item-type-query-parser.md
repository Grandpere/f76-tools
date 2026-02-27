# F76-027 - DDD Slice 17: Progression Item Type Query Parser

## Contexte
`PlayerItemKnowledgeController` contient encore du parsing inline du parametre query `type`.

## Scope
- Extraire le parsing `type` vers un composant UI dedie.
- Brancher `PlayerItemKnowledgeController` sur ce composant.
- Ajouter test unitaire du parser.

## Avancement
- [x] Extraire `ProgressionItemTypeQueryParser`.
- [x] Brancher `PlayerItemKnowledgeController`.
- [x] Ajouter tests unitaires.
- [x] Validation fonctionnelle manuelle (faite).

## Criteres d acceptance
- Comportement identique (`null` sans type, `false` sur type invalide, `ItemTypeEnum` valide).

## Tests
- Unit: parser query type.
- Functional: `PlayerItemKnowledgeControllerTest`.

## Risques / rollback
- Risque: difference de gestion des espaces/casse.
- Mitigation: reproduire exactement la logique actuelle + tests unitaires.
