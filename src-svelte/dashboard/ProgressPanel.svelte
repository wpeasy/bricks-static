<script lang="ts">
  import type { SyncSnapshot } from '../shared/types';

  let { snapshot }: { snapshot: SyncSnapshot | null } = $props();

  const PHASE_LABELS: Record<string, string> = {
    collect: 'Collecting URLs',
    render: 'Rendering pages',
    assets: 'Processing assets',
    finalize: 'Finalising',
    upload: 'Uploading',
    prune: 'Removing old files',
    done: 'Done',
    error: 'Error',
    cancelled: 'Cancelled',
  };

  let visible = $derived(snapshot !== null && snapshot.phase !== 'idle');
  let phaseLabel = $derived(snapshot ? (PHASE_LABELS[snapshot.phase] ?? snapshot.phase) : '');

  let pagesDone = $derived(snapshot?.counts?.pagesDone ?? 0);
  let pagesTotal = $derived(Math.max(snapshot?.totals?.pages ?? 0, pagesDone));
  let assetsDone = $derived(snapshot?.counts?.assetsDone ?? 0);
  let assetsTotal = $derived(Math.max(snapshot?.totals?.assets ?? 0, assetsDone));
  let uploaded = $derived(snapshot?.counts?.uploaded ?? 0);
  let uploadsTotal = $derived(Math.max(snapshot?.totals?.uploads ?? 0, uploaded));
  let showUploads = $derived((snapshot?.type === 'sync') && uploadsTotal > 0);

  let tone = $derived(
    snapshot?.phase === 'error' || snapshot?.phase === 'cancelled'
      ? 'warn'
      : snapshot?.phase === 'done'
        ? 'ok'
        : 'active',
  );

  function pct(done: number, total: number): number {
    return total > 0 ? Math.min(100, Math.round((done / total) * 100)) : 0;
  }

  function humanBytes(bytes: number): string {
    if (!bytes) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB'];
    const i = Math.min(units.length - 1, Math.floor(Math.log(bytes) / Math.log(1024)));
    return `${(bytes / Math.pow(1024, i)).toFixed(i === 0 ? 0 : 1)} ${units[i]}`;
  }
</script>

