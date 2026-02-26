# DDD Migration Roadmap

## Goal
Passer progressivement vers une architecture DDD pragmatique sans casser le produit en cours de route.

## Non-goals
- Pas d event sourcing.
- Pas de big-bang rewrite.
- Pas de replatforming technique.

## Guiding principles
1. Vertical slices: migrer contexte par contexte.
2. Backward compatibility: garder les routes/API stables tant que possible.
3. Business first: isoler les regles metier avant de bouger le reste.
4. Test safety: chaque slice migree conserve/etend sa couverture.

## Target bounded contexts
- `Identity`: user auth, verification, password reset, registration.
- `Catalog`: items, translations, import, metadata sources.
- `Progression`: players, learned knowledge, stats, transfer/export/import.
- `Support`: contact messages, admin support workflows.

## Target layering (per context)
- `Domain`: modeles metier, value objects, invariants.
- `Application`: use-cases, orchestration, ports/interfaces.
- `Infrastructure`: Doctrine repositories, external services.
- `UI`: controllers HTTP, templates, API payload mappers.

## Migration phases

## Phase A - Foundations (low risk)
- Definir convention de dossiers par contexte (`src/<Context>/{Domain,Application,Infrastructure,UI}`).
- Introduire des interfaces/ports la ou les controllers parlent directement a Doctrine.
- Garder les entites Doctrine en place au debut (anti-corruption pragmatique).

## Phase B - Progression context first
- Extraire les use-cases de `Player`/`Knowledge` en Application services.
- Deplacer les regles ownership/learned/invariants dans Domain services.
- Adapter controllers API pour deleguer uniquement a Application.
- Maintenir les endpoints actuels (IDs opaques) sans rupture.

## Phase C - Catalog context
- Extraire logique import/translations/sources dans Application services dedies.
- Encapsuler regles metier item (`MISC rank`, `BOOK lists`) dans Domain.
- Limiter les controllers a validation input + mapping output.

## Phase D - Identity context
- Unifier policy de securite auth dans Domain/Application (`TemporaryLinkPolicy`, rate limits, signed links).
- Extraire use-cases registration/verify/resend/forgot/reset vers Application.

## Phase E - Support context
- Implementer support contact persistant (DB + backoffice), deja prevu au backlog.
- Integrer directement avec la structure DDD cible.

## Definition of done (migration)
- Chaque contexte migre expose:
  - use-cases applicatifs explicites,
  - regles metier testees en unit,
  - repos/integrations derriere interfaces,
  - controllers minces (thin controllers).
- Aucune introduction d event sourcing.

## Risks
- Risque: sur-engineering.
  - Mitigation: DDD lite, slices courtes, priorite au delivery.
- Risque: drift docs/code.
  - Mitigation: update `docs/backlog/current-focus.md` + tickets a chaque phase.
- Risque: regressions API.
  - Mitigation: tests fonctionnels API conserves et etendus.
