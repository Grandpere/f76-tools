# F76-012 - DDD Slice 2: Catalog Context

## Contexte
Le contexte Catalog (items, import, traductions) est encore majoritairement porte par des controllers/services techniques. Il faut continuer la migration DDD apres Progression.

## Scope
- Extraire les use-cases backoffice traduction items dans `Catalog/Application`.
- Introduire des ports pour lecture/ecriture de catalogues de traduction.
- Amincir `ItemTranslationController` (validation HTTP + delegation).

## Avancement
- [x] Service applicatif `ItemTranslationBackofficeApplicationService` cree.
- [x] Ports `TranslationCatalogReaderInterface` / `TranslationCatalogWriterInterface` ajoutes.
- [x] `ItemTranslationController` delegue la logique metier au service applicatif.
- [x] Test unitaire service ajoute.
- [x] Resolution de contexte fichier import extraite dans `ItemImportFileContextResolver`.
- [x] `ImportItemsCommand` delegue la resolution de contexte au service Catalog.
- [x] Test unitaire `ItemImportFileContextResolver` ajoute.
- [x] Decouverte/lecture JSON import extraite dans `ItemImportJsonFileReader`.
- [x] `ImportItemsCommand` delegue lecture fichiers et parsing JSON au service Catalog.
- [x] Test unitaire `ItemImportJsonFileReader` ajoute.
- [x] Normalisation des valeurs import (`string/int/bool/payload`) extraite dans `ItemImportValueNormalizer`.
- [x] `ImportItemsCommand` delegue les conversions de donnees au service Catalog.
- [x] Test unitaire `ItemImportValueNormalizer` ajoute.

## Criteres d acceptance
- Controller admin traduction simplifie.
- Logique metier backoffice traduction centralisee en service applicatif.
- Tests existants conserves (functional) + ajout d un test unitaire service.

## Tests
- Unit: service applicatif translations.
- Functional: page/admin save/pagination inchanges.

## Risques / rollback
- Risque: rupture de pagination/filtre.
- Mitigation: garder les tests fonctionnels existants et contrats de payload inchanges.
