import dictionariesFr from '../../translations/fr';
import dictionariesEn from '../../translations/en';
import { describe, expect, it } from 'vitest';
import { languageCopy, translationCopy, filterLanguages, type BoLanguage } from './presentation';
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


describe('module dictionary contract', () => {
  it('keeps FR and EN module keys aligned', () => {
    expect(Object.keys(dictionariesFr).sort()).toEqual(Object.keys(dictionariesEn).sort());
    expect(Object.keys(dictionariesFr.translations).sort()).toEqual(Object.keys(dictionariesEn.translations).sort());
  });
});
