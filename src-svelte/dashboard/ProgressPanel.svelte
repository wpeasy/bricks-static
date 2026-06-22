<script lang="ts">
  import type { SyncSnapshot } from '../shared/types';
  import { caps } from '../shared/capabilities.svelte';
  import { PURCHASE_URL } from '../shared/upsell';
  import { __, __f } from '../shared/i18n';

  let {
    snapshot,
    onRetry,
    retrying = false,
  }: {
    snapshot: SyncSnapshot | null;
    onRetry?: () => void;
    retrying?: boolean;
  } = $props();

  let failedCount = $derived(snapshot?.failedCount ?? 0);
  let canRetry = $derived(failedCount > 0 && !snapshot?.running && !!onRetry);

  const PHASE_LABELS: Record<string, string> = {
    collect: __('phCollect'),
    render: __('phRender'),
    assets: __('phAssets'),
    finalize: __('phFinalize'),
    package: __('phPackage'),
    upload: __('phUpload'),
    prune: __('phPrune'),
    done: __('phDone'),
    error: __('phError'),
    cancelled: __('phCancelled'),
  };

  // Dismissal: once a run has finished it can be closed. Keyed to the run's
  // startedAt so a NEW run re-shows the card (the next run has a fresh id), and
  // persisted to localStorage so a dismissed run stays dismissed across reloads
  // (the status endpoint still returns the last finished snapshot on reload).
  const DISMISS_KEY = 'bs_progress_dismissed';

  function loadDismissed(): number | null {
    try {
      const v = localStorage.getItem(DISMISS_KEY);
      return v ? Number(v) : null;
    } catch {
      return null;
    }
  }

  let dismissedRun = $state<number | null>(loadDismissed());
  let finished = $derived(!snapshot?.running && ['done', 'error', 'cancelled'].includes(snapshot?.phase ?? ''));
  let dismissed = $derived(snapshot?.startedAt != null && dismissedRun === snapshot.startedAt);

  let visible = $derived(snapshot !== null && snapshot.phase !== 'idle' && !dismissed);
  let phaseLabel = $derived(snapshot ? (PHASE_LABELS[snapshot.phase] ?? snapshot.phase) : '');

  function dismiss(): void {
    if (snapshot?.startedAt != null) {
      dismissedRun = snapshot.startedAt;
      try {
        localStorage.setItem(DISMISS_KEY, String(snapshot.startedAt));
      } catch {
        /* ignore */
      }
    }
  }

  // Which destination(s) this run is for (a 'check' run has no real target).
  let destLabel = $derived.by<string>(() => {
    const t = snapshot?.targets;
    if (!t || !t.total || snapshot?.type === 'check') return '';
    if (t.total === 1) return t.name;
    const pos = snapshot?.phase === 'done' ? t.total : Math.min(t.done + 1, t.total);
    return `${t.name} · ${pos} of ${t.total}`;
  });

  let pagesDone = $derived(snapshot?.counts?.pagesDone ?? 0);
  let pagesTotal = $derived(Math.max(snapshot?.totals?.pages ?? 0, pagesDone));
  let assetsDone = $derived(snapshot?.counts?.assetsDone ?? 0);
  let assetsTotal = $derived(Math.max(snapshot?.totals?.assets ?? 0, assetsDone));
  let uploaded = $derived(snapshot?.counts?.uploaded ?? 0);
  let uploadsTotal = $derived(Math.max(snapshot?.totals?.uploads ?? 0, uploaded));
  // The package phase is one atomic operation, so a file counter would just sit
  // at 0 — show an indeterminate bar and the live stage message instead.
  let isPackaging = $derived(snapshot?.phase === 'package');
  let showUploads = $derived((snapshot?.type === 'sync') && uploadsTotal > 0 && !isPackaging);

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
      <h2>{__('progress')}{#if destLabel}<span class="bs-progress__dest"> · {destLabel}</span>{/if}</h2>
      <div class="bs-progress__head-right">
        <span class="bs-progress__phase">{phaseLabel}</span>
        {#if finished}
          <button type="button" class="bs-progress__close" onclick={dismiss} aria-label={__('btnDismiss')} data-balloon={__('btnDismiss')} data-balloon-pos="down-left">×</button>
        {/if}
      </div>
    </div>

    {#if snapshot.message}
      <p class="bs-progress__msg">{snapshot.message}</p>
    {/if}

    <div class="bs-progress__bar-group">
      <div class="bs-progress__row">
        <span class="bs-progress__label">{__('lblPages')}</span>
        <div class="bs-progress__track">
          <div class="bs-progress__fill" style="width: {pct(pagesDone, pagesTotal)}%"></div>
        </div>
        <span class="bs-progress__num">{pagesDone}/{pagesTotal}</span>
      </div>
      <div class="bs-progress__row">
        <span class="bs-progress__label">{__('lblAssets')}</span>
        <div class="bs-progress__track">
          <div class="bs-progress__fill" style="width: {pct(assetsDone, assetsTotal)}%"></div>
        </div>
        <span class="bs-progress__num">{assetsDone}/{assetsTotal}</span>
      </div>
      {#if isPackaging}
        <div class="bs-progress__row">
          <span class="bs-progress__label">{__('lblDeploy')}</span>
          <div class="bs-progress__track">
            <div class="bs-progress__fill bs-progress__fill--indeterminate"></div>
          </div>
          <span class="bs-progress__num">{__f('nFiles', uploadsTotal)}</span>
        </div>
      {:else if showUploads}
        <div class="bs-progress__row">
          <span class="bs-progress__label">{__('lblUploads')}</span>
          <div class="bs-progress__track">
            <div class="bs-progress__fill" style="width: {pct(uploaded, uploadsTotal)}%"></div>
          </div>
          <span class="bs-progress__num">{uploaded}/{uploadsTotal}</span>
        </div>
      {/if}
    </div>

    <div class="bs-progress__stats">
      <span>{__f('statsSite', snapshot.counts?.files ?? 0, humanBytes(snapshot.counts?.bytes ?? 0))}</span>
      {#if (snapshot.counts?.pruned ?? 0) > 0}<span>{__f('nRemoved', snapshot.counts?.pruned ?? 0)}</span>{/if}
      {#if (snapshot.skippedCount ?? 0) > 0}<span>{__f('nSkipped', snapshot.skippedCount ?? 0)}</span>{/if}
      {#if (snapshot.compatCount ?? 0) > 0}<span class="bs-progress__warn">{__f('nNotStatic', snapshot.compatCount ?? 0)}</span>{/if}
      {#if (snapshot.errorCount ?? 0) > 0}<span class="bs-progress__err">{__f('nErrors', snapshot.errorCount ?? 0)}</span>{/if}
      {#if failedCount > 0}<span class="bs-progress__err">{failedCount === 1 ? __f('nFailedUpload', failedCount) : __f('nFailedUploads', failedCount)}</span>{/if}
    </div>

    {#if snapshot.pageLimitHit}
      <div class="bs-progress__limit">
        <!-- eslint-disable-next-line svelte/no-at-html-tags -->
        <span>{@html __f('freePlanLimit', caps.maxPages)}</span>
        <a class="bs-btn bs-btn--primary" href={PURCHASE_URL} target="_blank" rel="noopener noreferrer">{__('upgradeToPro')}</a>
      </div>
    {/if}

    {#if failedCount > 0}
      <div class="bs-progress__retry">
        <span>{failedCount === 1 ? __f('nFileFailed', failedCount) : __f('nFilesFailed', failedCount)}</span>
        {#if canRetry}
          <button type="button" class="bs-btn bs-btn--primary" onclick={onRetry} disabled={retrying}>
            {retrying ? __('btnRetrying') : failedCount === 1 ? __f('btnRetryUpload', failedCount) : __f('btnRetryUploads', failedCount)}
          </button>
        {/if}
      </div>
    {/if}

    {#if snapshot.errors && snapshot.errors.length > 0}
      <details>
        <summary>{__f('summaryErrors', snapshot.errorCount ?? 0)}</summary>
        <ul class="bs-progress__list">
          {#each snapshot.errors as e}
            <li><code>{e.url}</code> — {e.error}</li>
          {/each}
        </ul>
      </details>
    {/if}

    {#if snapshot.skipped && snapshot.skipped.length > 0}
      <details>
        <summary>{__f('summarySkipped', snapshot.skippedCount ?? 0)}</summary>
        <ul class="bs-progress__list">
          {#each snapshot.skipped as s}
            <li><code>{s.url}</code> — {s.reason}</li>
          {/each}
        </ul>
      </details>
    {/if}

    {#if snapshot.compat && snapshot.compat.length > 0}
      <details>
        <summary>{__f('summaryCompat', snapshot.compatCount ?? 0)}</summary>
        <ul class="bs-progress__list">
          {#each snapshot.compat as c}
            <li>
              <code>{c.url}</code>
              <ul class="bs-progress__sublist">
                {#each c.issues as issue}
                  <li><span class="bs-progress__itype">{issue.type}</span>{#if issue.link}: <code>{issue.link}</code>{/if}</li>
                {/each}
              </ul>
            </li>
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

  .bs-progress__head-right {
    display: flex;
    align-items: center;
    gap: var(--bs-space--xs);
  }

  .bs-progress__phase {
    font-size: var(--bs-text--sm);
    font-weight: var(--bs-weight--semibold);
    padding: var(--bs-space--3xs) var(--bs-space--sm);
    border-radius: var(--bs-radius--pill);
    background: var(--bs-color-surface--sunken);
  }

  .bs-progress__close {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 1.6rem;
    height: 1.6rem;
    border: 0;
    border-radius: var(--bs-radius--sm);
    background: none;
    color: var(--bs-color-text--muted);
    font-size: var(--bs-text--lg);
    line-height: 1;
    cursor: pointer;
  }

  .bs-progress__close:hover {
    background: var(--bs-color-surface--sunken);
    color: var(--bs-color-text);
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

  .bs-progress__fill--indeterminate {
    width: 35%;
    border-radius: var(--bs-radius--pill);
    animation: bs-indeterminate 1.1s ease-in-out infinite;
  }

  @keyframes bs-indeterminate {
    0% { margin-left: -35%; }
    100% { margin-left: 100%; }
  }

  .bs-progress__dest {
    font-weight: var(--bs-weight--normal);
    color: var(--bs-color-text--muted);
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

  .bs-progress__warn {
    color: var(--bs-color-warning);
  }

  .bs-progress__retry {
    display: flex;
    align-items: center;
    gap: var(--bs-space--md);
    padding: var(--bs-space--sm) var(--bs-space--md);
    border: var(--bs-border--1) solid var(--bs-color-danger);
    border-radius: var(--bs-radius--md);
    background: color-mix(in srgb, var(--bs-color-danger) 7%, var(--bs-color-surface--raised));
    font-size: var(--bs-text--sm);
  }

  .bs-progress__retry span {
    flex: 1;
  }

  .bs-progress__limit {
    display: flex;
    align-items: center;
    gap: var(--bs-space--md);
    padding: var(--bs-space--sm) var(--bs-space--md);
    border: var(--bs-border--1) solid var(--bs-color-accent, #7c3aed);
    border-radius: var(--bs-radius--md);
    background: color-mix(in srgb, var(--bs-color-accent, #7c3aed) 7%, var(--bs-color-surface--raised));
    font-size: var(--bs-text--sm);
  }

  .bs-progress__limit span {
    flex: 1;
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

  .bs-btn--primary {
    background: var(--bs-color-primary);
    border-color: transparent;
    color: var(--bs-color-primary--contrast);
  }

  .bs-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
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

  .bs-progress__sublist {
    margin: var(--bs-space--3xs) 0 var(--bs-space--xs);
    padding-left: var(--bs-space--md);
    list-style: none;
  }

  .bs-progress__itype {
    font-weight: var(--bs-weight--medium);
    color: var(--bs-color-text);
  }

  summary {
    cursor: pointer;
    font-size: var(--bs-text--sm);
  }
</style>
