# Languages

Documentation développeur.

## Périmètre du portage depuis XCore (16/11/2026)

Le module est **natif obligatoire**. Le catalogue de 117 langues
nationales officielles provient du référentiel XCore
`src/Core/Languages/Resources/languages.json` (codes stables et noms
natifs), copié dans `back/resources/languages.json`. Une migration
Doctrine Schema crée `symsvel_languages` ; une seconde migration DML
sème les 117 lignes, toutes avec `active=false` et
`pre_open=false`. Aucun accès réseau n'est effectué pour établir
ou actualiser ce référentiel, et aucun pays n'est requis.

La version XCore embarquait environ 300 Ko de traductions CLDR dans
une migration SQL spécifique. SymSvel évite cette table redondante :
les noms dans la langue demandée sont obtenus par PHP Intl (CLDR de
l'environnement) et retombent sur le libellé natif en cas d'absence.
Le tri se fait sur le libellé réellement présenté. Cette différence
d'implémentation préserve le résultat visible en privilégiant le
mécanisme natif existant. Des traductions métier spécifiques feront
l'objet d'un chantier i18n indépendant.

Contrats fonctionnels XCore conservés : langue principale du site initialisée
par le flux local à l'étape où le catalogue est disponible, et non désactivable ensuite ; anglais ou français
ne sont pas imposés comme langue principale ; liste publique limitée
à la langue principale lorsque le multilinguisme est inactif ; les
langues en `pre_open` apparaissent uniquement côté BO.

L'état de validation du module natif exige la compilation de ses
migrations par Tooling, la CI et un CT initial multibase XT.

## Services et API du module (16/11/2026)

`LanguageCatalog` possède les règles de visibilité, le tri localisé
`Collator`, le repli sur les noms natifs et la protection de la langue
principale. `LanguageLocaleResolver` reconnaît `translation_lang`
(le champ déjà envoyé par les clients SymSvel), `locale`, `ui_lang`
et en dernier recours `Accept-Language`.

- `GET /apiopen/languages` et `GET /api/languages` : langues
  actives seulement, ramenées à la langue principale si le
  multilinguisme est désactivé.
- `GET /apibo/languages` : catalogue complet, ACL `languages.list`.
- `GET /apibo/languages/available` : actives ou préouvertes, même ACL.
- `PATCH /apibo/languages/{code}` : modification atomique des booléens
  `active` et `pre_open`, ACL `languages.manage`, rejet de la
  désactivation de la langue principale.
- `GET /setup/languages` : liste en noms natifs uniquement depuis
  loopback et pendant `database_ready`, sans exposer le setup après
  finalisation.
- `symsvel:languages:bootstrap:stdin` : commandée exclusivement par le
  Setup local ; accepte `{"language":"fr"}`, active la langue principale
  et refuse un changement de langue une fois configurée.

Les clés Settings sont `languages.primary_language` (bloquante,
renseignée par le Setup) et `languages.multilingual` (0/1, défaut
logique désactivé). Settings fournit la persistance ; aucune table de
paramètres n'est créée par Languages. Le BO ne peut pas modifier la
langue principale : ce verrou doit être confirmé par les tests
d'intégration. L'authentification BO est déléguée à `BoAccessGate`,
sans parser ni valider à nouveau les tokens dans Languages.

## Interface BO et contrôle d'accès (16/11/2026)

Le module déclare `boNavigation`, une route `/languages`
avec ACL `languages.list` et une sonde `/apibo/languages`.
La page Svelte utilise les composants `DataTable`, `Button`
et `Alert` de l'hôte, sans importer `AppShell` XCore.
Recherche, tri, pagination et messages FR/EN sont portés par
`presentation.ts`. Les actions de statut passent par `boApi`,
avec verrou côté UI pour la désactivation de la langue principale ;
la validation réelle demeure dans le service PHP. Le workflow QA
assemble le BO hôte et exécute les contrôles Svelte/TypeScript,
la compilation et Vitest. Le module est conçu pour fonctionner
sans serveur Node de production.

## Protection de la langue principale dans Settings (16/11/2026)

La définition `languages.primary_language` utilise `editable:false`
du module Settings : GET affiche la valeur, mais les requêtes directes
`PUT /apibo/settings/languages.primary_language` reçoivent un refus.
Seul `symsvel:languages:bootstrap:stdin` configure cette clé depuis
le Setup local. Une seconde initialisation avec un code différent
échoue en `DomainException` ; aucune mutation silencieuse de la
langue principale n'est possible.

## Tests de règles métier (16/11/2026)

`LanguageCatalogTest` valide la configuration unique de langue
principale, l'idempotence d'une reprise du Setup, les refus de
réaffectation/désactivation, les trois états (active, préouverture,
inactive), l'effet de `languages.multilingual`, le référentiel XCore
de 117 codes sans doublons et la priorité de `translation_lang`
sur la langue d'interface. Les tests utilisent Doctrine ORM/DBAL et
SQLite en mémoire ; il faudra exécuter les vraies migrations compilées
en CT PostgreSQL/MySQL/MariaDB/SQLite. Le workflow CI inclut désormais
le lint PHP du module et de ses migrations.

### Correction du test de tri (16/11/2026)

Le test d'intégration n'impose plus à tort l'ordre ISO lorsque
`LanguageCatalog` trie les langues selon leur **libellé traduit**.
La vérification d'appartenance trie une copie des codes avant
comparaison. La règle métier de tri XCore reste intacte.

## Langue des comptes Admins / Users (17/11/2026)

Les modules Admins et Users enregistrent la langue de leur compte dans leur propre colonne `language` (code ISO alphabétique normalisé de 2 à 4 lettres, sans clé étrangère). Languages reste propriétaire de l'éligibilité et expose `LanguageCatalog::activeForAccounts()` et `resolveAccountLanguage()`.

- Une seule langue `active=true` : la langue principale du site est imposée, même si une autre valeur est envoyée par un client ; aucune liste de sélection ne doit être affichée.
- Plusieurs langues actives : seuls leurs codes ISO sont acceptés ; si aucune préférence n'est donnée, la langue principale est utilisée.
- `pre_open=true` ne rend **pas** une langue sélectionnable pour les comptes tant qu'elle n'est pas également active.
- `GET /apiopen/languages/account-options` fournit les codes actifs pour l'inscription App, indépendamment du mode multilingue de l'interface.
- `GET /apibo/languages/account-options` renvoie les choix actifs aux acteurs BO autorisés à consulter Languages ou à créer/modifier Admins ou Users. Il reste protégé par `BoAccessGate`.
- Les valeurs nulles d'anciens comptes signifient « pas de préférence enregistrée » et sont interprétées côté consommateur via le repli du site ; elles ne créent aucune association SQL.

**Séparation au Setup :** la langue du premier administrateur est déduite de la langue du navigateur sans requêter le catalogue avant la création des données. Ce code individuel ne doit pas être assimilé à la langue principale du site. L'initialisation globale reste à vérifier au CT ; elle ne doit pas être implicitement déclenchée par un écran de création de compte.

Tests : le catalogue est vérifié pour une langue active, deux langues actives, une langue préouverte et le rejet des codes inconnus. CI et CT multi-SGBD restent nécessaires.

## Initialisation automatique et repli des traductions (17/11/2026)

À la phase 2 du Setup, après la compilation/exécution des migrations, la préférence `Accept-Language` du navigateur est réduite au code ISO de langue. Si l'en-tête n'est pas exploitable, le repli initial est `fr`. Le Setup transmet ce code à `symsvel:languages:bootstrap:stdin` avant la création du compte `ROLE_DEV`. Languages active la langue principale et la conserve dans Settings de façon idempotente ; aucune lecture de ses tables n'est effectuée avant les migrations. Le même code ISO est transmis au bootstrap du premier administrateur.

Le **fallback français pour les traductions** est une règle de rendu : lorsqu'aucun fichier de traduction ne correspond à la langue demandée, les textes proviennent du fichier français disponible. La langue ISO enregistrée pour le site ou le compte ne doit pas être remplacée par `fr` à cause de cette absence. Les clients BO/App/Front et la chaîne i18n devront vérifier cette résolution de fichiers. Les tests end-to-end et les CI restent nécessaires.

## Fallback français des libellés d'interface (17/11/2026)

Les libellés intégrés de l'interface sont sélectionnés à partir de la langue d'interface (code ISO du navigateur ou du compte). Lorsque la locale n'a pas de dictionnaire intégré, les libellés français sont utilisés ; l'anglais reste disponible explicitement pour `en`. Le fallback ne modifie ni la langue ISO du site ni celle du compte et n'impose pas l'existence de traductions avant l'installation. Le repli doit être réalisé à la clé lors de l'introduction des dictionnaires dynamiques ; l'éditeur BO des traductions et le chargement fichier/BDD ne sont pas fournis par cette seule correction.

Vérifications : tests de sélection FR/EN/autres locales et tests de build/CI. La validation finale nécessite les crash-tests locaux sur le parcours Setup, BO et App.


## Catalogue de traductions et export TypeScript (17/11/2026, chantier dev)

La migration source `Version20261117000003` (version réservée dans le registre global Tooling) ajoute `symsvel_translations`, table **appartenant exclusivement à Languages**. La clé primaire composite est `scope + translation_key + language`, et le texte est stocké sous la colonne SQL `text_value` (exposée sous le champ API `value`), pour éviter les ambiguïtés de mots-clés entre SGBDR. Les scopes supportés sont `bo`, `app`, `front`; les traductions sont des valeurs UTF-8 textuelles, pas des scripts et n'introduisent ni logique métier dans Back/Core ni requêtes réseau. Le manifeste du module déclare les deux tables dont Languages est propriétaire.

`TranslationCatalog` autorise la lecture, la modification et la suppression de traductions pour les langues **actives ou en préouverture**. Une langue inactive ne peut pas être modifiée ou exportée. Les clés sont limitées aux identifiants alphanumériques pointés/tiret/underscore, et les valeurs à 8192 octets, sans NUL. Le service valide les scopes et codes ISO avant requête. La persistance utilise DBAL et des opérations portables, sans SQL spécifique à un moteur.

API privée BO (Bearer, `BoAccessGate`): `GET /apibo/languages/translations?scope=bo&language=fr` (`languages.list`), `PUT /apibo/languages/translations` avec JSON `{scope, language, key, value}` et `DELETE /apibo/languages/translations?scope=...&language=...&key=...` (`languages.manage`). `GET /apibo/languages/translations/export?scope=...&language=...` exige aussi `languages.manage`; il retourne JSON `{data:{filename,content,entries}}`, le contenu étant un module TypeScript `export default { ... } as const;` à clés triées. Le fallback est résolu **à la clé** (français disponible, puis langue principale du site, puis valeur dans la langue demandée, prioritaire). Aucun changement implicite du code de langue du compte ou du site.

La page `/languages/translations` est déclarée par le module et ouverte depuis la page `/languages`. Elle sélectionne scope + langue, édite les clés et génère un **fichier .ts téléchargeable** depuis l'administration. Une requête HTTP ne lance pas npm, ne touche pas les fichiers source du workspace et ne prétend pas avoir publié/rebuildé les interfaces : ces opérations doivent rester gérées par CLI/Tooling. Le chaînage « exporter toutes les langues dans les projets → réécriture atomique des fichiers → rebuild » et le scan des clés existantes dans les interfaces sont **encore à implémenter**. Les fichiers .ts actuels de l'authentification demeurent leurs dictionnaires embarqués tant que leur consommation n'a pas été adaptée à ce catalogue. Aucun cron de synchronisation n'est installé à ce stade.

Tests intégration : `TranslationCatalogTest` (CRUD, isolation BO/App, fallback, export déterministe, langue désactivée, clés non fiables), test de présentation BO. Après CI, prévoir CT du nouvel écran, migration compilée MySQL/PostgreSQL, droits ACL, injection dans les valeurs, et génération/lecture d'un fichier TypeScript par langue ; pas de validation finale revendiquée.


## Comparaison documentée avec les archives HP (17/11/2026)

Audit direct des archives utilisateur `src back HP.zip` et `bo HP.zip` :
- HP possède `TranslationsKeys` (target, scope, key, description) et `TranslationsText` (langue, champ text/description, valeur), ce qui permet de conserver les clés non encore traduites et d'afficher les lacunes ; les composants du BO distinguent bien texte local, texte français, fallback et traduction manquante.
- `ApiBoTranslationsKeysController::exportTs()` exporte par cible, langue et scope facultatif. Il assemble un **objet imbriqué** depuis `scope.key`, trie récursivement et retourne nom de fichier, contenu et nombre de feuilles. Dans les sources du BO HP, les fichiers historiques ne sont pas uniformes : `fr.ts` utilise des clés pointées tandis que `en.ts` contient des objets imbriqués. Nous conservons pour SymSvel un **format généré imbriqué unique**.
- HP propose import CSV et export manuel via une modale ; `GlobalTranslationsProvider` fournit séparément un dictionnaire serveur par langue avec fallback français et cache expirant à une heure, invalidé après mutation.
- Les archives transmises **ne contiennent pas de commande de scan automatique des fichiers TypeScript, de réécriture du projet ni de rebuild périodique**. Ces mécanismes envisagés dans SymSvel ne peuvent pas être considérés comme un portage déjà vérifié depuis HP.

En conséquence, l'export SymSvel reconstruit désormais des objets TypeScript imbriqués avec tri stable et fallback à la clé. Les valeurs vides n'écrasent pas un fallback existant. L'éditeur simple actuel ne reprend **pas encore** le registre de clés séparé, les descriptions localisées, les indicateurs de traductions manquantes ou l'import CSV ; cela demeure explicitement un écart fonctionnel, non une fonctionnalité terminée. Aucun mécanisme de déploiement des sources Svelte ou d'invalidation de cache runtime n'est revendiqué. Le crash-test utilisateur reste obligatoire.


## Registre des clés indépendante des textes (17/11/2026)

Après comparaison avec les archives HP, une deuxième migration additive `Version20261117000004`, réservée dans `symsvel-tooling/docs/modules/migration-versions.json`, crée `symsvel_translation_keys` (clé composite `scope, translation_key`, description optionnelle). L'ensemble des tables reste déclaré dans `module.json`.

L'API BO permet désormais `POST /apibo/languages/translations/keys` avec JSON `{scope,key,description?}` et permission `languages.manage`. Une clé existe indépendamment des textes enregistrés ; supprimer une traduction de langue ne supprime donc pas la clé. `TranslationCatalog::save()` enregistre aussi automatiquement une clé auparavant inconnue, sans désolidariser la création et l'enregistrement du texte (même transaction DBAL).

`GET /apibo/languages/translations` est un catalogue **complet des clés** du scope : chaque réponse contient le texte réel de la langue cible, `description`, `fallback_value` (français puis langue principale du site) et `missing_translation`. Le fallback est purement affiché, jamais persisté comme traduction cible. L'éditeur indique le nombre de clés manquantes, propose les valeurs de secours et permet une création de clé sans texte. Les versions `20261117000003` et `20261117000004` doivent être compilées dans cet ordre, avec CT multi-SGBD ; le catalogue rétroactif de clés issues d'éventuelles traductions enregistrées avant cette deuxième migration reste à traiter si une telle installation existe.

L'export TypeScript utilise l'arbre imbriqué `auth.signIn` → `{auth:{signIn:...}}` documenté dans HP, ordonne les clés à chaque niveau et ignore les textes vides en faveur des valeurs de secours. L'export d'un scope sans contenu est un objet vide `{}`, non un tableau. Aucun scan automatique des sources TypeScript et aucune publication automatique dans Svelte ne sont implémentés.


## Cycle prod CSV → dev → fichiers .ts locaux (décision 17/11/2026)

La majorité des traductions étant produite en production, le flux validé est : **BO production exporte un CSV → BO développement prévisualise et importe en BDD → Tooling publie les .ts locaux → Git/CD construit et déploie les interfaces**. Les textes en production sont lus depuis les builds statiques Svelte, sans cache Redis pour ce chantier. Une intégration éventuelle de Redis sera traitée séparément.

**Format CSV SymSvel :** UTF-8, séparateur virgule, en-tête exact \`scope,key,description,language,value\`. L'export inclut toutes les langues du catalogue, même inactives, et conserve les clés sans aucune valeur avec \`language,value\` vides. Ce format d'échange étend celui de HP (\`scope,key,explication,trad_fr\`) pour les langues multiples et le cycle production→développement ; ce n'est pas une importation directe du CSV HP. Les champs quotés peuvent contenir des retours à la ligne. Les limites sont 2 Mio et 10 000 lignes. Les doublons (même scope/clé/langue), les langues inconnues et les formats invalides sont refusés.

\`TranslationCsvTransfer\` exporte via \`LEFT JOIN\` des clés et textes. L'import fonctionne en **prévisualisation** ou en **application atomique** (transaction DBAL). Une empreinte SHA-256 du CSV prévisualisé est exigée lors de l'application. Les clés inconnues sont créées ; les descriptions non vides sont actualisées ; les textes renseignés sont ajoutés ou remplacés ; les valeurs vides et les lignes absentes **ne suppriment rien**. Les données présentes dans le projet dev hors CSV restent intactes. Les suppressions volontaires sont des opérations explicites du BO, hors de ce format.

Routes \`/apibo/languages/translations/export/csv\` (GET, ACL \`languages.manage\`) et \`/apibo/languages/translations/import/csv\` (POST, ACL \`languages.manage\`, **route déclarée uniquement pour les environnements dev/test**, client loopback). JSON de l'import : \`{csv,mode:"preview"}\` puis \`{csv,mode:"apply",sha256:"..."}\` ; la réponse fournit le nombre de clés/textes créés, modifiés et inchangés. \`GET /apibo/languages/translations/csv-capabilities\` permet au BO de présenter les opérations disponibles. Aucune opération d'import n'est exposée dans le routage de production.

**Écriture locale des dictionnaires :** \`POST /apibo/languages/translations/publish\` n'est routé **qu'en dev**, exige les ACL et une connexion loopback. Il accepte uniquement \`{scope,language}\` ; \`TranslationCatalog\` construit le contenu TS de façon déterministe, puis \`SymSvel\Tooling\I18n\TranslationFilePublisher\`, présent uniquement en développement, publie sous \`<workspace>/<scope>/src/lib/translations/generated/<language>.ts\`. L'écrivain refuse les chemins/symlinks invalides, les composants non installés et les fichiers non identifiés par la signature SymSvel, écrit temporairement puis renomme de façon atomique ; il ne lance ni npm ni build. La route n'existe pas en prod et Tooling n'y est pas une dépendance requise. L'API ne transmet jamais au client de chemin absolu du serveur.

**Limites à vérifier au CT :** correspondance des dossiers de composants en cas de renommage du mapping, capacité réelle du serveur PHP local à écrire dans les sources Svelte du workspace, consommation effective de \`src/lib/translations/generated/<lang>.ts\` par les autres pages métier (helpers et premiers consommateurs BO/App raccordés), et exploitation du Git/CD depuis les répertoires des projets. Le fichier seul n'actualise pas les écrans avant intégration au bundle. Aucune compilation ni synchronisation prod des sources n'est proposée. CI et CT utilisateur requis avant clôture.


### Premier raccordement des dictionnaires aux hôtes (17/11/2026)

BO, App et Front disposent maintenant du helper `src/lib/i18n/generated-translations.ts`. Vite intègre au build les fichiers `generated/<lang>.ts` présents ; la résolution des objets imbriqués conserve le fallback français à la clé. Les installations sans fichier généré restent compilables. Les libellés de l'authentification App (`auth.*`), des composants BO (`components.*`) et des écrans Languages (`languages.*`, `languages.translations.*`) consomment ces dictionnaires avec repli sur les valeurs intégrées. Les autres pages/modules devront encore adopter ce contrat ; le Front fournit le helper mais aucune page métier ne l'utilise encore. Ni lecture BDD/Redis au runtime ni rebuild automatique n'ont été introduits.


### Contrôle fin des opérations du BO (17/11/2026)

L'endpoint de capacités CSV accepte les acteurs possédant `languages.list`, afin de ne pas bloquer la lecture de l'éditeur. Il annonce `can_export`, `can_import` et `can_publish` uniquement si `languages.manage` est accordé, avec les restrictions d'environnement et de loopback propres aux opérations locales. Les endpoints de mutation continuent à appliquer l'ACL serveur indépendamment de l'état des boutons. Les routes d'import et de publication sont absentes en production par déclaration `env` Symfony.


## Dictionnaires des modules et synchronisation post-migrations (18/11/2026, dev)

Tooling recense les dictionnaires JSON-compatibles `.ts` de chaque scope installé et des hôtes natifs (`system`) dans `backend/.symsvel/translation-sources.json` (schéma 1). Le module Languages est seul propriétaire de la persistance ; la commande locale `symsvel:languages:translations:sync`, orchestrée après migrations, valide intégralement le manifeste puis enregistre les clés `scope + module.key` et les textes dans une transaction DBAL. Les clés publiques suivent `<scope>.<module|system>.<key>`, sans double stockage du scope.

Migration Schema `Version20261118000001` : la colonne `customized` vaut vrai par défaut sur les anciennes traductions, pour les protéger. Le synchroniseur inscrit les nouveaux textes source avec `customized=false` et ne met à jour que ces valeurs ; une édition BO ou un import CSV marque `customized=true`. Les langues inactives peuvent recevoir leurs textes de référence, mais demeurent non éditables depuis le BO jusqu'à leur activation ou préouverture. Les clés sans valeur sont néanmoins inscrites. La suppression BO d'une valeur rétablit la possibilité d'installer la valeur par défaut lors de la prochaine synchronisation.

Ce mécanisme ne reconstruit pas les fichiers Svelte et ne publie rien en production ; Tooling reste chargé de la synchronisation des sources puis le build Git/CD incorpore les dictionnaires. Tests de migrations compilées PG/MySQL et crash-tests XT requis avant validation.


## Dictionnaires propres à Languages (18/11/2026)

Les libellés BO de Languages sont fournis sous `bo/src/lib/translations/fr.ts` et `en.ts`, en incluant le sous-ensemble `translations`. Tooling copie ces fichiers dans l'hôte sous `lib/translations/languages`, les indexe en BDD et applique les surcharges publiées sous `lib/translations/generated`. Convention : `bo.languages.<clé>`. Le contrôleur de publication annonce désormais le nouveau chemin.


## Indicateurs par module installé (18/11/2026)

`TranslationCatalog::moduleStatistics()` calcule les effectifs à partir de `symsvel_translation_keys` et des textes réellement stockés pour la langue demandée. Le propriétaire est le premier segment de `translation_key`, le scope restant distinct. Les modules affichés viennent exclusivement de `backend/.symsvel/modules.json` produit par Tooling ; `system` représente les clés natives des hôtes. Les traductions de modules désinstallés ne sont pas affichées. Une langue manquante ou une valeur vide n'est jamais comptée comme traduite, et aucun fallback ne gonfle le numérateur. Un module sans clé a un pourcentage nul (`null`), et la progression globale est pondérée par le nombre de clés. Routes BO `GET /apibo/languages/translations/modules?language=en` et filtre optionnel `module` sur `GET /apibo/languages/translations` (ACL `languages.list`).

### Compatibilité des personnalisations natives BO

Lorsqu'une valeur native du BO passe de `components.<clé>` à `system.<clé>`, la synchronisation initiale détecte l'ancienne traduction personnalisée, la copie dans la clé canonique et la protège avec `customized=true`. L'ancienne clé n'est pas supprimée sans confirmation : elle reste consultable pour l'historique et le repli des builds existants. Les valeurs canoniques déjà personnalisées gardent toujours priorité. Un test d'intégration couvre cette migration non destructive.

Le champ `scopes` des statistiques est calculé à partir des clés réellement enregistrées pour chaque propriétaire. L'éditeur propose le choix BO/App/Front uniquement lorsqu'au moins deux scopes contiennent des clés ; pour un seul scope, celui-ci est affiché sans liste déroulante. Les modules sans clés restent visibles, avec « aucune clé déclarée ».

### Assemblage de test du BO

La CI d'intégration monte les dictionnaires du module Languages sous `src/lib/translations/languages`, comme le synchroniseur Tooling. Le test de présentation utilise ces chemins d'hôte, au lieu de tenter d'importer les fichiers source non installés. Le contrat est ainsi validé sur le BO assemblé, sans mettre les dictionnaires dans Back/Core.

### Clé `translations` réservée au lien du module

Le libellé de navigation `bo.languages.translations` est un texte simple (« Traductions »). Les champs de l'écran d'édition sont regroupés sous `bo.languages.editor.*`, et non `bo.languages.translations.*`, afin d'éviter un conflit entre un texte et un objet lors de l'export TypeScript imbriqué. Une assertion de la CI verrouille les deux formes de clé.

Le propriétaire `generated` est réservé au dossier des surcharges publiées : le synchroniseur Languages refuse tout enregistrement source prétendant provenir d'un module de ce nom. `system` reste autorisé uniquement pour les dictionnaires natifs validés par Tooling. Un test d'intégration vérifie le refus sans aucune écriture en base.


### Export des clés à index numérique

Les sous-clés des libellés de listes fixes (par exemple `auth.strengthLevels.0`) doivent toujours être sérialisées en **propriétés d'objet JSON**, et non en tableaux JSON. Le catalogue construit les dictionnaires imbriqués puis convertit chaque nœud en objet avant l'encodage. Ce contrat est nécessaire au chargeur Svelte, qui accède à chaque segment d'une clé sans interpréter les tableaux comme des traductions. Un test d'intégration couvre `strengthLevels.0` et `.1`.


### Publication `.ts` : uniquement les surcharges personnalisées

`GET /apibo/languages/translations/export` conserve son export complet (valeurs + fallbacks) pour téléchargement et audit. En revanche, l'action BO de publication locale appelle `TranslationCatalog::export(scope, langue, overridesOnly: true)`, qui inclut uniquement les textes explicitement marqués `customized=true` pour la langue demandée. Les valeurs sources non personnalisées et les fallbacks ne sont **jamais recopiés** dans `src/lib/translations/generated/<lang>.ts`, afin qu'une mise à jour de module puisse effectivement renouveler ses textes par défaut. Les autres langues conservent leur propre dictionnaire de surcharges ; le helper statique applique le repli clé par clé. Une couverture d'intégration compare explicitement publication ciblée et export complet.

### Compatibilité des textes de dashboard App

Lors du déplacement des libellés d'accueil App depuis `auth.dashboard`/`auth.loggedIn` vers `system.dashboard.title`/`system.dashboard.loggedIn`, les traductions historiques présentes en BDD sont copiées dans les nouvelles clés canoniques et marquées `customized=true` si elles n'existent pas déjà. Les anciens enregistrements sont conservés. Un test couvre la conservation et l'idempotence de la synchronisation.


### Correction multi-SGBD : typage de `customized` (19/11/2026)

La colonne `symsvel_translations.customized` est un booléen Doctrine Schema, mais DBAL peut encoder un booléen PHP non typé comme une chaîne vide lors d'un `insert` / `update` (erreur MySQL `SQLSTATE[HY000] 1366 Incorrect integer value: ''`). Les opérations de synchronisation des sources, de sauvegarde dans le BO et d'import CSV utilisent désormais explicitement `Doctrine\\DBAL\\ParameterType::BOOLEAN`. Cette correction ne modifie ni la migration, ni la valeur par défaut des traductions historiques. Test d'intégration : valeur source = 0 ; valeur éditée dans le BO = 1, à valider également sur MySQL/MariaDB et PostgreSQL pendant les CT.

### Régression sur bases réelles (19/11/2026)

Le job CI `database-boolean-matrix` utilise les SGBDR locaux existants (PostgreSQL, MySQL et MariaDB) avec une base isolée `symsvel_languages_bool_ci` par moteur. Il applique les migrations Schema concernées, importe un dictionnaire source (`customized=false`), personnalise un texte par CSV (`customized=true`), puis vérifie l'idempotence et la protection de la valeur personnalisée. Ce test cible spécifiquement l'erreur MySQL 1366, invisible dans les tests SQLite. Il ne nécessite aucun service supplémentaire en production.

La première exécution du job multi-SGBD a révélé que la version DBAL 4 utilisée dans les runners exige le champ `driver` explicite lors de la création de la connexion de test. Le test sélectionne désormais `pdo_pgsql` pour l'URL PostgreSQL et `pdo_mysql` pour MySQL/MariaDB, sans changer la connexion applicative ni le code métier.
