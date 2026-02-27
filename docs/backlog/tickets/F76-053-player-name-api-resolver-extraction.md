# F76-053 - Player Name API Resolver Extraction

## Contexte
`PlayerController` gere encore localement l'extraction/validation du nom avec un `string|JsonResponse`.

## Scope
- Introduire un composant `PlayerNameApiResolver`.
- Brancher `PlayerController` sur ce composant.
- Ajouter tests unitaires du nouveau resolver.

## Avancement
- [x] Ajouter `PlayerNameApiResolver`.
- [x] Migrer `PlayerController`.
- [x] Ajouter tests unitaires.
- [x] Verifier phpstan/unit/integration.
