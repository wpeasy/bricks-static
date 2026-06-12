<script lang="ts">
  import type { Status } from '../shared/types';

  let { status }: { status: Status | null } = $props();

  type Tone = 'on' | 'off' | 'pending';

  function tone(value: boolean | undefined, known: boolean): Tone {
    if (!known) {
      return 'pending';
    }
    return value ? 'on' : 'off';
  }

  let connected = $derived(tone(status?.connected, status !== null));
  let pushed = $derived(tone(status?.hasPushed, status !== null));
  let inSync = $derived(tone(status?.inSync, status !== null));

  const labels: Record<Tone, string> = { on: 'Yes', off: 'No', pending: '…' };
</script>

<section class="bs-card bs-stack bs-stack--sm">
  <h2>Status</h2>
  <div class="bs-status">
    <div class="bs-status__item bs-status__item--{connected}">
      <span class="bs-status__dot"></span>
      <span class="bs-status__label">Connected</span>
      <span class="bs-status__value">{labels[connected]}</span>
    </div>
    <div class="bs-status__item bs-status__item--{pushed}">
      <span class="bs-status__dot"></span>
      <span class="bs-status__label">Pushed to destination</span>
      <span class="bs-status__value">{labels[pushed]}</span>
    </div>
    <div class="bs-status__item bs-status__item--{inSync}">
      <span class="bs-status__dot"></span>
      <span class="bs-status__label">In sync</span>
      <span class="bs-status__value">{labels[inSync]}</span>
    </div>
  </div>

  {#if status?.lastTest?.message}
    <p class="bs-status__note">
      Last test: {status.lastTest.message}
    </p>
  {/if}
</section>

<style>
  .bs-card {
    padding: var(--bs-space--lg);
    background: var(--bs-color-surface--raised);
    border: var(--bs-border--1) solid var(--bs-color-border);
    border-radius: var(--bs-radius--lg);
    box-shadow: var(--bs-shadow--sm);
  }

  .bs-status {
    display: flex;
    flex-wrap: wrap;
    gap: var(--bs-space--md);
  }

  .bs-status__item {
    display: flex;
    align-items: center;
    gap: var(--bs-space--xs);
    padding: var(--bs-space--xs) var(--bs-space--sm);
    border: var(--bs-border--1) solid var(--bs-color-border);
    border-radius: var(--bs-radius--pill);
    font-size: var(--bs-text--sm);
  }

  .bs-status__dot {
    width: 0.6rem;
    height: 0.6rem;
    border-radius: var(--bs-radius--full);
    background: var(--bs-color-text--subtle);
  }

  .bs-status__item--on .bs-status__dot {
    background: var(--bs-color-success);
  }

  .bs-status__item--off .bs-status__dot {
    background: var(--bs-color-danger);
  }

  .bs-status__value {
    font-weight: var(--bs-weight--semibold);
  }

  .bs-status__note {
    color: var(--bs-color-text--muted);
    font-size: var(--bs-text--sm);
  }
</style>
