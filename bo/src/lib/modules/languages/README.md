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



La liste BO des traductions provient du registre de clés `symsvel_translation_keys` et montre les entrées manquantes ainsi que leur fallback, sans écrire ce fallback en base. `POST /apibo/languages/translations/keys` prépare une clé vide avec une description facultative. Le format de l'export TypeScript est imbriqué (à l'identique du générateur HP étudié).


## CSV production / import développement / publication locale

Le BO appelle les routes \`export/csv\`, \`import/csv\`, \`csv-capabilities\` et \`publish\` via \`boApi\`. Le serveur expose l'export dans tous les environnements, mais les routes de modification des sources sont restreintes à \`dev\` et à la connexion loopback. Le bouton d'import n'est présenté que lorsque \`csv-capabilities.can_import\` est vrai ; le bouton de publication des .ts exige également \`can_publish\`.

Le CSV contient les scopes, clés, descriptions, langues et valeurs de traduction. Une prévisualisation est obligatoire avant l'application d'un fichier ; les modifications non vides mettent à jour la BDD sans supprimer les entrées absentes. Le bouton local « Écrire le .ts » délègue l'opération de fichier au Tooling, qui refuse les destinations non gérées. Aucun rebuild Svelte n'est lancé par le BO et les pages existantes ne consomment pas encore automatiquement les fichiers générés.
