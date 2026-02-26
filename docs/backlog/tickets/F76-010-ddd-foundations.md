# F76-010 - DDD Foundations (No Event Sourcing)

## Contexte
Le projet a grandi et doit rester maintenable. On veut migrer vers DDD progressivement, sans complexite inutile.

## Scope
- Definir conventions de structure par contexte (`Domain/Application/Infrastructure/UI`).
- Mettre en place la migration de base sur un premier slice (`Progression`).
- Conserver compatibilite des routes/API existantes.
- Exclure explicitement l event sourcing.

## Criteres d acceptance
- Roadmap DDD ecrite et validee.
- `current-focus` aligne sur la migration.
- Ticket d execution du premier slice cree.
- Regle \"no event sourcing\" documentee.

## Tests
- N/A (ticket de planification/architecture).

## Risques / rollback
- Risque: plan trop ambitieux.
- Mitigation: migration incrementale par slices et objectifs sprint courts.
