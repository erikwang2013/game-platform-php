// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

import 'dart:typed_data';

import 'package:flutter/material.dart';

import '../services/user_file.dart';

/// 用户自选图的展示。
///
/// 托管件（`/api/v1/user/file/…`）必须**带 token 取字节**再 `Image.memory`：插件的 display
/// 路由没在 service 侧注册，而 `<img>` / `Image.network` 也塞不进 Authorization 头。
/// 存量绝对 URL（第三方 OAuth）按普通 URL 直接渲染；空值或取不到时显示占位图 ——
/// 证件照取不到若渲染成空白，用户会以为自己没传过。
class UserFileImage extends StatefulWidget {
  const UserFileImage({
    super.key,
    required this.stored,
    this.width,
    this.height,
    this.fit = BoxFit.cover,
  });

  /// 存库值原文（托管相对 URL / 历史绝对 URL / 空串）
  final String stored;
  final double? width;
  final double? height;
  final BoxFit fit;

  @override
  State<UserFileImage> createState() => _UserFileImageState();
}

class _UserFileImageState extends State<UserFileImage> {
  Future<Uint8List>? _bytes;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void didUpdateWidget(UserFileImage oldWidget) {
    super.didUpdateWidget(oldWidget);
    // 换了值必须重取：上传成功后写回文本框，预览要跟着变
    if (oldWidget.stored != widget.stored) _load();
  }

  /// 只对托管件取字节；返回 null 表示走普通 URL 分支
  void _load() {
    final savedPath = UserFile.savedPathOf(widget.stored.trim());
    _bytes = savedPath == null ? null : UserFile.fetchBytes(savedPath);
  }

  @override
  Widget build(BuildContext context) {
    final stored = widget.stored.trim();
    if (stored.isEmpty) return _placeholder();

    final future = _bytes;
    if (future == null) {
      return Image.network(
        stored,
        width: widget.width,
        height: widget.height,
        fit: widget.fit,
        errorBuilder: (_, __, ___) => _placeholder(),
      );
    }

    return FutureBuilder<Uint8List>(
      future: future,
      builder: (context, snapshot) {
        if (snapshot.hasError) return _placeholder();
        final bytes = snapshot.data;
        if (bytes == null) return _placeholder(loading: true);
        return Image.memory(
          bytes,
          width: widget.width,
          height: widget.height,
          fit: widget.fit,
          errorBuilder: (_, __, ___) => _placeholder(),
        );
      },
    );
  }

  Widget _placeholder({bool loading = false}) {
    final scheme = Theme.of(context).colorScheme;
    return Container(
      width: widget.width,
      height: widget.height,
      color: scheme.surfaceContainerHighest,
      alignment: Alignment.center,
      child: loading
          ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
          : Icon(Icons.image_outlined, color: scheme.onSurfaceVariant),
    );
  }
}
