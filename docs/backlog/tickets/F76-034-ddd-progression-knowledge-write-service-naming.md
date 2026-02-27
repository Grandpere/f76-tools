# F76-034 - DDD Slice 24: Progression Knowledge Write Service Naming

## Contexte
Le service `PlayerKnowledgeApplicationService` porte un nom generique alors qu'il est desormais exclusivement dedie aux operations d'ecriture (mark/unmark learned).

## Scope
- Renommer le service en `PlayerKnowledgeWriteApplicationService`.
- Mettre a jour les references controllers/DI/tests.
- Aucun changement fonctionnel.

## Avancement
- [x] Renommer classe/fichier de service.
- [x] Mettre a jour les usages.
- [x] Valider tests/quality gate.

## Criteres d acceptance
- Le role write du service est explicite dans le code.
- Le runtime API reste identique.

## Tests
- Unit + integration.
- Functional: campagne globale finale.

## Risques / rollback
- Risque faible (renommage de symbole).
