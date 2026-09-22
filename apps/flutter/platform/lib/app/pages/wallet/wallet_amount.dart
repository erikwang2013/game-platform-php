// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

/// 金额校验/比较工具：金额一律以字符串参与，禁止 float 参与（项目规则）。
/// 提交前不改写用户输入（不 parse 成数值、不 toStringAsFixed），只做正则与字符串比较。
library;

final RegExp _decimalRe = RegExp(r'^\d+(\.\d+)?$');

/// 非负十进制字符串（不限小数位），用于仅需"是个数"的场合
bool isDecimal(String value) => _decimalRe.hasMatch(value);

/// ≤ [maxDecimals] 位小数的非负十进制字符串。
/// 与服务端 `^\d+(\.\d{1,N})?$` 对齐：JPY/KRW 传 0，其余货币传 2。
bool isValidAmount(String value, {int maxDecimals = 2}) {
  final re = maxDecimals == 0
      ? RegExp(r'^\d+$')
      : RegExp('^\\d+(\\.\\d{1,$maxDecimals})?\$');
  return re.hasMatch(value);
}

/// 严格大于 0 的十进制字符串
bool isPositiveAmount(String value) => isDecimal(value) && compareAmounts(value, '0') > 0;

/// 数值为 0 的十进制字符串。服务端金额列是 DECIMAL(18,4)，下发 "0.0000" 这类形式，
/// 判断"不限"不能写成 == '0'，否则零值判断永远为假
bool isZeroAmount(String value) => isDecimal(value) && compareAmounts(value, '0') == 0;

/// 展示用：裁掉小数尾部多余的 0（"100.0000" → "100"），
/// 只用于渲染，不改动任何将要提交的值
String displayAmount(dynamic value) {
  var s = value?.toString() ?? '0';
  if (s.contains('.')) {
    s = s.replaceAll(RegExp(r'0+$'), '');
    if (s.endsWith('.')) s = s.substring(0, s.length - 1);
  }
  return s.isEmpty ? '0' : s;
}

/// 精确比较两个十进制字符串，等价于 bccomp(a, b, 8)：放大为 BigInt 后比较，无 float。
/// 入参需为非负十进制字符串（isDecimal 通过）。
int compareAmounts(String a, String b) => _scaled(a).compareTo(_scaled(b));

BigInt _scaled(String value) {
  final parts = value.split('.');
  final frac = (parts.length > 1 ? parts[1] : '').padRight(8, '0').substring(0, 8);
  return BigInt.parse(parts[0] + frac);
}
