<script lang="ts">
  import { getProjectBrand } from '../../../../../branding';
  const brandName = getProjectBrand();
  import { onMount } from 'svelte';
  import { goto } from '$app/navigation';
  import { resolve } from '$app/paths';
  import Button from '../../../../../components/Button.svelte';
  import Alert from '../../../../../components/Alert.svelte';
  import Modal from '../../../../../components/Modal.svelte';
  import Pagination from '../../../../../components/Pagination.svelte';
  import { paginationState, type PageSize } from '../../../../../components/pagination';
  import Dropdown from '../../../../../components/Dropdown.svelte';
  import { languageFlag } from '../../../../../components/dropdown';
  import { ApiError } from '../../../../../api/client';
  import { boApi } from '../../../../../api/session';
  import { uiLanguage } from '../../../../../api/locales';
  import { filterTranslationEntries, languageCopy, translationCopy, type BoLanguage } from '../../../presentation';

  type Translation = {
    scope: string; translation_key: string; language: string; value: string;
    description: string | null; fallback_value: string; missing_translation: boolean;
  };
  type ModuleSummary = { module: string; total: number; translated: number; missing: number; percent: number | null; scopes: ('bo' | 'app' | 'front')[] };
  type ModuleProgress = { language: string; modules: ModuleSummary[]; total: number; translated: number; missing: number; percent: number | null };
  type TsExport = { filename: string; content: string; entries: number };
  type CsvExport = { filename: string; content: string; rows: number };
  type CsvSummary = {
    rows: number; keys_created: number; keys_updated: number;
    texts_created: number; texts_updated: number; unchanged: number;
    sha256: string; applied: boolean;
  };

  const locale = $derived($uiLanguage ?? (typeof navigator === 'undefined' ? 'fr' : navigator.language));
  const t = $derived(translationCopy(locale));
  const interfaceCode = $derived(locale.trim().replace('_', '-').split('-')[0].toLowerCase());
  const base = '/apibo/languages/translations';

  let languages = $state<BoLanguage[]>([]);
  const languageOptions = $derived(languages.map((row) => ({
    value: row.iso_code, label: row.name,
    code: row.iso_code.toUpperCase(), icon: languageFlag(row.iso_code)
  })));
  let scope = $state<'bo' | 'app' | 'front'>('bo');
  let exportScope = $state<'bo' | 'app' | 'front'>('bo');
  let language = $state('');
  let entries = $state<Translation[]>([]);
  let progress = $state<ModuleProgress | null>(null);
  let selectedModule = $state('');
  const activeScopes = $derived(progress?.modules.find((module) => module.module === selectedModule)?.scopes ?? []);
  let search = $state('');
  let missingOnly = $state(false);
  let page = $state(1);
  let limit = $state<PageSize>(20);
  const filteredEntries = $derived(filterTranslationEntries(entries, search, missingOnly));
  const pagination = $derived(paginationState(page, limit, filteredEntries.length));
  const visibleEntries = $derived(filteredEntries.slice(
    (pagination.page - 1) * pagination.limit,
    pagination.page * pagination.limit
  ));
  let key = $state('');
  let value = $state('');
  let description = $state('');
  let editorOpen = $state(false);
  let editingKey = $state('');
  let editValue = $state('');
  let editDescription = $state('');
  let savingRow = $state('');
  let canDeleteKeys = $state(false);
  let moduleLoadId = 0;
  let entryLoadId = 0;
  let csvCanExport = $state(false);
  let csvCanImport = $state(false);
  let canPublish = $state(false);
  let csvContent = $state('');
  let csvName = $state('');
  let csvPreview = $state<CsvSummary | null>(null);
  const missing = $derived(entries.filter((entry) => entry.missing_translation).length);
  let busy = $state(false);
  let error = $state('');
  let success = $state('');

  function explain(cause: unknown): string {
    if (cause instanceof ApiError) {
      if (cause.status === 401) {
        void goto(resolve('/login'), { replaceState: true });
        return t.session;
      }
      if (cause.status === 403) return t.forbidden;
      if (cause.status === 409) return t.notAvailable;
      if (cause.status === 400) return t.invalid;
    }
    return t.failure;
  }

  function params(includeKey = false): string {
    const baseParams = `scope=${encodeURIComponent(scope)}&language=${encodeURIComponent(language)}`;
    const query = selectedModule ? `${baseParams}&module=${encodeURIComponent(selectedModule)}` : baseParams;
    return includeKey ? `${query}&key=${encodeURIComponent(ownedKey())}` : query;
  }

  function ownedKey(): string {
    const typed = key.trim();
    return typed.startsWith(selectedModule + '.') ? typed : `${selectedModule}.${typed}`;
  }

  async function loadModules(): Promise<void> {
    const id = ++moduleLoadId;
    const requestedLanguage = language;
    if (!requestedLanguage) { progress = null; return; }
    try {
      const result = await boApi<{ data: ModuleProgress }>(
        base + '/modules?language=' + encodeURIComponent(requestedLanguage)
      );
      if (id === moduleLoadId && requestedLanguage === language) progress = result.data;
    } catch (cause) {
      if (id === moduleLoadId) error = explain(cause);
    }
  }

  async function openModule(module: ModuleSummary): Promise<void> {
    selectedModule = module.module;
    scope = module.scopes[0] ?? 'bo';
    search = '';
    missingOnly = false;
    page = 1;
    key = '';
    value = '';
    description = '';
    editingKey = '';
    await loadEntries();
  }

  function chooseLanguage(code: string): void {
    language = code;
    selectedModule = '';
    page = 1;
    entries = [];
    editingKey = '';
    void loadModules();
  }

  async function loadLanguages(): Promise<void> {
    busy = true;
    error = '';
    try {
      const result = await boApi<{ data: BoLanguage[] }>('/apibo/languages/available');
      languages = result.data;
      language = result.data.find((row) => row.primary)?.iso_code ?? result.data[0]?.iso_code ?? '';
      if (language) await loadModules();
      const caps = await boApi<{ data: { can_export: boolean; can_import: boolean; can_publish: boolean; can_delete_keys?: boolean } }>(`${base}/csv-capabilities`);
      csvCanExport = caps.data.can_export;
      csvCanImport = caps.data.can_import;
      canPublish = caps.data.can_publish;
      canDeleteKeys = caps.data.can_delete_keys === true;
    } catch (cause) {
      error = explain(cause);
    } finally {
      busy = false;
    }
  }

  async function loadEntries(): Promise<void> {
    const id = ++entryLoadId;
    const requestedLanguage = language;
    const requestedScope = scope;
    const requestedModule = selectedModule;
    if (!requestedLanguage || !requestedModule) { entries = []; return; }
    busy = true;
    error = '';
    try {
      const result = await boApi<{ data: Translation[] }>(base + '?' + params());
      if (id === entryLoadId && requestedLanguage === language &&
          requestedScope === scope && requestedModule === selectedModule) {
        entries = result.data;
      }
    } catch (cause) {
      if (id === entryLoadId) error = explain(cause);
    } finally {
      if (id === entryLoadId) busy = false;
    }
  }

  function newKey(): void {
    key = '';
    value = '';
    description = '';
    error = '';
    editorOpen = true;
  }

  function edit(entry: Translation): void {
    if (editingKey || busy || savingRow) return;
    editingKey = entry.translation_key;
    editValue = entry.value;
    editDescription = entry.description ?? '';
    error = '';
    success = '';
  }

  function cancelEdit(): void {
    if (savingRow) return;
    editingKey = '';
    editValue = '';
    editDescription = '';
  }

  async function saveInline(entry: Translation): Promise<void> {
    if (editingKey !== entry.translation_key || savingRow || busy) return;
    const nextValue = editValue;
    const nextDescription = editDescription.trim() || null;
    const requestedLanguage = language;
    const requestedScope = scope;
    const requestedModule = selectedModule;
    savingRow = entry.translation_key;
    error = '';
    success = '';
    try {
      await boApi(base + '/keys', {
        method: 'PATCH',
        body: JSON.stringify({
          scope: requestedScope, language: requestedLanguage,
          key: entry.translation_key, description: nextDescription, value: nextValue
        })
      });
      if (scope === requestedScope && language === requestedLanguage &&
          selectedModule === requestedModule) {
        entries = entries.map((item) => item.translation_key === entry.translation_key
          ? {
              ...item, description: nextDescription, value: nextValue,
              missing_translation: nextValue.trim() === ''
            }
          : item);
        cancelEditAfterSave();
        await loadModules();
      }
      success = t.saved;
    } catch (cause) {
      error = explain(cause);
    } finally {
      savingRow = '';
    }
  }

  function cancelEditAfterSave(): void {
    editingKey = '';
    editValue = '';
    editDescription = '';
  }

  async function saveEditor(): Promise<void> {
    if (!language || !key.trim() || busy) return;
    busy = true;
    error = '';
    success = '';
    try {
      await boApi(base + '/keys', {
        method: 'POST',
        body: JSON.stringify({
          scope, key: ownedKey(), description: description.trim() || null,
          language, value
        })
      });
      editorOpen = false;
      await loadEntries();
      await loadModules();
      success = t.keyCreated;
    } catch (cause) {
      error = explain(cause);
    } finally {
      busy = false;
    }
  }

  async function removeKey(entry: Translation): Promise<void> {
    if (!canDeleteKeys || busy || savingRow || !confirm(t.confirmDeleteKey)) return;
    busy = true;
    error = '';
    success = '';
    try {
      const query = 'scope=' + encodeURIComponent(scope) + '&key=' + encodeURIComponent(entry.translation_key);
      await boApi(base + '/keys?' + query, { method: 'DELETE' });
      entries = entries.filter((row) => row.translation_key !== entry.translation_key);
      if (editingKey === entry.translation_key) cancelEditAfterSave();
      await loadModules();
      success = t.keyDeleted;
    } catch (cause) {
      error = explain(cause);
    } finally {
      busy = false;
    }
  }

  async function download(): Promise<void> {
    if (!language || busy) return;
    busy = true;
    error = '';
    success = '';
    try {
      const result = await boApi<{ data: TsExport }>(`${base}/export?scope=${encodeURIComponent(exportScope)}&language=${encodeURIComponent(language)}`);
      saveBlob(result.data.content, result.data.filename, 'text/typescript;charset=utf-8');
      success = t.generated;
    } catch (cause) {
      error = explain(cause);
    } finally {
      busy = false;
    }
  }

  function saveBlob(content: string, filename: string, type: string): void {
    const blob = new Blob([content], { type });
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = filename;
    document.body.appendChild(anchor);
    anchor.click();
    anchor.remove();
    URL.revokeObjectURL(url);
  }

  async function publishTs(): Promise<void> {
    if (!canPublish || busy || !language) return;
    busy = true;
    error = '';
    success = '';
    try {
      const result = await boApi<{ data: { file: string; changed: boolean; entries: number } }>(
        `${base}/publish`,
        { method: 'POST', body: JSON.stringify({ scope: exportScope, language }) }
      );
      success = result.data.changed ? `${t.tsPublished}: ${result.data.file}` : t.tsUnchanged;
    } catch (cause) {
      error = explain(cause);
    } finally {
      busy = false;
    }
  }

  async function exportCsv(): Promise<void> {
    if (busy) return;
    busy = true;
    error = '';
    success = '';
    try {
      const result = await boApi<{ data: CsvExport }>(`${base}/export/csv`);
      saveBlob(result.data.content, result.data.filename, 'text/csv;charset=utf-8');
      success = t.csvExported;
    } catch (cause) {
      error = explain(cause);
    } finally {
      busy = false;
    }
  }

  async function selectCsv(event: Event): Promise<void> {
    const file = (event.currentTarget as HTMLInputElement).files?.[0];
    csvContent = '';
    csvPreview = null;
    csvName = '';
    if (!file) return;
    if (file.size > 2097152) {
      error = t.csvTooLarge;
      return;
    }
    csvContent = await file.text();
    csvName = file.name;
    error = '';
    success = '';
  }

  async function importCsv(mode: 'preview' | 'apply'): Promise<void> {
    if (!csvCanImport || busy || !csvContent) return;
    if (mode === 'apply' && !csvPreview) return;
    busy = true;
    error = '';
    success = '';
    try {
      const result = await boApi<{ data: CsvSummary }>(`${base}/import/csv`, {
        method: 'POST',
        body: JSON.stringify({
          csv: csvContent, mode,
          ...(mode === 'apply' ? { sha256: csvPreview?.sha256 } : {})
        })
      });
      csvPreview = result.data;
      if (mode === 'apply') {
        csvContent = '';
        csvName = '';
        csvPreview = null;
        if (selectedModule) await loadEntries();
        await loadModules();
      }
      success = mode === 'apply' ? t.csvImported : t.csvPreviewReady;
    } catch (cause) {
      error = explain(cause);
    } finally {
      busy = false;
    }
  }

  onMount(() => { void loadLanguages(); });
