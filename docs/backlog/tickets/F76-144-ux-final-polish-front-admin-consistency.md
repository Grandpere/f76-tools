# F76-144 - UX final polish front/admin consistency

## Contexte
La plupart des pages front/admin sont harmonisees, mais il reste une passe UX finale pour coherence visuelle, lisibilite et etats d interface.

## Objectif
- Finaliser la coherence UX globale (front joueur + backoffice).
- Ameliorer la lisibilite des actions sensibles et des etats systeme.
- Fermer les derniers ecarts de design/texte entre pages.

## Scope
- [ ] Faire un audit UI page par page (desktop + mobile) sur les parcours principaux.
- [x] Uniformiser les espaces/tailles/contrastes des blocs "etat" (success/warning/error/info).
- [ ] Harmoniser les textes d aide contextuels (ton, longueur, terminologie).
- [x] Renforcer les etats vides/erreur sur pages admin sensibles (Minerva, users, audit, contact).
- [x] Verifier la coherence navigation/header sur toutes les pages front principales.
- [x] Capturer les ajustements dans une checklist UX de reference dans `docs/ai/checklists.md`.

## Criteres d acceptance
- Les parcours critiques sont lisibles et coherents sans ambiguite d action.
- Les etats d erreur/succes sont explicites et homogènes.
- Aucun ecart majeur de style/navigation n est observe entre front et admin.

## Tests
- Unit: N/A.
- Functional: validations manuelles UX documentees (captures + checklist completee).

## Risques / rollback
- Risque: regressions visuelles non detectees sur mobile.
- Mitigation: validation explicite desktop/mobile et checklist de verification avant merge.
