<script lang="ts">
  import type { Replacement } from '../shared/types';

  let { replacements = $bindable(), disabled = false }: { replacements: Replacement[]; disabled?: boolean } = $props();

  function add(): void {
    replacements = [...replacements, { search: '', replace: '' }];
  }

  function remove(index: number): void {
    replacements = replacements.filter((_, i) => i !== index);
  }
</script>

<div class="bs-rep">
  <div class="bs-rep__head">
    <span>Text replacements <small>(optional)</small></span>
    <button type="button" class="bs-link" onclick={add} {disabled}>+ Add</button>
  </div>
  <p class="bs-rep__warn">
    Literal find/replace — applied only to visible text and <code>&lt;img&gt;</code> sources, never other attributes or markup. Be specific to avoid unintended matches.
  </p>

  {#if replacements.length > 0}
    <div class="bs-rep__rows">
      {#each replacements as row, i (i)}
        <div class="bs-rep__row">
          <input type="text" placeholder="Find" bind:value={row.search} {disabled} />
          <input type="text" placeholder="Replace with" bind:value={row.replace} {disabled} />
          <button type="button" class="bs-rep__del" onclick={() => remove(i)} {disabled} aria-label="Remove">×</button>
        </div>
      {/each}
    </div>
  {/if}
</div>

<style>
  .bs-rep {
    display: flex;
    flex-direction: column;
    gap: var(--bs-space--xs);
  }

  .bs-rep__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: var(--bs-text--sm);
    font-weight: var(--bs-weight--medium);
    color: var(--bs-color-text--muted);
  }

  .bs-rep__warn {
    margin: 0;
    font-size: var(--bs-text--xs);
    color: var(--bs-color-warning);
  }

  .bs-rep__rows {
    display: flex;
    flex-direction: column;
    gap: var(--bs-space--xs);
  }

  .bs-rep__row {
    display: grid;
    grid-template-columns: 1fr 1fr auto;
    gap: var(--bs-space--xs);
    align-items: center;
  }

  .bs-rep__row input {
    padding: var(--bs-space--xs) var(--bs-space--sm);
    border: var(--bs-border--1) solid var(--bs-color-border--strong);
    border-radius: var(--bs-radius--md);
    background: var(--bs-color-surface);
    color: var(--bs-color-text);
    font: inherit;
    font-size: var(--bs-text--sm);
  }

  .bs-rep__del {
    width: 1.8rem;
    height: 1.8rem;
    border: var(--bs-border--1) solid var(--bs-color-border--strong);
    border-radius: var(--bs-radius--md);
    background: var(--bs-color-surface);
    color: var(--bs-color-text--muted);
    cursor: pointer;
    font-size: var(--bs-text--lg);
    line-height: 1;
  }

  .bs-link {
    background: none;
    border: 0;
    padding: 0;
    color: var(--bs-color-primary);
    font: inherit;
    font-size: var(--bs-text--sm);
    cursor: pointer;
  }

  .bs-link:disabled,
  .bs-rep__del:disabled {
    opacity: 0.5;
    cursor: not-allowed;
  }
</style>
