<script lang="ts">
  // A small WYSIWYG with a raw-HTML source view. WYSIWYG edits write the box's
  // innerHTML to `value`; the source view edits that HTML directly. Output is
  // sanitised server-side via wp_kses_post.
  let {
    value = $bindable(''),
    disabled = false,
    placeholder = 'Replace with…',
    rows = 3,
  }: { value: string; disabled?: boolean; placeholder?: string; rows?: number } = $props();

  let el: HTMLDivElement | undefined = $state();
  let source = $state(false);

  // Push external value changes into the box without clobbering the caret while
  // the user is typing (only when they differ). Skipped while in source view —
  // the box is unmounted then and repainted from `value` when we switch back.
  $effect(() => {
    if (!source && el && el.innerHTML !== value) {
      el.innerHTML = value;
    }
  });

  function sync(): void {
    if (el) value = el.innerHTML;
  }

  function exec(command: string, arg?: string): void {
    if (disabled) return;
    el?.focus();
    document.execCommand(command, false, arg);
    sync();
  }

  function link(): void {
    if (disabled) return;
    const url = window.prompt('Link URL');
    if (url) exec('createLink', url);
  }

  function toggleSource(): void {
    if (disabled) return;
    // Capture the latest WYSIWYG edits before revealing the source.
    if (!source && el) value = el.innerHTML;
    source = !source;
  }

  // Keep the selection alive: a toolbar mousedown would otherwise blur the
  // editable and collapse the range before execCommand runs.
  function keepSelection(e: MouseEvent): void {
    if ((e.target as HTMLElement).closest('button')) e.preventDefault();
  }
</script>

