<script lang="ts">
  import { Badge, Checkbox, ConfirmButton } from '@wpeasy/ab-ui';
  import type { DestinationDisplay } from '../shared/types';
  import { __, __f } from '../shared/i18n';

  let {
    destinations,
    running,
    onSyncAll,
    onSyncOne,
    onSelect,
  }: {
    destinations: DestinationDisplay[];
    running: boolean;
    onSyncAll: (prune: boolean) => void;
    onSyncOne: (id: string, prune: boolean) => void;
    onSelect: (id: string) => void;
  } = $props();

  let prune = $state(false);
  let enabledCount = $derived(destinations.filter((d) => d.enabled).length);
</script>

<section class="bs-card bs-stack bs-stack--sm">
  <h2>{__('allDestHeading')}</h2>
  <p class="bs-all__lead">{__('allDestLead')}</p>

  <ul class="bs-all__list">
    {#each destinations as d (d.id)}
      <li class="bs-all__item">
        <button type="button" class="bs-all__name" onclick={() => onSelect(d.id)}>
          {d.name || __('destinationDefault')}
        </button>
        <span class="bs-all__meta">
          {d.enabled ? (d.host.value || '—') : __('stDisabled')}
        </span>
        <Badge tone={d.status.inSync ? 'success' : d.status.hasPushed ? 'warning' : 'neutral'} variant="soft">
          {d.status.inSync ? __('stInSync') : d.status.hasPushed ? __('stOutOfDate') : __('stNotPushed')}
        </Badge>
        <ConfirmButton
          variant="secondary"
          size="sm"
          label={__('btnSync')}
          confirmLabel={__('btnConfirmSync')}
          onconfirm={() => onSyncOne(d.id, prune)}
          disabled={running || !d.enabled}
        />
      </li>
    {/each}
  </ul>

  <Checkbox label={__('pruneOption')} checked={prune ? 1 : 0} disabled={running} onchange={(c) => (prune = c === 1)} />

  <div class="bs-row">
    <ConfirmButton
      variant="primary"
      label={running ? __('btnWorking') : __f('syncAllN', enabledCount)}
      confirmLabel={__('btnConfirmSyncAll')}
      onconfirm={() => onSyncAll(prune)}
      disabled={running || enabledCount === 0}
    />
  </div>
</section>

<style>
  .bs-card {
    /* Span two columns of the auto-fit panels grid (falls back to 1 when narrow). */
    grid-column: span 2;
    padding: var(--ab-space-5);
    background: var(--ab-color-surface);
    border: 1px solid var(--ab-color-border);
    border-radius: var(--ab-radius-lg);
    box-shadow: var(--ab-shadow-sm);
  }

  .bs-all__lead {
    color: var(--ab-color-text-muted);
    font-size: var(--ab-text-sm);
  }

  .bs-all__list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: var(--ab-space-2);
  }

  .bs-all__item {
    display: grid;
    grid-template-columns: 1fr auto auto auto;
    align-items: center;
    gap: var(--ab-space-3);
    padding: var(--ab-space-2) var(--ab-space-3);
    border: 1px solid var(--ab-color-border);
    border-radius: var(--ab-radius-md);
  }

  .bs-all__name {
    background: none;
    border: 0;
    padding: 0;
    text-align: left;
    font: inherit;
    font-weight: var(--ab-weight-medium);
    color: var(--ab-color-primary);
    cursor: pointer;
  }

  .bs-all__meta {
    font-size: var(--ab-text-sm);
    color: var(--ab-color-text-muted);
    font-family: var(--ab-font-mono);
  }
</style>
