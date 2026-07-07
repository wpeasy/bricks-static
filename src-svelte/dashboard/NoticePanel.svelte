<script lang="ts">
  import { Alert, Button, Status } from '@wpeasy/ab-ui';
  import { api } from '../shared/api';
  import type { Preflight, WpCliInfo } from '../shared/types';
  import { __ } from '../shared/i18n';

  let {
    isLocal,
    cli,
    wpCli,
    inline = false,
  }: { isLocal: boolean; cli: string; wpCli: WpCliInfo; inline?: boolean } = $props();

  let testing = $state(false);
  let result = $state<Preflight | null>(null);
  let copied = $state(false);

  // Positive: WP-CLI will be used (no web-server pressure).
  // Warning: only on a constrained host where WP-CLI isn't usable.
  let mode = $derived.by<'cli' | 'warn' | 'none'>(() => {
    if (wpCli.detected) {
      return 'cli';
    }
    if (isLocal || (result !== null && !result.ok)) {
      return 'warn';
    }
    return 'none';
  });

  async function test(): Promise<void> {
    testing = true;
    try {
      result = await api.preflight();
    } catch (e) {
      result = { ok: false, ms: 0, message: (e as Error).message };
    } finally {
      testing = false;
    }
  }

  async function copy(): Promise<void> {
    try {
      await navigator.clipboard.writeText(cli);
      copied = true;
      setTimeout(() => (copied = false), 1500);
    } catch {
      /* clipboard unavailable */
    }
  }
</script>

{#if mode === 'cli'}
  <Status status="ok">
    {#snippet label()}
      <span class="bs-notice__cli"
        ><strong>{__('wpCliDetected')}{wpCli.version ? ` (${wpCli.version})` : ''}</strong> — {__('wpCliBody')}</span
      >
    {/snippet}
  </Status>
{:else if mode === 'warn'}
  <Alert class={inline ? 'bs-notice--inline' : ''} tone="warning" title={__('runFromCli')}>
    <div class="bs-notice__body">
      <p>{__('cliWarnBody')}</p>
      <div class="bs-notice__cmd">
        <code>{cli}</code>
        <Button variant="ghost" size="sm" onclick={copy}>{copied ? __('btnCopied') : __('btnCopy')}</Button>
      </div>
      <!-- eslint-disable-next-line svelte/no-at-html-tags -->
      <p class="bs-notice__hint">{@html __('cliFlagsHint')}</p>
      <div class="bs-row">
        <Button variant="secondary" size="sm" onclick={test} disabled={testing}>
          {testing ? __('btnTesting') : __('testBrowserRender')}
        </Button>
        {#if result}
          <span class="bs-notice__result bs-notice__result--{result.ok ? 'ok' : 'err'}">
            {result.ok ? `✓ ${result.message}` : `✗ ${result.message}`}
          </span>
        {/if}
      </div>
    </div>
  </Alert>
{/if}

<style>
  .bs-notice__cli {
    font-size: var(--ab-text-sm);
    color: var(--ab-color-text-muted);
  }

  .bs-notice__body {
    display: flex;
    flex-direction: column;
    gap: var(--ab-space-3);
  }

  .bs-notice__body p {
    margin: 0;
    font-size: var(--ab-text-sm);
    color: var(--ab-color-text-muted);
  }

  .bs-notice__hint {
    color: var(--ab-color-text-muted) !important;
  }

  .bs-notice__cmd {
    display: flex;
    align-items: center;
    gap: var(--ab-space-3);
    padding: var(--ab-space-2) var(--ab-space-3);
    background: var(--ab-color-bg);
    border-radius: var(--ab-radius-md);
    font-family: var(--ab-font-mono);
  }

  .bs-notice__cmd code {
    flex: 1;
    color: var(--ab-color-text);
  }

  .bs-notice__result {
    font-size: var(--ab-text-sm);
  }

  .bs-notice__result--ok {
    color: var(--ab-color-success);
  }

  .bs-notice__result--err {
    color: var(--ab-color-danger);
  }
</style>
