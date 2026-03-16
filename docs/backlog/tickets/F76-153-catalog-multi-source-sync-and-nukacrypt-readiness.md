# F76-153 - Catalog multi-source sync pipeline + Nukacrypt readiness

## Status
`Todo (blocked by F76-152)`

## Contexte
- Les imports Nukaknights/Fandom existent, mais la consolidation multi-sources doit etre stabilisee apres la refonte de modele F76-152.
- Nukacrypt doit etre ajoute avec un format d URL/reference specifique.

## Objectif
Ajouter un pipeline de sync multi-sources coherent (sans duplication instable) et preparer l integration Nukacrypt sur le nouveau modele metadata source.

## Scope
- Etendre `app:data:sync` pour orchestrer les sources supportees (Nukaknights + Fandom, puis Nukacrypt).
- Ajouter un import Nukacrypt initial (read-only):
- generation `external_ref` (form_id),
- construction `external_url` standard (`https://nukacrypt.com/FO76/w/latest/SeventySix.esm/{FORM_ID}`),
- metadata minimale utile.
- Mettre en place des rapports de sync:
- volumes par source,
- lignes ignorees/rejetees,
- collisions detectees.
- Documenter le runbook operateur pour lancer/valider les syncs.

## Hors scope
- Matching semantique avance cross-source par similarite de nom.
- UI admin de moderation source par source.

## Criteres d acceptance
- `app:data:sync` produit des sorties explicites par source sans casser les imports existants.
- Les enregistrements Nukacrypt sont persistes via metadata source avec URL/reference coherentes.
- Les erreurs de sync sont exploitables sans aller lire du code.

## Tests
- Unit:
- mapping Nukacrypt vers source metadata.
- Integration:
- sync multi-sources et rapport de sortie.
- Functional:
- non requis sur ce slice (commande + persistence).

## Risques / rollback
- Risque: duplication d items si matching encore naif.
- Mitigation: phase read-only + rapports de collision avant toute logique de merge metier.
- Rollback: desactiver le provider Nukacrypt dans la commande tout en conservant Nukaknights/Fandom.
