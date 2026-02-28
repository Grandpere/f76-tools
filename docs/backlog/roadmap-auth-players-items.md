# Roadmap Auth + Players + Item Knowledge

## Objectif
Permettre a un utilisateur authentifie de:
- se connecter (avec inscription publique),
- creer ses personnages (`Player`),
- marquer pour chaque `Player` si un `Item` est appris ou non (`BOOK` Minerva et `MISC` Legendary Mods).

## Documentation ops
- Runbook exploitation: `docs/ops/ops-runbook.md`.

## Etat actuel (base)
- Import `Item` en place via `app:items:import`.
- Traductions generees en `translations/items.en.yaml` et `translations/items.de.yaml`.
- `BOOK` supporte plusieurs listes via `item_book_list`.
- Regle `rank` enforcee pour `MISC` (et interdite pour `BOOK`).

## Phase 1 - Authentification
- [x] Creer `UserEntity` (email unique, password hash, roles, isActive, createdAt/updatedAt).
- [x] Configurer `security.yaml` (provider Doctrine, firewall main, login form, logout).
- [x] Ajouter pages/routes:
  - [x] `GET/POST /login`
  - [x] `GET /logout`
- [x] Creer commande console:
  - [x] `app:user:create <email> --password=... [--role=ROLE_USER]`
  - [x] Option `--update-password`.

## Phase 2 - Players
- [x] Creer `PlayerEntity`:
  - [x] relation `ManyToOne User`
  - [x] `name` (non vide)
  - [x] `createdAt`, `updatedAt`
- [x] Ajouter contrainte d'unicite (au choix):
  - [x] unique `(user_id, name)` pour eviter doublons de nom par user.
- [x] Routes/controller:
  - [x] `GET /api/players`
  - [x] `POST /api/players`
  - [x] `GET /api/players/{id}`
  - [x] `PATCH /api/players/{id}` (optionnel)
  - [x] `DELETE /api/players/{id}` (optionnel)
- [x] Ajouter controle d'acces ownership (Voter ou check service).

## Phase 3 - Knowledge par Player
- [x] Creer `PlayerItemKnowledgeEntity`:
  - [x] relation `ManyToOne Player`
  - [x] relation `ManyToOne Item`
  - [x] modele presence/absence (une ligne = learned)
  - [x] `learnedAt`
  - [x] timestamps (`learnedAt`)
- [x] Contrainte unique `(player_id, item_id)`.
- [x] Index de perf:
  - [x] `(player_id, item_id)`
  - [x] `(item_id)` si necessaire pour stats.
- [x] API JSON:
  - [x] `PUT /api/players/{playerId}/items/{itemId}/learned` (set true)
  - [x] `DELETE /api/players/{playerId}/items/{itemId}/learned` (set false)
  - [x] `GET /api/players/{playerId}/items?type=...` (liste avec statut learned)
  - [x] `GET /api/players/{playerId}/knowledge/export` (export progression)
  - [x] `POST /api/players/{playerId}/knowledge/import` (import progression, mode replace/merge)
  - [x] `POST /api/players/{playerId}/knowledge/preview-import` (dry-run import + unknown items)

## Phase 4 - Front Twig + UX
- [x] Ecran catalogue items:
  - [x] filtres type (`MISC`/`BOOK`) + recherche texte (`q`)
  - [x] affichage split en 2 blocs: `Legendary Mods` (par rank) et `Minerva` (par liste)
  - [x] badge/rank/lists selon type
  - [x] statut appris pour player courant
- [x] Actions front:
  - [x] toggle learned via checkbox Stimulus + fetch API
  - [x] creation player via formulaire dashboard
  - [x] sync learned inter-listes pour un meme item Minerva (meme id)
  - [x] retour visuel (loading, succes, erreur)
- [x] Selection du player actif (localStorage, sans query param dedie).
- [x] Bloc de statistiques dashboard:
  - [x] endpoint `GET /api/players/{id}/stats`,
  - [x] KPI global/type + detail par rank/liste,
  - [x] rafraichissement live apres toggle learned.
- [x] Persistance UX locale par player (recherche, filtres source, accordions ouverts).
- [x] UI backup progression player:
  - [x] export JSON depuis dashboard,
  - [x] import JSON depuis dashboard (replace/merge).

## Phase 5 - Qualite et tests
- [x] Tests unitaires:
  - [x] service de toggle learned
  - [x] regles ownership
- [x] Tests fonctionnels:
  - [x] login/logout
  - [x] creation player
  - [x] marquage learned/unlearned
  - [x] interdiction acces cross-user
  - [x] rendu dashboard catalogue auth
  - [x] backoffice traductions items (acces + sauvegarde)
- [ ] Garder `make phpstan` et tests verts a chaque phase (discipline continue).

