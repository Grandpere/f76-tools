# F76-090 - Auth UX Finalization

## Contexte
Le parcours d'authentification est fonctionnel (inscription, verification email, mot de passe oublie/reset, contact) mais quelques details UX restent a harmoniser entre pages.

## Scope
- Uniformiser les metadonnees/front titles des pages auth.
- Harmoniser les liens de navigation secondaire entre ecrans auth (retour connexion, actions connexes).
- Verifier la coherence visuelle des formulaires auth avec les composants front.
- Completer/adapter les tests fonctionnels sur les points UX critiques.

## Criteres d acceptance
- Les pages auth front ont des titres homogenes et explicites.
- Chaque page auth propose au moins un chemin de retour clair vers la connexion.
- Les regressions UX sur reset/login/register/forgot/resend sont couvertes par les tests fonctionnels.

## Avancement
- [x] Prefix `F76 -` sur les titles des pages auth (`login/register/forgot/resend/contact/reset`).
- [x] Ajout du lien `retour connexion` sur la page reset password.
- [x] Test fonctionnel reset: presence du lien retour connexion.
- [ ] Revue finale de coherence des liens secondaires sur tout le flow auth.

## Statut
- In progress