<div class="bs-rte" class:is-disabled={disabled}>
  <div class="bs-rte__bar" role="toolbar" aria-label="Formatting" onmousedown={keepSelection}>
    {#if !source}
      <button type="button" class="bs-rte__btn" onclick={() => exec('formatBlock', 'P')} {disabled} data-balloon="Paragraph" data-balloon-pos="down">¶</button>
      <button type="button" class="bs-rte__btn" onclick={() => exec('formatBlock', 'H1')} {disabled} data-balloon="Heading 1" data-balloon-pos="down">H1</button>
      <button type="button" class="bs-rte__btn" onclick={() => exec('formatBlock', 'H2')} {disabled} data-balloon="Heading 2" data-balloon-pos="down">H2</button>
      <button type="button" class="bs-rte__btn" onclick={() => exec('formatBlock', 'H3')} {disabled} data-balloon="Heading 3" data-balloon-pos="down">H3</button>
      <span class="bs-rte__sep"></span>
      <button type="button" class="bs-rte__btn" onclick={() => exec('bold')} {disabled} data-balloon="Bold" data-balloon-pos="down"><b>B</b></button>
      <button type="button" class="bs-rte__btn" onclick={() => exec('italic')} {disabled} data-balloon="Italic" data-balloon-pos="down"><i>I</i></button>
      <button type="button" class="bs-rte__btn" onclick={() => exec('underline')} {disabled} data-balloon="Underline" data-balloon-pos="down"><u>U</u></button>
      <span class="bs-rte__sep"></span>
      <button type="button" class="bs-rte__btn" onclick={() => exec('insertUnorderedList')} {disabled} data-balloon="Bullet list" data-balloon-pos="down">•—</button>
      <button type="button" class="bs-rte__btn" onclick={() => exec('insertOrderedList')} {disabled} data-balloon="Numbered list" data-balloon-pos="down">1.</button>
      <span class="bs-rte__sep"></span>
      <button type="button" class="bs-rte__btn" onclick={link} {disabled} data-balloon="Link" data-balloon-pos="down">🔗</button>
      <button type="button" class="bs-rte__btn" onclick={() => exec('removeFormat')} {disabled} data-balloon="Clear formatting" data-balloon-pos="down">✕</button>
    {:else}
      <span class="bs-rte__srclabel">HTML source</span>
    {/if}
    <span class="bs-rte__spacer"></span>
    <button
      type="button"
      class="bs-rte__btn bs-rte__btn--code"
      class:is-active={source}
      onclick={toggleSource}
      {disabled}
      data-balloon={source ? 'Visual editor' : 'Edit HTML'}
      data-balloon-pos="down-left"
    >&lt;/&gt;</button>
  </div>

  {#if source}
    <textarea
      class="bs-rte__source"
      bind:value
      {disabled}
      {placeholder}
      style="min-height: {(rows * 1.6).toFixed(1)}em"
      spellcheck="false"
    ></textarea>
  {:else}
    <!-- svelte-ignore a11y_no_static_element_interactions -->
    <div
      bind:this={el}
      class="bs-rte__body"
      contenteditable={!disabled}
      data-placeholder={placeholder}
      style="min-height: {(rows * 1.6).toFixed(1)}em"
      oninput={sync}
      onblur={sync}
    ></div>
  {/if}
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
    align-items: center;
    gap: var(--bs-space--3xs);
    padding: var(--bs-space--2xs);
    border-bottom: var(--bs-border--1) solid var(--bs-color-border);
    background: var(--bs-color-surface--sunken);
  }

  .bs-rte__btn {
    min-width: 1.7rem;
    height: 1.7rem;
    padding: 0 var(--bs-space--2xs);
    border: 0;
    border-radius: var(--bs-radius--sm);
    background: none;
    color: var(--bs-color-text--muted);
    font: inherit;
    font-size: var(--bs-text--sm);
    line-height: 1;
    cursor: pointer;
  }

  .bs-rte__btn:hover:not(:disabled) {
    background: var(--bs-color-surface);
    color: var(--bs-color-text);
  }

  .bs-rte__btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
  }

  .bs-rte__btn--code {
    font-family: var(--bs-font-mono, monospace);
    font-size: var(--bs-text--xs);
  }

  .bs-rte__btn--code.is-active {
    background: var(--bs-color-primary);
    color: var(--bs-color-primary--contrast);
  }

  .bs-rte__sep {
    width: var(--bs-border--1);
    align-self: stretch;
    margin: var(--bs-space--3xs) var(--bs-space--2xs);
    background: var(--bs-color-border);
  }

  .bs-rte__spacer {
    flex: 1;
  }

  .bs-rte__srclabel {
    font-size: var(--bs-text--xs);
    color: var(--bs-color-text--muted);
    padding-inline: var(--bs-space--2xs);
  }

  .bs-rte__body {
    /* min-height is set inline from the `rows` prop. */
    max-height: 60vh;
    overflow: auto;
    padding: var(--bs-space--xs) var(--bs-space--sm);
    font-size: var(--bs-text--sm);
    line-height: 1.6;
    color: var(--bs-color-text);
    outline: none;
  }

  .bs-rte__body:empty::before {
    content: attr(data-placeholder);
    color: var(--bs-color-text--subtle);
  }

  /* Tame heading sizes inside the small editor so they stay usable. */
  .bs-rte__body :global(h1) {
    font-size: 1.5em;
    margin: 0.4em 0;
  }
  .bs-rte__body :global(h2) {
    font-size: 1.3em;
    margin: 0.4em 0;
  }
  .bs-rte__body :global(h3) {
    font-size: 1.15em;
    margin: 0.4em 0;
  }
  .bs-rte__body :global(p) {
    margin: 0.4em 0;
  }
  .bs-rte__body :global(ul),
  .bs-rte__body :global(ol) {
    margin: 0.4em 0;
    padding-left: 1.4em;
  }

  .bs-rte__source {
    display: block;
    width: 100%;
    max-height: 60vh;
    overflow: auto;
    padding: var(--bs-space--xs) var(--bs-space--sm);
    border: 0;
    background: var(--bs-color-surface);
    color: var(--bs-color-text);
    font-family: var(--bs-font-mono, monospace);
    font-size: var(--bs-text--xs);
    line-height: 1.6;
    resize: vertical;
    outline: none;
  }
</style>
