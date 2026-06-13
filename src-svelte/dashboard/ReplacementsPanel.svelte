<script lang="ts">
  import { untrack } from 'svelte';
  import { api } from '../shared/api';
  import type { DestinationDisplay, Replacement } from '../shared/types';
  import ReplacementsRepeater from './ReplacementsRepeater.svelte';
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

  const d = untrack(() => destination);

  let replacements = $state<Replacement[]>(d.replacements.map((r) => ({ ...r })));
  let saving = $state(false);
  let message = $state('');
  let messageOk = $state(true);
  let busy = $derived(saving || running);

  async function save(): Promise<void> {
    saving = true;
    message = '';
    try {
      await api.updateDestination(d.id, { replacements: replacements.filter((r) => r.search !== '') });
      message = 'Saved.';
      messageOk = true;
      onSaved();
    } catch (e) {
      message = (e as Error).message;
      messageOk = false;
    } finally {
      saving = false;
    }
  }
</script>

<section class="bs-card bs-stack bs-stack--md">
  <h2 class="bs-rp__title">Replacements <small>(applied to this destination only)</small></h2>

  <ReplacementsRepeater bind:replacements disabled={busy} />

  {#if message}
    <p class="bs-msg bs-msg--{messageOk ? 'ok' : 'err'}">{message}</p>
  {/if}

  <div class="bs-row">
    <button type="button" class="bs-btn bs-btn--secondary" onclick={save} disabled={busy}>
      {saving ? 'Saving…' : 'Save replacements'}
    </button>
  </div>

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

  .bs-btn {
    padding: var(--bs-space--xs) var(--bs-space--md);
    border: var(--bs-border--1) solid var(--bs-color-border--strong);
    border-radius: var(--bs-radius--md);
    background: var(--bs-color-surface);
    color: var(--bs-color-text);
    font: inherit;
    font-weight: var(--bs-weight--medium);
    cursor: pointer;
  }

  .bs-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
  }

  .bs-msg {
    margin: 0;
    font-size: var(--bs-text--sm);
  }

  .bs-msg--ok {
    color: var(--bs-color-success);
  }

  .bs-msg--err {
    color: var(--bs-color-danger);
  }
</style>
