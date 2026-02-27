# F76-038 - Progression Item API Resolver

## Contexte
La resolution item + `404 item not found` est encore locale au `PlayerItemKnowledgeController`.

## Scope
- Introduire un composant `ProgressionItemApiResolver` qui retourne `ItemEntity|JsonResponse`.
- Migrer `PlayerItemKnowledgeController` sur ce composant.
- Couvrir le composant avec test unitaire.

## Avancement
- [x] Creer resolver.
- [x] Migrer le controller.
- [x] Ajouter tests + verifier phpstan/unit/integration.
