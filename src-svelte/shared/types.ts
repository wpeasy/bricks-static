// Shared types mirroring the REST payloads (src/REST/*Controller.php).

export type Transport = 'sftp' | 'ftps' | 'ftp';

export interface ValueField<T> {
  value: T;
  fromConstant: boolean;
}

export interface SecretField {
  hasValue: boolean;
  fromConstant: boolean;
}

export interface SettingsDisplay {
  transport: ValueField<Transport>;
  host: ValueField<string>;
  port: ValueField<number>;
  username: ValueField<string>;
  password: SecretField;
  remotePath: ValueField<string>;
  basePath: ValueField<string>;
  destinationUrl: ValueField<string>;
}

export interface Capabilities {
  sftp: boolean;
  ftps: boolean;
  ftp: boolean;
}

export interface ConnectionResponse {
  settings: SettingsDisplay;
  capabilities: Capabilities;
}

export interface DiscoveryMethod {
  mode: string;
  description?: string;
  sitemap?: string;
  seed?: string;
}

export interface Method {
  discovery: DiscoveryMethod;
  transport: string;
  compression: { gzip: boolean };
  serverTarget: { htaccess: boolean; nginxSnippet: boolean };
  links: string;
}

export interface LastTest {
  ok: boolean;
  time: number;
  message: string;
}

export interface Status {
  connected: boolean;
  hasPushed: boolean;
  inSync: boolean;
  lastTest: LastTest | null;
  method: Method;
}

export interface TestResult {
  ok: boolean;
  message: string;
}

export interface SyncCounts {
  pagesDone: number;
  assetsDone: number;
  uploaded: number;
  pruned: number;
  bytes: number;
  files: number;
}

export interface SyncTotals {
  pages: number;
  assets: number;
  uploads: number;
}

export interface SyncSnapshot {
  phase: 'idle' | 'collect' | 'render' | 'assets' | 'finalize' | 'upload' | 'done' | 'error' | 'cancelled';
  type?: 'check' | 'sync';
  message?: string;
  counts?: SyncCounts;
  totals?: SyncTotals;
  queued?: { pages: number; assets: number; uploads: number };
  removed?: number;
  errorCount?: number;
  skippedCount?: number;
  errors?: Array<{ url: string; error: string }>;
  skipped?: Array<{ url: string; reason: string }>;
  startedAt?: number;
  updatedAt?: number;
  running?: boolean;
}

export interface ServerConfig {
  htaccess: string;
  nginx: string;
}

/** Payload sent to POST /connection and /connection/test. */
export interface ConnectionInput {
  transport?: Transport;
  host?: string;
  port?: number;
  username?: string;
  password?: string;
  clearPassword?: boolean;
  remotePath?: string;
  basePath?: string;
  destinationUrl?: string;
}
