// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// 全屏端口的**非 web 实现**：VM 上没有 DOM，也没有「浏览器全屏」这回事
// ⇒ 恒为否、切换是空操作。`flutter test` 跑在 VM 上，编到的恒是本文件
// （条件导入见 fullscreen.dart），DOM 互操作只存在于 fullscreen_web.dart。
/// 非 web 平台恒 false。
bool get isFullscreen => false;

/// 非 web 平台空操作。
Future<void> toggleFullscreen() async {}
