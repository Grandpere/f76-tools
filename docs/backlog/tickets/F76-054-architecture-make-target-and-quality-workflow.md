# F76-054 - Architecture Make Target And Quality Workflow

## Contexte
Les regles d'architecture (PHPat via extension PHPStan) sont actives, mais il manque une commande explicite `make` pour les lancer en cible dediee.

## Scope
- Ajouter une cible `make architecture`.
- Documenter son usage dans les checklists de qualite.
- Aucun changement fonctionnel applicatif.

## Avancement
- [x] Ajouter cible `make architecture`.
- [x] Mettre a jour `docs/ai/checklists.md`.
- [x] Verifier la cible et la quality gate locale.
