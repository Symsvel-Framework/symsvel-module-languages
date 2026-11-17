<script lang="ts">
  import { onMount } from 'svelte';
  import { goto } from '$app/navigation';
  import { resolve } from '$app/paths';
  import Button from '../../../../../components/Button.svelte';
  import Alert from '../../../../../components/Alert.svelte';
  import { ApiError } from '../../../../../api/client';
  import { boApi } from '../../../../../api/session';
  import { uiLanguage } from '../../../../../api/locales';
  import { languageCopy, translationCopy, type BoLanguage } from '../../../presentation';

  type Translation = {
    scope: string; translation_key: string; language: string; value: string;
    description: string | null; fallback_value: string; missing_translation: boolean;
  };
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
  let language = $state('');
  let entries = $state<Translation[]>([]);
  let key = $state('');
  let value = $state('');
  let description = $state('');
  let csvCanImport = $state(false);
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
    return includeKey ? `${baseParams}&key=${encodeURIComponent(key)}` : baseParams;
  }

  async function loadLanguages(): Promise<void> {
    busy = true;
    error = '';
    try {
      const result = await boApi<{ data: BoLanguage[] }>('/apibo/languages/available');
      languages = result.data;
      language = result.data.find((row) => row.primary)?.iso_code ?? result.data[0]?.iso_code ?? '';
      if (language) await loadEntries();
      const caps = await boApi<{ data: { can_import: boolean } }>(`${base}/csv-capabilities`);
      csvCanImport = caps.data.can_import;
    } catch (cause) {
      error = explain(cause);
    } finally {
      busy = false;
    }
  }

  async function loadEntries(): Promise<void> {
    if (!language) { entries = []; return; }
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

  function edit(entry: Translation): void {
    key = entry.translation_key;
    value = entry.value;
    description = entry.description ?? '';
    success = '';
  }

  async function createKey(): Promise<void> {
    if (!key.trim() || busy || !language) return;
    busy = true;
    error = '';
    success = '';
    try {
      await boApi(`${base}/keys`, {
        method: 'POST',
        body: JSON.stringify({ scope, key: key.trim(), description: description.trim() || null })
      });
      await loadEntries();
      success = t.keyCreated;
    } catch (cause) {
      error = explain(cause);
    } finally {
      busy = false;
    }
  }

  async function save(): Promise<void> {
    if (!language || !key.trim() || busy) return;
    busy = true;
    error = '';
    success = '';
    try {
      await boApi<{ data: Translation }>(base, {
        method: 'PUT', body: JSON.stringify({ scope, language, key: key.trim(), value })
      });
      await loadEntries();
      success = t.saved;
    } catch (cause) {
      error = explain(cause);
    } finally {
      busy = false;
    }
  }

  async function remove(): Promise<void> {
    if (!language || !key || busy || !confirm(t.confirmDelete)) return;
    busy = true;
    error = '';
    success = '';
    try {
      await boApi(`${base}?${params(true)}`, { method: 'DELETE' });
      key = '';
      value = '';
      description = '';
      await loadEntries();
      success = t.deleted;
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
      const result = await boApi<{ data: TsExport }>(`${base}/export?${params()}`);
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
        await loadEntries();
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
  <header>
    <div><h1>{t.title}</h1><p>{t.intro}</p></div>
    <Button type="button" variant="secondary" onclick={() => goto(resolve('/languages'))}>{languageCopy(locale).title}</Button>
  </header>
  {#if error}<Alert kind="error">{error}</Alert>{/if}
  {#if success}<Alert kind="success">{success}</Alert>{/if}
  <section class="panel">
    <div class="selectors">
      <label>{t.scope}
        <select bind:value={scope} onchange={(event) => { scope = event.currentTarget.value as 'bo' | 'app' | 'front'; key = ''; value = ''; void loadEntries(); }}>
          <option value="bo">BO</option><option value="app">App</option><option value="front">Front</option>
        </select>
      </label>
      <label>{t.language}
        <select bind:value={language} onchange={(event) => { language = event.currentTarget.value; key = ''; value = ''; void loadEntries(); }}>
          {#each languages as row (row.iso_code)}
            <option value={row.iso_code}>{row.name} ({row.iso_code})</option>
          {/each}
        </select>
      </label>
      <Button type="button" onclick={download} disabled={busy || !language}>{t.export}</Button>
    </div>
    <p class="hint">{t.exportHint}</p>
    <div class="actions">
      <Button type="button" variant="secondary" onclick={exportCsv} disabled={busy}>{t.csvExport}</Button>
    </div>
    {#if csvCanImport}
      <div class="csv-import">
        <h2>{t.csvImport}</h2>
        <p class="hint">{t.csvImportHint}</p>
        <label>{t.csvFile}
          <input type="file" accept=".csv,text/csv" onchange={(event) => { void selectCsv(event); }} />
        </label>
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
      </div>
    {/if}
  </section>
  <section class="panel">
    <h2>{t.editor}</h2>
    <form onsubmit={(event) => { event.preventDefault(); void save(); }}>
      <label>{t.key}<input bind:value={key} required maxlength="190" placeholder="auth.signIn" /></label>
      <label>{t.description}<input bind:value={description} maxlength="4096" /></label>
      <label>{t.value}<textarea bind:value={value} maxlength="8192" rows="3"></textarea></label>
      <div class="actions">
        <Button type="submit" disabled={busy || !language || !key.trim()}>{t.save}</Button>
        <Button type="button" variant="secondary" onclick={createKey} disabled={busy || !language || !key.trim()}>{t.createKey}</Button>
        <Button type="button" variant="secondary" onclick={() => { key = ''; value = ''; description = ''; }}>{t.clear}</Button>
        <Button type="button" variant="secondary" onclick={remove} disabled={busy || !language || !key}>{t.delete}</Button>
      </div>
    </form>
  </section>
  <section class="panel">
    <h2>{t.entries} ({entries.length}, {missing} {t.missing})</h2>
    {#if entries.length === 0}
      <p>{t.empty}</p>
    {:else}
      <div class="table-wrap"><table>
        <thead><tr><th>{t.key}</th><th>{t.value}</th><th>{t.status}</th><th></th></tr></thead>
        <tbody>
          {#each entries as entry (entry.translation_key)}
            <tr>
              <td><code>{entry.translation_key}</code></td>
              <td>{entry.value || entry.fallback_value || '—'}{#if entry.missing_translation && entry.fallback_value} <span class="fallback">({t.fallback})</span>{/if}</td>
              <td>{entry.missing_translation ? t.missing : t.complete}</td>
              <td><Button type="button" variant="secondary" onclick={() => edit(entry)}>{t.edit}</Button></td>
            </tr>
          {/each}
        </tbody>
      </table></div>
    {/if}
  </section>
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
  @media (max-width: 650px) { .selectors { display: grid; } }
</style>
