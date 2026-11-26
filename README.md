# Languages, module natif SymSvel

<!-- symsvel-brand:start -->
<p align="center">
  <a href="https://symsvel.com/"><img src="https://raw.githubusercontent.com/Symsvel-Framework/symsvel-tooling/07264829c404bcaecf1d8ce25c6a71e5beab2ae9/resources/documentation/theme/mark.png" alt="SymSvel Framework" width="48"></a>
  &nbsp;&nbsp;
  <a href="https://doc.symsvel.com/"><img src="https://raw.githubusercontent.com/Symsvel-Framework/symsvel-tooling/07264829c404bcaecf1d8ce25c6a71e5beab2ae9/resources/documentation/theme/zeko.webp" alt="Zeko, le gecko SymSvel" width="88"></a>
</p>
<p align="center"><a href="https://symsvel.com/">Site officiel</a> · <a href="https://doc.symsvel.com/">Documentation développeur</a></p>
<!-- symsvel-brand:end -->

> [!IMPORTANT]
> **Vous souhaitez utiliser SymSvel ? Rendez-vous sur [SymSvel CLI](https://github.com/Symsvel-Framework/symsvel-cli).** Ce dépôt est un **composant du framework**, pas un point d'installation autonome. Création de projets, installation des modules et mises à jour passent par la CLI. Les éventuelles commandes Composer, npm ou Symfony décrites ci-dessous concernent le travail des mainteneurs, sauf indication explicite contraire.

> [!WARNING]
> **SymSvel est en cours de développement.** Les fonctionnalités et procédures peuvent évoluer ou présenter des anomalies. **Utilisation à vos risques et périls.**

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

## Intégration continue GitHub-hosted (22/11/2026)

Le workflow actif `.github/workflows/qa.yml` s'exécute désormais sur **GitHub-hosted Ubuntu 24.04**, sans runner Docker local. La matrice conserve les tests PostgreSQL 17, MySQL 8.4 et MariaDB 11.8 sur services jetables ; l'intégration BO Svelte est aussi testée. L'ancien `qa.yml.stub`, lorsqu'il existe, est une archive inactive.

Les runners auto-hébergés restent réservés aux dépôts privés `symsvel-packages` et `symsvel-recipes`. Les crash-tests locaux restent requis indépendamment de la CI.

## Licence MIT et contributions

Ce dépôt est distribué sous [licence MIT](LICENSE). Le code peut être utilisé, modifié et redistribué, y compris dans des projets commerciaux ou propriétaires, dans les conditions de cette licence (notamment la conservation de la notice de copyright et de licence).

**Contribuer :** créer un fork, puis proposer une pull request vers `dev` lorsque cette branche existe, sinon vers la branche par défaut. Expliquer la modification et fournir les tests et la documentation concernés. Les mainteneurs conservent la maîtrise des revues, des validations (CI et crash-tests), de l'intégration et des releases officielles. Une PR ne garantit pas son acceptation.

**Versions officielles :** seules les versions diffusées par l'organisation [Symsvel-Framework](https://github.com/Symsvel-Framework) sont officielles. Les forks sont libres sous MIT, mais ne doivent pas être présentés comme officiellement approuvés par les mainteneurs. Cette distinction n'ajoute aucune restriction aux droits accordés par la licence MIT sur le code.

## Participer et soutenir le projet

SymSvel est un projet en cours de développement. Pour contribuer, proposer une amélioration ou échanger avec le mainteneur : **[xavier@xavier-tezza.fr](mailto:xavier@xavier-tezza.fr)**.

SymSvel vous est utile et vous souhaitez me remercier ? **Offrez-moi une bière ! 🍺** (lien PayPal à compléter avant l'ouverture publique).

## Promotion stable (24/11/2026)

Les contraintes Composer de publication sont stables (`^1.0`), sans modifier le canal `dev`. Publier après les dépendances, leur indexation et les CI vertes. CT stable distinct.
