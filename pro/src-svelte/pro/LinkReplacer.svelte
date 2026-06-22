<script lang="ts">
  import { onMount, untrack } from 'svelte';
  import { api } from '../../../src-svelte/shared/api';
  import type { LinkItem, LinkReplacement, DestinationDisplay } from '../../../src-svelte/shared/types';
  import { __, __f } from '../../../src-svelte/shared/i18n';

  let { destination, onSaved }: { destination: DestinationDisplay; onSaved: () => void } = $props();

  let links = $state<LinkItem[]>([]);
  let loading = $state(true);
  let error = $state('');
  let pageFilter = $state('');
  let search = $state('');
  let onlyReplacements = $state(false);

  // Local swap map (original href → replacement url), seeded from the destination.
  let swaps = $state<Record<string, string>>(
    untrack(() => Object.fromEntries((destination.linkReplacements ?? []).map((r) => [r.from, r.to]))),
  );

  onMount(async () => {
    try {
      links = (await api.getLinks()).links;
    } catch (e) {
      error = (e as Error).message;
    } finally {
      loading = false;
    }
  });

  async function persist(): Promise<void> {
    try {
      const linkReplacements: LinkReplacement[] = Object.entries(swaps)
        .filter(([, to]) => to.trim() !== '')
        .map(([from, to]) => ({ from, to: to.trim() }));
      await api.updateDestination(destination.id, { linkReplacements });
      onSaved();
    } catch (e) {
      error = (e as Error).message;
    }
  }

  // Commit a row's replacement on blur / Enter: store (or clear) then persist.
  function commit(url: string, value: string): void {
    const next = { ...swaps };
    if (value.trim() === '') {
      delete next[url];
    } else {
      next[url] = value.trim();
    }
    swaps = next;
    void persist();
  }

  let pages = $derived.by(() => {
    const set = new Set<string>();
    for (const l of links) for (const p of l.pages) set.add(p);
    return [...set].sort();
  });

  let filtered = $derived.by(() => {
    const term = search.trim().toLowerCase();
    const page = pageFilter.trim().toLowerCase();
    const exact = page !== '' && pages.some((p) => p.toLowerCase() === page);
    return links.filter((l) => {
      if (onlyReplacements && !swaps[l.url]) return false;
      if (page) {
        const match = exact ? l.pages.some((p) => p.toLowerCase() === page) : l.pages.some((p) => p.toLowerCase().includes(page));
        if (!match) return false;
      }
      if (term && !l.url.toLowerCase().includes(term) && !l.text.toLowerCase().includes(term)) return false;
      return true;
    });
  });

  let swapCount = $derived(Object.values(swaps).filter((v) => v.trim() !== '').length);
</script>

<div class="bs-links">
  <div class="bs-links__head">
    <span>
      {__('linkReplacer')}
      <small>({links.length === 1 ? __f('nLink', links.length) : __f('nLinks', links.length)}{swapCount > 0 ? __f('nReplaced', swapCount) : ''})</small>
    </span>
    <div class="bs-links__filters">
      <input type="search" placeholder={__('phSearchUrlLabel')} bind:value={search} />
      <input type="search" list="bs-link-pages" placeholder={__('filterByPage')} bind:value={pageFilter} aria-label={__('filterByPageAria')} />
      <datalist id="bs-link-pages">
        {#each pages as p}<option value={p}></option>{/each}
      </datalist>
      <label class="bs-only"><input type="checkbox" bind:checked={onlyReplacements} /> {__('onlyReplacements')}</label>
    </div>
  </div>
  <!-- eslint-disable-next-line svelte/no-at-html-tags -->
  <p class="bs-links__hint">{@html __('linksHint')}</p>

  {#if loading}
    <p class="bs-links__note">{__('loadingLinks')}</p>
  {:else if error}
    <p class="bs-links__note bs-links__note--err">{error}</p>
  {:else if links.length === 0}
    <p class="bs-links__note">{__('noLinksYet')}</p>
  {:else}
    <div class="bs-links__list">
      {#each filtered as item (item.url)}
        <div class="bs-links__row" class:is-swapped={swaps[item.url]}>
          <div class="bs-links__meta">
            <span class="bs-links__url" title={item.url}>{item.url}</span>
            <span class="bs-links__sub">{item.text || __('noLabel')} · {item.pages.length === 1 ? __f('nPage', item.pages.length) : __f('nPages', item.pages.length)}</span>
          </div>
          <input
            type="url"
            class="bs-links__input"
            placeholder={__('phReplacementUrl')}
            value={swaps[item.url] ?? ''}
            onchange={(e) => commit(item.url, e.currentTarget.value)}
            onblur={(e) => commit(item.url, e.currentTarget.value)}
          />
        </div>
      {/each}
      {#if filtered.length === 0}
        <p class="bs-links__note">{__('noLinksMatch')}</p>
      {/if}
    </div>
  {/if}
</div>

<style>
  .bs-links {
    display: flex;
    flex-direction: column;
    gap: var(--bs-space--sm);
  }

  .bs-links__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: var(--bs-space--sm);
    font-size: var(--bs-text--sm);
    font-weight: var(--bs-weight--medium);
    color: var(--bs-color-text--muted);
  }

  .bs-links__filters {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: var(--bs-space--xs);
  }

  .bs-only {
    display: inline-flex;
    align-items: center;
    gap: var(--bs-space--2xs);
    font-size: var(--bs-text--sm);
    font-weight: var(--bs-weight--normal);
    color: var(--bs-color-text--muted);
    white-space: nowrap;
    cursor: pointer;
  }

  .bs-links__filters input {
    padding: var(--bs-space--2xs) var(--bs-space--sm);
    border: var(--bs-border--1) solid var(--bs-color-border--strong);
    border-radius: var(--bs-radius--md);
    background: var(--bs-color-surface);
    color: var(--bs-color-text);
    font: inherit;
    font-size: var(--bs-text--sm);
  }

  .bs-links__hint {
    margin: 0;
    font-size: var(--bs-text--xs);
    color: var(--bs-color-text--muted);
  }

  .bs-links__list {
    display: flex;
    flex-direction: column;
    gap: var(--bs-space--xs);
    max-height: 32rem;
    overflow: auto;
  }

  .bs-links__row {
    display: flex;
    align-items: center;
    gap: var(--bs-space--sm);
    padding: var(--bs-space--xs) var(--bs-space--sm);
    border: var(--bs-border--1) solid var(--bs-color-border);
    border-radius: var(--bs-radius--md);
    background: var(--bs-color-surface);
  }

  .bs-links__row.is-swapped {
    border-color: var(--bs-color-primary);
  }

  .bs-links__meta {
    display: flex;
    flex-direction: column;
    gap: var(--bs-space--3xs);
    min-width: 0;
    flex: 1;
  }

  .bs-links__url {
    font-size: var(--bs-text--sm);
    font-weight: var(--bs-weight--medium);
    color: var(--bs-color-text);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .bs-links__sub {
    font-size: var(--bs-text--xs);
    color: var(--bs-color-text--muted);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .bs-links__input {
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

  .bs-links__note {
    margin: 0;
    font-size: var(--bs-text--sm);
    color: var(--bs-color-text--muted);
  }

  .bs-links__note--err {
    color: var(--bs-color-danger);
  }
</style>
