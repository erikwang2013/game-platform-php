// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// 选图端口的**非 web 实现**：本树没有 file_picker / image_picker 之类的平台插件（全仓 0 引用），
// 也不为「后台偶尔换张封面图」新增一个插件依赖 ⇒ 非 web 平台返回 null，界面按「用户取消」处理。
// widget 用例全都跑在这个实现上（VM），DOM 互操作只存在于 image_pick_web.dart。
import 'dart:typed_data';

/// 返回 null = 未选择文件（本实现恒为 null）。
Future<({Uint8List bytes, String name})?> pickImage() async => null;
