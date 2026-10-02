// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// 全屏端口的 **web 实现**：直接用 SDK 自带的 dart:js_interop，**不引入任何包**。
// 本文件只被 web 编译（条件导入见 fullscreen.dart），VM 上跑的是 stub。
import 'dart:async';
import 'dart:js_interop';

@JS('document.documentElement.requestFullscreen')
external JSPromise _requestFullscreen();

@JS('document.exitFullscreen')
external JSPromise _exitFullscreen();

@JS('document.fullscreenElement')
external JSObject? get _fullscreenElement;

/// 当前是否处于全屏。浏览器原生 Esc（或 F11）退出后这里立刻变回 false，
/// 图标随之切回「进入全屏」。
bool get isFullscreen => _fullscreenElement != null;

/// 切换全屏：不在全屏就进、在就退。
Future<void> toggleFullscreen() async {
  if (_fullscreenElement == null) {
    await _requestFullscreen().toDart;
  } else {
    await _exitFullscreen().toDart;
  }
}
