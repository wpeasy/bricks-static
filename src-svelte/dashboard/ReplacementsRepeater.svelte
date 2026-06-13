<script lang="ts">
  import type { Replacement } from '../shared/types';
  import RichTextEditor from '../lib/RichTextEditor.svelte';

  let { replacements = $bindable(), disabled = false }: { replacements: Replacement[]; disabled?: boolean } = $props();

  function add(): void {
    replacements = [...replacements, { search: '', replace: '', rich: false }];
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
    Literal find/replace, applied only to visible text content between tags — never attributes, scripts or markup. Be specific to avoid unintended matches.
  </p>

  {#if replacements.length > 0}
    <div class="bs-rep__rows">
      {#each replacements as row, i (i)}
        <div class="bs-rep__row">
          <input type="text" placeholder="Find" bind:value={row.search} {disabled} />
          <div class="bs-rep__replace">
            <div class="bs-seg" role="group" aria-label="Replacement format">
              <button type="button" class="bs-seg__btn" class:is-active={!row.rich} onclick={() => (row.rich = false)} {disabled}>Plain</button>
              <button type="button" class="bs-seg__btn" class:is-active={row.rich} onclick={() => (row.rich = true)} {disabled}>Rich</button>
            </div>
            {#if row.rich}
              <RichTextEditor bind:value={row.replace} {disabled} />
            {:else}
              <input type="text" placeholder="Replace with" bind:value={row.replace} {disabled} />
            {/if}
          </div>
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
    grid-template-columns: 1fr 1.6fr auto;
    gap: var(--bs-space--xs);
    align-items: start;
  }

  .bs-rep__replace {
    display: flex;
    flex-direction: column;
    gap: var(--bs-space--2xs);
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

  .bs-seg {
    display: inline-flex;
    align-self: flex-start;
    border: var(--bs-border--1) solid var(--bs-color-border--strong);
    border-radius: var(--bs-radius--sm);
    overflow: hidden;
  }

  .bs-seg__btn {
    padding: 0 var(--bs-space--xs);
    height: 1.4rem;
    border: 0;
    background: var(--bs-color-surface);
    color: var(--bs-color-text--muted);
    font: inherit;
    font-size: var(--bs-text--xs);
    cursor: pointer;
  }

  .bs-seg__btn + .bs-seg__btn {
    border-left: var(--bs-border--1) solid var(--bs-color-border--strong);
  }

  .bs-seg__btn.is-active {
    background: var(--bs-color-primary);
    color: var(--bs-color-primary--contrast);
  }

  .bs-seg__btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
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
