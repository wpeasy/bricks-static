<script lang="ts">
  import { Button } from '@wpeasy/ab-ui';
  import { api } from '../shared/api';
  import type { ServerConfig } from '../shared/types';
  import { __ } from '../shared/i18n';

  let config = $state<ServerConfig | null>(null);
  let open = $state(false);
  let copied = $state('');

  async function load(): Promise<void> {
    if (!config) {
      try {
        config = await api.serverConfig();
      } catch {
        /* non-fatal */
      }
    }
    open = !open;
  }

  async function copy(text: string, which: string): Promise<void> {
    try {
      await navigator.clipboard.writeText(text);
      copied = which;
      setTimeout(() => (copied = copied === which ? '' : copied), 1500);
    } catch {
      /* clipboard unavailable */
    }
  }
</script>

<section class="bs-card bs-stack bs-stack--sm">
  <div class="bs-row bs-row--between">
    <h2>{__('serverConfig')}</h2>
    <Button variant="ghost" size="sm" onclick={load}>{open ? __('btnHide') : __('btnView')}</Button>
  </div>
  <!-- eslint-disable-next-line svelte/no-at-html-tags -->
  <p class="bs-sc__lead">{@html __('serverConfigLead')}</p>

  {#if open && config}
    <div class="bs-stack bs-stack--xs">
      <div class="bs-row bs-row--between">
        <strong>.htaccess</strong>
        <Button variant="ghost" size="sm" onclick={() => copy(config!.htaccess, 'htaccess')}>
          {copied === 'htaccess' ? __('btnCopied') : __('btnCopy')}
        </Button>
      </div>
      <pre class="bs-sc__code">{config.htaccess}</pre>

      <div class="bs-row bs-row--between">
        <strong>nginx</strong>
        <Button variant="ghost" size="sm" onclick={() => copy(config!.nginx, 'nginx')}>
          {copied === 'nginx' ? __('btnCopied') : __('btnCopy')}
        </Button>
      </div>
      <pre class="bs-sc__code">{config.nginx}</pre>
    </div>
  {/if}
</section>

<style>
  .bs-card {
    padding: var(--ab-space-5);
    background: var(--ab-color-surface);
    border: 1px solid var(--ab-color-border);
    border-radius: var(--ab-radius-lg);
    box-shadow: var(--ab-shadow-sm);
  }

  .bs-sc__lead {
    color: var(--ab-color-text-muted);
    font-size: var(--ab-text-sm);
  }

  .bs-sc__code {
    margin: 0;
    padding: var(--ab-space-3);
    background: var(--ab-color-bg);
    border: 1px solid var(--ab-color-border);
    border-radius: var(--ab-radius-md);
    font-size: var(--ab-text-xs);
    line-height: 1.5;
    max-height: 16rem;
    overflow: auto;
    white-space: pre;
  }

</style>
