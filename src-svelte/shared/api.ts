import type {
  ConnectionInput,
  ConnectionResponse,
  Preflight,
  ServerConfig,
  Status,
  SyncSnapshot,
  TestResult,
} from './types';

interface BsData {
  restUrl: string;
  nonce: string;
}

declare global {
  interface Window {
    bsData?: BsData;
  }
}

function config(): BsData {
  const data = window.bsData;
  if (!data) {
    throw new Error('Bricks Static: bsData was not localised.');
  }
  return data;
}

async function request<T>(path: string, method = 'GET', body?: unknown): Promise<T> {
  const { restUrl, nonce } = config();
  const res = await fetch(restUrl.replace(/\/$/, '') + path, {
    method,
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': nonce,
    },
    body: body === undefined ? undefined : JSON.stringify(body),
  });

  if (!res.ok) {
    let message = `Request failed (${res.status}).`;
    try {
      const data = (await res.json()) as { message?: string };
      if (data?.message) {
        message = data.message;
      }
    } catch {
      /* non-JSON error body */
    }
    throw new Error(message);
  }

  return (await res.json()) as T;
}

export const api = {
  getConnection: () => request<ConnectionResponse>('/connection'),
  saveConnection: (data: ConnectionInput) => request<ConnectionResponse>('/connection', 'POST', data),
  testConnection: (data: ConnectionInput) => request<TestResult>('/connection/test', 'POST', data),
  getStatus: () => request<Status>('/status'),
  syncStart: (type: 'check' | 'sync', opts: { prune?: boolean } = {}) =>
    request<SyncSnapshot>('/sync/start', 'POST', { type, ...opts }),
  syncTick: () => request<SyncSnapshot>('/sync/tick', 'POST', {}),
  syncStatus: () => request<SyncSnapshot>('/sync'),
  syncCancel: () => request<SyncSnapshot>('/sync/cancel', 'POST', {}),
  syncReset: () => request<{ ok: boolean }>('/sync/reset', 'POST', {}),
  preflight: () => request<Preflight>('/sync/preflight', 'POST', {}),
  serverConfig: () => request<ServerConfig>('/sync/server-config'),
};
