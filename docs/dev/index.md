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

Contrats fonctionnels XCore conservés : langue principale choisie
explicitement au Setup et non désactivable ensuite ; anglais ou français
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
