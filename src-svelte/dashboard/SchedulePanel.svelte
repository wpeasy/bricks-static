<script lang="ts">
  import { Alert, Select, Switch, fadeUp } from '@wpeasy/ab-ui';
  import { api } from '../shared/api';
  import type { DestinationDisplay, SchedulePeriod, ScheduleSettings } from '../shared/types';
  import { __, __f } from '../shared/i18n';
  import ListSkeleton from '../lib/ListSkeleton.svelte';

  let {
    destinations = [],
  }: {
    destinations?: DestinationDisplay[];
  } = $props();

  let sched = $state<ScheduleSettings | null>(null);
  let error = $state('');
  let saving = $state(false);

  const periodOptions = [
    { value: 'hourly', label: __('schedHourly') },
    { value: 'daily', label: __('schedDaily') },
    { value: 'weekly', label: __('schedWeekly') },
    { value: 'monthly', label: __('schedMonthly') },
  ];

  const statusLabel: Record<string, string> = {
    running: __('schedStRunning'),
    done: __('schedStDone'),
    error: __('schedStError'),
    skipped: __('schedStSkipped'),
    cancelled: __('schedStCancelled'),
    unknown: __('schedStUnknown'),
  };

  let noneChosen = $derived(!!sched && sched.enabled && !sched.all && !sched.destinations.some((id) => destinations.some((d) => d.id === id && d.enabled)));

  function when(ts: number): string {
    return new Date(ts * 1000).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
  }

  async function load(): Promise<void> {
    try {
      sched = await api.getSchedule();
    } catch (e) {
      error = (e as Error).message;
    }
  }

  // Auto-save each change (optimistic; the server's answer wins).
  async function save(patch: Partial<Pick<ScheduleSettings, 'enabled' | 'period' | 'all' | 'destinations'>>): Promise<void> {
    if (!sched) return;
    const previous = $state.snapshot(sched) as ScheduleSettings;
    Object.assign(sched, patch);
    saving = true;
    error = '';
    try {
      sched = await api.saveSchedule(patch);
    } catch (e) {
      sched = previous;
      error = (e as Error).message;
    } finally {
      saving = false;
    }
  }

  function toggleDest(id: string, on: boolean): void {
    if (!sched) return;
    const next = on ? [...new Set([...sched.destinations, id])] : sched.destinations.filter((d) => d !== id);
    save({ destinations: next });
  }

  load();
</script>

<section class="bs-card bs-stack bs-stack--sm">
  <h2>{__('schedHeading')}</h2>
  <p class="bs-sched__lead">{__('schedLead')}</p>

  {#if error}
    <Alert tone="danger" description={error} />
  {/if}

  {#if !sched}
    <ListSkeleton />
  {:else}
    <div class="bs-stack bs-stack--sm" in:fadeUp>
      <Switch label={__('schedEnabled')} checked={sched.enabled ? 1 : 0} disabled={saving} onchange={(c) => save({ enabled: c === 1 })} />

      <div class="bs-sched__field">
        <span class="bs-sched__label">{__('schedPeriod')}</span>
        <Select
          value={sched.period}
          options={periodOptions}
          aria-label={__('schedPeriod')}
          disabled={saving}
          onchange={(v) => save({ period: String(v) as SchedulePeriod })}
        />
      </div>

      <div class="bs-sched__field bs-sched__field--top">
        <span class="bs-sched__label">{__('schedDestinations')}</span>
        <ul class="bs-sched__list">
          <li class="bs-sched__all">
            <Switch label={__('schedAll')} checked={sched.all ? 1 : 0} disabled={saving} onchange={(c) => save({ all: c === 1 })} />
            <span class="bs-sched__help">{__('schedAllHelp')}</span>
          </li>
          {#each destinations as d (d.id)}
            <li>
              <Switch
                label={d.name || d.id}
                checked={sched.all ? (d.enabled ? 1 : 0) : sched.destinations.includes(d.id) ? 1 : 0}
                disabled={saving || sched.all || !d.enabled}
                onchange={(c) => toggleDest(d.id, c === 1)}
              />
              {#if !d.enabled}<span class="bs-sched__help">({__('schedDisabledDest')})</span>{/if}
            </li>
          {/each}
        </ul>
      </div>

      {#if noneChosen}
        <Alert tone="warning" description={__('schedNoneChosen')} />
      {/if}

      {#if sched.enabled && sched.nextRun}
        <p class="bs-sched__meta">{__f('schedNext', when(sched.nextRun))}</p>
      {/if}
      {#if sched.last}
        <p class="bs-sched__meta">{__f('schedLast', when(sched.last.at), statusLabel[sched.last.status] ?? sched.last.status, sched.last.message)}</p>
      {/if}
      {#if sched.enabled && !sched.cronDisabled}
        <p class="bs-sched__help">{__('schedCronNote')}</p>
      {/if}
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
    max-width: 40rem;
  }

  .bs-sched__lead,
  .bs-sched__help,
  .bs-sched__meta {
    color: var(--ab-color-text-muted);
    font-size: var(--ab-text-sm);
  }

  .bs-sched__meta {
    color: var(--ab-color-text);
  }

  .bs-sched__field {
    display: grid;
    grid-template-columns: 9rem 1fr;
    align-items: center;
    gap: var(--ab-space-3);
  }

  .bs-sched__field--top {
    align-items: start;
  }

  .bs-sched__label {
    font-size: var(--ab-text-sm);
    font-weight: var(--ab-weight-medium);
  }

  .bs-sched__list {
    display: flex;
    flex-direction: column;
    gap: var(--ab-space-2);
    margin: 0;
    padding: 0;
    list-style: none;
  }

  .bs-sched__list li {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: var(--ab-space-2);
  }

  .bs-sched__all {
    padding-bottom: var(--ab-space-2);
    border-bottom: 1px solid var(--ab-color-border);
  }

  @media (max-width: 600px) {
    .bs-sched__field {
      grid-template-columns: 1fr;
    }
  }
</style>
