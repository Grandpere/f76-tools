# F76-042 - Player Knowledge Transfer Import Flow Cleanup

## Contexte
`import` et `previewImport` dans `PlayerKnowledgeTransferController` partagent presque toute la logique (resolve player, decode payload, respond).

## Scope
- Extraire un helper prive pour mutualiser ce flow.
- Garder les routes et comportements strictement identiques.

## Avancement
- [x] Extraire helper `importLike`.
- [x] Brancher `import` et `previewImport`.
- [x] Verifier phpstan/unit/integration.
