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
