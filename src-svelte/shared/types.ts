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

/** A page available for per-page media/video replacement (selector option). */
export interface ReplacementPage {
  /** Export-relative path (the stored `page` key), e.g. 'about/index.html'. */
  value: string;
  /** Friendly display path, e.g. '/about/'. */
  label: string;
}

export interface MediaItem {
  url: string;
  thumb: string;
  alt: string;
  type: 'image' | 'video';
  /** Resolves to a media-library attachment, so a swap covers all size variants. */
  inLibrary?: boolean;
  /** How many size variants (src + srcset + backgrounds) this image group covers. */
  variants?: number;
}

export interface MediaReplacement {
  /** Export-relative page path this swap applies to (per-page model). */
  page: string;
  from: string;
  to: string;
  toId?: number;
}

export interface LinkItem {
  url: string;
  text: string;
  pages: string[];
}

export interface LinkReplacement {
  from: string;
  to: string;
}

export interface VideoItem {
  url: string;
  thumb: string;
  provider: string;
  title: string;
}

export interface VideoReplacement {
  /** Export-relative page path this swap applies to (per-page model). */
  page: string;
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
  isPrimary: boolean;
  replacements: Replacement[];
  mediaReplacements: MediaReplacement[];
  linkReplacements: LinkReplacement[];
  videoReplacements: VideoReplacement[];
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

export type DiscoveryMode = 'linked' | 'all' | 'manual';

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
  /** Whether a render exists yet. */
  hasRendered: boolean;
  /** Whether the render reflects the current mode + content (list is accurate). */
  renderCurrent: boolean;
  /** Published pages not in the current export (orphans in linked mode, etc.). */
  excludedPublished: number;
  fabEnabled: boolean;
  /** Max destinations deployed at once during a multi-destination sync (1–10). */
  concurrentSyncs: number;
  /** Whether the host WordPress exposes the Abilities API (WP 6.9+). */
  aiAvailable: boolean;
  /** Opt-in: AI/MCP may change local settings (discovery mode, include). */
  aiAllowChanges: boolean;
  /** Opt-in: AI/MCP may run the engine / push to destinations. */
  aiAllowSync: boolean;
}

export interface PageRef {
  title: string;
  url: string;
  /** Excluded: reason tags (e.g. "Not linked"). Included: compatibility notices. */
  tags: string[];
}

export interface PagesOverview {
  hasRendered: boolean;
  included: PageRef[];
  excluded: PageRef[];
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

export interface ParallelTarget {
  destId: string;
  name: string;
  status: 'pending' | 'active' | 'done' | 'error' | 'cancelled';
  uploaded: number;
  total: number;
  /** Package (single-zip) deploy — no per-file %, show an indeterminate bar. */
  packaging: boolean;
  message: string;
}

export interface SyncSnapshot {
  phase: 'idle' | 'collect' | 'render' | 'assets' | 'finalize' | 'package' | 'upload' | 'deploy' | 'done' | 'error' | 'cancelled';
  type?: 'check' | 'sync';
  message?: string;
  counts?: SyncCounts;
  totals?: SyncTotals;
  queued?: { pages: number; assets: number; uploads: number };
  targets?: { index: number; total: number; done: number; name: string };
  /** Parallel deploy: fan-out across destinations is active. */
  parallel?: boolean;
  /** How many destinations are being deployed at once. */
  poolSize?: number;
  /** Detached coordinator needs the dashboard to launch the worker pool. */
  needsDispatch?: boolean;
  /** Per-destination progress rows during a parallel deploy (empty otherwise). */
  parallelTargets?: ParallelTarget[];
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
  /** True when a full sync stopped at the Free page cap (more pages need Pro). */
  pageLimitHit?: boolean;
  /** A package-configured destination fell back to file-by-file (runtime has no zip). */
  zipUnavailable?: boolean;
  /** Reason a package deploy was attempted but fell back to file-by-file (''=n/a). */
  packageFallback?: string;
  /** A "check" run's sync preview: what a sync to this destination would change. */
  preview?: {
    destId: string;
    destName: string;
    upload: number;
    remove: number;
    total: number;
    inSync: boolean;
  } | null;
}

/**
 * A synchronous (no-render) sync preview for one destination, from GET
 * /sync/check. When `needsProcess` is true the render is stale or missing —
 * the counts are absent and the UI should prompt a Process first.
 */
export interface CheckPreview {
  needsProcess: boolean;
  destId: string;
  destName: string;
  message: string;
  upload?: number;
  remove?: number;
  total?: number;
  inSync?: boolean;
  /** Published pages not in the export (present only when not needsProcess). */
  excludedPublished?: number;
}

/**
 * Export ZIP job snapshot, from POST /export/start|tick and GET /export.
 * A separate, much simpler shape than SyncSnapshot — export never renders,
 * uploads, or fans out across destinations.
 */
export interface ExportSnapshot {
  phase: 'idle' | 'needsProcess' | 'preparing' | 'gzip' | 'packaging' | 'saving' | 'done' | 'error' | 'cancelled';
  destId?: string;
  destName?: string;
  message?: string;
  gzipDone?: number;
  gzipTotal?: number;
  /** Set when gzip was expected (Pro) but the server's PHP lacks gzencode(). */
  gzipNotice?: string;
  packDone?: number;
  packTotal?: number;
  fileCount?: number;
  bytes?: number;
  running?: boolean;
  /** Single-use token for GET /export/download?token=…&_wpnonce=…, present once phase === 'done'. */
  downloadToken?: string;
  /** Same contract as CheckPreview.needsProcess — render is stale/missing, prompt Process first. */
  needsProcess?: boolean;
  startedAt?: number;
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
  replacements?: Replacement[];
  mediaReplacements?: MediaReplacement[];
  linkReplacements?: LinkReplacement[];
  videoReplacements?: VideoReplacement[];
}
