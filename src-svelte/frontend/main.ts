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
}

const data = (window as unknown as { bsFabData?: FabData }).bsFabData;

if (data && data.restUrl) {
  const host = document.createElement('div');
  host.className = 'bs-fab-root';
  document.body.appendChild(host);

  mount(Fab, {
    target: host,
    // inEditor may arrive as '1'/'' via wp_localize_script — coerce to boolean.
    props: { restUrl: data.restUrl, nonce: data.nonce, pageUrl: data.pageUrl, inEditor: !!data.inEditor },
  });
}
