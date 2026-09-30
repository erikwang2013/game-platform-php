// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// 选图端口的 **web 实现**：动态建一个 <input type="file">，等 change 事件后把文件读成字节。
// 只用 SDK 自带的 dart:js_interop（本树 pages/bigscreen/bigscreen_page.dart 同款写法），
// **不引入任何包**。本文件只被 web 编译（条件导入见 image_upload.dart），VM 上跑的是 stub。
import 'dart:async';
import 'dart:js_interop';
import 'dart:typed_data';

@JS('document')
external JSObject get _document;

extension type _Document(JSObject _) implements JSObject {
  external _InputElement createElement(String tagName);
  external _Body? get body;
}

extension type _Body(JSObject _) implements JSObject {
  external void appendChild(JSObject node);
}

extension type _InputElement(JSObject _) implements JSObject {
  external set type(String value);
  external set accept(String value);
  external set hidden(bool value);
  external set onchange(JSFunction? value);
  external set oncancel(JSFunction? value);
  external _FileList get files;
  external void click();
  external void remove();
}

extension type _FileList(JSObject _) implements JSObject {
  external int get length;
  external _File? item(int index);
}

extension type _File(JSObject _) implements JSObject {
  external String get name;
  external JSPromise<JSArrayBuffer> arrayBuffer();
}

/// 弹系统选图框。返回 null = 用户取消（关掉对话框 / 一个文件都没选）。
/// accept 与后端 groups.image 的白名单对齐（jpg/jpeg/png/gif/webp）—— 刻意不含 svg。
Future<({Uint8List bytes, String name})?> pickImage() async {
  final input = _Document(_document).createElement('input')
    ..type = 'file'
    ..accept = 'image/png,image/jpeg,image/gif,image/webp'
    ..hidden = true;

  final completer = Completer<({Uint8List bytes, String name})?>();
  void done(({Uint8List bytes, String name})? value) {
    if (!completer.isCompleted) completer.complete(value);
  }

  void onChange(JSAny? _) {
    final files = input.files;
    final file = files.length == 0 ? null : files.item(0);
    if (file == null) return done(null);
    // arrayBuffer() 是 JSPromise<JSArrayBuffer>：.toDart 拿 Future，再 toDart 拿 ByteBuffer
    file.arrayBuffer().toDart.then((buffer) => done((bytes: buffer.toDart.asUint8List(), name: file.name)));
  }

  input.onchange = onChange.toJS;
  // 取消时 change 不触发（Chrome 113+/Firefox 91+/Safari 16.4+ 会发 cancel）——少了这条，
  // future 永不完成，按钮会永久停在「上传中」。
  input.oncancel = ((JSAny? _) => done(null)).toJS;
  // 挂进 body 再点：脱离文档树的 input 在部分浏览器里点不开系统框
  _Document(_document).body?.appendChild(input);
  input.click(); // 在用户手势的调用栈里，浏览器才允许弹框
  final picked = await completer.future;
  input.remove();
  return picked;
}
