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


## Éditeur des traductions (chantier dev, 17/11/2026)

Le bouton **Traductions** de la page Languages ouvre `/languages/translations`. Sélectionnez une interface (BO/App/Front) et une langue active ou en préouverture, puis saisissez une clé telle que `auth.signIn` et son texte. Les traductions sont enregistrées séparément pour chaque couple interface/langue. Vous pouvez reprendre une clé existante, modifier ou supprimer la valeur.

Le bouton **Générer le fichier .ts** télécharge le dictionnaire TypeScript de la sélection. Les clés absentes utilisent les textes français disponibles, puis ceux de la langue principale du site, toujours avec priorité aux textes explicitement traduits dans la langue cible. Les clés sont triées pour fournir un résultat reproductible. La permission `languages.list` permet de consulter les valeurs ; `languages.manage` est requise pour modifier, supprimer ou exporter.

**Limite actuelle :** ce téléchargement ne déploie pas le fichier dans BO/App/Front et ne reconstruit pas les builds Svelte. Les libellés intégrés de certaines pages ne consomment pas encore ce dictionnaire. L'installation d'une chaîne automatique depuis l'éditeur BO jusqu'au build est un chantier Tooling distinct, non validé.


### Clés de référence et traductions manquantes

Les clés sont désormais enregistrées indépendamment de leurs valeurs par langue. Le bouton **Créer la clé** permet de préparer une clé et sa description avant qu'une traduction existe ; le bouton **Enregistrer** renseigne la traduction sélectionnée. Le tableau continue à afficher une clé après suppression de son texte et indique si elle est **manquante**. Un texte de secours français ou de langue principale peut être affiché sans être enregistré à la place de la traduction absente.

L'export `.ts` télécharge des objets imbriqués par catégories de clés, au format de l'export HP. Le déploiement dans l'application nécessite encore une opération Tooling distincte.


## Synchronisation production vers développement (17/11/2026)

Les traductions peuvent être éditées principalement en production. Depuis \`/languages/translations\`, **Exporter tout en CSV** fournit le catalogue complet (BO/App/Front, langues, clés, descriptions et textes). Les clés sans texte sont également exportées.

Dans le BO de développement local, **Importer les traductions** permet de sélectionner ce CSV. Utilisez **Prévisualiser** pour lire les nombres de clés et de textes créés ou mis à jour ; validez ensuite par **Importer les changements**. L'import est transactionnel : les lignes absentes ne sont pas supprimées, les cellules de texte vides préservent le texte local et seules les valeurs renseignées remplacent celles de développement. Le bouton et l'API d'import n'existent pas en production.

Toujours depuis le BO de développement local, le bouton **Écrire le .ts en local** délègue l'enregistrement au Tooling, pour l'interface et la langue affichées. Le résultat est enregistré à l'emplacement \`<scope>/src/lib/i18n/generated/<lang>.ts\`. Ce fichier doit ensuite être intégré à la lecture i18n des pages, versionné et publié par le circuit Git/CD ; aucun rebuild n'est lancé automatiquement. Le téléchargement manuel d'un fichier .ts reste possible.

En production, la création ou modification d'une entrée en BDD n'est pas immédiatement visible dans les interfaces statiques : elle apparaît après son import dans dev, la mise à jour des sources et le déploiement CD. La gestion de cache/Redis est hors de ce chantier.


## Textes fournis par les modules

Les dictionnaires de référence d'un module sont enregistrés lors de l'installation, après les migrations. Les textes personnalisés depuis le BO ou importés depuis un CSV sont protégés lors des mises à jour du module. Les nouvelles valeurs par défaut ne remplacent que les textes non personnalisés. La convention complète est `<scope>.<module|system>.<key>` ; `system` ne correspond qu'aux composants natifs des hôtes Svelte. L'édition BO n'exige pas d'activer le multilinguisme.


## Parcours de traduction par langue puis module (18/11/2026)

L'administrateur choisit d'abord la langue active ou préouverte, puis un des modules réellement installés ou la rubrique Système. La liste affiche un pourcentage calculé sur les clés déclarées, le nombre traduit/manquant et une progression. Zéro clé ne signifie pas 100 %. Dans le module, l'administrateur choisit éventuellement BO/App/Front, filtre/recherche puis édite les clés préfixées du module. Les imports/exports CSV conservent leur portée globale, et l'export `.ts` reste par scope et langue. La CI et les CT XT doivent confirmer le parcours et les droits ACL.
