import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

// 开发服务器端口与后端地址集中在此；产物用相对路径 /api/v1，由 nginx 同源转发
const DEV_PORT = 5173
const API_TARGET = 'http://localhost:8792'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  server: {
    port: DEV_PORT,
    // 前端一律用相对路径 /api/v1/...，由 dev server 转发到 service 应用
    proxy: {
      '/api': { target: API_TARGET, changeOrigin: true },
    },
  },
})