</script>

<svelte:head><title>{t.title} | {$brandName} BO</title></svelte:head>

<div class="page">
  <header class="page-heading">
    <div><h1>{t.title}</h1><p class="hint">{t.intro}</p></div>
    <Button type="button" variant="secondary" onclick={() => goto(resolve('/languages'))}>{languageCopy(locale).title}</Button>
  </header>
  {#if error}<Alert kind="error">{error}</Alert>{/if}
  {#if success}<Alert kind="success">{success}</Alert>{/if}

  <section class="language-bar" aria-label={t.language}>
    <div class="language-bar__rows">
      <div class="language-bar__row">
        <span class="language-bar__label">{t.interfaceLanguage}</span>
        <span class="language-bar__value">
          <span aria-hidden="true">{languageFlag(interfaceCode)}</span>
          <strong>{interfaceCode.toUpperCase()}</strong>
        </span>
      </div>
      <div class="language-bar__row">
        <span class="language-bar__label">{t.editingIn}</span>
        <span class="language-bar__value">
          <span aria-hidden="true">{language ? languageFlag(language) : '🌐'}</span>
          <strong>{language ? language.toUpperCase() : '—'}</strong>
        </span>
      </div>
    </div>
    <div class="language-bar__switcher">
      <Dropdown value={language} options={languageOptions} label={t.language}
        onChange={chooseLanguage} disabled={busy || !!savingRow || !!editingKey} />
    </div>
  </section>

  <section class="panel transfer-block">
    <h2>{t.importExport}</h2>
    <div class="actions">
      <label>{t.scope}
        <div class="transfer-scope">
          <Dropdown value={exportScope} options={[
            { value: 'bo', label: 'BO' },
            { value: 'app', label: 'App' },
            { value: 'front', label: 'Front' }
          ]} label={t.scope}
            onChange={(code) => (exportScope = code as 'bo' | 'app' | 'front')}
            disabled={busy} />
        </div>
      </label>
      <Button type="button" variant="secondary" onclick={download} disabled={busy || !language}>{t.export}</Button>
      {#if canPublish}
        <Button type="button" variant="secondary" onclick={publishTs} disabled={busy || !language}>{t.tsPublish}</Button>
      {/if}
      {#if csvCanExport}
        <Button type="button" variant="secondary" onclick={exportCsv} disabled={busy}>{t.csvExport}</Button>
      {/if}
    </div>
    {#if csvCanImport}
      <details class="csv-import">
        <summary>{t.csvImport}</summary>
        <p class="hint">{t.csvImportHint}</p>
        <label>{t.csvFile}<input type="file" accept=".csv,text/csv" onchange={(event) => { void selectCsv(event); }} /></label>
        {#if csvName}<p>{csvName}</p>{/if}
        <div class="actions">
          <Button type="button" variant="secondary" onclick={() => importCsv('preview')} disabled={busy || !csvContent}>{t.csvPreview}</Button>
          <Button type="button" onclick={() => importCsv('apply')} disabled={busy || !csvPreview || csvPreview.applied}>{t.csvApply}</Button>
        </div>
        {#if csvPreview}
          <p role="status">{t.csvRows}: {csvPreview.rows},
            {t.csvKeysNew}: {csvPreview.keys_created},
            {t.csvKeysUpdated}: {csvPreview.keys_updated},
            {t.csvTextsNew}: {csvPreview.texts_created},
            {t.csvTextsUpdated}: {csvPreview.texts_updated},
            {t.csvUnchanged}: {csvPreview.unchanged}</p>
        {/if}
      </details>
    {/if}
  </section>

  {#if !selectedModule}
    <section class="panel">
      <h2>{t.chooseModule}</h2>
      {#if progress}
        <p class="hint">{progress.translated} / {progress.total} {t.translatedCount}
          {#if progress.percent !== null} ({progress.percent}%){/if}
        </p>
        <div class="module-grid">
          {#each progress.modules as item (item.module)}
            <button type="button" class="module-card" onclick={() => { void openModule(item); }}>
              <strong>{item.module === 'system' ? t.system : item.module}</strong>
              <span>{item.translated} / {item.total} {t.translatedCount} · {item.missing} {t.missing}</span>
              <progress value={item.translated} max={Math.max(1, item.total)}></progress>
              <span class="percent">{item.percent === null ? t.noKeys : item.percent + '%'}</span>
            </button>
          {/each}
        </div>
      {:else}
        <p class="hint">{busy ? t.loading : t.empty}</p>
      {/if}
    </section>
  {:else}
    <section class="panel">
      <div class="module-heading">
        <div>
          <Button type="button" variant="secondary" onclick={() => { selectedModule = ''; entries = []; editingKey = ''; }} disabled={!!editingKey || !!savingRow || busy}>{t.backModules}</Button>
          <h2>{selectedModule === 'system' ? t.system : selectedModule}</h2>
          <p class="hint">{entries.length} {t.entries} · {missing} {t.missing}</p>
        </div>
        <div class="actions">
          {#if activeScopes.length > 1}
            <label>{t.scope}
              <div class="module-scope">
                <Dropdown value={scope} options={activeScopes.map((item) => ({
                  value: item, label: item.toUpperCase()
                }))} label={t.scope}
                  onChange={(code) => {
                    scope = code as 'bo' | 'app' | 'front';
                    editingKey = '';
                    page = 1;
                    void loadEntries();
                  }}
                  disabled={busy || !!savingRow || !!editingKey} />
              </div>
            </label>
          {:else if activeScopes.length === 1}
            <span>{activeScopes[0].toUpperCase()}</span>
          {/if}
          <Button type="button" onclick={newKey} disabled={busy || !!savingRow || !!editingKey}>{t.createKey}</Button>
        </div>
      </div>
      <div class="entry-filters">
        <label>{t.searchTranslations}<input type="search" bind:value={search} oninput={() => { page = 1; }} placeholder={t.searchTranslations} disabled={!!editingKey} /></label>
        <label class="checkbox-filter"><input type="checkbox" bind:checked={missingOnly} onchange={() => { page = 1; }} disabled={!!editingKey} /> {t.missingOnly}</label>
      </div>
      {#if entries.length === 0}
        <p class="hint">{t.empty}</p>
      {:else}
        <div class="table-wrap">
          <table>
            <thead><tr><th>{t.key}</th><th>{t.description}</th><th>{t.value}</th><th>{t.status}</th><th>{t.actions}</th></tr></thead>
            <tbody>
              {#each visibleEntries as entry (entry.translation_key)}
                <tr>
                  <td class="key-cell"><code>{entry.translation_key}</code></td>
                  <td class="explanation-cell">
                    {#if editingKey === entry.translation_key}
                      <input type="text" bind:value={editDescription} maxlength="4096"
                        aria-label={t.description + ': ' + entry.translation_key}
                        disabled={savingRow === entry.translation_key} />
                    {:else}
                      {entry.description || '—'}
                    {/if}
                  </td>
                  <td class="value-cell">
                    {#if editingKey === entry.translation_key}
                      <textarea rows="2" bind:value={editValue} maxlength="8192"
                        aria-label={t.value + ': ' + entry.translation_key}
                        disabled={savingRow === entry.translation_key}></textarea>
                    {:else}
                      <span class="translation-text">{entry.value || '—'}</span>
                    {/if}
                    {#if entry.fallback_value && (entry.missing_translation || language !== 'fr')}
                      <small>{t.fallback}: {entry.fallback_value}</small>
                    {/if}
                  </td>
                  <td><span class:missing={entry.missing_translation} class="status">{entry.missing_translation ? t.missing : t.complete}</span></td>
                  <td class="row-actions">
                    {#if editingKey === entry.translation_key}
                      <Button type="button" onclick={() => { void saveInline(entry); }}
                        loading={savingRow === entry.translation_key} disabled={busy || !!savingRow}>{t.validate}</Button>
                      <Button type="button" variant="secondary" onclick={cancelEdit}
                        disabled={busy || !!savingRow}>{t.cancel}</Button>
                    {:else}
                      <Button type="button" variant="secondary" onclick={() => edit(entry)}
                        disabled={busy || !!savingRow || !!editingKey}>{t.edit}</Button>
                    {/if}
                    {#if canDeleteKeys}
                      <Button type="button" variant="danger" onclick={() => { void removeKey(entry); }}
                        disabled={busy || !!savingRow || !!editingKey}>{t.deleteKey}</Button>
                    {/if}
                  </td>
                </tr>
              {/each}
            </tbody>
          </table>
        </div>
        <Pagination
          page={pagination.page}
          limit={pagination.limit}
          total={pagination.total}
          loading={busy || !!savingRow || !!editingKey}
          onPageChange={(next) => { page = next; }}
          onLimitChange={(next) => { limit = next; page = 1; }}
        />
      {/if}
    </section>
  {/if}

  <Modal open={editorOpen} title={t.createKey} onClose={() => { editorOpen = false; }}>
    <form class="modal-form" onsubmit={(event) => { event.preventDefault(); void saveEditor(); }}>
      <div class="modal-columns">
        <label>{t.scope}<input value={scope.toUpperCase()} readonly /></label>
        <label>{t.key}<input bind:value={key} required maxlength="190" placeholder="create.success" /></label>
      </div>
      <label>{t.description}<textarea bind:value={description} rows="3" maxlength="4096"></textarea></label>
      <label>{t.value} ({language.toUpperCase()})<textarea bind:value={value} rows="4" maxlength="8192"></textarea></label>
      <div class="actions modal-actions">
        <Button type="button" variant="secondary" onclick={() => { editorOpen = false; }} disabled={busy}>{t.cancel}</Button>
        <Button type="submit" loading={busy} disabled={busy || !key.trim()}>{t.createKey}</Button>
      </div>
    </form>
  </Modal>
</div>

<style>
  .page { width: 100%; max-width: 1400px; margin: 0 auto; display: grid; gap: 20px; }
  /* HP AdminLanguageBar / LanguageContextBanner: exact layout proportions,
     using SymSvel Design System colors for both themes. */
  .language-bar {
    display: flex; align-items: center; justify-content: space-between; gap: 18px;
    flex-wrap: wrap; padding: 14px 18px;
    border: 1px solid var(--bo-border, #d4e0e6); border-radius: 18px;
    background: var(--bo-surface, #fff); color: var(--bo-text, #182b36);
    box-shadow: var(--bo-shadow, 0 10px 28px rgba(0, 0, 0, 0.14));
  }
  .language-bar__rows { flex: 1 1 420px; min-width: 280px; display: grid; gap: 10px; }
  .language-bar__row { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
  .language-bar__label {
    min-width: 180px; font-size: 13px; font-weight: 800;
    color: var(--bo-muted, #667085); text-transform: uppercase; letter-spacing: 0.05em;
  }
  .language-bar__value { display: inline-flex; align-items: center; gap: 8px; color: var(--bo-text, #182b36); }
  .language-bar__value strong { font-size: 15px; }
  .language-bar__switcher { width: 260px; min-width: min(100%, 260px); }
  .transfer-scope { width: 145px; }
  .module-scope { width: 140px; }

  .page-heading, .module-heading {
    display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;
  }
  .page-heading h1 { margin: 0 0 6px; font-size: clamp(1.5rem, 3vw, 2rem); }
  h2 { margin: 0 0 12px; font-size: 1.2rem; }
  .module-heading h2 { margin: 12px 0 4px; }
  .hint { color: var(--bo-muted, #667085); margin: 6px 0 12px; }
  .panel {
    min-width: 0; padding: 22px; border: 1px solid var(--bo-border, #d4e0e6);
    border-radius: 14px; background: var(--bo-surface, #fff); color: var(--bo-text, #182b36);
  }
  label { display: grid; gap: 6px; font-size: 0.9rem; font-weight: 650; }
  select, input, textarea {
    width: 100%; min-width: 0; padding: 10px 12px;
    border: 1px solid var(--bo-border, #d4e0e6); border-radius: 8px;
    background: var(--bo-input, #fff); color: var(--bo-text, #182b36); font: inherit;
  }
  input[readonly] { opacity: 0.8; cursor: not-allowed; }
  input:focus-visible, select:focus-visible, textarea:focus-visible, button:focus-visible {
    outline: 2px solid var(--bo-focus, #148fa4); outline-offset: 2px;
  }
  .actions { display: flex; gap: 10px; align-items: end; flex-wrap: wrap; }
  .transfer-block .actions { margin-bottom: 10px; }
  .transfer-block .actions label { width: 140px; }
  .csv-import { border-top: 1px solid var(--bo-border, #d4e0e6); margin-top: 16px; padding-top: 14px; }
  .csv-import summary { cursor: pointer; color: var(--bo-primary, #087d92); font-weight: 700; }
  .csv-import label { margin: 12px 0; max-width: 450px; }
  .csv-import .actions label { width: auto; }
  .module-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 12px; }
  .module-card {
    display: grid; gap: 10px; text-align: left; padding: 16px; cursor: pointer;
    border: 1px solid var(--bo-border, #d4e0e6); border-radius: 12px;
    background: var(--bo-surface-muted, #f8fafb); color: var(--bo-text, #182b36);
  }
  .module-card:hover, .module-card:focus-visible { border-color: var(--bo-primary, #087d92); }
  .module-card span { color: var(--bo-muted, #667085); font-size: 0.85rem; }
  .module-card strong { font-size: 1rem; }
  .module-card .percent { font-weight: 750; color: var(--bo-primary, #087d92); }
  .module-card progress { width: 100%; height: 8px; accent-color: var(--bo-primary, #087d92); }
  .entry-filters { display: flex; align-items: end; flex-wrap: wrap; gap: 16px; margin: 20px 0; }
  .entry-filters label:first-child { width: min(100%, 400px); }
  .checkbox-filter { display: flex; align-items: center; gap: 8px; }
  .checkbox-filter input { width: auto; }
  .table-wrap { overflow-x: auto; }
  table { border-collapse: collapse; width: 100%; }
  th { background: var(--bo-surface-muted, #f8fafb); color: var(--bo-muted, #667085); font-size: 0.85rem; }
  th, td { padding: 12px 10px; border-bottom: 1px solid var(--bo-border, #d4e0e6); text-align: left; vertical-align: middle; }
  .key-cell { width: 23%; max-width: 260px; overflow-wrap: anywhere; }
  .key-cell code { font-size: 0.8rem; }
  .explanation-cell { width: 22%; color: var(--bo-muted, #667085); overflow-wrap: anywhere; }
  .value-cell { width: 36%; min-width: 240px; }
  .value-cell textarea { resize: vertical; }
  .value-cell small { display: block; color: var(--bo-muted, #667085); margin-top: 5px; }
  .status {
    display: inline-block; padding: 3px 9px; border-radius: 99px;
    background: var(--bo-success-bg, #e5f5ed); color: var(--bo-success-text, #146647);
    font-weight: 700; font-size: 0.75rem;
  }
  .status.missing { background: var(--bo-error-bg, #fcecef); color: var(--bo-error-text, #922d39); }
  .row-actions { white-space: nowrap; }
  .row-actions :global(.bo-button) { margin: 3px 4px 3px 0; }
  .modal-form { display: grid; gap: 16px; }
  .modal-columns { display: grid; grid-template-columns: 1fr 2fr; gap: 12px; }
  .modal-actions { justify-content: flex-end; border-top: 1px solid var(--bo-border, #d4e0e6); padding-top: 16px; }
  @media (max-width: 780px) {
    .language-bar { align-items: stretch; }
    .language-bar__rows { min-width: 0; flex-basis: 100%; }
    .language-bar__label { min-width: 0; width: 170px; }
    .language-bar__switcher { width: 100%; }
    .panel { padding: 14px; }
    .module-heading { align-items: flex-start; }
    .modal-columns { grid-template-columns: 1fr; }
    .row-actions { white-space: normal; }
  }
</style>
