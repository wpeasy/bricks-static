<script lang="ts">
  import { api } from '../shared/api';
  import type { ServerConfig } from '../shared/types';

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
    <h2>Server config</h2>
    <button type="button" class="bs-link" onclick={load}>{open ? 'Hide' : 'View'}</button>
  </div>
  <p class="bs-sc__lead">
    The <code>.htaccess</code> is uploaded to the destination automatically (Apache/LiteSpeed). On nginx, paste the snippet below into your server block.
  </p>

  {#if open && config}
    <div class="bs-stack bs-stack--xs">
      <div class="bs-row bs-row--between">
        <strong>.htaccess</strong>
        <button type="button" class="bs-link" onclick={() => copy(config!.htaccess, 'htaccess')}>
          {copied === 'htaccess' ? 'Copied' : 'Copy'}
        </button>
      </div>
      <pre class="bs-sc__code">{config.htaccess}</pre>

      <div class="bs-row bs-row--between">
        <strong>nginx</strong>
        <button type="button" class="bs-link" onclick={() => copy(config!.nginx, 'nginx')}>
          {copied === 'nginx' ? 'Copied' : 'Copy'}
        </button>
      </div>
      <pre class="bs-sc__code">{config.nginx}</pre>
    </div>
  {/if}
</section>

<style>
  .bs-card {
    padding: var(--bs-space--lg);
    background: var(--bs-color-surface--raised);
    border: var(--bs-border--1) solid var(--bs-color-border);
    border-radius: var(--bs-radius--lg);
    box-shadow: var(--bs-shadow--sm);
  }

  .bs-sc__lead {
    color: var(--bs-color-text--muted);
    font-size: var(--bs-text--sm);
  }

  .bs-sc__code {
    margin: 0;
    padding: var(--bs-space--sm);
    background: var(--bs-color-surface--sunken);
    border: var(--bs-border--1) solid var(--bs-color-border--subtle);
    border-radius: var(--bs-radius--md);
    font-size: var(--bs-text--xs);
    line-height: 1.5;
    max-height: 16rem;
    overflow: auto;
    white-space: pre;
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

  .bs-link:hover {
    text-decoration: underline;
  }
</style>
