<script lang="ts">
  import { untrack } from 'svelte';
  import { api } from '../shared/api';
  import type { Capabilities, ConnectionInput, DestinationDisplay, Transport } from '../shared/types';

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
  let confirmRemove = $state(false);
  let busy = $derived(saving || testing || running);

  // How this destination deploys (live from /destinations).
  let deployNote = $derived.by<{ tone: 'ok' | 'muted'; text: string } | null>(() => {
    const dp = destination.deploy;
    if (!dp) return null;
    if (dp.strategy === 'package') {
      return {
        tone: 'ok',
        text: dp.hasUrl
          ? 'Fast deploy: one package, extracted on the destination.'
          : 'Fast deploy enabled — the URL was guessed from the host. Set a Destination URL above to be sure it keeps working.',
      };
    }
    if (!dp.canBuild) {
      return { tone: 'muted', text: 'Per-file upload — ZipArchive isn’t available on this server.' };
    }
    return {
      tone: 'muted',
      text: 'Per-file upload (slower). Set a Destination URL above to enable fast package deploy (needs PHP on the host).',
    };
  });

  function handleTransportChange(): void {
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
      message = 'Saved.';
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
    <div class="bs-field">
      <label for="bs-transport-{d.id}">Transport</label>
      <select id="bs-transport-{d.id}" bind:value={transport} onchange={handleTransportChange} disabled={d.transport.fromConstant || busy}>
        <option value="sftp" disabled={!capabilities.sftp}>SFTP{capabilities.sftp ? '' : ' (unavailable)'}</option>
        <option value="ftps" disabled={!capabilities.ftps}>FTPS (FTP over TLS){capabilities.ftps ? '' : ' (unavailable)'}</option>
        <option value="ftp" disabled={!capabilities.ftp}>FTP (insecure){capabilities.ftp ? '' : ' (unavailable)'}</option>
      </select>
    </div>
    <div class="bs-field">
      <label for="bs-port-{d.id}">Port</label>
      <input id="bs-port-{d.id}" type="number" bind:value={port} placeholder={transport === 'sftp' ? '22' : '21'} disabled={d.port.fromConstant || busy} />
    </div>

    <div class="bs-field">
      <label for="bs-host-{d.id}">Host</label>
      <input id="bs-host-{d.id}" type="text" bind:value={host} placeholder="ftp.example.com" disabled={d.host.fromConstant || busy} />
    </div>
    <div class="bs-field">
      <label for="bs-user-{d.id}">Username</label>
      <input id="bs-user-{d.id}" type="text" bind:value={username} autocomplete="off" disabled={d.username.fromConstant || busy} />
    </div>

    <div class="bs-field">
      <label for="bs-pass-{d.id}">Password</label>
      <input id="bs-pass-{d.id}" type="password" bind:value={password} autocomplete="new-password" placeholder={d.password.hasValue ? 'Saved — leave blank to keep' : 'Password'} disabled={d.password.fromConstant || busy} />
    </div>
    <div class="bs-field">
      <label for="bs-remote-{d.id}">Remote path (web root)</label>
      <input id="bs-remote-{d.id}" type="text" bind:value={remotePath} placeholder="(empty = FTP login dir)" disabled={d.remotePath.fromConstant || busy} />
    </div>

    <div class="bs-field">
      <label for="bs-url-{d.id}">Destination URL (optional)</label>
      <input id="bs-url-{d.id}" type="url" bind:value={destinationUrl} placeholder="https://www.example.com" disabled={d.destinationUrl.fromConstant || busy} />
    </div>
    <div class="bs-field">
      <label for="bs-base-{d.id}">Served from sub-path (optional)</label>
      <input id="bs-base-{d.id}" type="text" bind:value={basePath} placeholder="/" disabled={d.basePath.fromConstant || busy} />
    </div>
  </div>

  {#if deployNote}
    <p class="bs-deploy bs-deploy--{deployNote.tone}">{#if deployNote.tone === 'ok'}⚡ {/if}{deployNote.text}</p>
  {/if}

  {#if message}
    <p class="bs-msg bs-msg--{messageOk ? 'ok' : 'err'}">{message}</p>
  {/if}

  <div class="bs-row bs-row--between">
    <div class="bs-row bs-row--wrap">
      <button type="button" class="bs-btn bs-btn--secondary" onclick={test} disabled={busy || !host}>{testing ? 'Testing…' : 'Test'}</button>
      <button type="button" class="bs-btn bs-btn--primary" onclick={save} disabled={busy}>{saving ? 'Saving…' : 'Save'}</button>
    </div>
    {#if canRemove}
      {#if confirmRemove}
        <span class="bs-remove">
          <span class="bs-remove__q">Remove this destination?</span>
          <button type="button" class="bs-link bs-link--danger" onclick={() => onRemove(d.id)} disabled={busy}>Yes, remove</button>
          <button type="button" class="bs-link" onclick={() => (confirmRemove = false)}>Cancel</button>
        </span>
      {:else}
        <button type="button" class="bs-link bs-link--danger" onclick={() => (confirmRemove = true)} disabled={busy}>Remove destination</button>
      {/if}
    {/if}
  </div>
</section>

<style>
  .bs-card {
    padding: var(--bs-space--lg);
    background: var(--bs-color-surface--raised);
    border: var(--bs-border--1) solid var(--bs-color-border);
    border-radius: var(--bs-radius--lg);
    box-shadow: var(--bs-shadow--sm);
  }

  .bs-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--bs-space--md);
  }

  .bs-field {
    display: flex;
    flex-direction: column;
    gap: var(--bs-space--2xs);
  }

  .bs-field label {
    font-size: var(--bs-text--sm);
    font-weight: var(--bs-weight--medium);
    color: var(--bs-color-text--muted);
  }

  .bs-field input,
  .bs-field select {
    padding: var(--bs-space--xs) var(--bs-space--sm);
    border: var(--bs-border--1) solid var(--bs-color-border--strong);
    border-radius: var(--bs-radius--md);
    background: var(--bs-color-surface);
    color: var(--bs-color-text);
    font: inherit;
    font-size: var(--bs-text--sm);
  }

  .bs-field input:disabled,
  .bs-field select:disabled {
    opacity: 0.6;
  }

  .bs-btn {
    padding: var(--bs-space--xs) var(--bs-space--md);
    border: var(--bs-border--1) solid var(--bs-color-border--strong);
    border-radius: var(--bs-radius--md);
    background: var(--bs-color-surface);
    color: var(--bs-color-text);
    font: inherit;
    font-weight: var(--bs-weight--medium);
    cursor: pointer;
  }

  .bs-btn--primary {
    background: var(--bs-color-primary);
    border-color: transparent;
    color: var(--bs-color-primary--contrast);
  }

  .bs-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
  }

  .bs-deploy {
    margin: 0;
    font-size: var(--bs-text--sm);
  }

  .bs-deploy--ok {
    color: var(--bs-color-success);
  }

  .bs-deploy--muted {
    color: var(--bs-color-text--muted);
  }

  .bs-msg {
    margin: 0;
    font-size: var(--bs-text--sm);
  }

  .bs-msg--ok {
    color: var(--bs-color-success);
  }

  .bs-msg--err {
    color: var(--bs-color-danger);
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

  .bs-link--danger {
    color: var(--bs-color-danger);
  }

  .bs-remove {
    display: flex;
    align-items: center;
    gap: var(--bs-space--sm);
  }

  .bs-remove__q {
    font-size: var(--bs-text--sm);
    color: var(--bs-color-danger);
  }

  .bs-link:disabled {
    opacity: 0.5;
    cursor: not-allowed;
  }

  @media (max-width: 640px) {
    .bs-grid {
      grid-template-columns: 1fr;
    }
  }
</style>
