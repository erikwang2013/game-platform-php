// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// 批次 3（用户与权限）四个页面的接线自检：离线（flutter_test 把网络请求变成 400）下页面必须能建起来，
// 标题/页签/动作按钮都在。列表行的字段映射要靠真数据，这里不覆盖（那是各模块 controller 的事）。
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';
import 'package:admin_app/app/pages/identity/identity_page.dart';
import 'package:admin_app/app/pages/platform_user/platform_user_page.dart';
import 'package:admin_app/app/pages/role/permission_page.dart';
import 'package:admin_app/app/pages/role/role_controller.dart';
import 'package:admin_app/app/pages/role/role_list_page.dart';
import 'package:admin_app/app/pages/ticket/ticket_page.dart';
import 'package:admin_app/app/widgets/permission_tree.dart';
import 'test_helpers.dart';

/// 不打网络、只记账的审核控制器：列表数据自己塞，review 只录调用参数。
class _FakeIdentityController extends IdentityController {
  final calls = <List<String>>[];

  @override
  Future<void> loadData({int? toPage}) async {} // 走的是父类 onInit，但这里不打网络：列表数据由用例自己塞

  @override
  Future<void> review(String id, String action, String note) async => calls.add([id, action, note]);
}

/// 角色/权限两个控制器同样不打网络：列表与权限树由用例自己塞，写操作只记账。
class _FakeRoleController extends RoleController {
  final created = <Map<String, dynamic>>[];
  final updated = <(String, Map<String, dynamic>)>[];

  @override
  Future<void> loadRoles({int? toPage}) async {}

  @override
  Future<void> createRole(Map<String, dynamic> data) async => created.add(data);

  @override
  Future<void> updateRole(String hashid, Map<String, dynamic> data) async => updated.add((hashid, data));
}

class _FakePermissionController extends PermissionController {
  final created = <Map<String, dynamic>>[];

  @override
  Future<void> load() async {}

  @override
  Future<void> create(Map<String, dynamic> data) async => created.add(data);
}

/// 两层的权限树：Game（根）→ Game List（子）。解析走产品代码的 PermissionNode.parse，
/// 形状与后端 buildTree 同款（根 parent_id 是**数字 0**、叶子**没有** children 键）。
List<PermissionNode> fakePermissionTree() => PermissionNode.parse(<dynamic>[
      <String, dynamic>{
        'id': 'perm-1',
        'parent_id': 0,
        'name': 'Game',
        'type': 1,
        'children': <dynamic>[
          <String, dynamic>{'id': 'perm-2', 'parent_id': 'perm-1', 'name': 'Game List', 'type': 1},
        ],
      },
    ]);

/// 带兄弟节点的树：Game（根）→ [Game List, Game Detail]（用来验「父级半选」这一态）。
List<PermissionNode> fakeBranchyPermissionTree() => PermissionNode.parse(<dynamic>[
      <String, dynamic>{
        'id': 'perm-1',
        'parent_id': 0,
        'name': 'Game',
        'children': <dynamic>[
          <String, dynamic>{'id': 'perm-2', 'name': 'Game List'},
          <String, dynamic>{'id': 'perm-3', 'name': 'Game Detail'},
        ],
      },
    ]);

