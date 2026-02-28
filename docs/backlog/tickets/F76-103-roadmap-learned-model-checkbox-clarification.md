# F76-103 - Roadmap Learned Model Checkbox Clarification

## Contexte
La roadmap conservait une case non cochee sur l'option `isLearned` bool alors que la decision est deja prise ("non retenu"), ce qui donnait un faux signal de travail restant.

## Scope
- Aligner l'etat de la case `isLearned` bool avec la decision deja documentee.

## Criteres d acceptance
- La section "Decider le modele exact learned" n'a plus de case ambiguë.
- La roadmap reflète correctement que la decision est prise.

## Avancement
- [x] Case `isLearned` bool cochee avec mention "non retenu".

## Statut
- Done - 2026-02-28
