<script lang="ts">
  import { Badge, Button, Progress, fadeUp } from '@wpeasy/ab-ui';
  import type { ExportSnapshot } from '../shared/types';
  import { exportDownloadUrl } from '../shared/api';
  import { __ } from '../shared/i18n';

  let {
    snapshot,
    onProcess,
    onCancel,
  }: {
    snapshot: ExportSnapshot | null;
    /** Run a Process (render) — shown when the export needs a current render first. */
    onProcess?: () => void;
    onCancel?: () => void;
  } = $props();

  // A small, dedicated panel rather than a ProgressPanel extension — export's
  // 4 linear phases (no page/asset/upload counters, no per-destination fan-out)
  // don't fit that shape without padding SyncSnapshot with always-empty fields.
  // Mounted globally (like ProgressPanel) since Export ZIP can be triggered
  // either from a destination's own toolbar or from the "All destinations" list.
  const PHASE_LABELS: Record<string, string> = {
    preparing: __('exportPhasePreparing'),
    gzip: __('exportPhaseGzip'),
    packaging: __('exportPhasePackaging'),
    saving: __('exportPhaseSaving'),
    done: __('exportPhaseDone'),
    error: __('phError'),
    cancelled: __('phCancelled'),
  };

  let phase = $derived(snapshot?.phase ?? '');
  let visible = $derived(snapshot !== null && phase !== 'idle');
  let needsProcess = $derived(phase === 'needsProcess');
  let phaseLabel = $derived(needsProcess ? __('btnProcess') : (PHASE_LABELS[phase] ?? phase));
  let running = $derived(!!snapshot?.running);

  let tone = $derived<'primary' | 'success' | 'warning' | 'danger'>(
    needsProcess || phase === 'cancelled'
      ? 'warning'
      : phase === 'error'
        ? 'danger'
        : phase === 'done'
          ? 'success'
          : 'primary',
  );

  // gzip/packaging have a real per-file counter; preparing/saving are single
  // atomic operations (indeterminate bar), same convention ProgressPanel uses
  // for its own atomic "package" phase.
  let showBar = $derived(phase === 'preparing' || phase === 'gzip' || phase === 'packaging' || phase === 'saving');
  let indeterminate = $derived(phase === 'preparing' || phase === 'saving');
  let barValue = $derived(phase === 'gzip' ? (snapshot?.gzipDone ?? 0) : phase === 'packaging' ? (snapshot?.packDone ?? 0) : null);
  let barMax = $derived(
    phase === 'gzip' ? Math.max(snapshot?.gzipTotal ?? 0, 1) : phase === 'packaging' ? Math.max(snapshot?.packTotal ?? 0, 1) : 1,
  );

  function humanBytes(bytes: number): string {
    if (!bytes) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB'];
    const i = Math.min(units.length - 1, Math.floor(Math.log(bytes) / Math.log(1024)));
    return `${(bytes / Math.pow(1024, i)).toFixed(i === 0 ? 0 : 1)} ${units[i]}`;
  }
</script>

{#if visible && snapshot}
  <section class="bs-card bs-export bs-export--{tone}" in:fadeUp>
    <div class="bs-row bs-row--between">
      <h2>{__('btnExport')}{#if snapshot.destName}<span class="bs-export__dest"> · {snapshot.destName}</span>{/if}</h2>
      <Badge {tone} variant="soft">{phaseLabel}</Badge>
    </div>

    {#if snapshot.message}<p class="bs-export__msg">{snapshot.message}</p>{/if}

    {#if needsProcess}
      {#if onProcess}
        <div class="bs-export__actions">
          <Button variant="secondary" size="sm" onclick={onProcess}>{__('btnProcess')}</Button>
        </div>
      {/if}
    {:else}
      {#if showBar}
        <Progress value={indeterminate ? null : barValue} max={indeterminate ? undefined : barMax} {tone} label={phaseLabel} />
      {/if}

      {#if snapshot.gzipNotice}
        <div class="bs-export__notice">{snapshot.gzipNotice}</div>
      {/if}

      <div class="bs-export__actions">
        {#if phase === 'done' && snapshot.downloadToken}
          <a class="ab-button ab-button--primary bs-export__download" href={exportDownloadUrl(snapshot.downloadToken)}>
            {__('btnDownload')}{#if snapshot.bytes}<span class="bs-export__size"> · {humanBytes(snapshot.bytes)}</span>{/if}
          </a>
        {/if}
        {#if running && onCancel}
          <Button variant="ghost" size="sm" onclick={onCancel}>{__('exportCancel')}</Button>
        {/if}
      </div>
    {/if}
  </section>
{/if}

<style>
  .bs-card {
    padding: var(--ab-space-5);
    background: var(--ab-color-surface);
    border: 1px solid var(--ab-color-border);
    border-radius: var(--ab-radius-lg);
    box-shadow: var(--ab-shadow-sm);
  }

  .bs-export {
    display: flex;
    flex-direction: column;
    gap: var(--ab-space-3);
  }

  .bs-export__dest {
    font-weight: var(--ab-weight-normal);
    color: var(--ab-color-text-muted);
  }

  .bs-export__msg {
    font-size: var(--ab-text-sm);
    color: var(--ab-color-text-muted);
  }

  .bs-export__actions {
    display: flex;
    align-items: center;
    gap: var(--ab-space-3);
  }

  .bs-export__notice {
    padding: var(--ab-space-3) var(--ab-space-4);
    border: 1px solid var(--ab-color-border);
    border-radius: var(--ab-radius-md);
    background: var(--ab-color-surface-2, var(--ab-color-surface));
    font-size: var(--ab-text-sm);
    color: var(--ab-color-text-muted);
    line-height: 1.5;
  }

  /* .ab-button supplies background/hover, but shared/app.css's global
     `.ab-ui :where(a)` link-color rule ties with `.ab-button`'s own color on
     specificity (both (0,1,0)) and loads later, so it would otherwise win and
     paint the text the same colour as the button background (invisible).
     The tag+class selector here outranks both, and also suppresses the
     underline an <a> would otherwise get (resting and hover). */
  a.bs-export__download,
  a.bs-export__download:hover {
    color: var(--ab-color-primary-on);
    text-decoration: none;
  }

  .bs-export__size {
    opacity: 0.85;
  }
</style>