void main() {
  setUp(setUpTest);

  /// 页面本身不带 Scaffold（它们在 AdminLayout 的 Scaffold body 里跑），测试要自己补上 Material 祖先。
  Future<void> pumpPage(WidgetTester tester, Widget page) async {
    tester.view.physicalSize = const Size(1400, 1000);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.reset);
    await tester.pumpWidget(GetMaterialApp(
      locale: const Locale('en', 'US'),
      home: Scaffold(body: page),
    ));
    await tester.pumpAndSettle();
  }

  /// 加载失败会走 Get.snackbar（自带自动关闭 Timer），测试结束前必须让它到期。
  Future<void> flushSnackbars(WidgetTester tester) async {
    await tester.pump(const Duration(seconds: 5));
    await tester.pumpAndSettle();
  }

  testWidgets('角色权限页: 角色/权限两个页签都在，且都能建起来', (tester) async {
    await pumpPage(tester, const RoleListPage());

    expect(find.text('Role Management'), findsWidgets); // 角色页签（含 CrudHeader）
    expect(find.text('Create'), findsOneWidget); // CrudHeader 的「+ 新建」
    expect(find.text('No roles'), findsOneWidget); // 离线空态

    // 加载失败的 snackbar 实测悬在顶部（y≈16、满宽），正好压住 TabBar 的命中区 ⇒ 点页签前先放掉它
    await flushSnackbars(tester);

    // 用 TabBar 内的那一个「Permissions」（权限页建起来后正文里还有同名标题）
    await tester.tap(find.descendant(of: find.byType(TabBar), matching: find.text('Permissions')));
    await tester.pumpAndSettle();
    expect(find.text('No permissions'), findsOneWidget); // 权限页签已建起来

    await flushSnackbars(tester); // 权限页自己那次加载失败的 snackbar
  });

  testWidgets('角色表单: 权限多选按权限树铺开，提交的是勾选的 hashid', (tester) async {
    final roleCtrl = _FakeRoleController();
    final permCtrl = _FakePermissionController()..tree.value = fakePermissionTree();
    Get.put<RoleController>(roleCtrl);
    Get.put<PermissionController>(permCtrl);
    await pumpPage(tester, const RoleListPage());

    await tester.tap(find.text('Create'));
    await tester.pumpAndSettle();
    expect(find.text('Create Role'), findsOneWidget);

    // 树按层级铺开（不再是摊平后靠空格缩进的一维列表）
    expect(find.text('Game'), findsOneWidget);
    expect(find.text('Game List'), findsOneWidget);

    await tester.enterText(find.widgetWithText(TextField, 'Name'), 'ops');
    await tester.enterText(find.widgetWithText(TextField, 'Slug'), 'ops');
    await tester.tap(find.byType(CheckboxListTile).at(1)); // 勾上子节点 Game List
    await tester.pump();
    await tester.tap(find.text('Save'));
    await tester.pumpAndSettle();

    // 提交的是权限 hashid 数组（后端 decodePermissionIds 逐个 decodeId）；
    // 子节点的父级被自动勾中 ⇒ 父子的 id 一起提交
    expect(roleCtrl.created.single['permission_ids'], unorderedEquals(<String>['perm-1', 'perm-2']));
    expect(find.text('Create Role'), findsNothing); // 成功即关框
  });

  testWidgets('角色表单: 只勾一个子节点时父级半选，授权里仍要带上父级', (tester) async {
    final roleCtrl = _FakeRoleController();
    Get.put<RoleController>(roleCtrl);
    Get.put<PermissionController>(_FakePermissionController()..tree.value = fakeBranchyPermissionTree());
    await pumpPage(tester, const RoleListPage());

    await tester.tap(find.text('Create'));
    await tester.pumpAndSettle();
    await tester.enterText(find.widgetWithText(TextField, 'Name'), 'ops');
    await tester.enterText(find.widgetWithText(TextField, 'Slug'), 'ops');

    final boxes = find.byType(CheckboxListTile);
    await tester.tap(boxes.at(1)); // 只勾 Game List
    await tester.pump();
    expect(tester.widget<CheckboxListTile>(boxes.at(0)).value, isNull); // 父级：半选（横杠）
    expect(tester.widget<CheckboxListTile>(boxes.at(1)).value, isTrue);
    expect(tester.widget<CheckboxListTile>(boxes.at(2)).value, isFalse); // 兄弟节点不受影响

    await tester.tap(find.text('Save'));
    await tester.pumpAndSettle();
    // 半选的父级也要提交：菜单类权限不参与鉴权但前端要靠它显示入口，
    // 只授子权限不授父菜单 ⇒ 拿到该角色的运营「有权限但看不到菜单」
    expect(roleCtrl.created.single['permission_ids'], unorderedEquals(<String>['perm-1', 'perm-2']));
  });

  testWidgets('角色编辑: 行里的权限回填成勾选，没动过就不发 permission_ids', (tester) async {
    final roleCtrl = _FakeRoleController();
    roleCtrl.roles.value = <dynamic>[
      <String, dynamic>{
        'id': 'role-1',
        'name': 'Ops',
        'slug': 'ops',
        'description': '',
        'status': 1,
        'users_count': 2,
        'permission_ids': <dynamic>['perm-1', 'perm-2'],
      },
    ];
    Get.put<RoleController>(roleCtrl);
    Get.put<PermissionController>(_FakePermissionController()..tree.value = fakePermissionTree());
    await pumpPage(tester, const RoleListPage());

    await tester.tap(find.byIcon(Icons.edit));
    await tester.pumpAndSettle();
    expect(find.text('Edit Role'), findsOneWidget);
    // 回填：行里两个权限都是勾上的（少了这一步，编辑一次就会把授权清空）
    expect(tester.widget<CheckboxListTile>(find.byType(CheckboxListTile).at(0)).value, isTrue);
    expect(tester.widget<CheckboxListTile>(find.byType(CheckboxListTile).at(1)).value, isTrue);

    await tester.tap(find.text('Save'));
    await tester.pumpAndSettle();
    expect(roleCtrl.updated.single.$1, 'role-1'); // {hashid} 用的是行里那个
    expect(roleCtrl.updated.single.$2.containsKey('permission_ids'), isFalse); // 没动过 ⇒ 不发
    expect(roleCtrl.updated.single.$2['name'], 'Ops'); // 其余字段照常局部提交
  });

  testWidgets('角色表单: 权限树取不到时不摆多选（空勾选集会把角色权限清空）', (tester) async {
    Get.put<RoleController>(_FakeRoleController());
    Get.put<PermissionController>(_FakePermissionController()); // tree 留空 = 权限树没拿到
    await pumpPage(tester, const RoleListPage());

    await tester.tap(find.text('Create'));
    await tester.pumpAndSettle();

    expect(find.text('Create Role'), findsOneWidget);
    expect(find.byType(CheckboxListTile), findsNothing);
  });

  testWidgets('权限表单: 父级下拉 = 根节点 + 权限树节点，提交父级 hashid', (tester) async {
    final permCtrl = _FakePermissionController()..tree.value = fakePermissionTree();
    Get.put<PermissionController>(permCtrl);
    await pumpPage(tester, const PermissionPage());

    await tester.tap(find.text('Create'));
    await tester.pumpAndSettle();
    expect(find.text('Create Permission'), findsOneWidget);
    expect(find.text('Root (top level)'), findsOneWidget); // 新建态默认根节点

    await tester.enterText(find.widgetWithText(TextField, 'Name'), 'Game Detail');
    await tester.enterText(find.widgetWithText(TextField, 'Slug'), 'game.detail');
    // 表单里有两个下拉（type 在前、parent 在后），父级是后一个
    await tester.tap(find.byType(DropdownButtonFormField<String>).last);
    await tester.pumpAndSettle();
    await tester.tap(find.text('Game').last); // 展开后菜单里那一项
    await tester.pumpAndSettle();
    await tester.tap(find.text('Save'));
    await tester.pumpAndSettle();

    expect(permCtrl.created.single['parent_id'], 'perm-1');
    expect(permCtrl.created.single['type'], '1'); // type 只有 store 收，新建态必给
    expect(find.text('Create Permission'), findsNothing);
  });

  testWidgets('KYC 驳回要二次确认（文案带申请人），通过则直接提交', (tester) async {
    final ctrl = _FakeIdentityController();
    ctrl.list.value = [
      <String, dynamic>{
        'id': 'hashid-rec-1',
        'real_name': 'Zhang San',
        'id_type': 'id_card',
        'status': 'pending',
        'created_at': '2026-01-01 00:00:00',
        'user': <String, dynamic>{'username': 'zhangsan'},
      },
    ];
    Get.put<IdentityController>(ctrl);
    await pumpPage(tester, const IdentityPage());

    // 表头 SegmentedButton 选中段也带 Icons.check ⇒ 行内按钮的 finder 必须限定在表格里
    final rowApprove = find.descendant(of: find.byType(DataTable), matching: find.byIcon(Icons.check));
    final rowReject = find.descendant(of: find.byType(DataTable), matching: find.byIcon(Icons.close));

    // 驳回：Save 之后还过一次确认，文案带对象标识；取消＝不写后端。
    // 确认框是叠在表单上的：表单此刻处于提交中（Save 上转圈＝无限动画）⇒ 这一步不能用 pumpAndSettle。
    await tester.tap(rowReject);
    await tester.pumpAndSettle();
    await tester.tap(find.text('Save'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 400));
    expect(find.text('Reject KYC "Zhang San"? A reviewed record cannot be reviewed again.'), findsOneWidget);
    // 表单自己的 Cancel 还在树上，取最上层那个对话框里的
    await tester.tap(find.descendant(of: find.byType(AlertDialog).last, matching: find.text('Cancel')));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 400));
    expect(ctrl.calls, isEmpty);

    // 再来一次，这回确认到底
    await tester.tap(rowReject);
    await tester.pumpAndSettle();
    await tester.tap(find.text('Save'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 400));
    await tester.tap(find.widgetWithText(ElevatedButton, 'Reject'));
    await tester.pumpAndSettle();
    expect(ctrl.calls, <List<String>>[
      ['hashid-rec-1', 'reject', ''],
    ]);

    // 通过：不是破坏性动作，Save 即提交，不许弹确认
    await tester.tap(rowApprove);
    await tester.pumpAndSettle();
    await tester.tap(find.text('Save'));
    await tester.pumpAndSettle();
    expect(ctrl.calls.last, ['hashid-rec-1', 'approve', '']);
    expect(find.textContaining('Reject KYC'), findsNothing);
  });

  testWidgets('平台用户页: 标题/筛选/编辑入口', (tester) async {
    await pumpPage(tester, const PlatformUserPage());

    expect(find.text('Platform Users'), findsWidgets);
    expect(find.text('Search username/nickname'), findsOneWidget);
    // 平台用户不能在管理端新建：没有「+ 新建」按钮
    expect(find.text('Create'), findsNothing);

    await flushSnackbars(tester);
  });

  testWidgets('KYC 审核页: 待审列表为空时给出空态', (tester) async {
    await pumpPage(tester, const IdentityPage());

    expect(find.text('KYC Review'), findsWidgets);
    expect(find.text('No data'), findsOneWidget);

    await flushSnackbars(tester);
  });

  testWidgets('工单页: 状态筛选段 + 空态', (tester) async {
    await pumpPage(tester, const TicketPage());

    expect(find.text('Tickets'), findsWidgets);
    for (final label in ['All', 'Open', 'Waiting', 'Replied', 'Closed']) {
      expect(find.text(label), findsWidgets, reason: '缺少状态筛选: $label');
    }
    expect(find.text('No tickets'), findsOneWidget);

    await flushSnackbars(tester);
  });
}
