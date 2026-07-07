// Frontend entry: mounts the draggable "Sync this page" floating button. Only
// enqueued for users who can manage the plugin (see src/Frontend/Fab.php), and
// never inside the Bricks builder iframe.
import { mount } from 'svelte';
import Fab from './Fab.svelte';

interface FabData {
  restUrl: string;
  nonce: string;
  pageUrl: string;
  inEditor?: boolean | string;
  manualMode?: boolean | string;
  postId?: number | string;
  included?: boolean | string;
  effective?: boolean | string;
  includedCount?: number | string;
  savedCount?: number | string;
  maxPages?: number | string;
  unlimited?: boolean | string;
}

const data = (window as unknown as { bsFabData?: FabData }).bsFabData;

if (data && data.restUrl) {
  const host = document.createElement('div');
  host.className = 'bs-fab-root';
  document.body.appendChild(host);

  mount(Fab, {
    target: host,
    // booleans may arrive as '1'/'' via wp_localize_script — coerce.
    props: {
      restUrl: data.restUrl,
      nonce: data.nonce,
      pageUrl: data.pageUrl,
      inEditor: !!data.inEditor,
      manualMode: !!data.manualMode,
      postId: Number(data.postId) || 0,
      included: !!data.included,
      effective: !!data.effective,
      includedCount: Number(data.includedCount) || 0,
      savedCount: Number(data.savedCount) || 0,
      maxPages: Number(data.maxPages) || 0,
      unlimited: !!data.unlimited,
    },
  });
}
