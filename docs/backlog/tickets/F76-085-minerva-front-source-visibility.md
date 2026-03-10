# F76-085 - Minerva Front Source Visibility

## Contexte
Les overrides manuels sont visibles en admin, mais le front Minerva ne montre pas encore l origine des fenetres (`generated` vs `manual`).

## Scope
- Afficher la source sur:
  - fenetre courante,
  - prochaines fenetres,
  - table timeline front.
- Ajouter des cles de traduction dediees.
- Renforcer les tests unit/functional pour verifier la presence de la source dans le payload et le rendu.

## Criteres d acceptance
- Un utilisateur connecte voit explicitement la source de chaque fenetre Minerva sur `/minerva-rotation`.
- Le rendu reste stable quel que soit la langue (test base sur attribut `data-minerva-source`).

## Tests
- Unit: `MinervaRotationTimelineApplicationServiceTest` verifie le champ `source`.
- Functional: `MinervaRotationControllerTest` verifie des badges `data-minerva-source` manuel/genere.

## Risques / rollback
- Risque faible: regression d affichage Twig.
- Rollback: revert du commit ticket (pas d impact schema).

## Statut
- Done - 2026-03-10
