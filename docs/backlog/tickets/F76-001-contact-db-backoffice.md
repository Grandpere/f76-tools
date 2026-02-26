# F76-001 - Contact DB + Backoffice

## Contexte
Le formulaire `/contact` envoie actuellement un email direct. Il faut une source de verite persistante et consultable en admin.

## Scope
- Creer entite/table `contact_message`.
- Persister chaque soumission contact.
- Ajouter vue admin liste + filtre statut.
- Ajouter action de changement de statut (`new`, `in_progress`, `closed`).

## Avancement
- [x] Entite/table `contact_message` + migration Postgres.
- [x] Persistance des soumissions `/contact` via service applicatif.
- [x] Vue admin liste + filtre statut.
- [x] Action changement de statut.

## Criteres d acceptance
- Une soumission contact cree une ligne DB.
- Un admin voit les messages et peut changer le statut.
- Pas d exposition d infos sensibles en front public.

## Tests
- Unit: service de creation message.
- Functional: submit contact + lecture admin + changement statut.

## Risques / rollback
- Risque: croissance table. Mitigation: pagination + purge/archive later.
