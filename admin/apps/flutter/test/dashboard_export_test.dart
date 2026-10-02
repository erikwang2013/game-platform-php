// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:excel/excel.dart' as xls;
import 'package:flutter_test/flutter_test.dart';
import 'package:admin_app/app/pages/dashboard/dashboard_controller.dart';

/// 「导出 Excel」的真钉子。
///
/// 这一栏曾经是**空壳**：`exportExcel()` 只有一句 `Get.snackbar`，点下去什么都不产出
/// （2026-10-02 修：改成客户端生成 xlsx，见 controller 里的注释说明为何不走
/// `/admin/v1/export/excel`）。断言的是**字节** —— 解回 xlsx 逐格比对，
/// 「不产字节」「产了但行错/列错/顺序错」三种坏法都过不了。
///
/// 不走 widget test：`FileSaver` 要平台通道，widget test 里必然抛，落进 catch 分支
/// 后与「真的成功了」在界面上无从区分（同一条 snackbar 路径）。
void main() {
  test('导出 Excel: 每行 label/value 原样落格，行序与入参一致', () {
    final bytes = DashboardController.workbookBytes([
      {'label': 'Total Users', 'value': '1,236'},
      {'label': 'Active', 'value': '89'},
      {'label': 'Daily New', 'value': '42'},
    ]);
    expect(bytes, isNotEmpty);

    final sheet = xls.Excel.decodeBytes(bytes)['Sheet1'];
    String at(int row, int col) => sheet.rows[row][col]?.value.toString() ?? '';

    expect(sheet.maxRows, 3);
    expect(sheet.maxColumns, 2);
    expect(at(0, 0), 'Total Users');
    // 逗号分组必须原样进格：本地化格式化成「1236」或数字 1236 都是丢信息
    expect(at(0, 1), '1,236');
    expect(at(1, 0), 'Active');
    expect(at(1, 1), '89');
    expect(at(2, 0), 'Daily New');
    expect(at(2, 1), '42');
  });
}
