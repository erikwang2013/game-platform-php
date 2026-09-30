// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'dart:ui';

import 'package:flutter_test/flutter_test.dart';
import 'package:game_platform/app/services/captcha_service.dart';

void main() {
  const canvas = Size(300, 200); // 服务端画布恒为 300×200

  test('点击坐标按显示框等比换算到画布：中心点与四角都落在画布内', () {
    const box = Size(600, 400); // 显示框尺寸与画布不同——写死尺寸的实现会在这里露馅
    expect(captchaMapPoint(const Offset(300, 200), box, canvas), const Offset(150, 100)); // 中心
    expect(captchaMapPoint(const Offset(600, 400), box, canvas), const Offset(300, 200)); // 右下角
    expect(captchaMapPoint(Offset.zero, box, canvas), Offset.zero); // 左上角
    // 反证：不做换算（恒等映射）会得到 600×400，超出画布
    expect(captchaMapPoint(const Offset(600, 400), box, canvas).dx, lessThanOrEqualTo(canvas.width));
  });

  test('显示框宽高比与画布不一致时仍按各自轴独立换算', () {
    const box = Size(500, 200);
    expect(captchaMapPoint(const Offset(250, 100), box, canvas), const Offset(150, 100));
  });

  test('标记位置与点击换算互为逆运算（否则标记会漂离点击处）', () {
    const box = Size(600, 340);
    const local = Offset(123, 77);
    final mapped = captchaMapPoint(local, box, canvas);
    // 与 captcha_service.dart 里标记 Positioned 的公式同源
    expect(mapped.dx / canvas.width * box.width, closeTo(local.dx, 1e-9));
    expect(mapped.dy / canvas.height * box.height, closeTo(local.dy, 1e-9));
  });

  test('显示框尚未布局（尺寸为 0）时不产生 NaN/越界坐标', () {
    expect(captchaMapPoint(const Offset(10, 10), Size.zero, canvas), Offset.zero);
  });

  test('请求体字段与契约一致：captcha_key + clicks[{x,y}]，坐标为 int', () {
    const result = CaptchaResult('k1', [
      {'x': 12, 'y': 34},
      {'x': 56, 'y': 78},
    ]);
    final body = result.toRequestBody();
    expect(body.keys.toSet(), {'captcha_key', 'clicks'});
    expect(body['captcha_key'], 'k1');
    final clicks = body['clicks'] as List;
    expect(clicks, hasLength(2));
    for (final c in clicks) {
      expect((c as Map)['x'], isA<int>());
      expect(c['y'], isA<int>());
    }
  });
}
