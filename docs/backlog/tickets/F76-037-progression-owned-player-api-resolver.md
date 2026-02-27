# F76-037 - Progression Owned Player API Resolver

## Contexte
Les controllers progression repetent la meme sequence:
- resolve player owned,
- si absent => `playerNotFound()`.

## Scope
- Introduire un composant UI/API unique qui renvoie `PlayerEntity|JsonResponse`.
- Brancher les controllers progression sur ce composant.
- Ajouter test unitaire du nouveau composant.

## Avancement
- [x] Creer resolver API dedie.
- [x] Migrer les controllers progression cibles.
- [x] Ajouter tests + verifier phpstan/unit/integration.
