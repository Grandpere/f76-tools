# F76-011 - DDD Slice 1: Progression Context

## Contexte
Premier chantier concret de migration DDD sur la partie la plus centrale: players + knowledge.

## Scope
- Creer structure `src/Progression/{Domain,Application,Infrastructure,UI}`.
- Extraire use-cases:
  - list players,
  - create player,
  - set/unset learned,
  - stats/export/import knowledge.
- Basculer controllers API vers services applicatifs.
- Conserver payloads et routes existants.

## Avancement
- [x] Player: list/create/update/delete via `PlayerApplicationService`.
- [x] Knowledge: set/unset learned + ownership via `PlayerKnowledgeApplicationService`.
- [x] Knowledge transfer: export/import/preview via `PlayerKnowledgeTransferApplicationService`.
- [x] Knowledge stats: aggregation via `PlayerKnowledgeStatsApplicationService`.
- [ ] Completer les tests d integration cibles sur les ports progression (ticket de stabilisation suivant si besoin).

## Criteres d acceptance
- Controllers API minces (validation + mapping + delegation).
- Regles metier progression dans Domain/Application.
- Coverage tests maintenue (unit + functional existants verts).
- Aucune introduction d event sourcing.

## Tests
- Unit: use-cases progression + invariants ownership.
- Integration: repositories/ports progression.
- Functional: endpoints API progression inchanges cote contrat.

## Risques / rollback
- Risque: casse silencieuse du contrat API.
- Mitigation: snapshots/assertions payload sur tests fonctionnels.
