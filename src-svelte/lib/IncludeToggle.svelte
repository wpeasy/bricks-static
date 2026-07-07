<script lang="ts">
  // Presentational "Include" switch shared by the editor metabox and the FAB
  // modal — now a thin wrapper over ab-ui's <Switch>. Strings are passed in as
  // props so each host controls translation (the metabox passes translated
  // strings; the FAB passes its own).
  import { Switch } from '@wpeasy/ab-ui';

  let {
    included = $bindable(true),
    label,
    hint = '',
    disabled = false,
    onChange,
  }: {
    included: boolean;
    label: string;
    hint?: string;
    disabled?: boolean;
    onChange: (value: boolean) => void;
  } = $props();
</script>

<div class="bs-inc">
  <Switch {label} checked={included ? 1 : 0} {disabled} onchange={(c) => onChange(c === 1)} />
  {#if hint}
    <p class="bs-inc__hint">{hint}</p>
  {/if}
</div>

<style>
  .bs-inc {
    display: flex;
    flex-direction: column;
    gap: var(--ab-space-1);
  }

  .bs-inc__hint {
    margin: 0;
    font-size: var(--ab-text-xs);
    color: var(--ab-color-text-muted);
  }
</style>
