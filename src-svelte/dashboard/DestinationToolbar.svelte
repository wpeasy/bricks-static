<script lang="ts">
  import type { DestinationDisplay } from '../shared/types';

  let { destination }: { destination: DestinationDisplay } = $props();

  let url = $derived.by(() => {
    const explicit = destination.destinationUrl.value;
    if (explicit) return explicit;
    const host = destination.host.value;
    return host ? `https://${host}` : '';
  });
</script>

<div class="bs-dtoolbar">
  <div class="bs-dstatus">
    <span class="bs-dstatus__item bs-dstatus__item--{destination.status.connected ? 'on' : 'off'}"><span class="bs-dstatus__dot"></span>Connected</span>
    <span class="bs-dstatus__item bs-dstatus__item--{destination.status.hasPushed ? 'on' : 'off'}"><span class="bs-dstatus__dot"></span>Pushed</span>
    <span class="bs-dstatus__item bs-dstatus__item--{destination.status.inSync ? 'on' : 'off'}"><span class="bs-dstatus__dot"></span>In sync</span>
  </div>
  {#if url}
    <a class="bs-dtoolbar__link" href={url} target="_blank" rel="noopener noreferrer">
      Visit site ↗
    </a>
  {/if}
</div>

<style>
  .bs-dtoolbar {
    grid-column: 1 / -1;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: var(--bs-space--md);
    padding: var(--bs-space--sm) var(--bs-space--md);
    background: var(--bs-color-surface--raised);
    border: var(--bs-border--1) solid var(--bs-color-border);
    border-radius: var(--bs-radius--md);
  }

  .bs-dstatus {
    display: flex;
    flex-wrap: wrap;
    gap: var(--bs-space--md);
  }

  .bs-dstatus__item {
    display: flex;
    align-items: center;
    gap: var(--bs-space--2xs);
    font-size: var(--bs-text--sm);
    color: var(--bs-color-text--muted);
  }

  .bs-dstatus__dot {
    width: 0.6rem;
    height: 0.6rem;
    border-radius: var(--bs-radius--full);
    background: var(--bs-color-text--subtle);
  }

  .bs-dstatus__item--on .bs-dstatus__dot {
    background: var(--bs-color-success);
  }

  .bs-dstatus__item--off .bs-dstatus__dot {
    background: var(--bs-color-danger);
  }

  .bs-dtoolbar__link {
    font-size: var(--bs-text--sm);
    font-weight: var(--bs-weight--medium);
    color: var(--bs-color-primary);
    text-decoration: none;
    white-space: nowrap;
  }

  .bs-dtoolbar__link:hover {
    text-decoration: underline;
  }
</style>
