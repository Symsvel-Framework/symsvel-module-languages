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
