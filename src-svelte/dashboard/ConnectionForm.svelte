<script lang="ts">
  import { untrack } from 'svelte';
  import { api } from '../shared/api';
  import type { ConnectionInput, ConnectionResponse, Transport } from '../shared/types';

  let {
    connection,
    onsaved,
    onrefresh,
  }: {
    connection: ConnectionResponse;
    onsaved: (next: ConnectionResponse) => void;
    onrefresh: () => void;
  } = $props();

  // One-time snapshot: seed values and constant-override flags don't change at runtime.
  const s = untrack(() => connection.settings);
  const caps = untrack(() => connection.capabilities);

  const DEFAULT_PORTS: Record<Transport, number> = { sftp: 22, ftps: 21, ftp: 21 };
  // Ports we consider "still a default" — safe to auto-update when the protocol changes.
  const KNOWN_DEFAULT_PORTS = [0, 21, 22];

  let transport = $state<Transport>(s.transport.value);
  let host = $state(s.host.value);
  let port = $state<number>(s.port.value || DEFAULT_PORTS[s.transport.value]);
  let username = $state(s.username.value);
  let password = $state('');
  let remotePath = $state(s.remotePath.value);
  let basePath = $state(s.basePath.value);
  let destinationUrl = $state(s.destinationUrl.value);

  let saving = $state(false);
  let testing = $state(false);
  let message = $state('');
  let messageOk = $state(true);

  let busy = $derived(saving || testing);

  function setMessage(text: string, ok: boolean): void {
    message = text;
    messageOk = ok;
  }

  // When the protocol changes, set the port to that protocol's default — unless
  // the user has typed a custom (non-default) port, which we preserve.
  function handleTransportChange(): void {
    if (!s.port.fromConstant && KNOWN_DEFAULT_PORTS.includes(port)) {
      port = DEFAULT_PORTS[transport];
    }
  }

  function payload(includeBase: boolean): ConnectionInput {
    const data: ConnectionInput = { transport, host, port, username, remotePath };
    if (includeBase) {
      data.basePath = basePath;
      data.destinationUrl = destinationUrl;
    }
    if (password) {
      data.password = password;
    }
    return data;
  }

  async function save(): Promise<void> {
    saving = true;
    setMessage('', true);
    try {
      const next = await api.saveConnection(payload(true));
      onsaved(next);
      password = '';
      setMessage('Settings saved.', true);
    } catch (e) {
      setMessage((e as Error).message, false);
    } finally {
      saving = false;
    }
  }

  async function test(): Promise<void> {
    testing = true;
    setMessage('', true);
    try {
      const result = await api.testConnection(payload(false));
      setMessage(result.message, result.ok);
      onrefresh();
    } catch (e) {
      setMessage((e as Error).message, false);
    } finally {
      testing = false;
    }
  }
</script>

