<script lang="ts">
  import { onMount } from 'svelte';
  import { api } from '../shared/api';
  import type { MediaItem } from '../shared/types';

  let media = $state<MediaItem[]>([]);
  let loading = $state(true);
  let error = $state('');
  let pageFilter = $state('');
  let search = $state('');

  onMount(async () => {
    try {
      media = (await api.getMedia()).media;
    } catch (e) {
      error = (e as Error).message;
    } finally {
      loading = false;
    }
  });

  function basename(url: string): string {
    const path = url.split('?')[0];
    return path.substring(path.lastIndexOf('/') + 1) || url;
  }

  // Pages present across all media, for the filter dropdown.
  let pages = $derived.by(() => {
    const set = new Set<string>();
    for (const m of media) for (const p of m.pages) set.add(p);
    return [...set].sort();
  });

  let filtered = $derived.by(() => {
    const term = search.trim().toLowerCase();
    return media.filter((m) => {
      if (pageFilter && !m.pages.includes(pageFilter)) return false;
      if (term && !basename(m.url).toLowerCase().includes(term) && !m.alt.toLowerCase().includes(term)) return false;
      return true;
    });
  });
</script>

<div class="bs-media">
  <div class="bs-media__head">
    <span>Media replacer <small>({media.length} item{media.length === 1 ? '' : 's'})</small></span>
    <div class="bs-media__filters">
      <input type="search" placeholder="Search name or alt…" bind:value={search} />
      <select bind:value={pageFilter} aria-label="Filter by page">
        <option value="">All pages</option>
        {#each pages as p}<option value={p}>{p}</option>{/each}
      </select>
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
        <div class="bs-media__row">
          <div class="bs-media__main">
            {#if item.type === 'video'}
              <div class="bs-media__thumb bs-media__thumb--video">▶</div>
            {:else}
              <img class="bs-media__thumb" src={item.thumb} alt={item.alt} loading="lazy" />
            {/if}
            <div class="bs-media__meta">
              <span class="bs-media__name" title={item.url}>{basename(item.url)}</span>
              <span class="bs-media__alt">{item.alt || '— no alt —'}</span>
              <span class="bs-media__pages">{item.pages.length} page{item.pages.length === 1 ? '' : 's'}: {item.pages.join(', ')}</span>
            </div>
          </div>
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

  .bs-media__filters input,
  .bs-media__filters select {
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
    max-height: 28rem;
    overflow: auto;
  }

  .bs-media__row {
    border: var(--bs-border--1) solid var(--bs-color-border);
    border-radius: var(--bs-radius--md);
    background: var(--bs-color-surface);
  }

  .bs-media__main {
    display: flex;
    gap: var(--bs-space--sm);
    align-items: center;
    padding: var(--bs-space--xs);
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

  .bs-media__note {
    margin: 0;
    font-size: var(--bs-text--sm);
    color: var(--bs-color-text--muted);
  }

  .bs-media__note--err {
    color: var(--bs-color-danger);
  }
</style>
