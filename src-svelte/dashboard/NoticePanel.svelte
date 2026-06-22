<script lang="ts">
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
  <section class="bs-notice bs-notice--ok bs-row" class:bs-notice--inline={inline}>
    <span class="bs-notice__dot"></span>
    <p>
      <strong>{__('wpCliDetected')}{wpCli.version ? ` (${wpCli.version})` : ''}</strong> — {__('wpCliBody')}
    </p>
  </section>
{:else if mode === 'warn'}
  <section class="bs-notice bs-notice--warn bs-stack bs-stack--sm" class:bs-notice--inline={inline}>
    <strong>{__('runFromCli')}</strong>
    <p>{__('cliWarnBody')}</p>
    <div class="bs-notice__cmd">
      <code>{cli}</code>
      <button type="button" class="bs-link" onclick={copy}>{copied ? __('btnCopied') : __('btnCopy')}</button>
    </div>
    <!-- eslint-disable-next-line svelte/no-at-html-tags -->
    <p class="bs-notice__hint">{@html __('cliFlagsHint')}</p>
    <div class="bs-row">
      <button type="button" class="bs-btn bs-btn--secondary" onclick={test} disabled={testing}>
        {testing ? __('btnTesting') : __('testBrowserRender')}
      </button>
      {#if result}
        <span class="bs-notice__result bs-notice__result--{result.ok ? 'ok' : 'err'}">
          {result.ok ? `✓ ${result.message}` : `✗ ${result.message}`}
        </span>
      {/if}
    </div>
  </section>
{/if}

<style>
  .bs-notice {
    padding: var(--bs-space--md) var(--bs-space--lg);
    border: var(--bs-border--1) solid var(--bs-color-border);
    border-radius: var(--bs-radius--lg);
    background: var(--bs-color-surface--raised);
  }

  /* Inside the app bar: drop the card chrome so it reads as a bar status. */
  .bs-notice--inline {
    padding: 0;
    border: 0;
    border-radius: 0;
    background: none;
  }

  .bs-notice--ok {
    border-color: var(--bs-color-success);
    border-left-width: var(--bs-border--4);
    align-items: center;
    gap: var(--bs-space--sm);
  }

  .bs-notice--warn {
    border-color: var(--bs-color-warning);
    border-left-width: var(--bs-border--4);
    background: color-mix(in srgb, var(--bs-color-warning) 8%, var(--bs-color-surface--raised));
  }

  .bs-notice__dot {
    flex: 0 0 auto;
    width: 0.6rem;
    height: 0.6rem;
    border-radius: var(--bs-radius--full);
    background: var(--bs-color-success);
  }

  .bs-notice p {
    margin: 0;
    font-size: var(--bs-text--sm);
    color: var(--bs-color-text--secondary);
  }

  .bs-notice__hint {
    color: var(--bs-color-text--muted);
  }

  .bs-notice__cmd {
    display: flex;
    align-items: center;
    gap: var(--bs-space--sm);
    padding: var(--bs-space--xs) var(--bs-space--sm);
    background: var(--bs-color-surface--sunken);
    border-radius: var(--bs-radius--md);
    font-family: var(--bs-font--mono);
  }

  .bs-notice__cmd code {
    flex: 1;
    color: var(--bs-color-text);
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

  .bs-notice__result {
    font-size: var(--bs-text--sm);
  }

  .bs-notice__result--ok {
    color: var(--bs-color-success);
  }

  .bs-notice__result--err {
    color: var(--bs-color-danger);
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
