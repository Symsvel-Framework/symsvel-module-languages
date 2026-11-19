import { generatedTranslation } from '../../i18n/generated-translations';

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
    title: 'Langues', name: 'Langue', primary: 'Principale', translations: 'Traductions',
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
    title: 'Languages', name: 'Language', primary: 'Primary', translations: 'Translations',
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
  const base = locale?.toLowerCase().split('-')[0] === 'en' ? copy.en : copy.fr;
  return Object.fromEntries(Object.entries(base).map(([key, value]) => [
    key, generatedTranslation(locale, `bo.languages.${key}`) ?? value
  ])) as LanguageCopy;
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
    languageHint: "Choisissez la langue à traduire, même si une seule langue est active.",
    importExport: "Import / Export",
    loading: "Chargement des traductions…",
    actions: "Actions",
    deleteKey: "Supprimer la clé",
    keyDeleted: "Clé et traductions supprimées.",
    confirmDeleteKey: "Supprimer cette clé et ses traductions dans toutes les langues ? Les clés des modules seront recréées lors de leur prochaine synchronisation.",
    keyLocked: "L'identifiant de la clé ne peut pas être modifié.",
    cancel: "Annuler",
    title: 'Traductions', intro: 'Éditez les textes par interface et par langue.',
    scope: 'Interface', language: 'Langue', editor: 'Éditeur', key: 'Clé',
    value: 'Traduction', description: 'Description de la clé', status: 'État', missing: 'manquantes', complete: 'Traduite', fallback: 'texte de secours', createKey: 'Créer la clé', keyCreated: 'Clé créée.', export: 'Générer le fichier .ts', exportHint: 'Télécharge le dictionnaire TypeScript. La publication et la compilation restent à effectuer via Tooling.',
    entries: 'Traductions enregistrées', empty: 'Aucune traduction dans cette langue.',
    save: 'Enregistrer', saved: 'Traduction enregistrée.', clear: 'Effacer le formulaire',
    delete: 'Supprimer', deleted: 'Traduction supprimée.', edit: 'Éditer',
    generated: 'Fichier TypeScript généré.', confirmDelete: 'Supprimer cette traduction ?',
    session: 'Session expirée. Reconnectez-vous.', forbidden: 'Action interdite.',
    invalid: 'Clé ou valeur invalide.', notAvailable: 'Langue désactivée : activez-la ou placez-la en préouverture.',
    tsPublish: 'Écrire le .ts en local', tsPublished: 'Fichier mis à jour', tsUnchanged: 'Fichier déjà à jour.',
    csvExport: 'Exporter tout en CSV', csvExported: 'Catalogue CSV exporté.',
    csvImport: 'Importer les traductions (développement)', csvImportHint: 'Prévisualisez le CSV de production avant de l’appliquer. Les cellules vides et lignes absentes ne suppriment rien.',
    csvFile: 'Fichier CSV', csvTooLarge: 'CSV trop volumineux (maximum 2 Mio).',
    csvPreview: 'Prévisualiser', csvPreviewReady: 'Prévisualisation prête.',
    csvApply: 'Importer les changements', csvImported: 'Traductions importées.',
    csvRows: 'Lignes', csvKeysNew: 'Clés ajoutées', csvKeysUpdated: 'Descriptions mises à jour',
    csvTextsNew: 'Textes ajoutés', csvTextsUpdated: 'Textes remplacés', csvUnchanged: 'Sans changement',
    chooseModule: 'Choisir un module', backModules: 'Retour aux modules', system: 'Système', translatedCount: 'clés traduites', noKeys: 'Aucune clé déclarée', searchTranslations: 'Rechercher une traduction', missingOnly: 'Afficher uniquement les manquantes',
    failure: 'Impossible de traiter les traductions.'
  },
  en: {
    languageHint: "Choose the language to translate, even if only one language is active.",
    importExport: "Import / Export",
    loading: "Loading translations…",
    actions: "Actions",
    deleteKey: "Delete key",
    keyDeleted: "Key and translations deleted.",
    confirmDeleteKey: "Delete this key and translations in all languages? Module-supplied keys will be recreated on the next synchronization.",
    keyLocked: "The key identifier cannot be changed.",
    cancel: "Cancel",
    title: 'Translations', intro: 'Edit text by interface and language.',
    scope: 'Interface', language: 'Language', editor: 'Editor', key: 'Key',
    value: 'Translation', description: 'Key description', status: 'Status', missing: 'missing', complete: 'Translated', fallback: 'fallback text', createKey: 'Create key', keyCreated: 'Key created.', export: 'Generate .ts file', exportHint: 'Downloads the TypeScript dictionary. Publishing and building remain tooling operations.',
    entries: 'Saved translations', empty: 'No translations for this language.',
    save: 'Save', saved: 'Translation saved.', clear: 'Clear form',
    delete: 'Delete', deleted: 'Translation deleted.', edit: 'Edit',
    generated: 'TypeScript file generated.', confirmDelete: 'Delete this translation?',
    session: 'Session expired. Please sign in.', forbidden: 'Action denied.',
    invalid: 'Invalid key or value.', notAvailable: 'Language is disabled: activate it or place it in pre-opening.',
    tsPublish: 'Write local .ts', tsPublished: 'File updated', tsUnchanged: 'File already up to date.',
    csvExport: 'Export all as CSV', csvExported: 'Translation CSV exported.',
    csvImport: 'Import translations (development)', csvImportHint: 'Preview the production CSV before applying. Blank cells and omitted rows never delete data.',
    csvFile: 'CSV file', csvTooLarge: 'CSV too large (maximum 2 MiB).',
    csvPreview: 'Preview', csvPreviewReady: 'Preview ready.',
    csvApply: 'Apply changes', csvImported: 'Translations imported.',
    csvRows: 'Rows', csvKeysNew: 'Keys created', csvKeysUpdated: 'Descriptions updated',
    csvTextsNew: 'Texts created', csvTextsUpdated: 'Texts replaced', csvUnchanged: 'Unchanged',
    chooseModule: 'Choose a module', backModules: 'Back to modules', system: 'System', translatedCount: 'translated keys', noKeys: 'No declared keys', searchTranslations: 'Search translations', missingOnly: 'Show missing only',
    failure: 'Cannot process translations.'
  }
};
export type TranslationCopy = (typeof translationLabels)['fr'];
export function translationCopy(locale: string | null): TranslationCopy {
  const base = locale?.toLowerCase().split('-')[0] === 'en' ? translationLabels.en : translationLabels.fr;
  return Object.fromEntries(Object.entries(base).map(([key, value]) => [
    key, generatedTranslation(locale, `bo.languages.editor.${key}`) ?? value
  ])) as TranslationCopy;
}
