import { defineConfig } from 'vite';

export default defineConfig({
  base: './',
  server: {
    port: 5175,
  },
  build: {
    outDir: 'dist',
    emptyOutDir: true,
  },
});
