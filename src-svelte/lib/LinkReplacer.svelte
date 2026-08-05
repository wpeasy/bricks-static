<script lang="ts">
  import { onMount, untrack } from 'svelte';
  import { Checkbox, Input } from '@wpeasy/ab-ui';
  import { api } from '../shared/api';
  import type { LinkItem, LinkReplacement, DestinationDisplay } from '../shared/types';
  import { __, __f } from '../shared/i18n';
  import ListSkeleton from './ListSkeleton.svelte';

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
      <Input type="search" placeholder={__('phSearchUrlLabel')} bind:value={search} />
      <Input type="search" list="bs-link-pages" placeholder={__('filterByPage')} bind:value={pageFilter} aria-label={__('filterByPageAria')} />
      <datalist id="bs-link-pages">
        {#each pages as p}<option value={p}></option>{/each}
      </datalist>
      <Checkbox label={__('onlyReplacements')} checked={onlyReplacements ? 1 : 0} onchange={(c) => (onlyReplacements = c === 1)} />
    </div>
  </div>
  <!-- eslint-disable-next-line svelte/no-at-html-tags -->
  <p class="bs-links__hint">{@html __('linksHint')}</p>

  {#if loading}
    <ListSkeleton rows={5} label={__('loadingLinks')} />
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
          <Input
            type="url"
            class="bs-links__input"
            placeholder={__('phReplacementUrl')}
            value={swaps[item.url] ?? ''}
            onchange={(e: Event) => commit(item.url, (e.currentTarget as HTMLInputElement).value)}
            onblur={(e: FocusEvent) => commit(item.url, (e.currentTarget as HTMLInputElement).value)}
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
    gap: var(--ab-space-3);
  }

  .bs-links__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: var(--ab-space-3);
    font-size: var(--ab-text-sm);
    font-weight: var(--ab-weight-medium);
    color: var(--ab-color-text-muted);
  }

  .bs-links__filters {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: var(--ab-space-2);
  }

  .bs-links__hint {
    margin: 0;
    font-size: var(--ab-text-xs);
    color: var(--ab-color-text-muted);
  }

  .bs-links__list {
    display: flex;
    flex-direction: column;
    gap: var(--ab-space-2);
    max-height: 32rem;
    overflow: auto;
  }

  .bs-links__row {
    display: flex;
    align-items: center;
    gap: var(--ab-space-3);
    padding: var(--ab-space-2) var(--ab-space-3);
    border: 1px solid var(--ab-color-border);
    border-radius: var(--ab-radius-md);
    background: var(--ab-color-surface);
  }

  .bs-links__row.is-swapped {
    border-color: var(--ab-color-primary);
  }

  .bs-links__meta {
    display: flex;
    flex-direction: column;
    gap: var(--ab-space-1);
    min-width: 0;
    flex: 1;
  }

  .bs-links__url {
    font-size: var(--ab-text-sm);
    font-weight: var(--ab-weight-medium);
    color: var(--ab-color-text);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .bs-links__sub {
    font-size: var(--ab-text-xs);
    color: var(--ab-color-text-muted);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  /* Layout only — the field itself is styled by ab-ui. The class is passed to
     the ab-ui Input child, so it needs :global to match (it carries no scope). */
  :global(.bs-links__input) {
    flex: 0 0 14rem;
    max-width: 45%;
  }

  .bs-links__note {
    margin: 0;
    font-size: var(--ab-text-sm);
    color: var(--ab-color-text-muted);
  }

  .bs-links__note--err {
    color: var(--ab-color-danger);
  }
</style>
