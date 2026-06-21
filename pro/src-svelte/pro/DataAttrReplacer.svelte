<script lang="ts">
  import { onMount, untrack } from 'svelte';
  import { api } from '../../../src-svelte/shared/api';
  import type { DataAttrItem, DataReplacement, DestinationDisplay } from '../../../src-svelte/shared/types';

  let { destination, onSaved }: { destination: DestinationDisplay; onSaved: () => void } = $props();

  const SEP = ' ';
  const key = (attr: string, value: string): string => attr + SEP + value;

  let attrs = $state<DataAttrItem[]>([]);
  let loading = $state(true);
  let error = $state('');
  let pageFilter = $state('');
  let search = $state('');

  // Local swap map ("attr\0value" → replacement value), seeded from the destination.
  let swaps = $state<Record<string, string>>(
    untrack(() => Object.fromEntries((destination.dataReplacements ?? []).map((r) => [key(r.attr, r.from), r.to]))),
  );

  onMount(async () => {
    try {
      attrs = (await api.getDataAttributes()).attributes;
      pruneStale();
    } catch (e) {
      error = (e as Error).message;
    } finally {
      loading = false;
    }
  });

  function pruneStale(): void {
    if (attrs.length === 0) return;
    const present = new Set(attrs.map((a) => key(a.attr, a.value)));
    const stale = Object.keys(swaps).filter((k) => !present.has(k));
    if (stale.length === 0) return;
    const next = { ...swaps };
    for (const k of stale) delete next[k];
    swaps = next;
    void persist();
  }

  async function persist(): Promise<void> {
    try {
      const dataReplacements: DataReplacement[] = Object.entries(swaps)
        .filter(([, to]) => to.trim() !== '')
        .map(([k, to]) => {
          // attr has no spaces, so the FIRST space splits attr from the value
          // (which may itself contain spaces).
          const i = k.indexOf(SEP);
          return { attr: k.slice(0, i), from: k.slice(i + SEP.length), to: to.trim() };
        });
      await api.updateDestination(destination.id, { dataReplacements });
      onSaved();
    } catch (e) {
      error = (e as Error).message;
    }
  }

  function commit(item: DataAttrItem, value: string): void {
    const k = key(item.attr, item.value);
    const next = { ...swaps };
    if (value.trim() === '') delete next[k];
    else next[k] = value.trim();
    swaps = next;
    void persist();
  }

  let pages = $derived.by(() => {
    const set = new Set<string>();
    for (const a of attrs) for (const p of a.pages) set.add(p);
    return [...set].sort();
  });

  let filtered = $derived.by(() => {
    const term = search.trim().toLowerCase();
    const page = pageFilter.trim().toLowerCase();
    const exact = page !== '' && pages.some((p) => p.toLowerCase() === page);
    return attrs.filter((a) => {
      if (page) {
        const match = exact ? a.pages.some((p) => p.toLowerCase() === page) : a.pages.some((p) => p.toLowerCase().includes(page));
        if (!match) return false;
      }
      if (term && !a.attr.toLowerCase().includes(term) && !a.value.toLowerCase().includes(term)) return false;
      return true;
    });
  });

  let swapCount = $derived(Object.values(swaps).filter((v) => v.trim() !== '').length);
</script>

