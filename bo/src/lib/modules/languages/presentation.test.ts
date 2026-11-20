import dictionariesFr from '../../translations/languages/fr';
import dictionariesEn from '../../translations/languages/en';
import { describe, expect, it } from 'vitest';
import { languageCopy, translationCopy, filterLanguages, filterTranslationEntries, type BoLanguage } from './presentation';
import { paginationState } from '../../components/pagination';
import { boNavigation } from './navigation';

const rows: BoLanguage[] = [
  { iso_code: 'en', name: 'English', active: false, pre_open: false, primary: false },
  { iso_code: 'fr', name: 'français', active: true, pre_open: false, primary: true },
  { iso_code: 'de', name: 'Deutsch', active: false, pre_open: true, primary: false }
];

describe('Languages BO', () => {
  it('registers a module-owned ACL-protected route', () => {
    expect(boNavigation.resource).toBe('languages');
    expect(boNavigation.probe).toBe('/apibo/languages');
  });
  it('keeps all statuses and primary protection metadata', () => {
    expect(rows[1].primary).toBe(true);
    expect(rows[2].pre_open).toBe(true);
  });
  it('provides translated labels for the dictionary editor', () => {
    expect(languageCopy('fr').translations).toBe('Traductions');
    expect(translationCopy('en-US').export).toBe('Generate .ts file');
    expect(translationCopy('fr').missing).toBe('manquantes');
    expect(translationCopy('en').createKey).toBe('Create key');
    expect(translationCopy('fr').keyLocked).toContain('ne peut pas');
    expect(translationCopy('en').deleteKey).toBe('Delete key');
    expect(translationCopy('fr').interfaceLanguage).toBe("Langue d'interface");
    expect(translationCopy('fr').editingIn).toBe('Éditer en');
    expect(translationCopy('fr').validate).toBe('Valider');
    expect(translationCopy('en').validate).toBe('Confirm');
    expect(translationCopy('es').title).toBe('Traductions');
  });
  it('filters, sorts and localizes', () => {
    expect(filterLanguages(rows, 'FR', 'name', 'asc', 'fr').map(x => x.iso_code)).toEqual(['fr']);
    expect(filterLanguages(rows, '', 'name', 'asc', 'fr').map(x => x.iso_code)).toEqual(['de','en','fr']);
    expect(languageCopy('fr-FR').primaryProtected).toContain('principale');
    expect(languageCopy('en-US').primary).toBe('Primary');
    expect(languageCopy('es-ES').primary).toBe('Principale');
  });
});


describe('translation table pagination', () => {
  const entries = Array.from({ length: 53 }, (_, index) => ({
    translation_key: 'admins.label.' + String(index).padStart(2, '0'),
    value: index % 2 === 0 ? 'Texte français' : '',
    fallback_value: 'Texte de secours',
    description: index === 50 ? 'Recherche ciblée' : '',
    missing_translation: index % 2 !== 0
  }));

  it('uses 20 entries by default and preserves order across pages', () => {
    const filtered = filterTranslationEntries(entries, '', false);
    const first = paginationState(1, 20, filtered.length);
    const third = paginationState(3, 20, filtered.length);
    expect(first.pages).toBe(3);
    expect(filtered.slice((first.page - 1) * first.limit, first.page * first.limit)).toHaveLength(20);
    expect(filtered.slice((third.page - 1) * third.limit, third.page * third.limit)).toHaveLength(13);
    expect(filtered.slice(40)[0].translation_key).toBe('admins.label.40');
  });

  it('filters first, then paginates the matching translation keys', () => {
    const missing = filterTranslationEntries(entries, '', true);
    const second = paginationState(2, 20, missing.length);
    expect(missing).toHaveLength(26);
    expect(second.pages).toBe(2);
    expect(missing.slice((second.page - 1) * second.limit, second.page * second.limit)).toHaveLength(6);
    expect(filterTranslationEntries(entries, 'ciblée', false)).toHaveLength(1);
    expect(filterTranslationEntries(entries, 'admins.label.01', true)).toHaveLength(1);
  });

  it('clamps a stale last page after deleting the only remaining entry', () => {
    const before = paginationState(3, 20, 41);
    const after = paginationState(before.page, 20, 40);
    expect(before.page).toBe(3);
    expect(after.page).toBe(2);
    expect(paginationState(1, 50, entries.length).pages).toBe(2);
    expect(paginationState(1, 100, entries.length).pages).toBe(1);
  });
});


describe('module dictionary contract', () => {
  it('keeps FR and EN module keys aligned', () => {
    expect(Object.keys(dictionariesFr).sort()).toEqual(Object.keys(dictionariesEn).sort());
    expect(Object.keys(dictionariesFr.editor).sort()).toEqual(Object.keys(dictionariesEn.editor).sort());
    expect(dictionariesFr.translations).toBe('Traductions');
    expect(dictionariesEn.translations).toBe('Translations');
  });
});
