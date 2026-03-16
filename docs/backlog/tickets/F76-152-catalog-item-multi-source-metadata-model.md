# F76-152 - Catalog items multi-source: core item + source metadata

## Status
`In progress`

## Contexte
- Le catalogue consomme maintenant plusieurs sources heterogenes (Nukaknights, Fandom, et prochainement Nukacrypt).
- Le schema `item` actuel est adapte aux imports existants mais melange des donnees communes et des metadonnees propres a une source.
- Le risque est de casser le schema a chaque nouvelle source (colonnes ad-hoc, validation fragile, migrations repetitives).

## Objectif
Stabiliser le modele de donnees pour accueillir plusieurs sources sans toucher en permanence la table `item`:
- `item` garde uniquement le noyau metier commun,
- les metadonnees et provenance externes sont deplacees dans une table dediee par item et par source.

## Scope
- Definir un modele `ItemExternalSource` (ou equivalent) relie a `Item`:
- `item_id`, `provider`, `external_ref`, `external_url`, `metadata` (jsonb), `created_at`, `updated_at`.
- Contraintes:
- unique `(item_id, provider, external_ref)`,
- index `(provider, external_ref)`,
- index `(item_id, provider)`.
- Garder dans `item` uniquement les champs communs de domaine (nom, type, rang, statut apprentissage, etc.).
- Migrer les mappings d import:
- Nukaknights -> metadonnees source dediees,
- Fandom -> disponibilites/valeur/tags/wiki_url en metadata source,
- Nukacrypt (preparation) -> `external_ref=form_id` + URL derivee.
- Ajouter une couche applicative claire pour lire/mapper les metadonnees source sans fuite d infrastructure dans l UI.
- Conserver la compatibilite front/admin actuelle sur les pages deja en production locale.

## Hors scope
- Refactor UX majeur des pages front/admin.
- Import automatique Nukacrypt complet (ticket separe apres modele).
- Dedoublonnage metier inter-sources avance (matching intelligent sur noms).

## Decisions verrouillees
- `editor_id=0` doit etre normalise en `null`.
- `wiki_url` est conserve comme metadonnee source.
- `external_url` doit rester generique (pas limite a Fandom).
- `external_ref` doit porter l identifiant source stable (ex: `form_id` Nukacrypt).

## Plan d execution (slices)
1. Schema + migration: nouvelle table source metadata + indexes + FK.
2. Mapping import: hydrators Nukaknights/Fandom vers le nouveau modele.
3. Read model: services de lecture adaptes pour ne pas casser l existant.
4. Nettoyage: suppression progressive des colonnes source-specifiques devenues inutiles.
5. Validation + docs runbook import.

### Progress
- Done (slice 1): entite `ItemExternalSource` + relation Doctrine avec `Item` + migration SQL + backfill initial depuis `item`.
- Done (slice 2): import dual-write branche sur `item_external_source` (provider/ref/url/metadata), sans regression sur les champs existants.
- Remaining: read model explicite (si necessaire), suppression des colonnes legacy source-specifiques, et runbook final.

## Criteres d acceptance
- Ajouter une nouvelle source ne demande plus d alterer la table `item`.
- Les imports Nukaknights et Fandom passent via la nouvelle table sans regression visible.
- Les donnees deja exploitees en front/admin restent accessibles.
- Le modele permet de stocker plusieurs references externes pour un meme item.

## Tests
- Unit:
- mapping `ItemImportItemHydrator` pour Nukaknights/Fandom vers metadata source.
- normalisation `editor_id=0 -> null`.
- Integration:
- persistance/relecture des metadonnees source par provider.
- unicite `(item_id, provider, external_ref)`.
- Functional:
- pages front/admin critiques (legendary/minerva/progression) sans regression.

## Risques / rollback
- Risque: migration de donnees incomplete entre anciens champs et metadata source.
- Mitigation: migration en deux phases (write dual puis cleanup), avec verifications SQL.
- Rollback: conserver temporairement anciens champs tant que la lecture duale est active.
