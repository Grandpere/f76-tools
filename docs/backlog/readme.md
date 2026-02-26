# Backlog

## Objectif
Centraliser les taches, priorites, et dependances de delivery.

## Convention ticket
- Un fichier par ticket: `docs/backlog/tickets/F76-XXX-*.md`.
- Sections minimales:
  - Contexte
  - Scope
  - Critere d acceptation
  - Tests
  - Risques / rollback

## Priorisation
1. Securite et stabilite
2. Flux utilisateur critiques
3. Maintenabilite et productivite

## Architecture track
- Roadmap DDD: `docs/backlog/ddd-migration-roadmap.md`.
- Decision explicite: pas d event sourcing pour ce projet (complexite > gain attendu).
