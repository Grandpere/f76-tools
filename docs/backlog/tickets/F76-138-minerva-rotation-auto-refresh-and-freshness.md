# F76-138 - Minerva rotation auto-refresh and freshness guard

## Contexte
La rotation Minerva est generee via commande et peut devenir obsolete si elle n est pas regeneree regulierement.
Le besoin produit est d eviter une timeline vide/incomplete sans intervention manuelle frequente.

## Scope
- Ajouter un service applicatif qui verifie la couverture de rotation sur une fenetre glissante (ex: aujourd hui -> +90 jours).
- Completer automatiquement les fenetres manquantes via le generateur existant.
- Exposer une commande console dediee idempotente (ex: `app:minerva:refresh-rotation`).
- Ajouter un indicateur de fraicheur en backoffice Minerva (date de derniere regeneration auto/manuelle).
- Mettre a jour runbook ops avec cadence recommandee (cron journalier).

## Hors scope
- Scraping de sources externes.
- Override manuel automatique.
- Affichage de la source technique cote front joueur.

## Criteres d acceptance
- Une execution de la commande garantit une couverture continue sur la fenetre cible.
- Deux executions consecutives n introduisent pas de doublons.
- Le backoffice affiche une information de fraicheur exploitable.
- Le comportement reste deterministe sur la base des regles existantes.

## Tests
- Unit:
  - detection des trous de couverture,
  - idempotence de refresh sur fenetre deja complete.
- Integration:
  - persistence correcte des fenetres regenerees sans doublons.
- Functional (manuel utilisateur):
  - verification backoffice de l indicateur de fraicheur apres refresh.

## Risques / rollback
- Risque: generation trop large en base.
- Mitigation: borne explicite de fenetre + commande idempotente.
- Rollback: conserver la commande existante `app:minerva:generate-rotation` comme fallback operatoire.
