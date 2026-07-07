<script lang="ts">
  // Loading placeholder for lists/tables — a stack of ab-ui Skeleton rows shown
  // while data is fetched, instead of a "Loading…" text line. `label` is the
  // accessible status text (screen readers); the bars themselves are decorative.
  import { Skeleton } from '@wpeasy/ab-ui';
  import { __ } from '../shared/i18n';

  let { rows = 5, label = __('loading') }: { rows?: number; label?: string } = $props();
</script>

<div class="bs-skel" role="status" aria-label={label}>
  {#each Array.from({ length: rows }) as _, i (i)}
    <Skeleton variant="rect" height="2.25rem" />
  {/each}
</div>

<style>
  .bs-skel {
    display: flex;
    flex-direction: column;
    gap: var(--ab-space-2);
  }
</style>
