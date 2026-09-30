// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// 「选图 → 上传」端口：表单里点一次「上传」的完整动作。
// 条件导入把 DOM 互操作挡在 web 实现里 —— VM 上的 widget 用例绝不会编到 dart:js_interop
// （无条件 import 会让整棵树的用例编不过，本树 widget_test 的既存失败就是同一个坑）。
import 'api_service.dart';
import 'image_pick_stub.dart' if (dart.library.js_interop) 'image_pick_web.dart' as picker;

/// 点「上传」时执行的动作：返回**可落库的绝对 URL**；null = 用户取消（现值不动）。
/// 表单把这个动作当参数收（默认 = 真实实现），用例注入假实现即可离线跑通全流程。
typedef CrudImageUpload = Future<String?> Function();

/// 默认实现：浏览器选图（非 web 平台恒返回 null）→ aetherupload 两步直传。
Future<String?> pickAndUploadImage() async {
  final picked = await picker.pickImage();
  if (picked == null) return null;
  return ApiService().uploadImage(picked.bytes, picked.name);
}