<section class="bs-card bs-stack bs-stack--md">
  <h2>Destination connection</h2>

  <div class="bs-field">
    <label for="bs-transport">Transport</label>
    <select id="bs-transport" bind:value={transport} onchange={handleTransportChange} disabled={s.transport.fromConstant || busy}>
      <option value="sftp" disabled={!caps.sftp}>SFTP{caps.sftp ? '' : ' (unavailable)'}</option>
      <option value="ftps" disabled={!caps.ftps}>FTPS (FTP over TLS){caps.ftps ? '' : ' (unavailable)'}</option>
      <option value="ftp" disabled={!caps.ftp}>FTP (insecure){caps.ftp ? '' : ' (unavailable)'}</option>
    </select>
    {#if s.transport.fromConstant}<small>Set in wp-config.php</small>{/if}
  </div>

  <div class="bs-field-row">
    <div class="bs-field bs-field--grow">
      <label for="bs-host">Host</label>
      <input id="bs-host" type="text" bind:value={host} placeholder="ftp.example.com" disabled={s.host.fromConstant || busy} />
      {#if s.host.fromConstant}<small>Set in wp-config.php</small>{/if}
    </div>
    <div class="bs-field bs-field--port">
      <label for="bs-port">Port</label>
      <input id="bs-port" type="number" bind:value={port} placeholder={transport === 'sftp' ? '22' : '21'} disabled={s.port.fromConstant || busy} />
    </div>
  </div>

  <div class="bs-field-row">
    <div class="bs-field bs-field--grow">
      <label for="bs-username">Username</label>
      <input id="bs-username" type="text" bind:value={username} autocomplete="off" disabled={s.username.fromConstant || busy} />
      {#if s.username.fromConstant}<small>Set in wp-config.php</small>{/if}
    </div>
    <div class="bs-field bs-field--grow">
      <label for="bs-password">Password</label>
      <input
        id="bs-password"
        type="password"
        bind:value={password}
        autocomplete="new-password"
        placeholder={s.password.hasValue ? 'Saved — leave blank to keep' : 'Password'}
        disabled={s.password.fromConstant || busy}
      />
      {#if s.password.fromConstant}<small>Set in wp-config.php</small>{/if}
    </div>
  </div>

  <div class="bs-field">
    <label for="bs-remote">Remote path (the site's web root)</label>
    <input id="bs-remote" type="text" bind:value={remotePath} placeholder="(empty = where FTP logs in)" disabled={s.remotePath.fromConstant || busy} />
    {#if s.remotePath.fromConstant}
      <small>Set in wp-config.php</small>
    {:else}
      <small>Leave empty if your FTP user lands in the web root. Use <code>public_html/</code> for a standard cPanel account.</small>
    {/if}
  </div>

  <div class="bs-field-row">
    <div class="bs-field bs-field--grow">
      <label for="bs-dest-url">Destination URL (optional)</label>
      <input id="bs-dest-url" type="url" bind:value={destinationUrl} placeholder="https://www.example.com" disabled={s.destinationUrl.fromConstant || busy} />
    </div>
    <div class="bs-field bs-field--grow">
      <label for="bs-base">Served from sub-path (optional)</label>
      <input id="bs-base" type="text" bind:value={basePath} placeholder="/" disabled={s.basePath.fromConstant || busy} />
    </div>
  </div>

  {#if message}
    <p class="bs-form__msg bs-form__msg--{messageOk ? 'ok' : 'err'}">{message}</p>
  {/if}

  <div class="bs-row">
    <button type="button" class="bs-btn bs-btn--secondary" onclick={test} disabled={busy || !host}>
      {testing ? 'Testing…' : 'Test connection'}
    </button>
    <button type="button" class="bs-btn bs-btn--primary" onclick={save} disabled={busy}>
      {saving ? 'Saving…' : 'Save settings'}
    </button>
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

  .bs-field {
    display: flex;
    flex-direction: column;
    gap: var(--bs-space--2xs);
  }

  .bs-field-row {
    display: flex;
    gap: var(--bs-space--md);
    flex-wrap: wrap;
  }

  .bs-field--grow {
    flex: 1 1 14rem;
  }

  .bs-field--port {
    flex: 0 0 7rem;
  }

  .bs-field label {
    font-size: var(--bs-text--sm);
    font-weight: var(--bs-weight--medium);
    color: var(--bs-color-text--muted);
  }

  .bs-field small {
    font-size: var(--bs-text--xs);
    color: var(--bs-color-text--subtle);
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
    cursor: not-allowed;
  }

  .bs-btn {
    padding: var(--bs-space--xs) var(--bs-space--md);
    border: var(--bs-border--1) solid transparent;
    border-radius: var(--bs-radius--md);
    font: inherit;
    font-weight: var(--bs-weight--medium);
    cursor: pointer;
  }

  .bs-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
  }

  .bs-btn--primary {
    background: var(--bs-color-primary);
    color: var(--bs-color-primary--contrast);
  }

  .bs-btn--primary:hover:not(:disabled) {
    background: var(--bs-color-primary--hover);
  }

  .bs-btn--secondary {
    background: var(--bs-color-surface);
    border-color: var(--bs-color-border--strong);
    color: var(--bs-color-text);
  }

  .bs-form__msg {
    font-size: var(--bs-text--sm);
    margin: 0;
  }

  .bs-form__msg--ok {
    color: var(--bs-color-success);
  }

  .bs-form__msg--err {
    color: var(--bs-color-danger);
  }
</style>
