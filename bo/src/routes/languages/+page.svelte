<script lang="ts">
  import { getProjectBrand } from '../../../../branding';
  const brandName = getProjectBrand();
  import { onMount } from 'svelte';
  import { goto } from '$app/navigation';
  import { resolve } from '$app/paths';
  import Button from '../../../../components/Button.svelte';
  import Alert from '../../../../components/Alert.svelte';
  import DataTable from '../../../../components/DataTable.svelte';
  import type { DataColumn, DataAction, SortOrder } from '../../../../components/data-table';
  import type { PageSize } from '../../../../components/pagination';
  import { ApiError } from '../../../../api/client';
  import { boApi } from '../../../../api/session';
  import { uiLanguage } from '../../../../api/locales';
  import { languageCopy, filterLanguages, type BoLanguage, type BoLanguagesResponse } from '../../presentation';

  let languages = $state<BoLanguage[]>([]);
  let loading = $state(false);
  let saving = $state('');
  let error = $state('');
  let notice = $state('');
  let search = $state('');
  let sortKey = $state<string | null>('name');
  let sortOrder = $state<SortOrder>('asc');
  let page = $state(1);
  let limit = $state<PageSize>(20);
  const locale = $derived($uiLanguage ?? (typeof navigator === 'undefined' ? 'en' : navigator.language));
  const t = $derived(languageCopy(locale));
  const filtered = $derived(filterLanguages(languages, search, sortKey, sortOrder, locale));
  const visible = $derived(filtered.slice((page - 1) * limit, page * limit));

  const columns = $derived<DataColumn<BoLanguage>[]>([
    {
      key: 'name', label: t.name, sortable: true,
      format: (_, language) => `${language.name} (${language.iso_code})${language.primary ? ` · ${t.primary}` : ''}`
    },
    { key: 'active', label: t.publicState, format: (value) => value ? t.yes : t.no },
    { key: 'pre_open', label: t.preOpenState, format: (value) => value ? t.yes : t.no }
  ]);
  const actions = $derived<DataAction<BoLanguage>[]>([
    {
      key: 'active',
      variant: (language) => language.active ? 'warning' : 'success',
      label: (language) => `${t.publicState}: ${language.active ? t.yes : t.no}`,
      disabled: (language) => saving !== '' || (language.primary && language.active),
      onClick: (language) => { void toggle(language, 'active'); }
    },
    {
      key: 'pre_open',
      variant: (language) => language.pre_open ? 'warning' : 'success',
      label: (language) => `${t.preOpenState}: ${language.pre_open ? t.yes : t.no}`,
      disabled: () => saving !== '',
      onClick: (language) => { void toggle(language, 'pre_open'); }
    }
  ]);

  function explain(cause: unknown): string {
    if (cause instanceof ApiError) {
      if (cause.status === 401) {
        void goto(resolve('/login'), { replaceState: true });
        return t.session;
      }
      if (cause.status === 403) return t.forbidden;
      if (cause.status === 409) return t.primaryProtected;
      if (cause.status === 400) return t.invalid;
    }
    return t.failure;
  }

  async function load(): Promise<void> {
    loading = true;
    error = '';
    try {
      const response = await boApi<BoLanguagesResponse>('/apibo/languages');
      languages = Array.isArray(response.data) ? response.data : [];
    } catch (cause) {
      error = explain(cause);
    } finally {
      loading = false;
    }
  }

  onMount(() => { void load(); });

  async function toggle(language: BoLanguage, field: 'active' | 'pre_open'): Promise<void> {
    if (saving || (field === 'active' && language.primary && language.active)) return;
    saving = `${language.iso_code}:${field}`;
    error = '';
    notice = '';
    const next = { active: language.active, pre_open: language.pre_open, [field]: !language[field] };
    try {
      await boApi<{ data: { iso_code: string; active: boolean; pre_open: boolean } }>(
        `/apibo/languages/${encodeURIComponent(language.iso_code)}`,
        { method: 'PATCH', body: JSON.stringify(next) }
      );
      languages = languages.map((item) => item.iso_code === language.iso_code ? { ...item, ...next } : item);
      notice = t.saved;
    } catch (cause) {
      error = explain(cause);
    } finally {
      saving = '';
    }
  }
</script>

<svelte:head><title>{t.title} | {$brandName} BO</title></svelte:head>
<div class="languages-page">
  <header class="page-heading">
    <div><h1>{t.title}</h1><p>{t.intro}</p></div>
    <div class="header-actions">
      <Button type="button" onclick={() => goto(resolve('/languages/translations'))}>{t.translations}</Button>
      <Button type="button" variant="secondary" onclick={load} disabled={loading}>{t.refresh}</Button>
    </div>
  </header>
  {#if error}<Alert kind="error">{error}</Alert>{/if}
  {#if notice}<Alert kind="success">{notice}</Alert>{/if}
  <section class="panel">
    <DataTable rows={visible} {columns} {actions} {loading} emptyMessage={t.none}
      filters={[{ key: 'search', label: t.search, type: 'search', placeholder: t.searchHint }]}
      filterValues={{ search }}
      onFilterChange={(_, value) => { search = value; page = 1; }}
      {sortKey} {sortOrder}
      onSortChange={(key, order) => { sortKey = key; sortOrder = order; page = 1; }}
      pagination={{
        page, limit, total: filtered.length,
        onPageChange: (value) => page = value,
        onLimitChange: (value) => { limit = value; page = 1; }
      }}
    />
  </section>
</div>

<style>
  .languages-page { max-width: 1200px; margin: auto; display: grid; gap: 20px; }
  .page-heading { display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap; }
  .page-heading h1 { margin: 0; }
  .header-actions { display: flex; gap: 10px; flex-wrap: wrap; }
  .page-heading p { color: var(--bo-muted); }
  .panel { padding: 24px; background: var(--bo-surface); color: var(--bo-text); border: 1px solid var(--bo-border); border-radius: 14px; min-width: 0; }
</style>
