import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

const TARGET = 'http://localhost:8787'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  server: {
    port: 5273,
    proxy: {
      '/admin/v1': TARGET,
      '/api/v1': TARGET,
      '/health': TARGET,
      '/metrics': TARGET,
    },
  },
})
