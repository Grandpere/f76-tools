# F76-002 - Rotation Minerva

## Contexte
Besoin d afficher localisation, dates et listes Minerva avec etats temporels (actif/a venir/termine).

## Scope
- Modele rotation (`startsAt`, `endsAt`, `location`, `listCycle`).
- Service de calcul d etat temporel.
- UI dediee (bloc ou page) avec tri par date.

## Criteres d acceptance
- Les rotations sont visibles et ordonnees.
- L etat temporel est correct selon date courante.
- Timezone explicite et documentee.

## Tests
- Unit: calcul d etat temporel.
- Functional: rendu page et transitions de statut.

## Risques / rollback
- Risque: donnees incoherentes sur timezone. Mitigation: timezone unique + validation.
