<script lang="ts">
  import type { DiscoveryMode, Method } from '../shared/types';
  import { __ } from '../shared/i18n';

  let { method, mode }: { method: Method | null; mode?: DiscoveryMode } = $props();

  // Derived from the (reactive, optimistic) discovery mode — updates instantly
  // when the mode changes, no server round-trip. Falls back to the server text.
  const DISC_KEY: Record<DiscoveryMode, string> = {
    linked: 'methodDiscLinked',
    all: 'methodDiscAll',
    manual: 'methodDiscManual',
  };
  let discovery = $derived(mode ? __(DISC_KEY[mode]) : (method?.discovery.description ?? '…'));

  let transportLabel = $derived.by(() => {
    if (!method) {
      return '…';
    }
    const labels: Record<string, string> = { sftp: 'SFTP', ftps: 'FTPS', ftp: 'FTP' };
    return labels[method.transport] ?? method.transport;
  });

  let gzip = $derived(method ? (method.compression.gzip ? 'gzip ✓' : 'gzip ✗') : '…');

  let target = $derived.by(() => {
    if (!method) {
      return '…';
    }
    const parts: string[] = [];
    if (method.serverTarget.htaccess) parts.push('.htaccess');
    if (method.serverTarget.nginxSnippet) parts.push('nginx snippet');
    return parts.join(' + ');
  });
</script>

<section class="bs-card bs-stack bs-stack--sm">
  <h2>{__('method')}</h2>
  <p class="bs-method__lead">{__('methodLead')}</p>
  <dl class="bs-method">
    <div class="bs-method__row">
      <dt>{__('mDiscovery')}</dt>
      <dd>{discovery}</dd>
    </div>
    <div class="bs-method__row">
      <dt>{__('mTransport')}</dt>
      <dd>{transportLabel}</dd>
    </div>
    <div class="bs-method__row">
      <dt>{__('mCompression')}</dt>
      <dd>{gzip}</dd>
    </div>
    <div class="bs-method__row">
      <dt>{__('mServerTarget')}</dt>
      <dd>{target}</dd>
    </div>
    <div class="bs-method__row">
      <dt>{__('mLinks')}</dt>
      <dd>{method ? method.links : '…'}</dd>
    </div>
  </dl>
</section>

<style>
  .bs-card {
    padding: var(--ab-space-5);
    background: var(--ab-color-surface);
    border: 1px solid var(--ab-color-border);
    border-radius: var(--ab-radius-lg);
    box-shadow: var(--ab-shadow-sm);
  }

  .bs-method__lead {
    color: var(--ab-color-text-muted);
    font-size: var(--ab-text-sm);
  }

  .bs-method {
    display: grid;
    gap: var(--ab-space-2);
    margin: 0;
  }

  .bs-method__row {
    display: grid;
    grid-template-columns: 9rem 1fr;
    gap: var(--ab-space-3);
    align-items: baseline;
  }

  .bs-method__row dt {
    color: var(--ab-color-text-muted);
    font-size: var(--ab-text-sm);
  }

  .bs-method__row dd {
    margin: 0;
    font-family: var(--ab-font-mono);
    font-size: var(--ab-text-sm);
    word-break: break-word;
  }
</style>
