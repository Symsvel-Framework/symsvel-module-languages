# Languages, interface BO

Le module fournit `/languages` à travers le relais de routes
construit par Tooling, et exporte `boNavigation` pour le menu
du BO. La sonde d'autorisation est `GET /apibo/languages` avec
`languages.list`.

L'implémentation Svelte utilise les composants partagés
`DataTable`, `Button`, `Alert`. La logique d'affichage
et les libellés FR/EN sont dans `presentation.ts`.
Les mutations utilisent `boApi`, sans token géré
directement par la page. Le contrôle définitif des droits et
de la langue principale reste côté API PHP.