{#if visible && snapshot}
  <section class="bs-card bs-stack bs-stack--sm bs-progress--{tone}">
    <div class="bs-row bs-row--between">
      <h2>Progress</h2>
      <span class="bs-progress__phase">{phaseLabel}</span>
    </div>

    {#if snapshot.message}
      <p class="bs-progress__msg">{snapshot.message}</p>
    {/if}

    <div class="bs-progress__bar-group">
      <div class="bs-progress__row">
        <span class="bs-progress__label">Pages</span>
        <div class="bs-progress__track">
          <div class="bs-progress__fill" style="width: {pct(pagesDone, pagesTotal)}%"></div>
        </div>
        <span class="bs-progress__num">{pagesDone}/{pagesTotal}</span>
      </div>
      <div class="bs-progress__row">
        <span class="bs-progress__label">Assets</span>
        <div class="bs-progress__track">
          <div class="bs-progress__fill" style="width: {pct(assetsDone, assetsTotal)}%"></div>
        </div>
        <span class="bs-progress__num">{assetsDone}/{assetsTotal}</span>
      </div>
      {#if showUploads}
        <div class="bs-progress__row">
          <span class="bs-progress__label">Uploads</span>
          <div class="bs-progress__track">
            <div class="bs-progress__fill" style="width: {pct(uploaded, uploadsTotal)}%"></div>
          </div>
          <span class="bs-progress__num">{uploaded}/{uploadsTotal}</span>
        </div>
      {/if}
    </div>

    <div class="bs-progress__stats">
      <span>{snapshot.counts?.files ?? 0} files</span>
      <span>{humanBytes(snapshot.counts?.bytes ?? 0)}</span>
      {#if (snapshot.counts?.pruned ?? 0) > 0}<span>{snapshot.counts?.pruned} removed</span>{/if}
      {#if (snapshot.skippedCount ?? 0) > 0}<span>{snapshot.skippedCount} skipped</span>{/if}
      {#if (snapshot.errorCount ?? 0) > 0}<span class="bs-progress__err">{snapshot.errorCount} errors</span>{/if}
    </div>

    {#if snapshot.errors && snapshot.errors.length > 0}
      <details>
        <summary>Errors ({snapshot.errorCount})</summary>
        <ul class="bs-progress__list">
          {#each snapshot.errors as e}
            <li><code>{e.url}</code> — {e.error}</li>
          {/each}
        </ul>
      </details>
    {/if}

    {#if snapshot.skipped && snapshot.skipped.length > 0}
      <details>
        <summary>Skipped ({snapshot.skippedCount})</summary>
        <ul class="bs-progress__list">
          {#each snapshot.skipped as s}
            <li><code>{s.url}</code> — {s.reason}</li>
          {/each}
        </ul>
      </details>
    {/if}
  </section>
{/if}

<style>
  .bs-card {
    padding: var(--bs-space--lg);
    background: var(--bs-color-surface--raised);
    border: var(--bs-border--1) solid var(--bs-color-border);
    border-radius: var(--bs-radius--lg);
    box-shadow: var(--bs-shadow--sm);
  }

  .bs-progress__phase {
    font-size: var(--bs-text--sm);
    font-weight: var(--bs-weight--semibold);
    padding: var(--bs-space--3xs) var(--bs-space--sm);
    border-radius: var(--bs-radius--pill);
    background: var(--bs-color-surface--sunken);
  }

  .bs-progress--ok .bs-progress__phase {
    background: color-mix(in srgb, var(--bs-color-success) 18%, transparent);
    color: var(--bs-color-success);
  }

  .bs-progress--warn .bs-progress__phase {
    background: color-mix(in srgb, var(--bs-color-warning) 18%, transparent);
    color: var(--bs-color-warning);
  }

  .bs-progress__msg {
    color: var(--bs-color-text--muted);
    font-size: var(--bs-text--sm);
  }

  .bs-progress__bar-group {
    display: flex;
    flex-direction: column;
    gap: var(--bs-space--xs);
  }

  .bs-progress__row {
    display: grid;
    grid-template-columns: 4rem 1fr 4rem;
    align-items: center;
    gap: var(--bs-space--sm);
  }

  .bs-progress__label {
    font-size: var(--bs-text--sm);
    color: var(--bs-color-text--muted);
  }

  .bs-progress__track {
    height: 0.5rem;
    border-radius: var(--bs-radius--pill);
    background: var(--bs-color-surface--sunken);
    overflow: hidden;
  }

  .bs-progress__fill {
    height: 100%;
    background: var(--bs-color-primary);
    transition: width 0.2s ease;
  }

  .bs-progress--ok .bs-progress__fill {
    background: var(--bs-color-success);
  }

  .bs-progress__num {
    font-size: var(--bs-text--sm);
    font-variant-numeric: tabular-nums;
    text-align: right;
    color: var(--bs-color-text--muted);
  }

  .bs-progress__stats {
    display: flex;
    flex-wrap: wrap;
    gap: var(--bs-space--md);
    font-size: var(--bs-text--sm);
    color: var(--bs-color-text--muted);
  }

  .bs-progress__err {
    color: var(--bs-color-danger);
  }

  .bs-progress__list {
    margin: var(--bs-space--xs) 0 0;
    padding-left: var(--bs-space--lg);
    font-size: var(--bs-text--xs);
    color: var(--bs-color-text--muted);
    max-height: 12rem;
    overflow: auto;
  }

  .bs-progress__list code {
    color: var(--bs-color-text);
  }

  summary {
    cursor: pointer;
    font-size: var(--bs-text--sm);
  }
</style>
