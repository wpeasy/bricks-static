<script lang="ts">
  import { Tooltip } from '@wpeasy/ab-ui';
  import type { DestinationDisplay } from '../shared/types';
  import { canAddDestination } from '../shared/capabilities.svelte';
  import { __ } from '../shared/i18n';

  let {
    destinations,
    active,
    onSelect,
    onAdd,
    onRename,
  }: {
    destinations: DestinationDisplay[];
    active: string;
    onSelect: (tab: string) => void;
    onAdd: () => void;
    onRename: (id: string, name: string) => void;
  } = $props();

  let showAll = $derived(destinations.length > 1);
  // Multiple destinations are a Pro capability; the cap is 1 in Free.
  let canAdd = $derived(canAddDestination(destinations.length));

  // Inline rename: double-click a tab to turn it into a text field.
  // ab-ui Tabs can't host an inline <input>, so this strip stays bespoke.
  let editingId = $state('');
  let editValue = $state('');

  function startEdit(d: DestinationDisplay): void {
    editingId = d.id;
    editValue = d.name;
  }

  function commit(): void {
    const name = editValue.trim();
    if (editingId && name) onRename(editingId, name);
    editingId = '';
  }

  function onKey(e: KeyboardEvent): void {
    if (e.key === 'Enter') commit();
    else if (e.key === 'Escape') editingId = '';
  }
</script>

<div class="bs-tabs" role="tablist">
  {#if showAll}
    <button type="button" class="bs-tab" class:bs-tab--active={active === 'all'} onclick={() => onSelect('all')}>
      {__('allDestinations')}
    </button>
  {/if}
  {#each destinations as d (d.id)}
    {#if editingId === d.id}
      <!-- svelte-ignore a11y_autofocus -->
      <input
        class="bs-tab bs-tab--edit"
        bind:value={editValue}
        onblur={commit}
        onkeydown={onKey}
        autofocus
        aria-label={__('destNameAria')}
      />
    {:else}
      <Tooltip content={__('dblClickRename')} placement="bottom">
        <button
          type="button"
          class="bs-tab"
          class:bs-tab--active={active === d.id}
          onclick={() => onSelect(d.id)}
          ondblclick={() => startEdit(d)}
        >
          {d.name || __('destinationDefault')}{#if !d.enabled}<span class="bs-tab__off">{__('tabOff')}</span>{/if}
        </button>
      </Tooltip>
    {/if}
  {/each}
  {#if canAdd}
    <button type="button" class="bs-tab bs-tab--add" onclick={onAdd} aria-label={__('addDestAria')}>+</button>
  {:else}
    <Tooltip content={__('multiDestReqPro')} placement="bottom">
      <button type="button" class="bs-tab bs-tab--add" disabled aria-label={__('addDestAria')}>+</button>
    </Tooltip>
  {/if}
</div>

<style>
  .bs-tabs {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: var(--ab-space-1);
    border-bottom: 1px solid var(--ab-color-border);
    padding-bottom: var(--ab-space-2);
  }

  .bs-tab {
    flex: 0 0 auto;
    text-align: center;
    padding: var(--ab-space-2) var(--ab-space-4);
    border: 1px solid transparent;
    border-radius: var(--ab-radius-md) var(--ab-radius-md) 0 0;
    background: none;
    color: var(--ab-color-text-muted);
    font: inherit;
    font-size: var(--ab-text-sm);
    font-weight: var(--ab-weight-medium);
    cursor: pointer;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .bs-tab:hover {
    color: var(--ab-color-text);
  }

  .bs-tab--active {
    background: var(--ab-color-surface);
    border-color: var(--ab-color-border);
    color: var(--ab-color-text);
  }

  .bs-tab--edit {
    background: var(--ab-color-surface);
    border-color: var(--ab-color-primary);
    color: var(--ab-color-text);
  }

  .bs-tab--add {
    flex: 0 0 auto;
    color: var(--ab-color-primary);
    font-size: var(--ab-text-lg);
    line-height: 1;
  }

  .bs-tab--add:disabled {
    color: var(--ab-color-text-muted);
    cursor: not-allowed;
  }

  .bs-tab__off {
    margin-left: var(--ab-space-1);
    font-size: var(--ab-text-xs);
    color: var(--ab-color-text-muted);
  }
</style>
