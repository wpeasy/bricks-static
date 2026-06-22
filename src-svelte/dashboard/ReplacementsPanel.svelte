<script lang="ts">
  import type { DestinationDisplay } from '../shared/types';
  import TextReplacements from './TextReplacements.svelte';
  import ProPanelMount from './ProPanelMount.svelte';
  import ProBadge from '../lib/ProBadge.svelte';
  import UpgradeCta from '../lib/UpgradeCta.svelte';
  import { REPLACEMENT_CATALOG, type ReplacementEntry } from '../shared/replacementCatalog';
  import { caps } from '../shared/capabilities.svelte';
  import { getPanel } from '../shared/panelRegistry.svelte';
  import { __ } from '../shared/i18n';

  // Catalogue entries carry English title/description in code; map each to its
  // translated i18n key for display.
  const TITLE_KEY: Record<string, string> = { text: 'catText', media: 'catMedia', links: 'catLinks', videos: 'catVideos' };
  const DESC_KEY: Record<string, string> = {
    text: 'catTextDesc',
    media: 'catMediaDesc',
    links: 'catLinksDesc',
    videos: 'catVideosDesc',
  };

  let {
    destination,
    running,
    onSaved,
  }: {
    destination: DestinationDisplay;
    running: boolean;
    onSaved: () => void;
  } = $props();

  // Single-open accordion; key matches the catalogue entry key.
  let open = $state<string>('text');

  function toggle(key: string): void {
    open = open === key ? '' : key;
  }

  // Saved-row count for the blue badge — from the registered Pro panel if it
  // provides one, otherwise from the destination's stored rows (still present in
  // the payload even when locked, so a downgraded user sees their saved counts).
  function count(entry: ReplacementEntry): number {
    const panel = getPanel(entry.key);
    if (panel?.badge) {
      return panel.badge(destination);
    }
    const rows = (destination as unknown as Record<string, unknown[]>)[entry.countProp];
    return Array.isArray(rows) ? rows.length : 0;
  }

  // A Pro row is locked when advanced replacements aren't licensed.
  function locked(entry: ReplacementEntry): boolean {
    return entry.tier === 'pro' && !caps.advancedReplacements;
  }
</script>

<section class="bs-card bs-stack bs-stack--sm">
  <h2 class="bs-rp__title">{__('replacements')} <small>{__('replacementsSub')}</small></h2>

  <div class="bs-acc">
    {#each REPLACEMENT_CATALOG as entry (entry.key)}
      <div class="bs-acc__item">
        <button
          type="button"
          class="bs-acc__head"
          aria-expanded={open === entry.key}
          onclick={() => toggle(entry.key)}
        >
          <span class="bs-acc__chev" class:is-open={open === entry.key}>▸</span>
          <span class="bs-acc__title">{__(TITLE_KEY[entry.key] ?? '') || entry.title}</span>
          {#if count(entry) > 0}<span class="bs-acc__badge">{count(entry)}</span>{/if}
          {#if locked(entry)}<ProBadge />{/if}
        </button>
        {#if open === entry.key}
          <div class="bs-acc__body">
            {#if entry.key === 'text'}
              <TextReplacements {destination} {running} {onSaved} />
            {:else if caps.advancedReplacements}
              {#if getPanel(entry.key)}
                <ProPanelMount entryKey={entry.key} {destination} {onSaved} />
              {:else}
                <p class="bs-acc__loading">{__('loading')}</p>
              {/if}
            {:else}
              <UpgradeCta description={__(DESC_KEY[entry.key] ?? '') || entry.description} />
            {/if}
          </div>
        {/if}
      </div>
    {/each}
  </div>
</section>

<style>
  .bs-card {
    grid-column: 1 / -1; /* full width when the grid is a single column */
    padding: var(--bs-space--lg);
    background: var(--bs-color-surface--raised);
    border: var(--bs-border--1) solid var(--bs-color-border);
    border-radius: var(--bs-radius--lg);
    box-shadow: var(--bs-shadow--sm);
  }

  /* Wide enough for ≥2 columns: sit after the Transport card and span to the end. */
  @media (min-width: 700px) {
    .bs-card {
      grid-column: 2 / -1;
    }
  }

  .bs-rp__title {
    margin: 0;
    font-size: var(--bs-text--md);
    font-weight: var(--bs-weight--semibold);
  }

  .bs-rp__title small {
    font-weight: var(--bs-weight--normal);
    color: var(--bs-color-text--muted);
  }

  .bs-acc {
    display: flex;
    flex-direction: column;
    border: var(--bs-border--1) solid var(--bs-color-border);
    border-radius: var(--bs-radius--md);
    overflow: hidden;
  }

  .bs-acc__item + .bs-acc__item {
    border-top: var(--bs-border--1) solid var(--bs-color-border);
  }

  .bs-acc__head {
    display: flex;
    align-items: center;
    gap: var(--bs-space--sm);
    width: 100%;
    padding: var(--bs-space--sm) var(--bs-space--md);
    border: 0;
    background: var(--bs-color-surface);
    color: var(--bs-color-text);
    font: inherit;
    font-weight: var(--bs-weight--semibold);
    font-size: var(--bs-text--sm);
    cursor: pointer;
    text-align: left;
  }

  .bs-acc__head:hover {
    background: var(--bs-color-surface--sunken);
  }

  .bs-acc__head[aria-expanded='true'] {
    background: var(--bs-color-surface--sunken);
  }

  .bs-acc__chev {
    display: inline-block;
    transition: transform 0.15s ease;
    color: var(--bs-color-text--muted);
  }

  .bs-acc__chev.is-open {
    transform: rotate(90deg);
  }

  .bs-acc__title {
    flex: 1;
  }

  .bs-acc__badge {
    min-width: 1.4rem;
    padding: 0 var(--bs-space--2xs);
    border-radius: var(--bs-radius--pill);
    background: var(--bs-color-primary);
    color: var(--bs-color-primary--contrast);
    font-size: var(--bs-text--xs);
    font-weight: var(--bs-weight--semibold);
    text-align: center;
  }

  .bs-acc__body {
    padding: var(--bs-space--md);
    border-top: var(--bs-border--1) solid var(--bs-color-border);
    background: var(--bs-color-surface--raised);
  }

  .bs-acc__loading {
    margin: 0;
    color: var(--bs-color-text--muted);
    font-size: var(--bs-text--sm);
  }
</style>
