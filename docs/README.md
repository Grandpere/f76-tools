# Documentation Map

Ce dossier centralise la documentation de pilotage du projet.

## Structure
- `ai/`
  - `memory.md`: memoire de travail (decisions, erreurs a eviter, conventions).
  - `checklists.md`: checklists operationnelles (dev, review, release).
- `backlog/`
  - `readme.md`: vision backlog et conventions de tickets.
  - `current-focus.md`: priorite active (single source of truth court terme).
  - `roadmap-auth-players-items.md`: roadmap historique/detaillee.
  - `tickets/`: tickets unitaires au format markdown.
- `security/`
  - docs dediees aux decisions de securite et hardening.
- `ops/`
  - `ops-runbook.md`: commandes et procedures d exploitation.
- `sprints/`
  - suivi d execution par sprint.

## Regles simples
1. Toute decision importante doit etre tracee dans `ai/memory.md` ou `security/*.md`.
2. Toute nouvelle tache passe par un ticket dans `backlog/tickets/`.
3. `backlog/current-focus.md` doit rester court et a jour.
