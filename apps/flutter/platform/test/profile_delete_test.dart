// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter_test/flutter_test.dart';
import 'package:game_platform/app/pages/profile/profile_page.dart';

/// C 端注销账号的钉子：请求体字段名是服务端契约，回读判定「只有 401/404 才算没了」。
/// 两处退化都会静默出事——字段名写错服务端 422、判定放宽会把「没注销掉」宣布成成功。
void main() {
  test('注销请求体字段名与 confirm 值：password + confirm（服务端契约）', () {
    expect(deleteAccountPayload('secret', 'yes'), {'password': 'secret', 'confirm': 'yes'});
    // 用户输入原样透传，由服务端裁决（'请输入 yes 确认注销'），客户端不替它决定
    expect(deleteAccountPayload('secret', 'no'), {'password': 'secret', 'confirm': 'no'});
  });

  test('注销后回读：只有 401/404 才算账号确已取不到', () {
    expect(isAccountGoneStatus(401), isTrue);
    expect(isAccountGoneStatus(404), isTrue);
    // 仍读得到（0/200）、被拒（422）、服务端故障（500/503）、网络（-1）都不算「已注销」
    for (final code in [0, 200, 403, 422, 500, 503, -1]) {
      expect(isAccountGoneStatus(code), isFalse, reason: 'code=$code 不得判定为已注销');
    }
  });
}
