import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

// 管理台后端地址与 dev 端口集中在此（4 条代理规则共用 TARGET）
const TARGET = 'http://localhost:8789'
const DEV_PORT = 5273

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  server: {
    port: DEV_PORT,
    proxy: {
      '/admin/v1': TARGET,
      '/api/v1': TARGET,
      '/health': TARGET,
      '/metrics': TARGET,
    },
  },
})
