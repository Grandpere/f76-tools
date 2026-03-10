# Minerva Governance

## Objectif
Garantir que la rotation Minerva affichee dans l application reste fiable dans le temps sans dependance runtime a des sites tiers.

## Decision de reference
- Source runtime unique: base de donnees locale (`minerva_rotation`).
- Generation: service/commande interne deterministe.
- Verification externe: manuelle, uniquement pour controle.
- Override runtime: autorise uniquement en mode exceptionnel (incident ponctuel), puis retour au genere.

## Roles et responsabilites (incident)
- Detection (Support/Ops):
  - detecte un ecart (alerte monitoring, ticket utilisateur, controle manuel),
  - ouvre un ticket incident avec preuves minimales.
- Validation (Produit/Owner fonctionnel):
  - confirme qu il s agit d un incident reel (et non d un faux positif de source externe),
  - valide la gravite (`mineur` ou `majeur`) et la priorite.
- Execution (Ops/Admin):
  - applique la correction technique (refresh/regeneration/override),
  - trace chaque action dans le ticket incident.
- Cloture (Produit + Ops):
  - verifie le retour a un etat stable,
  - supprime les overrides temporaires devenus inutiles,
  - documente la cause racine et les actions preventives.

## Gravite et priorisation
- Mineur:
  - impact limite (ex: une seule fenetre incorrecte, pas de blocage user critique),
  - correction attendue dans la journee.
- Majeur:
  - impact fort (plusieurs fenetres fausses, incoherence visible large, confiance utilisateur degradee),
  - prise en charge immediate et suivi prioritaire.

## Rythme operationnel
- Verification legere: 1 fois par semaine (ou quotidien via `minerva-refresh-check-json`).
- Regeneration preventive: 1 fois par mois.
- Regeneration complete: debut d annee (N) sur N + 1.

## Procedure standard (mensuelle)
1. Dry-run de generation sur l annee en cours:
   - `docker compose -f compose.yaml exec -T app php bin/console app:minerva:generate-rotation --from=2026-01-01 --to=2026-12-31 --dry-run`
2. Verification manuelle:
   - comparer 2-3 fenetres proches avec les pages de reference (wiki/communautaire/Bethesda support si disponible),
   - verifier listes, dates, localisation.
3. Generation reelle:
   - `docker compose -f compose.yaml exec -T app php bin/console app:minerva:generate-rotation --from=2026-01-01 --to=2026-12-31`
4. Verification UI:
   - page publique: `/minerva-rotation`,
   - page admin: `/admin/minerva-rotation`.

## Procedure en cas d ecart constate
1. Ouvrir un ticket incident a partir du template:
   - `docs/ops/minerva-incident-template.md`.
2. Preconditions minimales avant override:
   - au moins 2 preuves externes coherentes (source A + source B),
   - plage impactee clairement identifiee (`from/to`, localisation, listCycle),
   - gravite qualifiee (`mineur`/`majeur`),
   - validation fonctionnelle explicite (Produit/Owner).
3. Executer la correction dans l ordre:
   - lancer un refresh/regeneration ciblee,
   - verifier le resultat,
   - appliquer un override manuel uniquement si l ecart persiste.
4. Contraintes override:
   - plage minimale necessaire uniquement,
   - duree temporaire,
   - justification obligatoire dans le ticket incident.
5. Cloture obligatoire:
   - regenerer la plage impactee apres normalisation,
   - supprimer l override temporaire,
   - verifier front + admin,
   - consigner cause racine et prevention.

## Journalisation minimale recommandee
Conserver une trace (ticket ou note interne) avec:
- date de regeneration,
- plage `from/to`,
- operateur,
- resultat (`deleted`/`inserted`),
- statut verification (OK/KO).
- id d override manuel (si applique) + date de suppression.

## Liens de reference (controle manuel)
- https://www.falloutbuilds.com/fo76/minerva/
- https://nukaknights.com/minerva-dates-inventory.html
- https://fallout.fandom.com/wiki/Minerva
