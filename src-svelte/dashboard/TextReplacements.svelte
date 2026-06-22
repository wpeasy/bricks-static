<script lang="ts">
  import { untrack } from 'svelte';
  import { api } from '../shared/api';
  import type { DestinationDisplay, Replacement } from '../shared/types';
  import Modal from '../lib/Modal.svelte';
  import RichTextEditor from '../lib/RichTextEditor.svelte';
  import { __ } from '../shared/i18n';

  let {
    destination,
    running = false,
    onSaved,
  }: {
    destination: DestinationDisplay;
    running?: boolean;
    onSaved: () => void;
  } = $props();

  let replacements = $state<Replacement[]>(untrack(() => destination.replacements.map((r) => ({ ...r }))));
  let saving = $state(false);
  let error = $state('');
  let busy = $derived(saving || running);

  // Editor modal state (separate scalar fields avoid null-binding gymnastics).
  let editorOpen = $state(false);
  let editIndex = $state(-1);
  let fSearch = $state('');
  let fReplace = $state('');
  let fRich = $state(false);

  // Inline double-opt-in delete: first click arms the row, second confirms.
  let confirmIndex = $state(-1);

  async function persist(): Promise<void> {
    saving = true;
    error = '';
    try {
      await api.updateDestination(destination.id, { replacements: replacements.filter((r) => r.search !== '') });
      onSaved();
    } catch (e) {
      error = (e as Error).message;
    } finally {
      saving = false;
    }
  }

  function openAdd(): void {
    editIndex = -1;
    fSearch = '';
    fReplace = '';
    fRich = false;
    editorOpen = true;
  }

  function openEdit(i: number): void {
    const r = replacements[i];
    editIndex = i;
    fSearch = r.search;
    fReplace = r.replace;
    fRich = !!r.rich;
    confirmIndex = -1;
    editorOpen = true;
  }

  function saveDraft(): void {
    if (fSearch.trim() === '') return;
    const row: Replacement = { search: fSearch, replace: fReplace, rich: fRich };
    replacements = editIndex === -1 ? [...replacements, row] : replacements.map((r, i) => (i === editIndex ? row : r));
    editorOpen = false;
    void persist();
  }

  function del(i: number): void {
    replacements = replacements.filter((_, j) => j !== i);
    confirmIndex = -1;
    void persist();
  }
</script>

