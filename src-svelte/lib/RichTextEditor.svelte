<script lang="ts">
  import { Tooltip } from '@wpeasy/ab-ui';
  import { __ } from '../shared/i18n';

  // A small WYSIWYG with a raw-HTML source view. WYSIWYG edits write the box's
  // innerHTML to `value`; the source view edits that HTML directly. Output is
  // sanitised server-side via wp_kses_post.
  let {
    value = $bindable(''),
    disabled = false,
    placeholder = __('rtPlaceholder'),
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
    const url = window.prompt(__('rtLinkPrompt'));
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
  <div class="bs-rte__bar" role="toolbar" tabindex="-1" aria-label={__('rtFormatting')} onmousedown={keepSelection}>
    {#if !source}
      <Tooltip content={__('rtParagraph')} placement="bottom"><button type="button" class="bs-rte__btn" onclick={() => exec('formatBlock', 'P')} {disabled}>¶</button></Tooltip>
      <Tooltip content={__('rtH1')} placement="bottom"><button type="button" class="bs-rte__btn" onclick={() => exec('formatBlock', 'H1')} {disabled}>H1</button></Tooltip>
      <Tooltip content={__('rtH2')} placement="bottom"><button type="button" class="bs-rte__btn" onclick={() => exec('formatBlock', 'H2')} {disabled}>H2</button></Tooltip>
      <Tooltip content={__('rtH3')} placement="bottom"><button type="button" class="bs-rte__btn" onclick={() => exec('formatBlock', 'H3')} {disabled}>H3</button></Tooltip>
      <span class="bs-rte__sep"></span>
      <Tooltip content={__('rtBold')} placement="bottom"><button type="button" class="bs-rte__btn" onclick={() => exec('bold')} {disabled}><b>B</b></button></Tooltip>
      <Tooltip content={__('rtItalic')} placement="bottom"><button type="button" class="bs-rte__btn" onclick={() => exec('italic')} {disabled}><i>I</i></button></Tooltip>
      <Tooltip content={__('rtUnderline')} placement="bottom"><button type="button" class="bs-rte__btn" onclick={() => exec('underline')} {disabled}><u>U</u></button></Tooltip>
      <span class="bs-rte__sep"></span>
      <Tooltip content={__('rtBullet')} placement="bottom"><button type="button" class="bs-rte__btn" onclick={() => exec('insertUnorderedList')} {disabled}>•—</button></Tooltip>
      <Tooltip content={__('rtNumbered')} placement="bottom"><button type="button" class="bs-rte__btn" onclick={() => exec('insertOrderedList')} {disabled}>1.</button></Tooltip>
      <span class="bs-rte__sep"></span>
      <Tooltip content={__('rtLink')} placement="bottom"><button type="button" class="bs-rte__btn" onclick={link} {disabled}>🔗</button></Tooltip>
      <Tooltip content={__('rtClearFormat')} placement="bottom"><button type="button" class="bs-rte__btn" onclick={() => exec('removeFormat')} {disabled}>✕</button></Tooltip>
    {:else}
      <span class="bs-rte__srclabel">{__('rtHtmlSource')}</span>
    {/if}
    <span class="bs-rte__spacer"></span>
    <Tooltip content={source ? __('rtVisualEditor') : __('rtEditHtml')} placement="bottom-end">
      <button
        type="button"
        class="bs-rte__btn bs-rte__btn--code"
        class:is-active={source}
        onclick={toggleSource}
        {disabled}
      >&lt;/&gt;</button>
    </Tooltip>
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
    border: 1px solid var(--ab-color-border-strong);
    border-radius: var(--ab-radius-md);
    background: var(--ab-color-surface);
    overflow: hidden;
  }

  .bs-rte.is-disabled {
    opacity: 0.6;
  }

  .bs-rte__bar {
    display: flex;
    align-items: center;
    gap: var(--ab-space-1);
    padding: var(--ab-space-1);
    border-bottom: 1px solid var(--ab-color-border);
    background: var(--ab-color-bg);
  }

  .bs-rte__btn {
    min-width: 1.7rem;
    height: 1.7rem;
    padding: 0 var(--ab-space-1);
    border: 0;
    border-radius: var(--ab-radius-sm);
    background: none;
    color: var(--ab-color-text-muted);
    font: inherit;
    font-size: var(--ab-text-sm);
    line-height: 1;
    cursor: pointer;
  }

  .bs-rte__btn:hover:not(:disabled) {
    background: var(--ab-color-surface);
    color: var(--ab-color-text);
  }

  .bs-rte__btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
  }

  .bs-rte__btn--code {
    font-family: var(--ab-font-mono, monospace);
    font-size: var(--ab-text-xs);
  }

  .bs-rte__btn--code.is-active {
    background: var(--ab-color-primary);
    color: var(--ab-color-primary-on);
  }

  .bs-rte__sep {
    width: 1px;
    align-self: stretch;
    margin: var(--ab-space-1) var(--ab-space-1);
    background: var(--ab-color-border);
  }

  .bs-rte__spacer {
    flex: 1;
  }

  .bs-rte__srclabel {
    font-size: var(--ab-text-xs);
    color: var(--ab-color-text-muted);
    padding-inline: var(--ab-space-1);
  }

  .bs-rte__body {
    /* min-height is set inline from the `rows` prop. */
    max-height: 60vh;
    overflow: auto;
    padding: var(--ab-space-2) var(--ab-space-3);
    font-size: var(--ab-text-sm);
    line-height: 1.6;
    color: var(--ab-color-text);
    outline: none;
  }

  .bs-rte__body:empty::before {
    content: attr(data-placeholder);
    color: var(--ab-color-text-muted);
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
    padding: var(--ab-space-2) var(--ab-space-3);
    border: 0;
    background: var(--ab-color-surface);
    color: var(--ab-color-text);
    font-family: var(--ab-font-mono, monospace);
    font-size: var(--ab-text-xs);
    line-height: 1.6;
    resize: vertical;
    outline: none;
  }
</style>
