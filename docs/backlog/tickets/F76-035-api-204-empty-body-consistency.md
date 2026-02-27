# F76-035 - API 204 Empty Body Consistency

## Contexte
Certaines reponses `204` utilisaient `JsonResponse(null, 204)`, ce qui peut produire `{}` selon le contexte.

## Scope
- Remplacer ces retours par `Response(status: 204)` pour garantir un body vide.
- Adapter les imports/types si necessaire.

## Avancement
- [x] Corriger les retours 204 cibles.
- [x] Verifier phpstan + unit + integration.

## Criteres d acceptance
- Les endpoints `204` renvoient un body vide strict.
- Aucun changement de code metier.
