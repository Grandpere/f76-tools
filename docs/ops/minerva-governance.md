# Minerva Governance

## Objectif
Garantir que la rotation Minerva affichee dans l application reste fiable dans le temps sans dependance runtime a des sites tiers.

## Decision de reference
- Source runtime unique: base de donnees locale (`minerva_rotation`).
- Generation: service/commande interne deterministe.
- Verification externe: manuelle, uniquement pour controle.
- Override runtime: autorise uniquement en mode exceptionnel (incident ponctuel), puis retour au genere.

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
1. Confirmer l ecart sur au moins 2 sources externes.
2. Regenerer une plage cible (ex: 2 mois) et revérifier.
3. Si l ecart persiste:
   - ouvrir un ticket backlog "Minerva divergence",
   - documenter date/heure/source/impact,
   - creer un override manuel limite a la fenetre impactee.
4. Une fois l incident resolu:
   - regenerer la plage impactee,
   - supprimer les overrides devenus obsoletes.

## Journalisation minimale recommandee
Conserver une trace (ticket ou note interne) avec:
- date de regeneration,
- plage `from/to`,
- operateur,
- resultat (`deleted`/`inserted`),
- statut verification (OK/KO).

## Liens de reference (controle manuel)
- https://www.falloutbuilds.com/fo76/minerva/
- https://nukaknights.com/minerva-dates-inventory.html
- https://fallout.fandom.com/wiki/Minerva
