# F76-065 - Remove Redundant Owned Player API Resolver

## Contexte
Apres l'introduction de `PlayerOwnedContextResolver`, `ProgressionOwnedPlayerApiResolver` ne faisait plus qu'un simple relais.

## Scope
- Integrer la logique `not found` dans `PlayerOwnedContextResolver`.
- Supprimer `ProgressionOwnedPlayerApiResolver` et son test dedie.
- Adapter les tests unitaires qui construisent `PlayerOwnedContextResolver`.
- Verifier phpstan/unit/integration.

## Avancement
- [x] Deplacer la logique dans `PlayerOwnedContextResolver`.
- [x] Supprimer `ProgressionOwnedPlayerApiResolver` + test obsolete.
- [x] Adapter les tests unitaires associes.
- [x] Verifier phpstan/unit/integration.
