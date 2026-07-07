<script lang="ts">
  import { Accordion, Badge } from '@wpeasy/ab-ui';
  import type { DestinationDisplay } from '../shared/types';
  import TextReplacements from './TextReplacements.svelte';
  import MediaReplacer from './MediaReplacer.svelte';
  import ProPanelMount from './ProPanelMount.svelte';
  import ProBadge from '../lib/ProBadge.svelte';
  import UpgradeCta from '../lib/UpgradeCta.svelte';
  import ListSkeleton from '../lib/ListSkeleton.svelte';
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

  const entry = (key: string): ReplacementEntry => REPLACEMENT_CATALOG.find((e) => e.key === key)!;

  // Saved-row count for the count badge — from the registered Pro panel if it
  // provides one, otherwise from the destination's stored rows (still present in
  // the payload even when locked, so a downgraded user sees their saved counts).
  function count(e: ReplacementEntry): number {
    const panel = getPanel(e.key);
    if (panel?.badge) {
      return panel.badge(destination);
    }
    const rows = (destination as unknown as Record<string, unknown[]>)[e.countProp];
    return Array.isArray(rows) ? rows.length : 0;
  }

  // A Pro row is locked when advanced replacements aren't licensed.
  function locked(e: ReplacementEntry): boolean {
    return e.tier === 'pro' && !caps.advancedReplacements;
  }

  let items = $derived([
    { value: 'text', title: titleText, content: bodyText },
    { value: 'media', title: titleMedia, content: bodyMedia },
    { value: 'links', title: titleLinks, content: bodyLinks },
    { value: 'videos', title: titleVideos, content: bodyVideos },
  ]);
</script>

{#snippet titleInner(e: ReplacementEntry)}
  <span class="bs-rp__head">
    <span class="bs-rp__name">{__(TITLE_KEY[e.key] ?? '') || e.title}</span>
    {#if count(e) > 0}<Badge tone="primary" variant="soft">{count(e)}</Badge>{/if}
    {#if locked(e)}<ProBadge />{/if}
  </span>
{/snippet}

{#snippet proBody(e: ReplacementEntry)}
  {#if caps.advancedReplacements}
    {#if getPanel(e.key)}
      <ProPanelMount entryKey={e.key} {destination} {onSaved} />
    {:else}
      <ListSkeleton rows={3} />
    {/if}
  {:else}
    <UpgradeCta description={__(DESC_KEY[e.key] ?? '') || e.description} />
  {/if}
{/snippet}

{#snippet titleText()}{@render titleInner(entry('text'))}{/snippet}
{#snippet titleMedia()}{@render titleInner(entry('media'))}{/snippet}
{#snippet titleLinks()}{@render titleInner(entry('links'))}{/snippet}
{#snippet titleVideos()}{@render titleInner(entry('videos'))}{/snippet}

{#snippet bodyText()}<TextReplacements {destination} {running} {onSaved} />{/snippet}
{#snippet bodyMedia()}<MediaReplacer {destination} {onSaved} />{/snippet}
{#snippet bodyLinks()}{@render proBody(entry('links'))}{/snippet}
{#snippet bodyVideos()}{@render proBody(entry('videos'))}{/snippet}

<section class="bs-card bs-stack bs-stack--sm">
  <h2 class="bs-rp__title">{__('replacements')} <small>{__('replacementsSub')}</small></h2>
  <Accordion bind:value={open} {items} />
</section>

<style>
  .bs-card {
    /* Fills the remaining width of the destination row beside the fixed-width
       Connection panel; min-width:0 lets it shrink and wrap when narrow. */
    flex: 1 1 0;
    min-width: 0;
    padding: var(--ab-space-5);
    background: var(--ab-color-surface);
    border: 1px solid var(--ab-color-border);
    border-radius: var(--ab-radius-lg);
    box-shadow: var(--ab-shadow-sm);
  }

  .bs-rp__title {
    margin: 0;
    font-size: var(--ab-text-md);
    font-weight: var(--ab-weight-semibold);
  }

  .bs-rp__title small {
    font-weight: var(--ab-weight-normal);
    color: var(--ab-color-text-muted);
  }

  .bs-rp__head {
    display: inline-flex;
    align-items: center;
    gap: var(--ab-space-2);
  }

</style>
