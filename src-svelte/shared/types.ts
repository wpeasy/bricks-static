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

export interface Replacement {
  search: string;
  replace: string;
  rich?: boolean;
}

export interface MediaItem {
  url: string;
  thumb: string;
  alt: string;
  type: 'image' | 'video';
  pages: string[];
}

export interface MediaReplacement {
  from: string;
  to: string;
  toId?: number;
}

// Minimal typing for the WordPress media library (wp.media), enqueued by the plugin.
export interface WpMediaAttachment {
  id: number;
  url: string;
  alt: string;
  mime: string;
}

export interface WpMediaFrame {
  on(event: string, cb: () => void): WpMediaFrame;
  open(): void;
  state(): { get(key: string): { first(): { toJSON(): WpMediaAttachment } } };
}

export interface WpMediaOptions {
  title?: string;
  button?: { text?: string };
  multiple?: boolean;
  library?: { type?: string | string[] };
}

declare global {
  interface Window {
    wp?: { media: (opts: WpMediaOptions) => WpMediaFrame };
  }
}

export interface DestinationStatus {
  connected: boolean;
  hasPushed: boolean;
  inSync: boolean;
}

export interface DestinationDeploy {
  strategy: 'package' | 'perfile';
  canBuild: boolean;
  hasUrl: boolean;
  disabled: boolean;
}

export interface DestinationDisplay extends SettingsDisplay {
  id: string;
  name: string;
  enabled: boolean;
  includeInSinglePageSync: boolean;
  isPrimary: boolean;
  replacements: Replacement[];
  mediaReplacements: MediaReplacement[];
  status: DestinationStatus;
  deploy: DestinationDeploy;
}

export interface DestinationsResponse {
  destinations: DestinationDisplay[];
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

export type DiscoveryMode = 'linked' | 'all';

export interface Status {
  connected: boolean;
  hasPushed: boolean;
  inSync: boolean;
  lastTest: LastTest | null;
  method: Method;
  isLocal: boolean;
  cli: string;
  wpCli: WpCliInfo;
  discoveryMode: DiscoveryMode;
  fabEnabled: boolean;
}

export interface WpCliInfo {
  detected: boolean;
  version: string;
  runtime: string;
}

export interface Preflight {
  ok: boolean;
  ms: number;
  message: string;
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
  phase: 'idle' | 'collect' | 'render' | 'assets' | 'finalize' | 'package' | 'upload' | 'done' | 'error' | 'cancelled';
  type?: 'check' | 'sync';
  message?: string;
  counts?: SyncCounts;
  totals?: SyncTotals;
  queued?: { pages: number; assets: number; uploads: number };
  targets?: { index: number; total: number; done: number; name: string };
  removed?: number;
  errorCount?: number;
  skippedCount?: number;
  failedCount?: number;
  compatCount?: number;
  errors?: Array<{ url: string; error: string }>;
  skipped?: Array<{ url: string; reason: string }>;
  compat?: Array<{ url: string; issues: Array<{ type: string; link: string }> }>;
  startedAt?: number;
  updatedAt?: number;
  running?: boolean;
  cliAlive?: boolean;
  driver?: 'cli' | 'browser';
}

export interface ServerConfig {
  htaccess: string;
  nginx: string;
}

/** Payload sent to the connection/destination save + test endpoints. */
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
  name?: string;
  enabled?: boolean;
  includeInSinglePageSync?: boolean;
  replacements?: Replacement[];
  mediaReplacements?: MediaReplacement[];
}
