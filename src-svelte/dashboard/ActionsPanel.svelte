<script lang="ts">
  let {
    running,
    onCheck,
    onCancel,
  }: {
    running: boolean;
    onCheck: () => void;
    onCancel: () => void;
  } = $props();
</script>

<section class="bs-card bs-stack bs-stack--sm">
  <h2>Actions</h2>
  <p class="bs-actions__lead">
    <strong>Check sync</strong> renders the whole site to the local cache and reports what would be pushed — it does not upload anything yet.
  </p>
  <div class="bs-row bs-row--wrap">
    <button type="button" class="bs-btn bs-btn--primary" onclick={onCheck} disabled={running}>
      {running ? 'Working…' : 'Check sync'}
    </button>
    {#if running}
      <button type="button" class="bs-btn bs-btn--secondary" onclick={onCancel}>Cancel</button>
    {/if}
    <button type="button" class="bs-btn bs-btn--secondary" disabled title="Uploading lands in the next milestone (M3)">
      Sync (coming soon)
    </button>
  </div>
</section>

<style>
  .bs-card {
    padding: var(--bs-space--lg);
    background: var(--bs-color-surface--raised);
    border: var(--bs-border--1) solid var(--bs-color-border);
    border-radius: var(--bs-radius--lg);
    box-shadow: var(--bs-shadow--sm);
  }

  .bs-actions__lead {
    color: var(--bs-color-text--muted);
    font-size: var(--bs-text--sm);
  }

  .bs-btn {
    padding: var(--bs-space--xs) var(--bs-space--md);
    border: var(--bs-border--1) solid transparent;
    border-radius: var(--bs-radius--md);
    font: inherit;
    font-weight: var(--bs-weight--medium);
    cursor: pointer;
  }

  .bs-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
  }

  .bs-btn--primary {
    background: var(--bs-color-primary);
    color: var(--bs-color-primary--contrast);
  }

  .bs-btn--primary:hover:not(:disabled) {
    background: var(--bs-color-primary--hover);
  }

  .bs-btn--secondary {
    background: var(--bs-color-surface);
    border-color: var(--bs-color-border--strong);
    color: var(--bs-color-text);
  }
</style>
