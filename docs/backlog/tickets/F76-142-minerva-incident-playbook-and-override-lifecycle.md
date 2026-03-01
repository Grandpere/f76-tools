# F76-142 - Minerva incident playbook and override lifecycle

## Contexte
Le projet dispose d un mecanisme d override Minerva en backoffice et d une gouvernance source clarifiee.  
Il manque encore un playbook metier strict pour gerer un incident reel de divergence (decision, execution, cloture).

## Objectif
- Formaliser la procedure metier complete pour un incident Minerva.
- Definir le cycle de vie d un override manuel (creation, suivi, sortie).
- Eviter les overrides persistants non justifies.

## Scope
- [ ] Definir les roles/responsabilites (qui detecte, qui valide, qui execute).
- [ ] Definir la gravite d incident et la priorisation (mineur/majeur).
- [ ] Definir les preconditions de creation d override (preuves minimales).
- [ ] Definir la checklist de cloture (regeneration, suppression override, verification post-incident).
- [ ] Ajouter un template de ticket incident Minerva dans `docs/ops/`.
- [ ] Mettre a jour `docs/ops/minerva-governance.md` et `docs/ops/ops-runbook.md`.

## Criteres d acceptance
- Le processus incident Minerva est documente de bout en bout.
- La regle "override temporaire uniquement" est operable et verifiable.
- Un nouvel incident peut etre gere sans ambiguite par une personne non auteur initial.

## Tests
- Unit: N/A (ticket gouvernance/process).
- Functional: N/A.

## Risques / rollback
- Risque: documentation trop theorique et non utilisable en exploitation.
- Mitigation: procedure orientee actions + exemples concrets + checklist executable.

