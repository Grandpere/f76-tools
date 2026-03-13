# F76-149 - Roadmap OCR image preprocessing pipeline (layout-first)

## Status
`Done`

## Contexte
- Les roadmaps saison sont des images chargees (fond illustre + colonnes + texte petit).
- Le moteur OCR brut sur image complete est instable, surtout sur les saisons anciennes.
- Le levier principal n est pas le provider seul, mais la qualite des zones envoyees au provider.

## Objectif
Introduire une pipeline "image first" avant OCR:
1) segmentation des zones utiles,
2) filtres visuels (grayscale/contrast/bw),
3) OCR par zone,
4) aggregation et scoring.

## Scope
- Ajouter un service applicatif dedie pre-ocr (sans nouvelle dependance):
  - entree: image + locale + profil preprocess/layout,
  - sortie: liste de crops utilises + texte OCR agrege + metriques.
- Implementer une segmentation minimale roadmap:
  - ignorer zone gauche decorative,
  - extraire bloc timeline droite,
  - extraire 4 sous-bandes mensuelles.
- Ajouter des profils preprocess:
  - `none`, `grayscale`, `bw`, `strong-bw`.
- Normaliser la sortie pour tous providers:
  - `lines`, `confidence`, `provider`, `attempts`, `zone_stats`.
- Exposer un mode debug admin (lecture seule) pour visualiser:
  - zones retenues,
  - texte OCR par zone,
  - score final.

## Hors scope
- Nouveau provider externe payant.
- Remplacement du parseur metier des evenements.
- Microservice Python (traite dans ticket optionnel separe).

## Criteres d acceptance
- Le pipeline fonctionne sans dependance supplementaire.
- Les commandes OCR existantes peuvent activer ce pipeline via options explicites.
- Le mode debug permet de comprendre pourquoi un OCR est accepte/rejete.
- Sur le dataset `data/roadmap_calendar_examples`, les metriques brutes sont plus stables qu OCR image complete (lignes date-like, month-like, non-empty).

## Tests
- Unit:
  - service preprocess (modes + erreurs),
  - segmentation zones (coordonnees valides),
  - aggregation OCR multi-zones.
- Integration:
  - commande benchmark OCR providers en mode raw-only avec pipeline active.
- Functional:
  - N/A pour le premier slice (debug console/admin lecture seule).

## Risques / rollback
- Risque: plus de bruit si les zones sont mal cadrees.
- Mitigation: profils explicites + mode debug + fallback rapide vers `none`.
- Rollback: desactiver pipeline preprocess via option/parametre, sans impact sur les snapshots existants.