<div class="bs-tr">
  <div class="bs-tr__head">
    <span>{__('textReplTitle')} <small>{__('optional')}</small></span>
    <button type="button" class="bs-btn bs-btn--sm" onclick={openAdd} disabled={busy}>{__('btnAdd')}</button>
  </div>
  <p class="bs-tr__warn">{__('textReplWarn')}</p>

  {#if error}
    <p class="bs-tr__msg">{error}</p>
  {/if}

  {#if replacements.length === 0}
    <p class="bs-tr__empty">{__('noTextRepl')}</p>
  {:else}
    <ul class="bs-tr__list">
      {#each replacements as row, i (i)}
        <li class="bs-tr__row">
          <div class="bs-tr__main">
            <div class="bs-tr__line">
              <span class="bs-tr__label">{__('lblFind')}</span>
              <span class="bs-tr__val bs-clamp">{row.search}</span>
            </div>
            <div class="bs-tr__line">
              <span class="bs-tr__label">{__('lblReplace')}</span>
              {#if row.rich}
                <!-- eslint-disable-next-line svelte/no-at-html-tags -->
                <span class="bs-tr__val bs-clamp">{@html row.replace || __('richEmpty')}</span>
              {:else}
                <span class="bs-tr__val bs-clamp">{row.replace || __('valEmpty')}</span>
              {/if}
            </div>
          </div>

          <div class="bs-tr__actions">
            {#if confirmIndex === i}
              <button type="button" class="bs-icon bs-icon--danger" onclick={() => del(i)} disabled={busy} data-balloon={__('confirmDelete')} data-balloon-pos="down">{__('delete')}</button>
              <button type="button" class="bs-icon" onclick={() => (confirmIndex = -1)} data-balloon={__('keep')} data-balloon-pos="down">{__('btnCancel')}</button>
            {:else}
              <button type="button" class="bs-icon" onclick={() => openEdit(i)} disabled={busy} aria-label={__('edit')} data-balloon={__('edit')} data-balloon-pos="down">✎</button>
              <button type="button" class="bs-icon" onclick={() => (confirmIndex = i)} disabled={busy} aria-label={__('delete')} data-balloon={__('delete')} data-balloon-pos="down">🗑</button>
            {/if}
          </div>
        </li>
      {/each}
    </ul>
  {/if}
</div>

<Modal bind:open={editorOpen} title={editIndex === -1 ? __('modalAddText') : __('modalEditText')}>
  <div class="bs-ed">
    <label class="bs-ed__field">
      <span class="bs-ed__label">{__('lblFind')}</span>
      <input type="text" placeholder={__('phTextToFind')} bind:value={fSearch} disabled={busy} />
    </label>

    <div class="bs-ed__field">
      <span class="bs-ed__label">{__('lblReplaceWith')}</span>
      <div class="bs-seg" role="group" aria-label={__('replFormatAria')}>
        <button type="button" class="bs-seg__btn" class:is-active={!fRich} onclick={() => (fRich = false)} disabled={busy}>{__('formatPlain')}</button>
        <button type="button" class="bs-seg__btn" class:is-active={fRich} onclick={() => (fRich = true)} disabled={busy}>{__('formatRich')}</button>
      </div>
      {#if fRich}
        <RichTextEditor bind:value={fReplace} disabled={busy} rows={10} />
      {:else}
        <input type="text" placeholder={__('phReplaceWith')} bind:value={fReplace} disabled={busy} />
      {/if}
    </div>

    <div class="bs-ed__actions">
      <button type="button" class="bs-btn" onclick={() => (editorOpen = false)}>{__('btnCancel')}</button>
      <button type="button" class="bs-btn bs-btn--primary" onclick={saveDraft} disabled={busy || fSearch.trim() === ''}>
        {editIndex === -1 ? __('btnAddShort') : __('btnSave')}
      </button>
    </div>
  </div>
</Modal>

<style>
  .bs-tr {
    display: flex;
    flex-direction: column;
    gap: var(--bs-space--xs);
  }

  .bs-tr__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: var(--bs-text--sm);
    font-weight: var(--bs-weight--medium);
    color: var(--bs-color-text--muted);
  }

  .bs-tr__warn {
    margin: 0;
    font-size: var(--bs-text--xs);
    color: var(--bs-color-warning);
  }

  .bs-tr__msg {
    margin: 0;
    font-size: var(--bs-text--sm);
    color: var(--bs-color-danger);
  }

  .bs-tr__empty {
    margin: 0;
    font-size: var(--bs-text--sm);
    color: var(--bs-color-text--muted);
  }

  .bs-tr__list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: var(--bs-space--xs);
  }

  .bs-tr__row {
    display: flex;
    align-items: center;
    gap: var(--bs-space--sm);
    padding: var(--bs-space--xs) var(--bs-space--sm);
    border: var(--bs-border--1) solid var(--bs-color-border);
    border-radius: var(--bs-radius--md);
    background: var(--bs-color-surface);
  }

  .bs-tr__main {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: var(--bs-space--3xs);
  }

  .bs-tr__line {
    display: flex;
    align-items: baseline;
    gap: var(--bs-space--xs);
    min-width: 0;
  }

  .bs-tr__label {
    flex: 0 0 3rem;
    font-size: var(--bs-text--xs);
    color: var(--bs-color-text--muted);
    text-transform: uppercase;
    letter-spacing: 0.04em;
  }

  .bs-tr__val {
    flex: 1;
    min-width: 0;
    font-size: var(--bs-text--sm);
    color: var(--bs-color-text);
  }

  /* One-line clamp. Rich values can contain block tags (<p>, <div>); forcing
     every child inline keeps them on the single ellipsised line. */
  .bs-clamp {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .bs-clamp :global(*) {
    display: inline;
    margin: 0;
    padding: 0;
  }

  .bs-tr__actions {
    flex: 0 0 auto;
    display: flex;
    gap: var(--bs-space--2xs);
  }

  .bs-icon {
    height: 1.8rem;
    min-width: 1.8rem;
    padding: 0 var(--bs-space--xs);
    border: var(--bs-border--1) solid var(--bs-color-border--strong);
    border-radius: var(--bs-radius--md);
    background: var(--bs-color-surface);
    color: var(--bs-color-text--muted);
    font: inherit;
    font-size: var(--bs-text--sm);
    line-height: 1;
    cursor: pointer;
  }

  .bs-icon:hover:not(:disabled) {
    color: var(--bs-color-text);
  }

  .bs-icon--danger {
    border-color: var(--bs-color-danger);
    color: var(--bs-color-danger);
    font-size: var(--bs-text--xs);
  }

  .bs-icon:disabled {
    opacity: 0.5;
    cursor: not-allowed;
  }

  /* Editor modal — single column. */
  .bs-ed {
    display: flex;
    flex-direction: column;
    gap: var(--bs-space--md);
  }

  .bs-ed__field {
    display: flex;
    flex-direction: column;
    gap: var(--bs-space--2xs);
  }

  .bs-ed__label {
    font-size: var(--bs-text--sm);
    font-weight: var(--bs-weight--medium);
    color: var(--bs-color-text--muted);
  }

  .bs-ed__field input[type='text'] {
    padding: var(--bs-space--xs) var(--bs-space--sm);
    border: var(--bs-border--1) solid var(--bs-color-border--strong);
    border-radius: var(--bs-radius--md);
    background: var(--bs-color-surface);
    color: var(--bs-color-text);
    font: inherit;
    font-size: var(--bs-text--sm);
  }

  .bs-ed__actions {
    display: flex;
    justify-content: flex-end;
    gap: var(--bs-space--sm);
  }

  .bs-seg {
    display: inline-flex;
    align-self: flex-start;
    border: var(--bs-border--1) solid var(--bs-color-border--strong);
    border-radius: var(--bs-radius--sm);
    overflow: hidden;
  }

  .bs-seg__btn {
    padding: 0 var(--bs-space--sm);
    height: 1.6rem;
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

  .bs-btn {
    padding: var(--bs-space--xs) var(--bs-space--md);
    border: var(--bs-border--1) solid var(--bs-color-border--strong);
    border-radius: var(--bs-radius--md);
    background: var(--bs-color-surface);
    color: var(--bs-color-text);
    font: inherit;
    font-weight: var(--bs-weight--medium);
    font-size: var(--bs-text--sm);
    cursor: pointer;
  }

  .bs-btn--sm {
    padding: var(--bs-space--2xs) var(--bs-space--sm);
    font-size: var(--bs-text--xs);
  }

  .bs-btn--primary {
    background: var(--bs-color-primary);
    border-color: var(--bs-color-primary);
    color: var(--bs-color-primary--contrast);
  }

  .bs-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
  }
</style>
