<script lang="ts">
  import Modal from '../lib/Modal.svelte';
  import type { DiscoveryMode } from '../shared/types';
  import { __ } from '../shared/i18n';

  let {
    mode,
    disabled = false,
    onChange,
  }: {
    mode: DiscoveryMode;
    disabled?: boolean;
    onChange: (mode: DiscoveryMode) => void;
  } = $props();

  let helpOpen = $state(false);

  function set(next: DiscoveryMode): void {
    if (next !== mode) onChange(next);
  }
</script>

<div class="bs-disc">
  <span class="bs-disc__label">{__('pagesToInclude')}</span>
  <div class="bs-seg" role="group" aria-label={__('pagesToInclude')}>
    <button
      type="button"
      class="bs-seg__btn"
      class:is-active={mode === 'linked'}
      onclick={() => set('linked')}
      {disabled}
    >{__('onlyLinkedPages')}</button>
    <button
      type="button"
      class="bs-seg__btn"
      class:is-active={mode === 'all'}
      onclick={() => set('all')}
      {disabled}
    >{__('allPublished')}</button>
  </div>
  <button type="button" class="bs-disc__help" aria-label={__('whatsDifference')} onclick={() => (helpOpen = true)}>?</button>
</div>

<Modal bind:open={helpOpen} title={__('pagesToInclude')}>
  <div class="bs-help">
    <p>{__('discControls')}</p>
    <dl>
      <dt>{__('onlyLinkedPages')} <span class="bs-help__tag">{__('tagDefault')}</span></dt>
      <!-- eslint-disable-next-line svelte/no-at-html-tags -->
      <dd>{@html __('discLinkedBody')}</dd>
      <dt>{__('allPublished')}</dt>
      <dd>{__('discAllBody')}</dd>
    </dl>
    <p class="bs-help__note">{__('discNote')}</p>
  </div>
</Modal>

<style>
  .bs-disc {
    display: flex;
    align-items: center;
    gap: var(--bs-space--sm);
  }

  .bs-disc__label {
    font-size: var(--bs-text--sm);
    font-weight: var(--bs-weight--medium);
    color: var(--bs-color-text--muted);
    white-space: nowrap;
  }

  .bs-seg {
    display: inline-flex;
    border: var(--bs-border--1) solid var(--bs-color-border--strong);
    border-radius: var(--bs-radius--md);
    overflow: hidden;
  }

  .bs-seg__btn {
    padding: var(--bs-space--xs) var(--bs-space--sm);
    border: 0;
    background: var(--bs-color-surface);
    color: var(--bs-color-text--muted);
    font: inherit;
    font-size: var(--bs-text--sm);
    cursor: pointer;
    white-space: nowrap;
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

  .bs-disc__help {
    flex: 0 0 auto;
    width: 1.4rem;
    height: 1.4rem;
    border: var(--bs-border--1) solid var(--bs-color-border--strong);
    border-radius: var(--bs-radius--full);
    background: var(--bs-color-surface);
    color: var(--bs-color-text--muted);
    font-size: var(--bs-text--xs);
    font-weight: var(--bs-weight--bold);
    line-height: 1;
    cursor: pointer;
  }

  .bs-disc__help:hover {
    color: var(--bs-color-text);
    border-color: var(--bs-color-primary);
  }

  .bs-help p {
    margin: 0 0 var(--bs-space--md);
    font-size: var(--bs-text--sm);
    color: var(--bs-color-text--secondary);
  }

  .bs-help dl {
    margin: 0 0 var(--bs-space--md);
  }

  .bs-help dt {
    display: flex;
    align-items: center;
    gap: var(--bs-space--xs);
    font-size: var(--bs-text--sm);
    font-weight: var(--bs-weight--semibold);
    color: var(--bs-color-text);
    margin-top: var(--bs-space--sm);
  }

  .bs-help dd {
    margin: var(--bs-space--2xs) 0 0;
    font-size: var(--bs-text--sm);
    color: var(--bs-color-text--secondary);
  }

  .bs-help__tag {
    padding: 0 var(--bs-space--2xs);
    border-radius: var(--bs-radius--sm);
    background: var(--bs-color-primary);
    color: var(--bs-color-primary--contrast);
    font-size: var(--bs-text--xs);
    font-weight: var(--bs-weight--medium);
    text-transform: uppercase;
  }

  .bs-help__note {
    margin: 0;
    font-size: var(--bs-text--xs);
    color: var(--bs-color-text--muted);
  }
</style>
