<script lang="ts">
  import type { DestinationDisplay } from '../shared/types';
  import TextReplacements from './TextReplacements.svelte';
  import MediaReplacer from './MediaReplacer.svelte';
  import LinkReplacer from './LinkReplacer.svelte';
  import VideoReplacer from './VideoReplacer.svelte';

  let {
    destination,
    running,
    onSaved,
  }: {
    destination: DestinationDisplay;
    running: boolean;
    onSaved: () => void;
  } = $props();

  type Section = 'text' | 'media' | 'links' | 'videos';
  let open = $state<Section>('text');

  function toggle(section: Section): void {
    open = open === section ? ('' as Section) : section;
  }

  let textCount = $derived(destination.replacements?.length ?? 0);
  let mediaCount = $derived(destination.mediaReplacements?.length ?? 0);
  let linkCount = $derived(destination.linkReplacements?.length ?? 0);
  let videoCount = $derived(destination.videoReplacements?.length ?? 0);
</script>

<section class="bs-card bs-stack bs-stack--sm">
  <h2 class="bs-rp__title">Replacements <small>(applied to this destination only · saved automatically)</small></h2>

  <div class="bs-acc">
    <!-- Text -->
    <div class="bs-acc__item">
      <button type="button" class="bs-acc__head" aria-expanded={open === 'text'} onclick={() => toggle('text')}>
        <span class="bs-acc__chev" class:is-open={open === 'text'}>▸</span>
        <span class="bs-acc__title">Text</span>
        {#if textCount > 0}<span class="bs-acc__badge">{textCount}</span>{/if}
      </button>
      {#if open === 'text'}
        <div class="bs-acc__body">
          <TextReplacements {destination} {running} {onSaved} />
        </div>
      {/if}
    </div>

    <!-- Media -->
    <div class="bs-acc__item">
      <button type="button" class="bs-acc__head" aria-expanded={open === 'media'} onclick={() => toggle('media')}>
        <span class="bs-acc__chev" class:is-open={open === 'media'}>▸</span>
        <span class="bs-acc__title">Media</span>
        {#if mediaCount > 0}<span class="bs-acc__badge">{mediaCount}</span>{/if}
      </button>
      {#if open === 'media'}
        <div class="bs-acc__body">
          <MediaReplacer {destination} {onSaved} />
        </div>
      {/if}
    </div>

    <!-- Links -->
    <div class="bs-acc__item">
      <button type="button" class="bs-acc__head" aria-expanded={open === 'links'} onclick={() => toggle('links')}>
        <span class="bs-acc__chev" class:is-open={open === 'links'}>▸</span>
        <span class="bs-acc__title">Links</span>
        {#if linkCount > 0}<span class="bs-acc__badge">{linkCount}</span>{/if}
      </button>
      {#if open === 'links'}
        <div class="bs-acc__body">
          <LinkReplacer {destination} {onSaved} />
        </div>
      {/if}
    </div>

    <!-- Videos -->
    <div class="bs-acc__item">
      <button type="button" class="bs-acc__head" aria-expanded={open === 'videos'} onclick={() => toggle('videos')}>
        <span class="bs-acc__chev" class:is-open={open === 'videos'}>▸</span>
        <span class="bs-acc__title">Videos</span>
        {#if videoCount > 0}<span class="bs-acc__badge">{videoCount}</span>{/if}
      </button>
      {#if open === 'videos'}
        <div class="bs-acc__body">
          <VideoReplacer {destination} {onSaved} />
        </div>
      {/if}
    </div>
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
</style>
