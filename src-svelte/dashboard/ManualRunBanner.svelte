<script lang="ts">
  import { __ } from '../shared/i18n';

  let { command, onDismiss }: { command: string; onDismiss: () => void } = $props();

  let copied = $state(false);

  async function copy(): Promise<void> {
    try {
      await navigator.clipboard.writeText(command);
      copied = true;
      setTimeout(() => (copied = false), 1500);
    } catch {
      /* clipboard unavailable */
    }
  }
</script>

<section class="bs-manual">
  <div class="bs-manual__head">
    <strong>{__('manualRunTitle')}</strong>
    <button type="button" class="bs-link" onclick={onDismiss}>{__('btnDismiss')}</button>
  </div>
  <!-- eslint-disable-next-line svelte/no-at-html-tags -->
  <p>{@html __('manualRunBody')}</p>
  <div class="bs-manual__cmd">
    <code>{command}</code>
    <button type="button" class="bs-btn bs-btn--secondary" onclick={copy}>{copied ? __('btnCopied') : __('btnCopy')}</button>
  </div>
  <!-- eslint-disable-next-line svelte/no-at-html-tags -->
  <p class="bs-manual__hint">{@html __('manualRunHint')}</p>
</section>

<style>
  .bs-manual {
    grid-column: 1 / -1;
    display: flex;
    flex-direction: column;
    gap: var(--bs-space--sm);
    padding: var(--bs-space--md) var(--bs-space--lg);
    border: var(--bs-border--1) solid var(--bs-color-warning);
    border-left-width: var(--bs-border--4);
    border-radius: var(--bs-radius--lg);
    background: color-mix(in srgb, var(--bs-color-warning) 8%, var(--bs-color-surface--raised));
  }

  .bs-manual__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--bs-space--md);
  }

  .bs-manual p {
    margin: 0;
    font-size: var(--bs-text--sm);
    color: var(--bs-color-text--secondary);
  }

  .bs-manual__hint {
    color: var(--bs-color-text--muted);
  }

  .bs-manual__cmd {
    display: flex;
    align-items: center;
    gap: var(--bs-space--sm);
    padding: var(--bs-space--xs) var(--bs-space--sm);
    background: var(--bs-color-surface--sunken);
    border-radius: var(--bs-radius--md);
    font-family: var(--bs-font--mono);
  }

  .bs-manual__cmd code {
    flex: 1;
    color: var(--bs-color-text);
    word-break: break-all;
  }

  .bs-btn {
    padding: var(--bs-space--xs) var(--bs-space--md);
    border: var(--bs-border--1) solid var(--bs-color-border--strong);
    border-radius: var(--bs-radius--md);
    background: var(--bs-color-surface);
    color: var(--bs-color-text);
    font: inherit;
    font-size: var(--bs-text--sm);
    font-weight: var(--bs-weight--medium);
    cursor: pointer;
    white-space: nowrap;
  }

  .bs-link {
    background: none;
    border: 0;
    padding: 0;
    color: var(--bs-color-primary);
    font: inherit;
    font-size: var(--bs-text--sm);
    cursor: pointer;
  }
</style>
