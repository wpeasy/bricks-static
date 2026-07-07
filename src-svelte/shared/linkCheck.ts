// Link-integrity REST helpers for manual-mode include/exclude, shared by the
// sync modal (SyncPanel) and the editor metabox. Each takes the caller's
// restUrl + nonce so neither component depends on a particular data global.

export interface LinkPage {
  postId: number;
  title: string;
}

async function postJson<T>(restUrl: string, nonce: string, path: string, body: unknown): Promise<T> {
  const res = await fetch(restUrl + path, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
    body: JSON.stringify(body),
  });
  return res.json();
}

async function getJson<T>(restUrl: string, nonce: string, path: string): Promise<T> {
  const res = await fetch(restUrl + path, { headers: { 'X-WP-Nonce': nonce } });
  return res.json();
}

/** Pages this post links to that aren't included yet (offered on enable). */
export function outboundExcluded(
  restUrl: string,
  nonce: string,
  postId: number,
): Promise<{ pages: LinkPage[]; busy?: boolean }> {
  return postJson(restUrl, nonce, '/editor/links', { postId });
}

/** Included pages that link to this post (warned on disable). */
export function inboundIncluded(restUrl: string, nonce: string, postId: number): Promise<{ pages: LinkPage[] }> {
  return getJson(restUrl, nonce, '/editor/inbound?postId=' + postId);
}

/** Include a batch of posts (the "Include all" cascade). */
export function includeBulk(
  restUrl: string,
  nonce: string,
  postIds: number[],
): Promise<{ included: number[]; skipped: number[]; includedCount: number; savedCount: number; maxPages: number; unlimited: boolean }> {
  return postJson(restUrl, nonce, '/editor/include-bulk', { postIds });
}
