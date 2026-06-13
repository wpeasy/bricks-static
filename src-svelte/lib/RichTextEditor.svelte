<script lang="ts">
  // A deliberately small WYSIWYG: a contenteditable box with a few formatting
  // commands. Value is the box's inner HTML (sanitised server-side via wp_kses).
  let {
    value = $bindable(''),
    disabled = false,
    placeholder = 'Replace with…',
  }: { value: string; disabled?: boolean; placeholder?: string } = $props();

  let el: HTMLDivElement | undefined = $state();

  // Push external value changes into the box without clobbering the caret while
  // the user is typing (only when they differ).
  $effect(() => {
    if (el && el.innerHTML !== value) {
      el.innerHTML = value;
    }
  });

  function sync(): void {
    if (el) value = el.innerHTML;
  }

  function exec(command: string): void {
    if (disabled) return;
    el?.focus();
    document.execCommand(command, false);
    sync();
  }

  function link(): void {
    if (disabled) return;
    const url = window.prompt('Link URL');
    if (url) {
      el?.focus();
      document.execCommand('createLink', false, url);
      sync();
    }
  }
</script>

<div class="bs-rte" class:is-disabled={disabled}>
  <div class="bs-rte__bar" role="toolbar" aria-label="Formatting">
    <button type="button" onclick={() => exec('bold')} {disabled} aria-label="Bold"><b>B</b></button>
    <button type="button" onclick={() => exec('italic')} {disabled} aria-label="Italic"><i>I</i></button>
    <button type="button" onclick={() => exec('underline')} {disabled} aria-label="Underline"><u>U</u></button>
    <button type="button" onclick={link} {disabled} aria-label="Link">🔗</button>
    <button type="button" onclick={() => exec('removeFormat')} {disabled} aria-label="Clear formatting">✕</button>
  </div>
  <!-- svelte-ignore a11y_no_static_element_interactions -->
  <div
    bind:this={el}
    class="bs-rte__body"
    contenteditable={!disabled}
    data-placeholder={placeholder}
    oninput={sync}
    onblur={sync}
  ></div>
</div>

<style>
  .bs-rte {
    border: var(--bs-border--1) solid var(--bs-color-border--strong);
    border-radius: var(--bs-radius--md);
    background: var(--bs-color-surface);
    overflow: hidden;
  }

  .bs-rte.is-disabled {
    opacity: 0.6;
  }

  .bs-rte__bar {
    display: flex;
    gap: var(--bs-space--3xs);
    padding: var(--bs-space--2xs);
    border-bottom: var(--bs-border--1) solid var(--bs-color-border);
    background: var(--bs-color-surface--sunken);
  }

  .bs-rte__bar button {
    min-width: 1.6rem;
    height: 1.6rem;
    border: 0;
    border-radius: var(--bs-radius--sm);
    background: none;
    color: var(--bs-color-text--muted);
    font-size: var(--bs-text--sm);
    cursor: pointer;
  }

  .bs-rte__bar button:hover:not(:disabled) {
    background: var(--bs-color-surface);
    color: var(--bs-color-text);
  }

  .bs-rte__body {
    min-height: 3rem;
    max-height: 10rem;
    overflow: auto;
    padding: var(--bs-space--xs) var(--bs-space--sm);
    font-size: var(--bs-text--sm);
    color: var(--bs-color-text);
    outline: none;
  }

  .bs-rte__body:empty::before {
    content: attr(data-placeholder);
    color: var(--bs-color-text--subtle);
  }
</style>
