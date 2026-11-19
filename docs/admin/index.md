# Languages : administration

Languages est un **module natif obligatoire**. Il propose
117 langues officielles avec code et nom natif, reprises de XCore.

## États

- **Désactivée** : ni disponible publiquement, ni proposée à la préparation BO.
- **Préouverture** : accessible au BO pour préparer les traductions, pas publique.
- **Active** : disponible au BO et publiquement si le mode multilingue est actif.

La langue principale est sélectionnée explicitement, sans
présélection, lors du Setup. Elle est activée automatiquement et
**ne peut plus être modifiée ni désactivée** après installation.
Elle reste la seule langue proposée publiquement lorsque le
multilinguisme est inactif.

## Écran BO

La page `/languages` permet de rechercher les langues, les trier,
les paginer et modifier les états **Active** et **Préouverture**.
Le code de langue et le libellé localisé sont affichés.
Les actions sont contrôlées côté API par les ACL
`languages.list` et `languages.manage`; le verrou de la
langue principale est appliqué côté serveur.

Dans **Settings**, `languages.primary_language` est en lecture
seule et `languages.multilingual` détermine l'exposition publique
des langues actives. La langue d'administration demeure indépendante
du mode multilingue public. Les noms sont traduits par PHP Intl,
avec repli sur le nom natif.

Aucune sonde système, synchronisation réseau ou installation de
traductions n'est lancée automatiquement.


## Éditeur des traductions (refonte UX du 19/11/2026)

Depuis `/languages`, le lien **Traductions** ouvre `/languages/translations`. L'ordre de travail reprend HP : **choisir la langue**, même si une seule est active, puis parcourir les modules installés et leur taux de traduction. La langue d'interface BO reste indépendante de la langue que l'on traduit. Chaque module affiche ses clés et, si nécessaire, permet de choisir le scope BO/App/Front.

Le panneau **Import / Export**, placé sous la langue, regroupe le téléchargement des `.ts` (par interface et par langue), leur publication locale par Tooling en environnement dev autorisé, l'export CSV global et l'import CSV local (replié par défaut). Il ne change pas le module sélectionné. Les `.ts` publiés ne contiennent que les personnalisations, pour préserver les sources livrées par chaque module. Le téléchargement d'un export complet est une opération différente de la publication locale.

Dans un module, le tableau contient la clé immuable, son explication et la traduction. Les valeurs sont **éditables en ligne** : un bouton Enregistrer apparaît seulement sur les lignes modifiées et n'actualise que la donnée concernée plus les compteurs de progression, sans recharger la page ou perdre filtre, module ou scroll. Le texte de secours français ou de la langue principale peut être affiché sans être enregistré comme traduction de la langue cible.

**Créer une clé** ouvre la modale commune du BO (HTML `dialog`). Elle crée en une transaction la clé, la description et, si renseignée, une première valeur dans la langue choisie. **Modifier** ouvre la même modale avec l'identifiant de clé en lecture seule ; seules l'explication et la traduction sont modifiables. Les opérations sont asynchrones ; la modale conserve la saisie en cas d'erreur, elle ne ferme qu'après succès.

La suppression d'une **valeur de traduction** (dans une langue) et celle de la **clé** (toutes ses langues) sont deux opérations distinctes. La suppression de clé globale n'est exposée qu'au `ROLE_DEV` et le serveur vérifie explicitement ce rôle via `BoAccessGate` : un super-administrateur ne suffit pas. La confirmation avertit qu'une clé appartenant à un dictionnaire fourni par un module pourra être recréée lors de la prochaine synchronisation de ce module.

Les accès `languages.list` et `languages.manage` contrôlent respectivement la lecture et les modifications classiques ; la suppression globale applique en plus la restriction `ROLE_DEV`. Les traductions sont persistées séparément par interface et langue, sans exiger l'activation publique du multilinguisme.

Le BO reste une interface **Svelte statique** : écrire une valeur en base ou publier le `.ts` ne reconstruit pas magiquement un build déployé. La mise à jour visible dans les écrans demande le circuit Tooling/build et, en production, la CD prévue. Aucun serveur Node en production.

## Synchronisation production vers développement (17/11/2026)

Les traductions peuvent être éditées principalement en production. Depuis `/languages/translations`, **Exporter tout en CSV** fournit le catalogue complet (BO/App/Front, langues, clés, descriptions et textes). Les clés sans texte sont également exportées.

Dans le BO de développement local, **Importer les traductions** permet de sélectionner ce CSV. Utilisez **Prévisualiser** pour lire les nombres de clés et de textes créés ou mis à jour ; validez ensuite par **Importer les changements**. L'import est transactionnel : les lignes absentes ne sont pas supprimées, les cellules de texte vides préservent le texte local et seules les valeurs renseignées remplacent celles de développement. Le bouton et l'API d'import n'existent pas en production.

Toujours depuis le BO de développement local, le bouton **Écrire le .ts en local** délègue l'enregistrement au Tooling, pour l'interface et la langue affichées. Le résultat est enregistré à l'emplacement `<scope>/src/lib/translations/generated/<lang>.ts`. Ce fichier doit ensuite être intégré à la lecture i18n des pages, versionné et publié par le circuit Git/CD ; aucun rebuild n'est lancé automatiquement. Le téléchargement manuel d'un fichier .ts reste possible.

En production, la création ou modification d'une entrée en BDD n'est pas immédiatement visible dans les interfaces statiques : elle apparaît après son import dans dev, la mise à jour des sources et le déploiement CD. La gestion de cache/Redis est hors de ce chantier.


## Textes fournis par les modules

