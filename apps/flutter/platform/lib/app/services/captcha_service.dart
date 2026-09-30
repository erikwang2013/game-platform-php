// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// 弹框验证码：取图 + 坐标换算 + 模态框集中在这里，登录/注册、提现、兑换卖出、领券四处共用。
// 服务端契约：POST /api/v1/captcha/generate → {key, image(裸 base64 PNG), extra.texts[{order,text}]}
//   - texts 按 order 升序即点击顺序；**坐标不下发**（坐标就是答案），由客户端点击后换算回传
//   - 画布恒为 300×200，点击坐标必须按"显示框 → 画布"等比换算，写死尺寸会让验证码永远点不对
import 'dart:convert';
import 'dart:typed_data';
import 'dart:ui' as ui;
import 'package:flutter/material.dart';
import 'api_service.dart';
import '../i18n/translations.dart';

/// 验证通过后的产物：captcha_key + 点击坐标（服务端要求 ≥2 个点）。
/// 通过 [toRequestBody] 与原请求体合并后提交，不要嵌套成子对象。
class CaptchaResult {
  final String key;
  final List<Map<String, int>> clicks;

  const CaptchaResult(this.key, this.clicks);

  Map<String, dynamic> toRequestBody() => {'captcha_key': key, 'clicks': clicks};
}

class CaptchaData {
  final String key;
  final Uint8List image;
  /// 服务端画布真实尺寸：由解码后的图片实测得出，禁止写死
  final Size canvas;
  /// 按 order 升序的待点文字
  final List<String> texts;

  const CaptchaData({required this.key, required this.image, required this.canvas, required this.texts});
}

/// 取一张新图。验证码一次性，每次开框都要重取（复用旧 key 必验不过）。
Future<CaptchaData> fetchCaptcha() async {
  final body = await ApiService().post('/api/v1/captcha/generate', data: {'difficulty': 'easy'});
  final data = body['data'] as Map;
  final bytes = base64Decode(
    '${data['image']}'.replaceFirst(RegExp(r'^data:image/\w+;base64,'), ''),
  );
  final frame = await (await ui.instantiateImageCodec(bytes)).getNextFrame();
  final texts = ((data['extra']?['texts'] as List?) ?? [])
      .map((t) => Map<String, dynamic>.from(t as Map))
      .toList()
    ..sort((a, b) => (a['order'] as int).compareTo(b['order'] as int));
  return CaptchaData(
    key: '${data['key']}',
    image: bytes,
    canvas: Size(frame.image.width.toDouble(), frame.image.height.toDouble()),
    texts: texts.map((t) => '${t['text']}').toList(),
  );
}

/// 显示框坐标 → 画布坐标。显示框按画布同宽高比 BoxFit.fill 铺满（无留白），故线性换算即精确。
Offset captchaMapPoint(Offset local, Size box, Size canvas) {
  if (box.width == 0 || box.height == 0) return Offset.zero;
  return Offset(local.dx / box.width * canvas.width, local.dy / box.height * canvas.height);
}

/// 弹出验证码模态框（开框时现取新图）。
/// 返回 null = 用户取消；调用方据此中止提交，不要拿旧结果继续。
/// barrierDismissible: false —— 点遮罩不关框，否则误触会丢掉已点标记；出口只有框内「取消」。
Future<CaptchaResult?> showCaptchaDialog(BuildContext context) => showDialog<CaptchaResult>(
      context: context,
      barrierDismissible: false,
      builder: (_) => const _CaptchaDialog(),
    );

class _CaptchaDialog extends StatefulWidget {
  const _CaptchaDialog();

  @override
  State<_CaptchaDialog> createState() => _CaptchaDialogState();
}