## Phase 6 - Backoffice traductions
- [x] Route backoffice `GET/POST /admin/translations/items`.
- [x] Filtre par locale (defaut `fr`) et recherche texte.
- [x] Edition des cles `item.misc.*` et `item.book.*` basee sur `items.en.yaml`.
- [x] Sauvegarde dans `translations/items.<locale>.yaml` via `TranslationCatalogWriter`.
- [x] Locale applicative activee via `?locale=` (en/de/fr), conservee en session.
- [x] Fallback traducteur vers `en` quand la locale cible n'est pas complete.
- [x] Textes UI localises via `translations/messages.{fr,en,de}.yaml` (Twig + messages JS).
- [x] Ajouter pagination UI si la grille devient trop lourde.

## Phase 7 - Comptes utilisateurs
- [x] Inscription publique `GET/POST /register` (email + mot de passe + confirmation + CSRF).
- [x] Backoffice utilisateurs `GET /admin/users` (ROLE_ADMIN):
  - [x] toggle actif,
  - [x] toggle role admin,
  - [x] generation lien de reset mot de passe (token temporaire).
- [x] Decision securite: pas de creation d utilisateur via backoffice admin (creation via inscription publique ou commandes console).
- [x] Protection acces `/admin/*` reservee ROLE_ADMIN.
- [x] Journal d audit minimal des actions admin sensibles (toggle actif, toggle admin, generation lien reset).
- [x] Page admin de consultation des logs d audit (`GET /admin/audit-logs`) avec filtres/pagination.
- [x] Cooldown serveur sur generation de lien reset (anti-spam).
- [x] Limite globale de generation de liens reset par admin sur fenetre courte.
- [x] Export CSV des logs d audit avec filtres.
- [x] Commande console de purge des logs d audit anciens.

## Phase 8 - Securite Auth (a planifier)
- [x] Verification d email obligatoire a l inscription:
  - [x] ajouter `isEmailVerified` sur `UserEntity`,
  - [x] generer un token de verification avec expiration (24h),
  - [x] bloquer la connexion tant que l email n est pas verifie,
  - [x] endpoint "renvoyer l email de verification" (rate-limite).
- [x] Flow public "mot de passe oublie":
  - [x] page `GET/POST /forgot-password` (saisie email),
  - [x] reponse generique anti-enumeration (ne pas reveler si email existe),
  - [x] generation + envoi token reset avec expiration,
  - [x] conservation du flow final `GET/POST /reset-password/{token}`.
- [x] Anti-bot / anti-abus:
  - [x] rate limit sur `/register`, `/login`, `/forgot-password`:
    - [x] `/register`,
    - [x] `/login`,
    - [x] `/forgot-password`,
    - [x] `/resend-verification`,
  - [x] honeypot formulaire sur register/forgot/resend (protection basique anti-bot),
  - [x] captcha (Turnstile) sur register/forgot/resend (actif si cles configurees),
  - [x] journalisation des tentatives sensibles.
- [x] Contact:
  - [x] page de contact (formulaire),
  - [x] anti-spam (honeypot/rate limit/captcha),
  - [x] option de livraison: email direct ou stockage DB + backoffice.
- [x] Hygiene URL / exposition des identifiants:
  - [x] reduire l usage de query params pour les actions sensibles (preferer POST + CSRF quand possible),
  - [x] eviter les identifiants previsibles dans les URLs publiques (envisager UUID/ULID ou slugs opaques selon contexte),
  - [x] signer les URLs temporaires sensibles (verification email, reset, liens d action),
  - [x] standardiser une politique de duree de vie et invalidation des liens temporaires.

## Phase 9 - Minerva (rotation, localisation, listes)
- [x] Definir le scope UI:
  - [x] option B retenue: page dediee "Minerva" (`/minerva-rotation`).
- [x] Definir le modele de donnees Minerva rotation:
  - [x] localisation (`location`),
  - [x] fenetre de disponibilite (`startsAt`, `endsAt`),
  - [x] cycle liste (`listCycle`).
- [x] Affichage front:
  - [x] etat "actif maintenant" base sur date courante,
  - [x] etat "a venir" / "termine" selon les dates,
  - [x] timeline ordonnee par date de debut.
- [x] Source et mise a jour des donnees:
  - [x] commande `app:minerva:generate-rotation --from --to`,
  - [x] backoffice admin de regeneration (`/admin/minerva-rotation`),
  - [x] timezone explicite et regle de calcul centralisee.

## Risques / Decisions a figer
- [x] Decider le modele exact learned:
  - [x] `isLearned` bool (historique simple, non retenu),
  - [x] ou presence/absence en table pivot.
- [x] Politique de suppression:
  - [x] supprimer un player supprime son knowledge (`ON DELETE CASCADE`).
- [x] Pagination catalogue pour eviter surcharge front:
  - [x] Decision actuelle: pas de pagination immediate (volume de donnees faible), a reevaluer si dataset augmente.

## Definition of Done (MVP)
- [x] Un admin/dev peut creer un user via commande.
- [x] Le user se connecte.
- [x] Le user cree au moins un player.
- [x] Le user marque learned/unlearned sur items.
- [x] Le statut est persiste et reaffiche correctement.
- [x] Les traductions FR peuvent etre ajoutees/maj sans re-import JSON.
