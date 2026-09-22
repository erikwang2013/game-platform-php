// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter_test/flutter_test.dart';
import 'package:game_platform/app/pages/wallet/wallet_amount.dart';

void main() {
  test('金额精度正则与服务端一致，比较不经 float', () {
    // 两位小数货币：≤2 位通过，3 位/空小数/负数/科学计数法拒绝
    expect(isValidAmount('10'), isTrue);
    expect(isValidAmount('10.5'), isTrue);
    expect(isValidAmount('10.50'), isTrue);
    expect(isValidAmount('10.505'), isFalse);
    expect(isValidAmount('10.'), isFalse);
    expect(isValidAmount('.5'), isFalse);
    expect(isValidAmount('-10'), isFalse);
    expect(isValidAmount('1e3'), isFalse);
    expect(isValidAmount(''), isFalse);

    // JPY/KRW：0 位小数
    expect(isValidAmount('100', maxDecimals: 0), isTrue);
    expect(isValidAmount('100.00', maxDecimals: 0), isFalse);

    // 精确十进制比较（等值不同写法视为相等，9.5 < 10）
    expect(compareAmounts('10.00', '10'), 0);
    expect(compareAmounts('9.5', '10'), lessThan(0));
    expect(compareAmounts('0.0001', '0'), greaterThan(0));
    expect(compareAmounts('100', '1000'), lessThan(0));

    expect(isPositiveAmount('0.00'), isFalse);
    expect(isPositiveAmount('0.0001'), isTrue);

    // DECIMAL 下发的零值形如 "0.0000"，语义零判断不能写成 == '0'
    expect(isZeroAmount('0'), isTrue);
    expect(isZeroAmount('0.0000'), isTrue);
    expect(isZeroAmount('0.0'), isTrue);
    expect(isZeroAmount('5000.0000'), isFalse);
    expect(isZeroAmount('0.0001'), isFalse);
    expect(isZeroAmount('abc'), isFalse);

    // 展示裁剪只影响渲染，不参与提交值
    expect(displayAmount('100.0000'), '100');
    expect(displayAmount('0.5000'), '0.5');
    expect(displayAmount('0.0000'), '0');
    expect(displayAmount(null), '0');
  });
}
