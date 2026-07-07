<script lang="ts">
  import { Badge } from '@wpeasy/ab-ui';
  import { __ } from '../shared/i18n';

  // Small "Requires Pro" pill shown on locked feature rows/controls.
  let { label = __('requiresPro') }: { label?: string } = $props();
</script>

<Badge class="bs-pro-badge" variant="solid" {label} />

<style>
  /* Pro brand purple — ab-ui has no purple tone, so override the Badge fill.
     0,3,0 beats ab-ui's `.ab-badge--{tone}` (0,1,0); no !important needed. */
  :global(.ab-ui .ab-badge.bs-pro-badge) {
    background: #7c3aed;
    color: #fff;
  }
</style>
