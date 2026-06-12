<script lang="ts">
  import type { Snippet } from 'svelte';

  let {
    open = $bindable(false),
    title,
    children,
  }: {
    open: boolean;
    title: string;
    children: Snippet;
  } = $props();

  function close(): void {
    open = false;
  }

  function onKey(e: KeyboardEvent): void {
    if (e.key === 'Escape') close();
  }
</script>

<svelte:window onkeydown={open ? onKey : undefined} />

{#if open}
  <!-- svelte-ignore a11y_click_events_have_key_events, a11y_no_static_element_interactions -->
  <div class="bs-modal" onclick={close}>
    <div class="bs-modal__box" role="dialog" aria-modal="true" aria-label={title} tabindex="-1" onclick={(e) => e.stopPropagation()}>
      <div class="bs-modal__head">
        <h2 class="bs-modal__title">{title}</h2>
        <button type="button" class="bs-modal__close" aria-label="Close" onclick={close}>×</button>
      </div>
      <div class="bs-modal__body">
        {@render children()}
      </div>
    </div>
  </div>
{/if}

<style>
  .bs-modal {
    position: fixed;
    inset: 0;
    z-index: 100000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: var(--bs-space--lg);
    background: color-mix(in srgb, var(--bs-color-text) 45%, transparent);
  }

  .bs-modal__box {
    width: min(34rem, 100%);
    max-height: 85vh;
    overflow: auto;
    background: var(--bs-color-surface--raised);
    border: var(--bs-border--1) solid var(--bs-color-border);
    border-radius: var(--bs-radius--lg);
    box-shadow: var(--bs-shadow--lg);
  }

  .bs-modal__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--bs-space--md);
    padding: var(--bs-space--md) var(--bs-space--lg);
    border-bottom: var(--bs-border--1) solid var(--bs-color-border);
  }

  .bs-modal__title {
    margin: 0;
    font-size: var(--bs-text--md);
    font-weight: var(--bs-weight--semibold);
  }

  .bs-modal__close {
    background: none;
    border: 0;
    padding: 0;
    width: 1.75rem;
    height: 1.75rem;
    font-size: var(--bs-text--lg);
    line-height: 1;
    color: var(--bs-color-text--muted);
    cursor: pointer;
  }

  .bs-modal__close:hover {
    color: var(--bs-color-text);
  }

  .bs-modal__body {
    padding: var(--bs-space--lg);
  }
</style>
