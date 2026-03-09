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
- [x] Harmoniser les textes d aide contextuels (ton, longueur, terminologie).
- [x] Renforcer les etats vides/erreur sur pages admin sensibles (Minerva, users, audit, contact).
- [x] Verifier la coherence navigation/header sur toutes les pages front principales.
- [x] Capturer les ajustements dans une checklist UX de reference dans `docs/ai/checklists.md`.

## Matrice de validation UI (manuel)

Front (desktop + mobile):
- [ ] `/fr/` (Mods legendaires): header/nav, filtres, etats vides, lisibilite des cartes.
- [ ] `/fr/minerva`: blocs info, filtres, pagination, cohérence des espaces.
- [ ] `/fr/progression`: blocs Personnage/Backup, import/export, messages aide.
- [ ] `/fr/roadmap-calendar`: filtres + timeline + etats vides.
- [ ] `/fr/nuke-codes`: hero, validite, etat cache, lisibilite.
- [ ] `/fr/account-security`: sections activite/sessions, badges et actions.

Admin (desktop + mobile):
- [ ] `/fr/admin/users`: filtres, tableau dense, badges, actions et etats vides.
- [ ] `/fr/admin/users/{id}/auth-events`: filtres, badges niveau, export, etat vide.
- [ ] `/fr/admin/translations`: sections `misc/book`, références EN/DE, edition.
- [ ] `/fr/admin/minerva-rotation`: cartes d action, gouvernance/info, tables et vides.
- [ ] `/fr/admin/roadmap-snapshots`: snapshots, review, canonical, aides info.
- [ ] `/fr/admin/contact-messages`: filtre/statut/actions, etat vide.
- [ ] `/fr/admin/audit-logs`: filtres/export/context JSON, etat vide.

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
