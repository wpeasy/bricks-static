<script lang="ts">
  import { untrack } from 'svelte';
  import { Button, ConfirmButton, Input, Select } from '@wpeasy/ab-ui';
  import { api } from '../shared/api';
  import type { Capabilities, ConnectionInput, DestinationDisplay, Transport } from '../shared/types';
  import { __ } from '../shared/i18n';

  let {
    destination,
    capabilities,
    running,
    canRemove,
    onSaved,
    onRemove,
  }: {
    destination: DestinationDisplay;
    capabilities: Capabilities;
    running: boolean;
    canRemove: boolean;
    onSaved: () => void;
    onRemove: (id: string) => void;
  } = $props();

  const DEFAULT_PORTS: Record<Transport, number> = { sftp: 22, ftps: 21, ftp: 21 };
  const KNOWN_DEFAULT_PORTS = [0, 21, 22];

  const d = untrack(() => destination);

  let transport = $state<Transport>(d.transport.value);
  let host = $state(d.host.value);
  let port = $state<number>(d.port.value || DEFAULT_PORTS[d.transport.value]);
  let username = $state(d.username.value);
  let password = $state('');
  let remotePath = $state(d.remotePath.value);
  let basePath = $state(d.basePath.value);
  let destinationUrl = $state(d.destinationUrl.value);

  let saving = $state(false);
  let testing = $state(false);
  let message = $state('');
  let messageOk = $state(true);
  let busy = $derived(saving || testing || running);

  // How this destination deploys (live from /destinations).
  let deployNote = $derived.by<{ tone: 'ok' | 'muted'; text: string; retest: boolean } | null>(() => {
    const dp = destination.deploy;
    if (!dp) return null;
    if (dp.strategy === 'package') {
      return {
        tone: 'ok',
        retest: false,
        text: dp.hasUrl ? __('deployFastOk') : __('deployFastGuessed'),
      };
    }
    if (dp.disabled) {
      return {
        tone: 'muted',
        retest: true,
        text: __('deployPerFileErr'),
      };
    }
    if (!dp.canBuild) {
      return { tone: 'muted', retest: false, text: __('deployNoZip') };
    }
    return {
      tone: 'muted',
      retest: false,
      text: __('deployPerFile'),
    };
  });

  let retesting = $state(false);
  let retestMsg = $state('');
  async function retestPackage(): Promise<void> {
    retesting = true;
    retestMsg = '';
    try {
      const r = await api.packageTest(d.id);
      retestMsg = r.message;
      if (r.ok) onSaved(); // reload destinations so the badge flips to package
    } catch (e) {
      retestMsg = (e as Error).message;
    } finally {
      retesting = false;
    }
  }

  let transportOptions = $derived([
    { value: 'sftp', label: 'SFTP' + (capabilities.sftp ? '' : __('optUnavailable')), disabled: !capabilities.sftp },
    { value: 'ftps', label: __('optFtps') + (capabilities.ftps ? '' : __('optUnavailable')), disabled: !capabilities.ftps },
    { value: 'ftp', label: __('optFtp') + (capabilities.ftp ? '' : __('optUnavailable')), disabled: !capabilities.ftp },
  ]);

  function handleTransportChange(value: string): void {
    transport = value as Transport;
    if (!d.port.fromConstant && KNOWN_DEFAULT_PORTS.includes(port)) {
      port = DEFAULT_PORTS[transport];
    }
  }

  function payload(includeMeta: boolean): ConnectionInput {
    const data: ConnectionInput = { transport, host, port, username, remotePath };
    if (password) data.password = password;
    if (includeMeta) {
      data.basePath = basePath;
      data.destinationUrl = destinationUrl;
    }
    return data;
  }

  async function save(): Promise<void> {
    saving = true;
    message = '';
    try {
      await api.updateDestination(d.id, payload(true));
      password = '';
      message = __('msgSaved');
      messageOk = true;
      onSaved();
    } catch (e) {
      message = (e as Error).message;
      messageOk = false;
    } finally {
      saving = false;
    }
  }

  async function test(): Promise<void> {
    testing = true;
    message = '';
    try {
      const r = await api.testDestination(d.id, payload(false));
      message = r.message;
      messageOk = r.ok;
      if (r.ok) {
        onSaved(); // refresh the Connected indicator from the stored test result
      }
    } catch (e) {
      message = (e as Error).message;
      messageOk = false;
    } finally {
      testing = false;
    }
  }
