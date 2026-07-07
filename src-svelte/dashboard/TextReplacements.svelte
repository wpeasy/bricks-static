<script lang="ts">
  import { untrack } from 'svelte';
  import { Button, ConfirmButton, Input, Segmented, Tooltip } from '@wpeasy/ab-ui';
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
    void persist();
  }
</script>

<div class="bs-tr">
  <div class="bs-tr__head">
    <span>{__('textReplTitle')} <small>{__('optional')}</small></span>
    <Button variant="secondary" size="sm" onclick={openAdd} disabled={busy}>{__('btnAdd')}</Button>
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
            <Tooltip content={__('edit')} placement="bottom">
              <Button variant="ghost" size="sm" onclick={() => openEdit(i)} disabled={busy} aria-label={__('edit')}>✎</Button>
            </Tooltip>
            <ConfirmButton
              variant="danger"
              size="sm"
              label="🗑"
              confirmLabel={__('confirmDelete')}
              onconfirm={() => del(i)}
              disabled={busy}
              aria-label={__('delete')}
            />
          </div>
        </li>
      {/each}
    </ul>
  {/if}
</div>

<Modal bind:open={editorOpen} title={editIndex === -1 ? __('modalAddText') : __('modalEditText')}>
  <div class="bs-ed">
    <Input label={__('lblFind')} placeholder={__('phTextToFind')} bind:value={fSearch} disabled={busy} />

    <div class="bs-ed__field">
      <span class="bs-ed__label">{__('lblReplaceWith')}</span>
      <Segmented
        size="sm"
        value={fRich ? 'rich' : 'plain'}
        onchange={(v) => (fRich = v === 'rich')}
        options={[
          { value: 'plain', label: __('formatPlain') },
          { value: 'rich', label: __('formatRich') },
        ]}
        disabled={busy}
        ariaLabel={__('replFormatAria')}
      />
      {#if fRich}
        <RichTextEditor bind:value={fReplace} disabled={busy} rows={10} />
      {:else}
        <Input placeholder={__('phReplaceWith')} bind:value={fReplace} disabled={busy} />
      {/if}
    </div>

    <div class="bs-ed__actions">
      <Button variant="ghost" onclick={() => (editorOpen = false)}>{__('btnCancel')}</Button>
      <Button variant="primary" onclick={saveDraft} disabled={busy || fSearch.trim() === ''}>
        {editIndex === -1 ? __('btnAddShort') : __('btnSave')}
      </Button>
    </div>
  </div>
</Modal>

<style>
  .bs-tr {
    display: flex;
    flex-direction: column;
    gap: var(--ab-space-2);
  }

  .bs-tr__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: var(--ab-text-sm);
    font-weight: var(--ab-weight-medium);
    color: var(--ab-color-text-muted);
  }

  .bs-tr__warn {
    margin: 0;
    font-size: var(--ab-text-xs);
    color: var(--ab-color-warning);
  }

  .bs-tr__msg {
    margin: 0;
    font-size: var(--ab-text-sm);
    color: var(--ab-color-danger);
  }

  .bs-tr__empty {
    margin: 0;
    font-size: var(--ab-text-sm);
    color: var(--ab-color-text-muted);
  }

  .bs-tr__list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: var(--ab-space-2);
  }

  .bs-tr__row {
    display: flex;
    align-items: center;
    gap: var(--ab-space-3);
    padding: var(--ab-space-2) var(--ab-space-3);
    border: 1px solid var(--ab-color-border);
    border-radius: var(--ab-radius-md);
    background: var(--ab-color-surface);
  }

  .bs-tr__main {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: var(--ab-space-1);
  }

  .bs-tr__line {
    display: flex;
    align-items: baseline;
    gap: var(--ab-space-2);
    min-width: 0;
  }

  .bs-tr__label {
    flex: 0 0 3rem;
    font-size: var(--ab-text-xs);
    color: var(--ab-color-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.04em;
  }

  .bs-tr__val {
    flex: 1;
    min-width: 0;
    font-size: var(--ab-text-sm);
    color: var(--ab-color-text);
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
    gap: var(--ab-space-1);
  }

  /* Editor modal — single column. */
  .bs-ed {
    display: flex;
    flex-direction: column;
    gap: var(--ab-space-4);
  }

  .bs-ed__field {
    display: flex;
    flex-direction: column;
    gap: var(--ab-space-1);
  }

  .bs-ed__label {
    font-size: var(--ab-text-sm);
    font-weight: var(--ab-weight-medium);
    color: var(--ab-color-text-muted);
  }

  .bs-ed__actions {
    display: flex;
    justify-content: flex-end;
    gap: var(--ab-space-3);
  }
</style>
