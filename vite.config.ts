import { defineConfig } from 'vite';
import { svelte } from '@sveltejs/vite-plugin-svelte';

/**
 * Builds the admin dashboard bundle into assets/dist with a Vite manifest.
 * WordPress (Admin/Menu.php) reads the manifest to enqueue the hashed JS + CSS.
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
      },
    },
  },
});
