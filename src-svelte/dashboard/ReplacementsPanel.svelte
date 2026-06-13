<script lang="ts">
  import type { DestinationDisplay } from '../shared/types';
  import TextReplacements from './TextReplacements.svelte';
  import MediaReplacer from './MediaReplacer.svelte';

  let {
    destination,
    running,
    onSaved,
  }: {
    destination: DestinationDisplay;
    running: boolean;
    onSaved: () => void;
  } = $props();
</script>

<section class="bs-card bs-stack bs-stack--md">
  <h2 class="bs-rp__title">Replacements <small>(applied to this destination only · saved automatically)</small></h2>

  <TextReplacements {destination} {running} {onSaved} />

  <hr class="bs-rp__divider" />

  <MediaReplacer {destination} {onSaved} />
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

  .bs-rp__divider {
    width: 100%;
    border: 0;
    border-top: var(--bs-border--1) solid var(--bs-color-border);
    margin: 0;
  }
</style>
