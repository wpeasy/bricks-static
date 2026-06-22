<script lang="ts">
  import { onMount, untrack } from 'svelte';
  import { api } from '../../../src-svelte/shared/api';
  import type { VideoItem, VideoReplacement, DestinationDisplay } from '../../../src-svelte/shared/types';
  import { __, __f } from '../../../src-svelte/shared/i18n';

  let { destination, onSaved }: { destination: DestinationDisplay; onSaved: () => void } = $props();

  let videos = $state<VideoItem[]>([]);
  let loading = $state(true);
  let error = $state('');
  let pageFilter = $state('');
  let search = $state('');
  let onlyReplacements = $state(false);

  // Local swap map (original src → {replacement url, attachment id}).
  let swaps = $state<Record<string, { to: string; toId: number }>>(
    untrack(() =>
      Object.fromEntries((destination.videoReplacements ?? []).map((r) => [r.from, { to: r.to, toId: r.toId ?? 0 }])),
    ),
  );

  onMount(async () => {
    try {
      videos = (await api.getVideos()).videos;
      pruneStale();
    } catch (e) {
      error = (e as Error).message;
    } finally {
      loading = false;
    }
  });

  function pruneStale(): void {
    if (videos.length === 0) return;
    const present = new Set(videos.map((v) => v.url));
    const stale = Object.keys(swaps).filter((from) => !present.has(from));
    if (stale.length === 0) return;
    const next = { ...swaps };
    for (const k of stale) delete next[k];
    swaps = next;
    void persist();
  }

  async function persist(): Promise<void> {
    try {
      const videoReplacements: VideoReplacement[] = Object.entries(swaps)
        .filter(([, s]) => s.to.trim() !== '')
        .map(([from, s]) => ({ from, to: s.to.trim(), toId: s.toId }));
      await api.updateDestination(destination.id, { videoReplacements });
      onSaved();
    } catch (e) {
      error = (e as Error).message;
    }
  }

  function setSwap(from: string, to: string, toId: number): void {
    const next = { ...swaps };
    if (to.trim() === '') delete next[from];
    else next[from] = { to: to.trim(), toId };
    swaps = next;
    void persist();
  }

  // Local video → WP media library (videos only).
  function pick(item: VideoItem): void {
    const wp = window.wp;
    if (!wp?.media) {
      error = __('mediaUnavailable');
      return;
    }
    const frame = wp.media({ title: __('chooseReplacementVideo'), button: { text: __('useThisVideo') }, multiple: false, library: { type: 'video' } });
    frame.on('select', () => {
      const att = frame.state().get('selection').first().toJSON();
      if (att?.url) setSwap(item.url, att.url, att.id ?? 0);
    });
    frame.open();
  }

  // Embed → accept a full URL, a bare video ID, or a pasted <iframe> code, and
  // resolve to the right embed URL for the provider (matching how Bricks works).
  function commitEmbed(item: VideoItem, value: string): void {
    setSwap(item.url, resolveEmbed(item.provider, value), 0);
  }

  function resolveEmbed(provider: string, raw: string): string {
    const value = raw.trim();
    if (value === '') return '';

    if (provider === 'youtube') {
      const id = youtubeId(value);
      return id ? `https://www.youtube.com/embed/${id}` : value;
    }
    if (provider === 'vimeo') {
      const id = vimeoId(value);
      return id ? `https://player.vimeo.com/video/${id}` : value;
    }
    // Other embeds: a URL, or the src extracted from pasted <iframe> code.
    const m = value.match(/<iframe[^>]*\ssrc\s*=\s*["']([^"']+)["']/i);
    return m ? m[1] : value;
  }

  // A bare YouTube id, or one pulled from any YouTube URL / embed code.
  function youtubeId(v: string): string {
    if (/^[A-Za-z0-9_-]{6,15}$/.test(v)) return v;
    const m = v.match(/(?:youtube(?:-nocookie)?\.com\/embed\/|youtu\.be\/|[?&]v=)([A-Za-z0-9_-]{6,})/i);
    return m ? m[1] : '';
  }

  // A bare numeric Vimeo id, or one pulled from any Vimeo URL.
  function vimeoId(v: string): string {
    if (/^\d+$/.test(v)) return v;
    const m = v.match(/vimeo\.com\/(?:video\/)?(\d+)/i);
    return m ? m[1] : '';
  }

  function isLocal(item: VideoItem): boolean {
    return item.provider === 'video';
  }

  function shortUrl(url: string): string {
    return url.replace(/^https?:\/\//, '').split('?')[0];
  }

  function basename(url: string): string {
    const path = url.split('?')[0];
    return path.substring(path.lastIndexOf('/') + 1) || url;
  }

  let pages = $derived.by(() => {
    const set = new Set<string>();
    for (const v of videos) for (const p of v.pages) set.add(p);
    return [...set].sort();
  });

  let filtered = $derived.by(() => {
    const term = search.trim().toLowerCase();
    const page = pageFilter.trim().toLowerCase();
    const exact = page !== '' && pages.some((p) => p.toLowerCase() === page);
    return videos.filter((v) => {
      if (onlyReplacements && !swaps[v.url]) return false;
      if (page) {
        const match = exact ? v.pages.some((p) => p.toLowerCase() === page) : v.pages.some((p) => p.toLowerCase().includes(page));
        if (!match) return false;
      }
      if (term && !v.url.toLowerCase().includes(term) && !v.title.toLowerCase().includes(term)) return false;
      return true;
    });
  });

  let swapCount = $derived(Object.values(swaps).filter((s) => s.to.trim() !== '').length);
</script>

<div class="bs-vids">
  <div class="bs-vids__head">
    <span>
      {__('videoReplacer')}
      <small>({videos.length === 1 ? __f('nVideo', videos.length) : __f('nVideos', videos.length)}{swapCount > 0 ? __f('nReplaced', swapCount) : ''})</small>
    </span>
    <div class="bs-vids__filters">
      <input type="search" placeholder={__('phSearchUrlTitle')} bind:value={search} />
      <input type="search" list="bs-video-pages" placeholder={__('filterByPage')} bind:value={pageFilter} aria-label={__('filterByPageAria')} />
      <datalist id="bs-video-pages">
        {#each pages as p}<option value={p}></option>{/each}
      </datalist>
      <label class="bs-only"><input type="checkbox" bind:checked={onlyReplacements} /> {__('onlyReplacements')}</label>
    </div>
  </div>
  <!-- eslint-disable-next-line svelte/no-at-html-tags -->
  <p class="bs-vids__hint">{@html __('videosHint')}</p>

  {#if loading}
    <p class="bs-vids__note">{__('loadingVideos')}</p>
  {:else if error}
    <p class="bs-vids__note bs-vids__note--err">{error}</p>
  {:else if videos.length === 0}
    <p class="bs-vids__note">{__('noVideosYet')}</p>
  {:else}
    <div class="bs-vids__list">
      {#each filtered as item (item.url)}
        <div class="bs-vids__row" class:is-swapped={swaps[item.url]}>
          {#if item.thumb}
            <img class="bs-vids__thumb" src={item.thumb} alt="" loading="lazy" />
          {:else}
            <span class="bs-vids__thumb bs-vids__thumb--placeholder">▶</span>
          {/if}
          <div class="bs-vids__meta">
            <span class="bs-vids__title">{item.title || item.provider}</span>
            <span class="bs-vids__url" title={item.url}>{shortUrl(item.url)}</span>
            <span class="bs-vids__tag">{isLocal(item) ? __('localFile') : item.provider}</span>
          </div>

          {#if isLocal(item)}
            <div class="bs-vids__control">
              {#if swaps[item.url]}
                <span class="bs-vids__swapped" title={swaps[item.url].to}>↳ {basename(swaps[item.url].to)}</span>
                <button type="button" class="bs-link bs-link--danger" onclick={() => setSwap(item.url, '', 0)}>{__('btnRemove')}</button>
              {:else}
                <button type="button" class="bs-vids__btn" onclick={() => pick(item)}>{__('btnReplace')}</button>
              {/if}
            </div>
          {:else}
            <input
              type="text"
              class="bs-vids__input"
              placeholder={__('phReplUrlOrId')}
              value={swaps[item.url]?.to ?? ''}
              onchange={(e) => commitEmbed(item, e.currentTarget.value)}
              onblur={(e) => commitEmbed(item, e.currentTarget.value)}
            />
          {/if}
        </div>
      {/each}
      {#if filtered.length === 0}
        <p class="bs-vids__note">{__('noVideosMatch')}</p>
      {/if}
    </div>
  {/if}
</div>

<style>
  .bs-vids {
    display: flex;
    flex-direction: column;
    gap: var(--bs-space--sm);
  }

  .bs-vids__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: var(--bs-space--sm);
    font-size: var(--bs-text--sm);
    font-weight: var(--bs-weight--medium);
    color: var(--bs-color-text--muted);
  }

  .bs-vids__filters {
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

  .bs-vids__filters input {
    padding: var(--bs-space--2xs) var(--bs-space--sm);
    border: var(--bs-border--1) solid var(--bs-color-border--strong);
    border-radius: var(--bs-radius--md);
    background: var(--bs-color-surface);
    color: var(--bs-color-text);
    font: inherit;
    font-size: var(--bs-text--sm);
  }

  .bs-vids__hint {
    margin: 0;
    font-size: var(--bs-text--xs);
    color: var(--bs-color-text--muted);
  }

  .bs-vids__list {
    display: flex;
    flex-direction: column;
    gap: var(--bs-space--xs);
    max-height: 32rem;
    overflow: auto;
  }

  .bs-vids__row {
    display: flex;
    align-items: center;
    gap: var(--bs-space--sm);
    padding: var(--bs-space--xs) var(--bs-space--sm);
    border: var(--bs-border--1) solid var(--bs-color-border);
    border-radius: var(--bs-radius--md);
    background: var(--bs-color-surface);
  }

  .bs-vids__row.is-swapped {
    border-color: var(--bs-color-primary);
  }

  .bs-vids__thumb {
    width: 4rem;
    height: 2.5rem;
    flex: 0 0 auto;
    object-fit: cover;
    border-radius: var(--bs-radius--sm);
    background: var(--bs-color-surface--sunken);
  }

  .bs-vids__thumb--placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--bs-color-text--muted);
  }

  .bs-vids__meta {
    display: flex;
    flex-direction: column;
    gap: var(--bs-space--3xs);
    min-width: 0;
    flex: 1;
  }

  .bs-vids__title {
    font-size: var(--bs-text--sm);
    font-weight: var(--bs-weight--medium);
    color: var(--bs-color-text);
    text-transform: capitalize;
  }

  .bs-vids__url {
    font-size: var(--bs-text--xs);
    color: var(--bs-color-text--secondary);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .bs-vids__tag {
    font-size: var(--bs-text--xs);
    color: var(--bs-color-text--muted);
    text-transform: capitalize;
  }

  .bs-vids__control {
    display: flex;
    align-items: center;
    gap: var(--bs-space--xs);
    flex: 0 0 auto;
  }

  .bs-vids__swapped {
    font-size: var(--bs-text--xs);
    color: var(--bs-color-primary);
    max-width: 10rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .bs-vids__input {
    flex: 0 0 16rem;
    max-width: 48%;
    padding: var(--bs-space--xs) var(--bs-space--sm);
    border: var(--bs-border--1) solid var(--bs-color-border--strong);
    border-radius: var(--bs-radius--md);
    background: var(--bs-color-surface);
    color: var(--bs-color-text);
    font: inherit;
    font-size: var(--bs-text--sm);
  }

  .bs-vids__btn {
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

  .bs-vids__note {
    margin: 0;
    font-size: var(--bs-text--sm);
    color: var(--bs-color-text--muted);
  }

  .bs-vids__note--err {
    color: var(--bs-color-danger);
  }
</style>
