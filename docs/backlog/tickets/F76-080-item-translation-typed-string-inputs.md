# F76-080 - Item Translation Typed String Inputs

## Contexte
La couche Application de traduction items recevait encore des valeurs `mixed` pour la locale cible et la recherche.

## Scope
- Taper les signatures `sanitizeTargetLocale` et `normalizeQuery` avec `?string`.
- Adapter `ItemTranslationController` pour fournir des chaines optionnelles explicites.
- Verifier phpstan/unit/integration.

## Avancement
- [x] Taper `ItemTranslationBackofficeApplicationService` (`?string`).
- [x] Adapter `ItemTranslationController` avec conversion explicite `mixed -> ?string`.
- [x] Verifier phpstan/unit/integration.
