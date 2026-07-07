<script lang="ts">
  import { Alert, Button } from '@wpeasy/ab-ui';
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

<Alert class="bs-manual" tone="warning" title={__('manualRunTitle')} dismissible ondismiss={onDismiss}>
  <div class="bs-manual__body">
    <!-- eslint-disable-next-line svelte/no-at-html-tags -->
    <p>{@html __('manualRunBody')}</p>
    <div class="bs-manual__cmd">
      <code>{command}</code>
      <Button variant="secondary" size="sm" onclick={copy}>{copied ? __('btnCopied') : __('btnCopy')}</Button>
    </div>
    <!-- eslint-disable-next-line svelte/no-at-html-tags -->
    <p class="bs-manual__hint">{@html __('manualRunHint')}</p>
  </div>
</Alert>

<style>
  /* The Alert root carries our class (set from outside the library). */
  :global(.bs-manual) {
    grid-column: 1 / -1;
  }

  .bs-manual__body {
    display: flex;
    flex-direction: column;
    gap: var(--ab-space-3);
  }

  .bs-manual__body p {
    margin: 0;
    font-size: var(--ab-text-sm);
    color: var(--ab-color-text-muted);
  }

  .bs-manual__hint {
    color: var(--ab-color-text-muted) !important;
  }

  .bs-manual__cmd {
    display: flex;
    align-items: center;
    gap: var(--ab-space-3);
    padding: var(--ab-space-2) var(--ab-space-3);
    background: var(--ab-color-bg);
    border-radius: var(--ab-radius-md);
    font-family: var(--ab-font-mono);
  }

  .bs-manual__cmd code {
    flex: 1;
    color: var(--ab-color-text);
    word-break: break-all;
  }
</style>
