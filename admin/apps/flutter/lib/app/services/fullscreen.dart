// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// 全屏端口：条件导入把 DOM 互操作挡在 web 实现里 —— VM 上的 widget 用例绝不会编到
// dart:js_interop（无条件 import 会让整棵树的用例编不过，本树 widget_test 的既存失败
// 就是同一个坑）。形态与 services/image_upload.dart 的选图端口一致。
import 'fullscreen_stub.dart' if (dart.library.js_interop) 'fullscreen_web.dart' as impl;

/// 当前是否处于全屏（非 web 平台恒 false）
bool get isFullscreen => impl.isFullscreen;

/// 切换全屏：不在全屏就进、在就退（非 web 平台空操作）
Future<void> toggleFullscreen() => impl.toggleFullscreen();
