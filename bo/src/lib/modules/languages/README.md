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



## Traductions administrables

La route `/languages/translations` est générée par le même synchroniseur Svelte que `/languages` : la source du module est sous `bo/src/routes/languages/translations/+page.svelte`. Le lien depuis la page Languages conserve les ACL existantes et les appels HTTP utilisent exclusivement `boApi`.

Le contrôle HTTP final est porté par `BoTranslationsController`. Le navigateur télécharge un fichier TypeScript à partir du JSON reçu de `GET /apibo/languages/translations/export`; il n'écrit ni dans les sources Svelte ni dans les répertoires du Back. La publication dans les projets et la reconstruction des composants nécessiteront une opération CLI/Tooling dédiée. Les textes d'interface de cette page ont des fallback français et anglais intégrés tant que la chaîne dynamique n'est pas raccordée.
