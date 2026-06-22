<script lang="ts">
  import type { Method } from '../shared/types';
  import { __ } from '../shared/i18n';

  let { method }: { method: Method | null } = $props();

  let discovery = $derived(method?.discovery.description ?? '…');

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
    padding: var(--bs-space--lg);
    background: var(--bs-color-surface--raised);
    border: var(--bs-border--1) solid var(--bs-color-border);
    border-radius: var(--bs-radius--lg);
    box-shadow: var(--bs-shadow--sm);
  }

  .bs-method__lead {
    color: var(--bs-color-text--muted);
    font-size: var(--bs-text--sm);
  }

  .bs-method {
    display: grid;
    gap: var(--bs-space--xs);
    margin: 0;
  }

  .bs-method__row {
    display: grid;
    grid-template-columns: 9rem 1fr;
    gap: var(--bs-space--sm);
    align-items: baseline;
  }

  .bs-method__row dt {
    color: var(--bs-color-text--muted);
    font-size: var(--bs-text--sm);
  }

  .bs-method__row dd {
    margin: 0;
    font-family: var(--bs-font--mono);
    font-size: var(--bs-text--sm);
    word-break: break-word;
  }
</style>
