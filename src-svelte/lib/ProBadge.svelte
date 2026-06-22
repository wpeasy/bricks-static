<script lang="ts">
  import { __ } from '../shared/i18n';

  // Small "Requires Pro" pill shown on locked feature rows/controls.
  let { label = __('requiresPro') }: { label?: string } = $props();
</script>

<span class="bs-pro-badge">{label}</span>

<style>
  .bs-pro-badge {
    display: inline-flex;
    align-items: center;
    padding: 0 var(--bs-space--xs);
    border-radius: var(--bs-radius--pill);
    background: var(--bs-color-accent, #7c3aed);
    color: #fff;
    font-size: var(--bs-text--xs);
    font-weight: var(--bs-weight--semibold);
    line-height: 1.6;
    white-space: nowrap;
    letter-spacing: 0.02em;
  }
</style>
