# F76-147 - Upload roadmap OCR via backoffice

## Contexte
Le flux OCR roadmap reposait sur des chemins fichiers manuels (commande console + image deja presente dans le projet), peu adapte a la prod.

## Scope
- Ajouter un formulaire d upload image dans le backoffice roadmap.
- L upload declenche OCR + creation de snapshot en base.
- Stockage local des fichiers uploades sous `var/data/roadmap_uploads/`.
- Validation minimale: locale supportee + fichier image uniquement.

## Criteres d acceptance
- Un admin peut uploader une image roadmap (FR/EN/DE) depuis l ecran admin roadmap.
- L upload cree un snapshot OCR `draft` consultable immediatement.
- Aucun passage par Git/rebuild image n est necessaire pour injecter une nouvelle image.

## Tests
- Functional:
- upload admin avec OCR stub, snapshot cree en base.

## Risques / rollback
- Risque: espace disque local (uploads).
- Mitigation: dossier dedie, cleanup manuel possible.
- Rollback: retirer route/form upload, conserver workflow console existant.
