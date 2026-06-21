<script lang="ts">
  import { onMount, untrack } from 'svelte';
  import { api } from '../../../src-svelte/shared/api';
  import type { MediaItem, MediaReplacement, DestinationDisplay } from '../../../src-svelte/shared/types';

  let { destination, onSaved }: { destination: DestinationDisplay; onSaved: () => void } = $props();

  let media = $state<MediaItem[]>([]);
  let loading = $state(true);
  let error = $state('');
  let pageFilter = $state('');
  let search = $state('');

  // Local swap map (original url → {replacement url, attachment id}), seeded
  // from the destination.
  let swaps = $state<Record<string, { to: string; toId: number }>>(
    untrack(() =>
      Object.fromEntries((destination.mediaReplacements ?? []).map((r) => [r.from, { to: r.to, toId: r.toId ?? 0 }])),
    ),
  );

  onMount(async () => {
    try {
      media = (await api.getMedia()).media;
      pruneStaleSwaps();
    } catch (e) {
      error = (e as Error).message;
    } finally {
      loading = false;
    }
  });

  // Drop any saved replacement whose original media is no longer referenced on
  // the rendered site (e.g. the element was deleted), keeping settings tidy.
  // Guarded: only prunes when there IS a render to compare against, so an empty
  // collection (nothing rendered yet) never wipes valid replacements.
  function pruneStaleSwaps(): void {
    if (media.length === 0) return;
    const present = new Set(media.map((m) => m.url));
    const stale = Object.keys(swaps).filter((from) => !present.has(from));
    if (stale.length === 0) return;

    const next = { ...swaps };
    for (const key of stale) delete next[key];
    swaps = next;
    void persist();
  }

  function basename(url: string): string {
    const path = url.split('?')[0];
    return path.substring(path.lastIndexOf('/') + 1) || url;
  }

  async function persist(): Promise<void> {
    try {
      const mediaReplacements: MediaReplacement[] = Object.entries(swaps).map(([from, s]) => ({
        from,
        to: s.to,
        toId: s.toId,
      }));
      await api.updateDestination(destination.id, { mediaReplacements });
      onSaved();
    } catch (e) {
      error = (e as Error).message;
    }
  }

  function pick(item: MediaItem): void {
    const wp = window.wp;
    if (!wp?.media) {
      error = 'The WordPress media library is unavailable on this page.';
      return;
    }
    const frame = wp.media({
      title: 'Choose a replacement',
      button: { text: 'Use this media' },
      multiple: false,
      library: { type: item.type },
    });
    frame.on('select', () => {
      const att = frame.state().get('selection').first().toJSON();
      if (att?.url) {
        swaps = { ...swaps, [item.url]: { to: att.url, toId: att.id ?? 0 } };
        void persist();
      }
    });
    frame.open();
  }

  function clearSwap(url: string): void {
    const next = { ...swaps };
    delete next[url];
    swaps = next;
    void persist();
  }

  let pages = $derived.by(() => {
    const set = new Set<string>();
    for (const m of media) for (const p of m.pages) set.add(p);
    return [...set].sort();
  });

  let filtered = $derived.by(() => {
    const term = search.trim().toLowerCase();
    const page = pageFilter.trim().toLowerCase();
    // An exact page path (e.g. the home "/", picked from the list) matches that
    // page only; a partial string matches any page containing it.
    const exact = page !== '' && pages.some((p) => p.toLowerCase() === page);
    return media.filter((m) => {
      if (page) {
        const match = exact
          ? m.pages.some((p) => p.toLowerCase() === page)
          : m.pages.some((p) => p.toLowerCase().includes(page));
        if (!match) return false;
      }
      if (term && !basename(m.url).toLowerCase().includes(term) && !m.alt.toLowerCase().includes(term)) return false;
      return true;
    });
  });

  let swapCount = $derived(Object.keys(swaps).length);
</script>

