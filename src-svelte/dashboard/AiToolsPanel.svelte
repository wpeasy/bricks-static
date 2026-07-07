<script lang="ts">
  import { Alert, Switch } from '@wpeasy/ab-ui';
  import { __ } from '../shared/i18n';

  let {
    available,
    allowChanges,
    allowSync,
    disabled = false,
    onChange,
  }: {
    available: boolean;
    allowChanges: boolean;
    allowSync: boolean;
    disabled?: boolean;
    onChange: (toggles: { aiAllowChanges?: boolean; aiAllowSync?: boolean }) => void;
  } = $props();
</script>

<section class="bs-card bs-stack bs-stack--sm bs-ai">
  <h3 class="bs-ai__title">{__('aiTitle')}</h3>
  <p class="bs-ai__intro">{__('aiIntro')}</p>

  {#if !available}
    <Alert tone="warning" description={__('aiUnavailable')} />
  {:else}
    <Switch checked={allowChanges ? 1 : 0} {disabled} onchange={(c) => onChange({ aiAllowChanges: c === 1 })}>
      {#snippet label()}
        <span class="bs-ai__rowtext">
          <span class="bs-ai__rowlabel">{__('aiAllowChanges')}</span>
          <span class="bs-ai__rowhint">{__('aiAllowChangesHint')}</span>
        </span>
      {/snippet}
    </Switch>

    <Switch checked={allowSync ? 1 : 0} {disabled} onchange={(c) => onChange({ aiAllowSync: c === 1 })}>
      {#snippet label()}
        <span class="bs-ai__rowtext">
          <span class="bs-ai__rowlabel">{__('aiAllowSync')}</span>
          <span class="bs-ai__rowhint">{__('aiAllowSyncHint')}</span>
        </span>
      {/snippet}
    </Switch>

    <p class="bs-ai__note">{__('aiConsumerNote')}</p>
  {/if}
</section>

<style>
  .bs-ai__title {
    margin: 0;
    font-size: var(--ab-text-md);
    font-weight: var(--ab-weight-semibold);
    color: var(--ab-color-text);
  }

  .bs-ai__intro {
    margin: 0;
    font-size: var(--ab-text-sm);
    color: var(--ab-color-text-muted);
  }

  .bs-ai__rowtext {
    display: flex;
    flex-direction: column;
    gap: var(--ab-space-1);
  }

  .bs-ai__rowlabel {
    font-size: var(--ab-text-sm);
    font-weight: var(--ab-weight-medium);
    color: var(--ab-color-text);
  }

  .bs-ai__rowhint {
    font-size: var(--ab-text-xs);
    color: var(--ab-color-text-muted);
  }

  .bs-ai__note {
    margin: 0;
    font-size: var(--ab-text-xs);
    color: var(--ab-color-text-muted);
  }
</style>
