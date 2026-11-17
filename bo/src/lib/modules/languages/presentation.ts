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
