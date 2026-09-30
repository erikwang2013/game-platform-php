// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// 选图端口的**非 web 实现**：本树没有 file_picker / image_picker 之类的平台插件（全仓 0 引用），
// 也不为「换张头像 / 传证件照」新增插件依赖 ⇒ 非 web 平台返回 null，界面按「用户取消」处理。
// `flutter test` 跑在 VM 上，编到的恒是本文件（条件导入见 user_file.dart）。
import 'user_file.dart';

/// 返回 null = 未选择文件（本实现恒为 null）。
Future<PickedImage?> pickImage() async => null;
