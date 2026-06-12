<script lang="ts">
  let {
    running,
    onCheck,
    onSync,
    onCancel,
    onReset,
  }: {
    running: boolean;
    onCheck: () => void;
    onSync: (prune: boolean) => void;
    onCancel: () => void;
    onReset: () => void;
  } = $props();

  let prune = $state(false);
</script>

<section class="bs-card bs-stack bs-stack--sm">
  <h2>Actions</h2>
  <p class="bs-actions__lead">
    <strong>Check sync</strong> renders the site locally and reports what would change — no upload.
    <strong>Sync</strong> renders and pushes the changed files to the destination (a holding page is shown while it runs).
  </p>
  <div class="bs-row bs-row--wrap">
    <button type="button" class="bs-btn bs-btn--secondary" onclick={onCheck} disabled={running}>
      {running ? 'Working…' : 'Check sync'}
    </button>
    <button type="button" class="bs-btn bs-btn--primary" onclick={() => onSync(prune)} disabled={running}>
      {running ? 'Working…' : 'Sync to destination'}
    </button>
    {#if running}
      <button type="button" class="bs-btn bs-btn--secondary" onclick={onCancel}>Cancel</button>
    {/if}
  </div>
  <label class="bs-actions__opt">
    <input type="checkbox" bind:checked={prune} disabled={running} />
    Remove files from the destination that no longer exist locally (prune)
  </label>
  <div class="bs-row bs-row--between bs-actions__foot">
    <span class="bs-actions__hint">Switched destinations or wiped the remote? Reset clears the local push record so the next Sync re-uploads everything.</span>
    <button type="button" class="bs-link" onclick={onReset} disabled={running}>Reset sync state</button>
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