</script>

<section class="bs-card bs-stack bs-stack--md">
  <div class="bs-grid">
    <Select
      label={__('fldTransport')}
      id="bs-transport-{d.id}"
      value={transport}
      options={transportOptions}
      disabled={d.transport.fromConstant || busy}
      onchange={(v) => handleTransportChange(String(v))}
    />
    <Input label={__('fldPort')} type="number" bind:value={port} placeholder={transport === 'sftp' ? '22' : '21'} disabled={d.port.fromConstant || busy} />

    <Input label={__('fldHost')} bind:value={host} placeholder="ftp.example.com" disabled={d.host.fromConstant || busy} />
    <Input label={__('fldUsername')} bind:value={username} autocomplete="off" disabled={d.username.fromConstant || busy} />

    <Input label={__('fldPassword')} type="password" bind:value={password} autocomplete="new-password" placeholder={d.password.hasValue ? __('phSavedBlank') : __('fldPassword')} disabled={d.password.fromConstant || busy} />
    <Input label={__('fldRemotePath')} bind:value={remotePath} placeholder={__('phRemoteEmpty')} disabled={d.remotePath.fromConstant || busy} />

    <Input label={__('fldDestUrl')} type="url" bind:value={destinationUrl} placeholder="https://www.example.com" disabled={d.destinationUrl.fromConstant || busy} />
    <Input label={__('fldSubPath')} bind:value={basePath} placeholder="/" disabled={d.basePath.fromConstant || busy} />
  </div>

  {#if deployNote}
    <p class="bs-deploy bs-deploy--{deployNote.tone}">
      {#if deployNote.tone === 'ok'}⚡ {/if}{deployNote.text}
      {#if deployNote.retest}
        <Button variant="ghost" size="sm" onclick={retestPackage} disabled={retesting || busy}>{retesting ? __('btnReTesting') : __('btnReTest')}</Button>
      {/if}
      {#if retestMsg}<span class="bs-deploy__result"> — {retestMsg}</span>{/if}
    </p>
  {/if}

  {#if message}
    <p class="bs-msg bs-msg--{messageOk ? 'ok' : 'err'}">{message}</p>
  {/if}

  <div class="bs-row bs-row--between">
    <div class="bs-row bs-row--wrap">
      <Button variant="secondary" onclick={test} disabled={busy || !host}>{testing ? __('btnTesting') : __('btnTest')}</Button>
      <Button variant="primary" onclick={save} disabled={busy}>{saving ? __('btnSaving') : __('btnSave')}</Button>
    </div>
    {#if canRemove}
      <ConfirmButton
        variant="danger"
        size="sm"
        label={__('btnRemoveDest')}
        confirmLabel={__('btnYesRemove')}
        onconfirm={() => onRemove(d.id)}
        disabled={busy}
      />
    {/if}
  </div>
</section>

<style>
  .bs-card {
    /* Fixed-width Connection panel; Replacements fills the rest of the row. */
    flex: 0 0 34rem;
    max-width: 100%;
    padding: var(--ab-space-5);
    background: var(--ab-color-surface);
    border: 1px solid var(--ab-color-border);
    border-radius: var(--ab-radius-lg);
    box-shadow: var(--ab-shadow-sm);
  }

  .bs-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--ab-space-4);
  }

  .bs-deploy {
    margin: 0;
    font-size: var(--ab-text-sm);
  }

  .bs-deploy--ok {
    color: var(--ab-color-success);
  }

  .bs-deploy--muted {
    color: var(--ab-color-text-muted);
  }

  .bs-deploy__result {
    color: var(--ab-color-text-muted);
  }

  .bs-msg {
    margin: 0;
    font-size: var(--ab-text-sm);
  }

  .bs-msg--ok {
    color: var(--ab-color-success);
  }

  .bs-msg--err {
    color: var(--ab-color-danger);
  }

  @media (max-width: 640px) {
    .bs-grid {
      grid-template-columns: 1fr;
    }
  }
</style>
