# F76-030 - DDD Slice 20: Progression Test Hardening

## Contexte
Les nouveaux composants UI/API progression sont couverts, mais certains cas limites importants (notamment guard unauthenticated) ne sont pas encore verrouilles.

## Scope
- Completer les tests unitaires sur les resolvers avec cas `unauthenticated`.
- Verrouiller quelques cas limites de parsing deja observes (espaces, valeurs inattendues).
- Aucun changement fonctionnel applicatif.

## Avancement
- [x] Ajouter tests guard sur `ProgressionOwnedPlayerResolver`.
- [x] Ajouter tests guard sur `ProgressionOwnedPlayerReadResolver`.
- [x] Ajouter cas limites parser query type.

## Criteres d acceptance
- Couverture unitaire renforcee sur branches d erreur.
- Aucun impact runtime sur controllers/services.

## Tests
- Unit uniquement (pas de changement fonctionnel).
- Functional: campagne globale finale pour validation non regression.

## Risques / rollback
- Risque faible, modifications limitees aux tests.
