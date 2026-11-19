<script lang="ts">
  import { onMount } from 'svelte';
  import { goto } from '$app/navigation';
  import { resolve } from '$app/paths';
  import Button from '../../../../../components/Button.svelte';
  import Alert from '../../../../../components/Alert.svelte';
  import Modal from '../../../../../components/Modal.svelte';
  import { ApiError } from '../../../../../api/client';
  import { boApi } from '../../../../../api/session';
  import { uiLanguage } from '../../../../../api/locales';
  import { languageCopy, translationCopy, type BoLanguage } from '../../../presentation';

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
  const base = '/apibo/languages/translations';

  let languages = $state<BoLanguage[]>([]);
  let scope = $state<'bo' | 'app' | 'front'>('bo');
  let exportScope = $state<'bo' | 'app' | 'front'>('bo');
  let language = $state('');
  let entries = $state<Translation[]>([]);
  let progress = $state<ModuleProgress | null>(null);
  let selectedModule = $state('');
  const activeScopes = $derived(progress?.modules.find((module) => module.module === selectedModule)?.scopes ?? []);
  let search = $state('');
  let missingOnly = $state(false);
  const filteredEntries = $derived(entries.filter((entry) =>
    (!missingOnly || entry.missing_translation) &&
    (entry.translation_key.toLowerCase().includes(search.toLowerCase()) ||
      (entry.value || entry.fallback_value).toLowerCase().includes(search.toLowerCase()))
  ));
  let key = $state('');
  let value = $state('');
  let description = $state('');
  let editorOpen = $state(false);
  let editingExisting = $state(false);
  let drafts = $state<Record<string, string>>({});
  let savingRow = $state('');
  let canDeleteKeys = $state(false);
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
    if (!language) { progress = null; return; }
    try {
      const result = await boApi<{ data: ModuleProgress }>(`${base}/modules?language=${encodeURIComponent(language)}`);
      progress = result.data;
    } catch (cause) { error = explain(cause); }
  }

  async function openModule(module: ModuleSummary): Promise<void> {
    selectedModule = module.module;
    scope = module.scopes[0] ?? 'bo';
    search = '';
    missingOnly = false;
    key = '';
    value = '';
    description = '';
    drafts = {};
    await loadEntries();
  }

  function chooseLanguage(code: string): void {
    language = code;
    selectedModule = '';
    entries = [];
    drafts = {};
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
    if (!language || !selectedModule) { entries = []; return; }
    busy = true;
    error = '';
    try {
      const result = await boApi<{ data: Translation[] }>(`${base}?${params()}`);
      entries = result.data;
    } catch (cause) {
      error = explain(cause);
    } finally {
      busy = false;
    }
  }

  function newKey(): void {
    editingExisting = false;
    key = '';
    value = '';
    description = '';
    error = '';
    editorOpen = true;
  }

  function edit(entry: Translation): void {
    editingExisting = true;
    key = entry.translation_key;
    value = entry.value;
    description = entry.description ?? '';
    error = '';
    editorOpen = true;
  }

  async function saveInline(entry: Translation): Promise<void> {
    const next = drafts[entry.translation_key];
    if (next === undefined || next === entry.value || savingRow || busy) return;
    const expectedLanguage = language;
    const expectedScope = scope;
    const expectedModule = selectedModule;
    savingRow = entry.translation_key;
    error = '';
    success = '';
    try {
      await boApi(base, {
        method: 'PUT',
        body: JSON.stringify({
          scope: expectedScope, language: expectedLanguage,
          key: entry.translation_key, value: next
        })
      });
      if (scope === expectedScope && language === expectedLanguage && selectedModule === expectedModule) {
        entries = entries.map((item) => item.translation_key === entry.translation_key
          ? { ...item, value: next, missing_translation: next.trim() === '' } : item);
        const pending = { ...drafts };
        delete pending[entry.translation_key];
        drafts = pending;
        await loadModules();
      }
      success = t.saved;
    } catch (cause) {
      error = explain(cause);
    } finally {
      savingRow = '';
    }
  }

  async function saveEditor(): Promise<void> {
    if (!language || !key.trim() || busy) return;
    busy = true;
    error = '';
    success = '';
    const fullKey = ownedKey();
    try {
      if (editingExisting) {
        await boApi(base + '/keys', {
          method: 'PATCH',
          body: JSON.stringify({
            scope, key: fullKey, language, description: description.trim() || null, value
          })
        });
      } else {
        await boApi(base + '/keys', {
          method: 'POST',
          body: JSON.stringify({
            scope, key: fullKey, description: description.trim() || null,
            language, value
          })
        });
      }
      editorOpen = false;
      await loadEntries();
      await loadModules();
      success = editingExisting ? t.saved : t.keyCreated;
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
      const pending = { ...drafts };
      delete pending[entry.translation_key];
      drafts = pending;
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

<svelte:head><title>{t.title} | SymSvel BO</title></svelte:head>

<div class="page">
  <header class="page-heading">
    <div><h1>{t.title}</h1><p class="hint">{t.intro}</p></div>
    <Button type="button" variant="secondary" onclick={() => goto(resolve('/languages'))}>{languageCopy(locale).title}</Button>
  </header>
  {#if error}<Alert kind="error">{error}</Alert>{/if}
  {#if success}<Alert kind="success">{success}</Alert>{/if}

  <section class="panel language-block">
    <h2>{t.language}</h2>
    <p class="hint">{t.languageHint}</p>
    <label>{t.language}
      <select bind:value={language} onchange={(event) => chooseLanguage(event.currentTarget.value)} disabled={busy || !!savingRow}>
        {#each languages as row (row.iso_code)}
          <option value={row.iso_code}>{row.name} ({row.iso_code.toUpperCase()})</option>
        {/each}
      </select>
    </label>
  </section>

  <section class="panel transfer-block">
    <h2>{t.importExport}</h2>
    <div class="actions">
      <label>{t.scope}
        <select bind:value={exportScope} disabled={busy}>
          <option value="bo">BO</option><option value="app">App</option><option value="front">Front</option>
        </select>
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
          <Button type="button" variant="secondary" onclick={() => { selectedModule = ''; entries = []; drafts = {}; }}>{t.backModules}</Button>
          <h2>{selectedModule === 'system' ? t.system : selectedModule}</h2>
          <p class="hint">{entries.length} {t.entries} · {missing} {t.missing}</p>
        </div>
        <div class="actions">
          {#if activeScopes.length > 1}
            <label>{t.scope}
              <select bind:value={scope} onchange={(event) => {
                scope = event.currentTarget.value as 'bo' | 'app' | 'front';
                drafts = {};
                void loadEntries();
              }} disabled={busy || !!savingRow}>
                {#each activeScopes as item (item)}
                  <option value={item}>{item.toUpperCase()}</option>
                {/each}
              </select>
            </label>
          {:else if activeScopes.length === 1}
            <span>{activeScopes[0].toUpperCase()}</span>
          {/if}
          <Button type="button" onclick={newKey} disabled={busy || !!savingRow}>{t.createKey}</Button>
        </div>
      </div>
      <div class="entry-filters">
        <label>{t.searchTranslations}<input type="search" bind:value={search} placeholder={t.searchTranslations} /></label>
        <label class="checkbox-filter"><input type="checkbox" bind:checked={missingOnly} /> {t.missingOnly}</label>
      </div>
      {#if entries.length === 0}
        <p class="hint">{t.empty}</p>
      {:else}
        <div class="table-wrap">
          <table>
            <thead><tr><th>{t.key}</th><th>{t.description}</th><th>{t.value}</th><th>{t.status}</th><th>{t.actions}</th></tr></thead>
            <tbody>
              {#each filteredEntries as entry (entry.translation_key)}
                <tr>
                  <td class="key-cell"><code>{entry.translation_key}</code></td>
                  <td class="explanation-cell">{entry.description || '—'}</td>
                  <td class="value-cell">
                    <textarea rows="2" aria-label={t.value + ': ' + entry.translation_key}
                      value={drafts[entry.translation_key] ?? entry.value}
                      oninput={(event) => {
                        drafts = { ...drafts, [entry.translation_key]: event.currentTarget.value };
                      }}
                      disabled={busy || (!!savingRow && savingRow !== entry.translation_key)}></textarea>
                    {#if entry.missing_translation && entry.fallback_value}
                      <small>{t.fallback}: {entry.fallback_value}</small>
                    {/if}
                  </td>
                  <td><span class:missing={entry.missing_translation} class="status">{entry.missing_translation ? t.missing : t.complete}</span></td>
                  <td class="row-actions">
                    {#if drafts[entry.translation_key] !== undefined && drafts[entry.translation_key] !== entry.value}
                      <Button type="button" onclick={() => { void saveInline(entry); }}
                        loading={savingRow === entry.translation_key} disabled={busy || (!!savingRow && savingRow !== entry.translation_key)}>{t.save}</Button>
                    {/if}
                    <Button type="button" variant="secondary" onclick={() => edit(entry)} disabled={busy || !!savingRow}>{t.edit}</Button>
                    {#if canDeleteKeys}
                      <Button type="button" variant="danger" onclick={() => { void removeKey(entry); }} disabled={busy || !!savingRow}>{t.deleteKey}</Button>
                    {/if}
                  </td>
                </tr>
              {/each}
            </tbody>
          </table>
        </div>
      {/if}
    </section>
  {/if}

  <Modal open={editorOpen} title={editingExisting ? t.edit : t.createKey} onClose={() => { editorOpen = false; }}>
    <form class="modal-form" onsubmit={(event) => { event.preventDefault(); void saveEditor(); }}>
      <div class="modal-columns">
        <label>{t.scope}<input value={scope.toUpperCase()} readonly /></label>
        <label>{t.key}<input bind:value={key} readonly={editingExisting} required maxlength="190" placeholder="create.success" /></label>
      </div>
      {#if editingExisting}<p class="hint">{t.keyLocked}</p>{/if}
      <label>{t.description}<textarea bind:value={description} rows="3" maxlength="4096"></textarea></label>
      <label>{t.value} ({language.toUpperCase()})<textarea bind:value={value} rows="4" maxlength="8192"></textarea></label>
      <div class="actions modal-actions">
        <Button type="button" variant="secondary" onclick={() => { editorOpen = false; }} disabled={busy}>{t.cancel}</Button>
        <Button type="submit" loading={busy} disabled={busy || !key.trim()}>{editingExisting ? t.save : t.createKey}</Button>
      </div>
    </form>
  </Modal>
</div>

<style>
  .page { max-width: 1200px; margin: auto; display: grid; gap: 20px; }
  header { display: flex; justify-content: space-between; gap: 16px; align-items: center; flex-wrap: wrap; }
  h1, h2 { margin: 0 0 10px; }
  header p, .hint { color: #667085; }
  .panel { padding: 24px; border: 1px solid #e2e7ee; border-radius: 14px; background: white; }
  .selectors { display: flex; align-items: end; gap: 14px; flex-wrap: wrap; }
  label { display: grid; gap: 6px; font-weight: 600; }
  input, select, textarea { padding: 9px; border: 1px solid #cbd5e1; border-radius: 7px; font: inherit; }
  form { display: grid; gap: 14px; }
  .actions { display: flex; gap: 10px; flex-wrap: wrap; }
  .table-wrap { overflow-x: auto; }
  .csv-import { border-top: 1px solid #e2e7ee; margin-top: 20px; padding-top: 18px; display: grid; gap: 12px; }
  table { width: 100%; border-collapse: collapse; }
  th, td { text-align: left; border-bottom: 1px solid #e2e7ee; padding: 10px; }
  td { overflow-wrap: anywhere; }
  .fallback { color: #667085; font-size: 0.85em; }
  .module-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; }
  .module-card { display: grid; gap: 8px; text-align: left; padding: 16px; border: 1px solid #cbd5e1; border-radius: 10px; background: white; cursor: pointer; }
  .module-card:hover, .module-card:focus-visible { border-color: #2563eb; }
  .module-card progress { width: 100%; }
  .entry-filters { display: flex; flex-wrap: wrap; align-items: end; gap: 14px; margin-bottom: 16px; }
  .entry-filters label { display: flex; align-items: center; gap: 8px; }
  @media (max-width: 650px) { .selectors { display: grid; } }
</style>
