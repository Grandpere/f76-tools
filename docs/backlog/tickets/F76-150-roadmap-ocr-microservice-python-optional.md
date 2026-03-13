# F76-150 - Roadmap OCR microservice Python (optionnel, apres stabilisation)

## Status
`Todo (Optional)`

## Contexte
- Le pipeline OCR roadmap peut devenir difficile a maintenir en PHP seul (layout analysis, detection fine, scoring).
- Une extraction dans un service dedie Python (OpenCV/Paddle detector) peut faciliter les iterations si le besoin se confirme.

## Objectif
Preparer une evolution optionnelle vers un microservice OCR specialise, sans bloquer la livraison actuelle.

## Scope
- Definir contrat HTTP interne:
  - input: image + locale + profil preprocess/layout,
  - output: zones detectees + textes par zone + score global + metadata.
- Definir mode de fallback:
  - service indisponible -> pipeline locale actuelle.
- Definir runbook ops:
  - demarrage local docker,
  - healthcheck,
  - timeout/retry.
- Evaluer cout/benefice sur 10 images de reference (fr/en/de).

## Hors scope
- Mise en prod immediate du microservice.
- Dependances payantes.

## Criteres d acceptance
- Contrat technique fige et documente.
- Prototype benchmarkable localement sans casser le flux admin actuel.
- Decision go/no-go documentee (gain qualite vs complexite ops).

## Tests
- Unit: client HTTP + mapping resultat.
- Integration: timeout/fallback.
- Functional: N/A tant que non active par defaut.

## Risques / rollback
- Risque: complexite ops disproportionnee.
- Mitigation: ticket marque optionnel + activation progressive.
- Rollback: garder uniquement pipeline locale PHP.

