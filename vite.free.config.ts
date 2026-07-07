import { defineConfig } from 'vite';
import { svelte } from '@sveltejs/vite-plugin-svelte';

/**
 * FREE build → assets/dist with a Vite manifest. WordPress (Admin/Menu.php)
 * reads the manifest to enqueue the hashed JS + CSS. After the Free/Pro split
 * the dashboard entry imports zero Pro panels, so they never land in this bundle.
 * Input paths are relative to the project root (this config's directory).
 */
export default defineConfig({
  plugins: [svelte()],
  build: {
    outDir: 'assets/dist',
    emptyOutDir: true,
    manifest: true,
    target: 'es2020',
    rollupOptions: {
      input: {
        dashboard: 'src-svelte/dashboard/main.ts',
        frontend: 'src-svelte/frontend/main.ts',
        docs: 'src-svelte/docs/main.ts',
        editor: 'src-svelte/editor/main.ts',
      },
    },
  },
});
