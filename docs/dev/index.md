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
