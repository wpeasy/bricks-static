import { defineConfig } from 'vite';
import { svelte } from '@sveltejs/vite-plugin-svelte';

/**
 * PRO build → pro/assets/dist with its own Vite manifest. ProMenu.php reads it
 * to enqueue the Pro bundle onto the Free dashboard page. A single entry that
 * registers the Pro replacement panels into window.BS. Input paths are relative
 * to the project root.
 */
export default defineConfig({
  plugins: [svelte()],
  build: {
    outDir: 'pro/assets/dist',
    emptyOutDir: true,
    manifest: true,
    target: 'es2020',
    rollupOptions: {
      input: {
        pro: 'pro/src-svelte/pro/main.ts',
      },
    },
  },
});