<div class="bs-media">
  <div class="bs-media__head">
    <span>
      Media replacer <small>({media.length} item{media.length === 1 ? '' : 's'}{swapCount > 0 ? `, ${swapCount} swapped` : ''})</small>
    </span>
    <div class="bs-media__filters">
      <input type="search" placeholder="Search name or alt…" bind:value={search} />
      <input
        type="search"
        list="bs-media-pages"
        placeholder="Filter by page…"
        bind:value={pageFilter}
        aria-label="Filter by page"
      />
      <datalist id="bs-media-pages">
        {#each pages as p}<option value={p}></option>{/each}
      </datalist>
    </div>
  </div>

  {#if loading}
    <p class="bs-media__note">Loading media…</p>
  {:else if error}
    <p class="bs-media__note bs-media__note--err">{error}</p>
  {:else if media.length === 0}
    <p class="bs-media__note">No media found yet — run a Check or Sync first so the pages are rendered.</p>
  {:else}
    <div class="bs-media__list">
      {#each filtered as item (item.url)}
        <div class="bs-media__row" class:is-swapped={swaps[item.url]}>
          <div class="bs-media__line">
            <button type="button" class="bs-media__thumbbtn" onclick={() => pick(item)} title="Click to replace">
              {#if item.type === 'video'}
                <span class="bs-media__thumb bs-media__thumb--video">▶</span>
              {:else}
                <img class="bs-media__thumb" src={item.thumb} alt={item.alt} loading="lazy" />
              {/if}
            </button>
            <div class="bs-media__meta">
              <span class="bs-media__name" title={item.url}>{basename(item.url)}</span>
              <span class="bs-media__alt">{item.alt || '— no alt —'}</span>
              <span class="bs-media__pages">{item.pages.length} page{item.pages.length === 1 ? '' : 's'}: {item.pages.join(', ')}</span>
            </div>
            <button type="button" class="bs-media__replace" onclick={() => pick(item)}>Replace…</button>
          </div>

          {#if swaps[item.url]}
            <div class="bs-media__line bs-media__line--swap">
              {#if item.type === 'video'}
                <span class="bs-media__thumb bs-media__thumb--video">▶</span>
              {:else}
                <img class="bs-media__thumb" src={swaps[item.url].to} alt="" loading="lazy" />
              {/if}
              <div class="bs-media__meta">
                <span class="bs-media__swaplabel">↳ replaced with</span>
                <span class="bs-media__name" title={swaps[item.url].to}>{basename(swaps[item.url].to)}</span>
              </div>
              <button type="button" class="bs-link bs-link--danger" onclick={() => clearSwap(item.url)}>Remove</button>
            </div>
          {/if}
        </div>
      {/each}
      {#if filtered.length === 0}
        <p class="bs-media__note">No media match the current filter.</p>
      {/if}
    </div>
  {/if}
</div>

<style>
  .bs-media {
    display: flex;
    flex-direction: column;
    gap: var(--bs-space--sm);
  }

  .bs-media__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: var(--bs-space--sm);
    font-size: var(--bs-text--sm);
    font-weight: var(--bs-weight--medium);
    color: var(--bs-color-text--muted);
  }

  .bs-media__filters {
    display: flex;
    gap: var(--bs-space--xs);
  }

  .bs-media__filters input {
    padding: var(--bs-space--2xs) var(--bs-space--sm);
    border: var(--bs-border--1) solid var(--bs-color-border--strong);
    border-radius: var(--bs-radius--md);
    background: var(--bs-color-surface);
    color: var(--bs-color-text);
    font: inherit;
    font-size: var(--bs-text--sm);
  }

  .bs-media__list {
    display: flex;
    flex-direction: column;
    gap: var(--bs-space--xs);
    max-height: 32rem;
    overflow: auto;
  }

  .bs-media__row {
    border: var(--bs-border--1) solid var(--bs-color-border);
    border-radius: var(--bs-radius--md);
    background: var(--bs-color-surface);
  }

  .bs-media__row.is-swapped {
    border-color: var(--bs-color-primary);
  }

  .bs-media__line {
    display: flex;
    gap: var(--bs-space--sm);
    align-items: center;
    padding: var(--bs-space--xs);
  }

  .bs-media__line--swap {
    border-top: var(--bs-border--1) dashed var(--bs-color-border);
    background: color-mix(in srgb, var(--bs-color-primary) 6%, transparent);
  }

  .bs-media__thumbbtn {
    border: 0;
    padding: 0;
    background: none;
    cursor: pointer;
    line-height: 0;
  }

  .bs-media__thumb {
    width: 3rem;
    height: 3rem;
    flex: 0 0 auto;
    object-fit: cover;
    border-radius: var(--bs-radius--sm);
    background: var(--bs-color-surface--sunken);
  }

  .bs-media__thumb--video {
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--bs-color-text--muted);
  }

  .bs-media__meta {
    display: flex;
    flex-direction: column;
    gap: var(--bs-space--3xs);
    min-width: 0;
    flex: 1;
  }

  .bs-media__name {
    font-size: var(--bs-text--sm);
    font-weight: var(--bs-weight--medium);
    color: var(--bs-color-text);
    word-break: break-all;
  }

  .bs-media__alt {
    font-size: var(--bs-text--xs);
    color: var(--bs-color-text--secondary);
  }

  .bs-media__pages {
    font-size: var(--bs-text--xs);
    color: var(--bs-color-text--muted);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .bs-media__swaplabel {
    font-size: var(--bs-text--xs);
    color: var(--bs-color-primary);
    font-weight: var(--bs-weight--medium);
  }

  .bs-media__replace {
    flex: 0 0 auto;
    padding: var(--bs-space--2xs) var(--bs-space--sm);
    border: var(--bs-border--1) solid var(--bs-color-border--strong);
    border-radius: var(--bs-radius--md);
    background: var(--bs-color-surface);
    color: var(--bs-color-text);
    font: inherit;
    font-size: var(--bs-text--xs);
    cursor: pointer;
  }

  .bs-link {
    flex: 0 0 auto;
    background: none;
    border: 0;
    padding: 0;
    color: var(--bs-color-primary);
    font: inherit;
    font-size: var(--bs-text--sm);
    cursor: pointer;
  }

  .bs-link--danger {
    color: var(--bs-color-danger);
  }

  .bs-media__note {
    margin: 0;
    font-size: var(--bs-text--sm);
    color: var(--bs-color-text--muted);
  }

  .bs-media__note--err {
    color: var(--bs-color-danger);
  }
</style>