Les dictionnaires de référence d'un module sont enregistrés lors de l'installation, après les migrations. Les textes personnalisés depuis le BO ou importés depuis un CSV sont protégés lors des mises à jour du module. Les nouvelles valeurs par défaut ne remplacent que les textes non personnalisés. La convention complète est `<scope>.<module|system>.<key>` ; `system` ne correspond qu'aux composants natifs des hôtes Svelte. L'édition BO n'exige pas d'activer le multilinguisme.


## Parcours de traduction par langue puis module (18/11/2026)

L'administrateur choisit d'abord la langue active ou préouverte, puis un des modules réellement installés ou la rubrique Système. La liste affiche un pourcentage calculé sur les clés déclarées, le nombre traduit/manquant et une progression. Zéro clé ne signifie pas 100 %. Dans le module, l'administrateur choisit éventuellement BO/App/Front, filtre/recherche puis édite les clés préfixées du module. Les imports/exports CSV conservent leur portée globale, et l'export `.ts` reste par scope et langue. La CI et les CT XT doivent confirmer le parcours et les droits ACL.

Lorsqu'un module n'a de traductions que dans un seul scope (par exemple BO), l'éditeur n'affiche pas de sélecteur BO/App/Front inutile. Plusieurs scopes déclarés restent sélectionnables dans le module, sans modifier la langue courante.


**Publication TypeScript :** le fichier local généré à partir du BO ne contient que les traductions volontairement personnalisées. Les textes livrés avec un module restent dans son propre dictionnaire, mis à jour par Tooling ; un fichier généré ne les fige donc pas. Le téléchargement de l'export complet reste disponible séparément, avec fallbacks, pour les échanges ou l'audit.

### Parcours HP : état de l'éditeur (19/11/2026)

L'écran maintient la langue, le module et le scope comme états Svelte distincts. La création et la modification utilisent une modale commune ; le nom de clé n'est plus modifiable en mode édition. Chaque ligne possède un brouillon de traduction indépendant ; sa validation actualise la ligne et réinterroge les compteurs, sans navigation ni perte de scroll. Import/export ont une interface cible distincte. La suppression de clé n'est affichée qu'avec la capacité DEV renvoyée par le backend.

### Libellés UX HP (19/11/2026)

Le module Languages déclare et traduit dans ses propres dictionnaires les textes du nouveau parcours : choix de langue toujours visible, Import / Export, chargement, actions, identifiant verrouillé dans la modale et suppression globale des clés. Les messages français et anglais restent alignés et les textes dynamiques passent par `translationCopy`, sans chaîne métier codée en dur dans Svelte.

### Hiérarchie des panneaux (19/11/2026)

Le rendu présente désormais une sélection de langue persistante, un panneau Import/Export, les modules et leur taux de traduction, puis le tableau éditable du module. Le formulaire de création ou modification se trouve dans la modale native BO. Les valeurs existantes sont modifiées directement dans les cellules ; seules les valeurs changées affichent Enregistrer. Les actions sauvegardent par API sans rechargement ni retour forcé à la liste.

### Styles du module (19/11/2026)

Le composant Traductions utilise les jetons `--bo-*` pour les surfaces, champs, textes secondaires, focus, tableaux et statuts. Les fallbacks CSS permettent son assemblage avec un BO antérieur pendant les CI isolées ; le Design System BO devient la source des couleurs une fois synchronisé. La présentation conserve la hiérarchie HP (cartes modules, tableaux, modale) sans ajouter une bibliothèque UI.

### Réponses asynchrones périmées (19/11/2026)

Les chargements du catalogue et des clés utilisent des identifiants de requête : si l'administrateur change rapidement de langue, scope ou module, une réponse HTTP plus ancienne ne doit jamais remplacer la sélection courante. Les valeurs de secours restent visibles en référence lors de l'édition d'une autre langue que le français, même lorsque la traduction cible existe déjà. La validation de ligne demeure indépendante et ne déclenche aucune navigation.

### Édition de ligne unique, sans modale (19/11/2026)

La modale est désormais réservée à **Créer une clé**. Les traductions existantes et leur explication s'affichent d'abord comme textes. Cliquer **Éditer** remplace le texte de la ligne par des champs préremplis et les actions par **Valider / Annuler**. Une seule ligne peut être éditée simultanément, la clé reste toujours en lecture seule, et le `PATCH /apibo/languages/translations/keys` existant sauve en une transaction explication et valeur. En cas d'échec, les champs et leur saisie restent visibles. En cas de succès, la ligne et les compteurs sont actualisés sans reload ni perte de la sélection et du scroll. Le formulaire de création existant reste asynchrone et indépendant.

### Bandeau HP et dropdown partagé (19/11/2026)

Le bandeau de traduction est désormais une composition du `AdminLanguageBar` HP : deux lignes sur la gauche, **LANGUE D'INTERFACE** (prise dans `uiLanguage` du BO) et **ÉDITER EN** (langue cible du catalogue), chaque ligne indiquant drapeau + code ISO. À droite, le Dropdown partagé du BO affiche nom de langue, drapeau, code et chevron. Le choix reste accessible même lorsque la seule langue active est la langue principale ; il ne modifie pas la langue d'interface. Les deux sélecteurs d'interface (export `.ts` et scope module) utilisent aussi ce Dropdown réutilisable. Les drapeaux proviennent du mapping vérifié dans `bo HP(2).zip`.

Sur une ligne, l'état initial est **du texte**, sans champ de saisie ni modale d'édition. Le bouton Éditer passe seulement cette ligne en mode saisie, avec explication et valeur préremplies, et remplace les actions par Valider / Annuler. Les autres lignes restent consultables. La clé n'est jamais éditable. La création reste dans la modale.
