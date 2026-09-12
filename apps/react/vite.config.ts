import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  server: {
    port: 5173,
    // 前端一律用相对路径 /api/v1/...，由 dev server 转发到 service 应用
    proxy: {
      '/api': { target: 'http://localhost:8788', changeOrigin: true },
    },
  },
})
