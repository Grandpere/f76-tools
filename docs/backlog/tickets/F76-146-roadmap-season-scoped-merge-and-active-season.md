# F76-146 - Roadmap saison: merge scope par saison + saison active

## Contexte
Les imports OCR roadmap ne devaient plus ecraser l historique complet lors d un nouveau merge. Le besoin est de modeliser explicitement la saison et de publier les events canoniques par saison uniquement.

## Scope
- Ajouter un modele `RoadmapSeason` persiste en base.
- Associer snapshot OCR et canonical events a une saison (nullable au depart).
- Extraire le numero de saison depuis le texte OCR (FR/EN/DE, tolerance au bruit).
- Modifier le merge locales:
- snapshots obligatoirement `APPROVED`,
- snapshots obligatoirement de meme saison,
- remplacement canonical scope a cette saison uniquement,
- activation automatique de la saison mergee.
- Adapter admin roadmap:
- affichage/filtre saison sur snapshots,
- contexte saison dans le bloc merge,
- timeline canonique filtree par saison active par defaut.
- Adapter le front calendrier pour ne lire que la saison active et afficher la saison en en-tete.
- Mettre a jour migration + runbook/README.

## Criteres d acceptance
- Un merge d une nouvelle saison n efface pas les evenements des saisons precedentes.
- Un merge multi-saisons est refuse avec message explicite.
- Une seule saison est active apres merge.
- Le calendrier front affiche uniquement la saison active.
- Le dry-run n ecrit rien.

## Tests
- Unit:
- extraction saison OCR FR/EN/DE + fallback null,
- merge KO si saison mismatch,
- merge OK scoped par saison,
- dry-run sans persistance.
- Integration:
- lecture canonical scoping par saison active,
- activation saison unique apres merge.
- Functional:
- admin snapshots: saison visible + filtres,
- merge refuse mix de saisons,
- merge valide active la bonne saison,
- front calendrier scope saison active.

## Risques / rollback
- Risque principal: snapshots sans saison detectee.
- Mitigation: warning explicite + merge bloque tant que la saison n est pas coherente.
- Rollback: revert migration + code season-aware puis reimport OCR.
