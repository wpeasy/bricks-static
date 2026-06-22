<script lang="ts">
  import { caps } from '../shared/capabilities.svelte';
  import { PURCHASE_URL, ACCOUNT_URL } from '../shared/upsell';
  import { __ } from '../shared/i18n';

  // Upsell shown in the body of a locked Pro feature. Switches to "renew" copy
  // when a previously-valid license has expired.
  let { description = '' }: { description?: string } = $props();

  let expired = $derived(caps.licenseState === 'expired');
</script>

<div class="bs-upsell">
  {#if description}
    <p class="bs-upsell__desc">{description}</p>
  {/if}

  {#if expired}
    <p class="bs-upsell__note">{__('upsellExpired')}</p>
    <a class="bs-upsell__btn" href={ACCOUNT_URL} target="_blank" rel="noopener noreferrer">{__('renewLicense')}</a>
  {:else}
    <a class="bs-upsell__btn" href={PURCHASE_URL} target="_blank" rel="noopener noreferrer">{__('upgradeToPro')}</a>
  {/if}
</div>

<style>
  .bs-upsell {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: var(--bs-space--sm);
  }

  .bs-upsell__desc {
    margin: 0;
    color: var(--bs-color-text--muted);
    font-size: var(--bs-text--sm);
  }

  .bs-upsell__note {
    margin: 0;
    color: var(--bs-color-text--subtle);
    font-size: var(--bs-text--xs);
  }

  .bs-upsell__btn {
    display: inline-flex;
    align-items: center;
    padding: var(--bs-space--xs) var(--bs-space--md);
    border-radius: var(--bs-radius--md);
    background: var(--bs-color-accent, #7c3aed);
    color: #fff;
    font-size: var(--bs-text--sm);
    font-weight: var(--bs-weight--semibold);
    text-decoration: none;
  }

  .bs-upsell__btn:hover {
    filter: brightness(1.08);
  }
</style>
