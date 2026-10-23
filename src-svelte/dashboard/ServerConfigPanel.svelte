<script lang="ts">
  import { Button, Select, Status, Tag, fadeUp } from '@wpeasy/ab-ui';
  import { api } from '../shared/api';
  import type { DestinationDisplay, ServerCheck, ServerConfig, ServerKind } from '../shared/types';
  import { __, __f } from '../shared/i18n';

  let {
    destinations = [],
    defaultDestId = '',
  }: {
    destinations?: DestinationDisplay[];
    defaultDestId?: string;
  } = $props();

  let config = $state<ServerConfig | null>(null);
  let open = $state(false);
  let copied = $state('');

  // Server check — destinations that can be probed are those with a public
  // URL (or a host to guess one from); the pick defaults to the active tab.
  let checkable = $derived(destinations.filter((d) => d.enabled && (d.destinationUrl.value || d.host.value)));
  let destOptions = $derived(checkable.map((d) => ({ value: d.id, label: d.name || d.id })));
  let destId = $state('');
  let checking = $state(false);
  let checkError = $state('');
  let checks = $state<Record<string, ServerCheck>>({});
  let result = $derived(destId ? (checks[destId] ?? null) : null);

  $effect(() => {
    if (!destId || !checkable.some((d) => d.id === destId)) {
      destId = checkable.some((d) => d.id === defaultDestId) ? defaultDestId : (checkable[0]?.id ?? '');
    }
  });

  async function loadConfig(): Promise<void> {
    if (config) {
      return;
    }
    try {
      config = await api.serverConfig();
      checks = { ...config.checks, ...checks };
    } catch {
      /* non-fatal */
    }
  }

  // The last stored checks come with the config payload — fetch it up front so
  // a previous result shows without a re-probe.
  $effect(() => {
    void loadConfig();
  });

  async function toggleSnippets(): Promise<void> {
    await loadConfig();
    open = !open;
  }

  async function runCheck(): Promise<void> {
    if (!destId || checking) {
      return;
    }
    checking = true;
    checkError = '';
    try {
      const r = await api.serverCheck(destId);
      checks = { ...checks, [destId]: r };
    } catch (e) {
      checkError = e instanceof Error ? e.message : String(e);
    } finally {
      checking = false;
    }
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

  const SERVER_LABEL: Record<ServerKind, () => string> = {
    apache: () => 'Apache',
    litespeed: () => 'LiteSpeed',
    nginx: () => 'nginx',
    cloudflare: () => 'Cloudflare',
    github: () => 'GitHub Pages',
    proxied: () => __('serverProxied'),
    other: () => __('serverUnknown'),
    unknown: () => __('serverUnknown'),
  };

  type Tone = 'success' | 'warning' | 'danger' | 'neutral';
  const APPLIED: Record<ServerCheck['applied'], { key: string; tone: Tone; status: 'ok' | 'warn' | 'error' | 'unknown' }> = {
    yes: { key: 'cacheApplied', tone: 'success', status: 'ok' },
    partial: { key: 'cachePartial', tone: 'warning', status: 'warn' },
    no: { key: 'cacheNotApplied', tone: 'warning', status: 'warn' },
    unknown: { key: 'cacheUnknown', tone: 'neutral', status: 'unknown' },
  };

  function serverLabel(r: ServerCheck): string {
    const base = SERVER_LABEL[r.server]();
    return r.serverHeader && r.server !== 'unknown' && r.serverHeader.toLowerCase() !== base.toLowerCase()
      ? `${base} (${r.serverHeader})`
      : base;
  }

  function when(ts: number): string {
    return new Date(ts * 1000).toLocaleString();
  }

  // On nginx the rules are a manual paste — open the snippets so the fix is
  // right there under the verdict.
  $effect(() => {
    if (result && result.ok && result.server === 'nginx' && result.applied !== 'yes') {
      open = true;
    }
  });
</script>

<section class="bs-card bs-stack bs-stack--sm">
  <div class="bs-row bs-row--between">
    <h2>{__('serverConfig')}</h2>
    <Button variant="ghost" size="sm" onclick={toggleSnippets}>{open ? __('btnHide') : __('btnView')}</Button>
  </div>
  <!-- eslint-disable-next-line svelte/no-at-html-tags -->
  <p class="bs-sc__lead">{@html __('serverConfigLead')}</p>

  <div class="bs-sc__check bs-stack bs-stack--xs">
    <h3>{__('serverCheckTitle')}</h3>
    <p class="bs-sc__lead">{__('serverCheckLead')}</p>

    {#if checkable.length === 0}
      <p class="bs-sc__lead">{__('noDestUrl')}</p>
    {:else}
      <div class="bs-row bs-sc__controls">
        {#if checkable.length > 1}
          <Select
            value={destId}
            options={destOptions}
            disabled={checking}
            aria-label={__('lblDestination')}
            onchange={(v) => (destId = String(v))}
          />
        {/if}
        <Button variant="primary" size="sm" disabled={checking || !destId} onclick={runCheck}>
          {checking ? __('checkingServer') : result ? __('btnRecheck') : __('btnCheckServer')}
        </Button>
      </div>

      {#if checkError}
        <Status status="error" label={checkError} />
      {/if}

      {#key result}
        {#if result}
          <div class="bs-sc__result bs-stack bs-stack--xs" in:fadeUp>
            {#if !result.ok}
              <Status status="error" label={result.error} />
            {:else}
              <Status status={APPLIED[result.applied].status} label={result.message} />
              <dl class="bs-sc__facts">
                <dt>{__('lblServer')}</dt>
                <dd>{serverLabel(result)}</dd>

                <dt>{__('lblCachePolicy')}</dt>
                <dd><Tag size="sm" tone={APPLIED[result.applied].tone} label={__(APPLIED[result.applied].key)} /></dd>

                {#if result.html}
                  <dt>{__('lblCompression')}</dt>
                  <dd>
                    <Tag size="sm" tone={result.html.gzip ? 'success' : 'neutral'} label={result.html.gzip ? __('gzipYes') : __('gzipNo')} />
                  </dd>

                  <dt>{__('lblHtmlHeaders')}</dt>
                  <dd><code>{result.html.cacheControl || __('noCacheControl')}</code> <small>(HTTP {result.html.code})</small></dd>
                {/if}

                {#if result.asset}
                  <dt>{__('lblAssetHeaders')}</dt>
                  <dd>
                    <code>{result.asset.cacheControl || __('noCacheControl')}</code>
                    <small>(HTTP {result.asset.code} — {result.asset.path})</small>
                  </dd>
                {/if}
              </dl>
            {/if}
            <small>
              {__f('checkedAt', when(result.checkedAt))} — <code>{result.url}</code>
              {#if result.urlGuessed}<br />{__('urlGuessedHint')}{/if}
            </small>
          </div>
        {/if}
      {/key}
    {/if}
  </div>

  {#if open && config}
    <div class="bs-stack bs-stack--xs" in:fadeUp>
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

      <div class="bs-row bs-row--between">
        <strong>Cloudflare <code>_headers</code></strong>
        <Button variant="ghost" size="sm" onclick={() => copy(config!.cloudflareHeaders, 'cloudflare')}>
          {copied === 'cloudflare' ? __('btnCopied') : __('btnCopy')}
        </Button>
      </div>
      <p class="bs-sc__lead">{__('cfHeadersNote')}</p>
      <pre class="bs-sc__code">{config.cloudflareHeaders}</pre>
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

  .bs-sc__check {
    padding: var(--ab-space-4);
    background: var(--ab-color-bg);
    border: 1px solid var(--ab-color-border);
    border-radius: var(--ab-radius-md);
  }

  .bs-sc__controls {
    flex-wrap: wrap;
  }

  .bs-sc__result {
    padding-top: var(--ab-space-2);
    border-top: 1px solid var(--ab-color-border);
  }

  .bs-sc__facts {
    display: grid;
    grid-template-columns: max-content 1fr;
    gap: var(--ab-space-1) var(--ab-space-4);
    margin: 0;
    font-size: var(--ab-text-sm);
  }
  .bs-sc__facts dt {
    color: var(--ab-color-text-muted);
  }
  .bs-sc__facts dd {
    margin: 0;
    overflow-wrap: anywhere;
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