<div class="bs-da">
  <div class="bs-da__head">
    <span>
      Data attributes
      <small>({attrs.length} value{attrs.length === 1 ? '' : 's'}{swapCount > 0 ? `, ${swapCount} replaced` : ''})</small>
    </span>
    <div class="bs-da__filters">
      <input type="search" placeholder="Search attribute or value…" bind:value={search} />
      <input type="search" list="bs-da-pages" placeholder="Filter by page…" bind:value={pageFilter} aria-label="Filter by page" />
      <datalist id="bs-da-pages">
        {#each pages as p}<option value={p}></option>{/each}
      </datalist>
    </div>
  </div>
  <p class="bs-da__hint">
    Replace the <em>value</em> of a <code>data-*</code> attribute for this destination (the attribute name is never changed).
    Framework-internal attributes and random ids are hidden.
  </p>

  {#if loading}
    <p class="bs-da__note">Loading data attributes…</p>
  {:else if error}
    <p class="bs-da__note bs-da__note--err">{error}</p>
  {:else if attrs.length === 0}
    <p class="bs-da__note">No data attributes found yet — run a Check or Sync first so the pages are rendered.</p>
  {:else}
    <div class="bs-da__list">
      {#each filtered as item (item.attr + ' ' + item.value)}
        <div class="bs-da__row" class:is-swapped={swaps[item.attr + ' ' + item.value]}>
          <div class="bs-da__meta">
            <code class="bs-da__attr">data-{item.attr}</code>
            <span class="bs-da__value" title={item.value}>{item.value}</span>
            <span class="bs-da__pages">{item.pages.length} page{item.pages.length === 1 ? '' : 's'}</span>
          </div>
          <input
            type="text"
            class="bs-da__input"
            placeholder="Replacement value…"
            value={swaps[item.attr + ' ' + item.value] ?? ''}
            onchange={(e) => commit(item, e.currentTarget.value)}
            onblur={(e) => commit(item, e.currentTarget.value)}
          />
        </div>
      {/each}
      {#if filtered.length === 0}
        <p class="bs-da__note">No data attributes match the current filter.</p>
      {/if}
    </div>
  {/if}
</div>

<style>
  .bs-da {
    display: flex;
    flex-direction: column;
    gap: var(--bs-space--sm);
  }

  .bs-da__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: var(--bs-space--sm);
    font-size: var(--bs-text--sm);
    font-weight: var(--bs-weight--medium);
    color: var(--bs-color-text--muted);
  }

  .bs-da__filters {
    display: flex;
    gap: var(--bs-space--xs);
  }

  .bs-da__filters input {
    padding: var(--bs-space--2xs) var(--bs-space--sm);
    border: var(--bs-border--1) solid var(--bs-color-border--strong);
    border-radius: var(--bs-radius--md);
    background: var(--bs-color-surface);
    color: var(--bs-color-text);
    font: inherit;
    font-size: var(--bs-text--sm);
  }

  .bs-da__hint {
    margin: 0;
    font-size: var(--bs-text--xs);
    color: var(--bs-color-text--muted);
  }

  .bs-da__list {
    display: flex;
    flex-direction: column;
    gap: var(--bs-space--xs);
    max-height: 32rem;
    overflow: auto;
  }

  .bs-da__row {
    display: flex;
    align-items: center;
    gap: var(--bs-space--sm);
    padding: var(--bs-space--xs) var(--bs-space--sm);
    border: var(--bs-border--1) solid var(--bs-color-border);
    border-radius: var(--bs-radius--md);
    background: var(--bs-color-surface);
  }

  .bs-da__row.is-swapped {
    border-color: var(--bs-color-primary);
  }

  .bs-da__meta {
    display: flex;
    flex-direction: column;
    gap: var(--bs-space--3xs);
    min-width: 0;
    flex: 1;
  }

  .bs-da__attr {
    font-size: var(--bs-text--xs);
    color: var(--bs-color-text--muted);
  }

  .bs-da__value {
    font-size: var(--bs-text--sm);
    font-weight: var(--bs-weight--medium);
    color: var(--bs-color-text);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .bs-da__pages {
    font-size: var(--bs-text--xs);
    color: var(--bs-color-text--muted);
  }

  .bs-da__input {
    flex: 0 0 14rem;
    max-width: 45%;
    padding: var(--bs-space--xs) var(--bs-space--sm);
    border: var(--bs-border--1) solid var(--bs-color-border--strong);
    border-radius: var(--bs-radius--md);
    background: var(--bs-color-surface);
    color: var(--bs-color-text);
    font: inherit;
    font-size: var(--bs-text--sm);
  }

  .bs-da__note {
    margin: 0;
    font-size: var(--bs-text--sm);
    color: var(--bs-color-text--muted);
  }

  .bs-da__note--err {
    color: var(--bs-color-danger);
  }
</style>