class _CaptchaDialogState extends State<_CaptchaDialog> {
  CaptchaData? _data;
  bool _loading = true;
  bool _failed = false;
  final List<Offset> _clicks = [];

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final data = await fetchCaptcha();
      if (!mounted) return;
      setState(() {
        _data = data;
        _clicks.clear();
        _loading = false;
        _failed = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _failed = true;
        _loading = false;
      });
    }
  }

  /// 换一张 / 重试：先回到加载态再取图
  void _reload() {
    setState(() {
      _loading = true;
      _failed = false;
    });
    _load();
  }

  /// 点满目标数量即封顶，多余点击忽略
  void _onTap(Offset local, Size box) {
    final data = _data;
    if (data == null || _clicks.length >= data.texts.length) return;
    setState(() => _clicks.add(captchaMapPoint(local, box, data.canvas)));
  }

  @override
  Widget build(BuildContext context) {
    final data = _data;
    final ready = data != null && _clicks.isNotEmpty && _clicks.length >= data.texts.length;

    return AlertDialog(
      title: Text('${AppTranslations.t('captcha.title')}'),
      content: SizedBox(width: 360, child: _buildContent(data)),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: Text('${AppTranslations.t('app.cancel')}'),
        ),
        FilledButton(
          onPressed: ready
              ? () => Navigator.pop(context, CaptchaResult(data.key, [
                    for (final c in _clicks) {'x': c.dx.round(), 'y': c.dy.round()},
                  ]))
              : null,
          child: Text('${AppTranslations.t('app.confirm')}'),
        ),
      ],
    );
  }

  Widget _buildContent(CaptchaData? data) {
    if (_loading) {
      return const SizedBox(height: 120, child: Center(child: CircularProgressIndicator()));
    }
    if (_failed || data == null) {
      return Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text('${AppTranslations.t('captcha.load_failed')}', style: const TextStyle(color: Colors.red, fontSize: 13)),
          const SizedBox(height: 8),
          TextButton(onPressed: _reload, child: Text('${AppTranslations.t('app.retry')}')),
        ],
      );
    }

    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          '${AppTranslations.t('captcha.hint')}: ${data.texts.map((t) => '"$t"').join(' → ')}',
          style: const TextStyle(fontSize: 13),
        ),
        const SizedBox(height: 8),
        ClipRRect(
          borderRadius: BorderRadius.circular(8),
          child: LayoutBuilder(
            builder: (context, constraints) {
              final w = constraints.maxWidth;
              final h = w * data.canvas.height / data.canvas.width;
              return GestureDetector(
                onTapUp: (d) => _onTap(d.localPosition, Size(w, h)),
                child: Stack(
                  children: [
                    Image.memory(data.image, width: w, height: h, fit: BoxFit.fill),
                    ..._clicks.asMap().entries.map((entry) {
                      final idx = entry.key;
                      final c = entry.value;
                      return Positioned(
                        left: (c.dx / data.canvas.width) * w - 14,
                        top: (c.dy / data.canvas.height) * h - 14,
                        child: Container(
                          width: 28,
                          height: 28,
                          decoration: BoxDecoration(
                            color: const Color(0xFF1677FF).withValues(alpha: 0.8),
                            shape: BoxShape.circle,
                          ),
                          child: Center(
                            child: Text(
                              '${idx + 1}',
                              style: const TextStyle(color: Colors.white, fontSize: 14, fontWeight: FontWeight.bold),
                            ),
                          ),
                        ),
                      );
                    }),
                  ],
                ),
              );
            },
          ),
        ),
        const SizedBox(height: 4),
        Row(
          children: [
            Text(
              '${AppTranslations.t('captcha.clicked')} ${_clicks.length}/${data.texts.length}',
              style: const TextStyle(fontSize: 12, color: Colors.grey),
            ),
            const Spacer(),
            TextButton(
              onPressed: _clicks.isEmpty ? null : () => setState(() => _clicks.removeLast()),
              child: Text('${AppTranslations.t('captcha.undo')}'),
            ),
            TextButton.icon(
              icon: const Icon(Icons.refresh, size: 16),
              label: Text('${AppTranslations.t('captcha.refresh')}'),
              onPressed: _reload,
            ),
          ],
        ),
      ],
    );
  }
}
