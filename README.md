# Languages, module natif SymSvel

Languages est présent dès l'installation de **tous les projets SymSvel**,
y compris BO seul. Il porte le référentiel initial de 117 langues
officielles repris de XCore, leurs états d'activation, la sélection
définitive de la langue principale et les API de consultation.

## Installation et Setup

Le module est installé par le manifeste natif du Back et reste
non désinstallable. Ses migrations Schema multibase créent et peuplent
`symsvel_languages`. Au Setup, le développeur doit sélectionner
explicitement la langue principale parmi les noms natifs, sans
présélection. Le module enregistre ce choix dans Settings
(`languages.primary_language`, en lecture seule) et l'active.

Le paramètre `languages.multilingual` reste modifiable dans Settings.
Avec le multilinguisme désactivé, seule la langue principale est
proposée publiquement. Les langues en préouverture sont disponibles
dans le BO mais pas publiquement.

## Administration

La page BO `/languages` présente le catalogue avec recherche,
tri, pagination et actions d'activation / préouverture, sous contrôle
des ACL `languages.list` et `languages.manage`. Les API partagées
sont `/apiopen/languages`, `/api/languages`, ainsi que les endpoints
BO. Les noms sont localisés via PHP Intl et un repli sur le nom natif.

Voir `docs/dev/index.md` pour l'architecture et les migrations,
`docs/admin/index.md` pour les usages d'administration, et
`docs/user/index.md` pour la disponibilité des langues.

## Validation

Les tests d'intégration SQLite et les contrôles du BO assemblé sont
exécutés par la CI auto-hébergée. L'installation complète, la
compilation réelle des migrations et les quatre SGBD restent soumis
au crash-test XT. Aucune publication stable avant cette validation.
