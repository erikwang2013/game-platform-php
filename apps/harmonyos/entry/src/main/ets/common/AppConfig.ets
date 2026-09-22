/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 全树唯一的服务地址出处 — 真机部署时只改这里。
 * 模拟器: http://10.0.2.2:8792  （映射宿主机 localhost:8792，C 端 service 端口）
 * 真机:   http://<电脑局域网IP>:8792  （与手机同一局域网，并放行 8792）
 */
export const API_BASE_URL: string = 'http://10.0.2.2:8792';
