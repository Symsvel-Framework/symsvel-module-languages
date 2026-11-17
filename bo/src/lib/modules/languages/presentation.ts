export type BoLanguage = {
  iso_code: string;
  name: string;
  active: boolean;
  pre_open: boolean;
  primary: boolean;
};
export type BoLanguagesResponse = { data: BoLanguage[] };
const copy = {
  fr: {
    title: 'Langues', name: 'Langue', primary: 'Principale',
    intro: 'Activez les langues publiques ou placez-les en préouverture pour préparer leurs traductions.',
    publicState: 'Active', preOpenState: 'Préouverture',
    yes: 'Oui', no: 'Non', refresh: 'Actualiser', saved: 'Statut enregistré.',
    none: 'Aucune langue trouvée.', search: 'Rechercher une langue',
    searchHint: 'Nom ou code ISO…', forbidden: 'Action interdite par les ACL.',
    session: 'Session expirée. Reconnectez-vous.', invalid: 'Statut invalide.',
    primaryProtected: 'La langue principale ne peut pas être désactivée.',
    failure: 'Impossible de charger ou modifier les langues.'
  },
  en: {
    title: 'Languages', name: 'Language', primary: 'Primary',
    intro: 'Enable public languages or put them in pre-opening to prepare translations.',
    publicState: 'Active', preOpenState: 'Pre-opening',
    yes: 'Yes', no: 'No', refresh: 'Refresh', saved: 'Status saved.',
    none: 'No languages found.', search: 'Search languages',
    searchHint: 'Name or ISO code…', forbidden: 'Action denied by ACL.',
    session: 'Session expired. Please sign in.', invalid: 'Invalid status.',
    primaryProtected: 'The primary language cannot be disabled.',
    failure: 'Cannot load or update languages.'
  }
};
export type LanguageCopy = (typeof copy)['fr'];
export function languageCopy(locale: string | null): LanguageCopy {
  return locale?.toLowerCase().split('-')[0] === 'en' ? copy.en : copy.fr;
}
export function filterLanguages(
  rows: BoLanguage[],
  search: string,
  sortKey: string | null,
  order: 'asc' | 'desc',
  locale: string | null
): BoLanguage[] {
  const needle = search.trim().toLocaleLowerCase();
  const field = sortKey === 'iso_code' ? 'iso_code' : 'name';
  return [...rows]
    .filter((row) => `${row.name} ${row.iso_code}`.toLocaleLowerCase().includes(needle))
    .sort((a, b) =>
      a[field].localeCompare(b[field], locale || 'fr', { sensitivity: 'base' }) *
        (order === 'asc' ? 1 : -1)
    );
}

const translationLabels = {
  fr: {
    title: 'Traductions', intro: 'Éditez les textes par interface et par langue.',
    scope: 'Interface', language: 'Langue', editor: 'Éditeur', key: 'Clé',
    value: 'Traduction', export: 'Générer le fichier .ts', exportHint: 'Télécharge le dictionnaire TypeScript. La publication et la compilation restent à effectuer via Tooling.',
    entries: 'Traductions enregistrées', empty: 'Aucune traduction dans cette langue.',
    save: 'Enregistrer', saved: 'Traduction enregistrée.', clear: 'Effacer le formulaire',
    delete: 'Supprimer', deleted: 'Traduction supprimée.', edit: 'Éditer',
    generated: 'Fichier TypeScript généré.', confirmDelete: 'Supprimer cette traduction ?',
    session: 'Session expirée. Reconnectez-vous.', forbidden: 'Action interdite.',
    invalid: 'Clé ou valeur invalide.', notAvailable: 'Langue désactivée : activez-la ou placez-la en préouverture.',
    failure: 'Impossible de traiter les traductions.'
  },
  en: {
    title: 'Translations', intro: 'Edit text by interface and language.',
    scope: 'Interface', language: 'Language', editor: 'Editor', key: 'Key',
    value: 'Translation', export: 'Generate .ts file', exportHint: 'Downloads the TypeScript dictionary. Publishing and building remain tooling operations.',
    entries: 'Saved translations', empty: 'No translations for this language.',
    save: 'Save', saved: 'Translation saved.', clear: 'Clear form',
    delete: 'Delete', deleted: 'Translation deleted.', edit: 'Edit',
    generated: 'TypeScript file generated.', confirmDelete: 'Delete this translation?',
    session: 'Session expired. Please sign in.', forbidden: 'Action denied.',
    invalid: 'Invalid key or value.', notAvailable: 'Language is disabled: activate it or place it in pre-opening.',
    failure: 'Cannot process translations.'
  }
};
export type TranslationCopy = (typeof translationLabels)['fr'];
export function translationCopy(locale: string | null): TranslationCopy {
  return locale?.toLowerCase().split('-')[0] === 'en' ? translationLabels.en : translationLabels.fr;
}
