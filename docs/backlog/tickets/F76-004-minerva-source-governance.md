# F76-004 - Minerva Source Governance

## Contexte
La rotation Minerva est generee de maniere deterministe en interne. Il faut cadrer la gouvernance de la source (verification periodique, seed, procedure en cas d ecart constate).

## Scope
- Definir une procedure de verification periodique de la timeline generee.
- Definir une source de reference documentaire (liens, captures, historique).
- Definir la reponse operationnelle en cas d ecart constate (sans override runtime pour l instant).
- Documenter le runbook de regeneration annuelle/mensuelle.

## Avancement
- [x] Procedure de verification periodique formalisee.
- [x] Sources de reference listees pour verification manuelle.
- [x] Procedure de gestion d ecart documentee.
- [x] Runbook de regeneration (mensuel/annuel) documente.
- [x] Decision explicite: pas d override runtime pour l instant.

## Criteres d acceptance
- Procedure de verification documentee (qui, quand, comment).
- Procedure de correction documentee (regeneration, validation manuelle).
- Decision explicite sur l usage (ou non) d un mecanisme d override.

## Tests
- N/A (ticket de gouvernance/doc).

## Risques / rollback
- Risque: divergence silencieuse entre jeu reel et rotation calculee.
- Mitigation: verification periodique et journalisation des regenerations.
