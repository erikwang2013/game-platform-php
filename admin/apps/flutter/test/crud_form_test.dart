// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// 写操作底座（lib/app/widgets/crud.dart）的自检：字段描述驱动的取值/必填/失败不关框。
// 这四条是所有模块共用的路径，底座改坏了这里先红。
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';
import 'package:admin_app/app/services/api_service.dart';
import 'package:admin_app/app/widgets/crud.dart';
import 'test_helpers.dart';

void main() {
  setUp(setUpTest);

  const fields = <CrudField>[
    CrudField('name', 'game.name', required: true),
    CrudField('slug', 'game.slug', editableOnEdit: false),
    CrudField('sort', 'game.sort', type: CrudFieldType.number),
    CrudField('status', 'game.status', type: CrudFieldType.toggle),
    CrudField('type', 'game.type', type: CrudFieldType.select, options: <CrudOption>[
      CrudOption('self', 'game.self'),
      CrudOption('third_party', 'game.third_party'),
    ]),
  ];

  Future<void> openForm(
    WidgetTester tester,
    Future<void> Function(Map<String, dynamic> data) onSubmit, {
    Map<String, dynamic>? initial,
    List<CrudField> formFields = fields,
    bool fullEdit = false,
  }) async {
    tester.view.physicalSize = const Size(1400, 1200);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.reset);
    await tester.pumpWidget(GetMaterialApp(
      locale: const Locale('en', 'US'),
      home: Builder(
        builder: (context) => ElevatedButton(
          onPressed: () => showCrudForm(
            context,
            title: 'Form',
            fields: formFields,
            initial: initial,
            fullEdit: fullEdit,
            onSubmit: onSubmit,
          ),
          child: const Text('open'),
        ),
      ),
    ));
    await tester.tap(find.text('open'));
    await tester.pumpAndSettle();
  }

  testWidgets('提交把 number 转 int、toggle 转 0/1、select 取默认第一项', (tester) async {
    Map<String, dynamic>? submitted;
    await openForm(tester, (data) async => submitted = data);

    await tester.enterText(find.widgetWithText(TextField, 'Name'), 'dota');
    await tester.enterText(find.widgetWithText(TextField, 'Slug'), 'dota-slug');
    await tester.enterText(find.widgetWithText(TextField, 'Sort Order'), '7');
    await tester.tap(find.byType(Switch)); // 新建默认启用 → 关掉
    await tester.pump();
    await tester.tap(find.text('Save'));
    await tester.pumpAndSettle();

    expect(submitted, isNotNull);
    expect(submitted!['name'], 'dota');
    expect(submitted!['sort'], 7); // 数字控件不能提交字符串（后端 nullable|integer 会 422）
    expect(submitted!['status'], 0);
    expect(submitted!['type'], 'self');
    expect(find.text('Form'), findsNothing); // 成功即关框
  });

  testWidgets('必填为空 → 框内提示且不提交、不关框', (tester) async {
    var calls = 0;
    await openForm(tester, (data) async => calls++);

    await tester.tap(find.text('Save'));
    await tester.pumpAndSettle();

    expect(calls, 0);
    expect(find.text('Name is required'), findsOneWidget);
    expect(find.text('Form'), findsOneWidget);
  });

  testWidgets('提交失败 → 服务端 message 显示在框内、不关框，改后可重试', (tester) async {
    var fail = true;
    await openForm(tester, (data) async {
      if (fail) throw ApiException(422, '游戏标识已存在');
    });

    await tester.enterText(find.widgetWithText(TextField, 'Name'), 'dota');
    await tester.tap(find.text('Save'));
    await tester.pumpAndSettle();

    expect(find.text('游戏标识已存在'), findsOneWidget);
    expect(find.text('Form'), findsOneWidget); // 不关框

    fail = false;
    await tester.tap(find.text('Save'));
    await tester.pumpAndSettle();

    expect(find.text('Form'), findsNothing);
  });

  testWidgets('编辑态预填，且 editableOnEdit=false 的字段不提交', (tester) async {
    Map<String, dynamic>? submitted;
    await openForm(
      tester,
      (data) async => submitted = data,
      initial: <String, dynamic>{'name': 'dota', 'slug': 'dota-slug', 'sort': 3, 'status': 0, 'type': 'third_party'},
    );

    expect(tester.widget<TextField>(find.widgetWithText(TextField, 'Slug')).enabled, isFalse);
    await tester.tap(find.text('Save'));
    await tester.pumpAndSettle();

    expect(submitted!.containsKey('slug'), isFalse); // PUT 的 validator 里没有 slug
    expect(submitted!['sort'], 3);
    expect(submitted!['status'], 0); // 预填的 0 不能被「默认 true」顶掉
    expect(submitted!['type'], 'third_party'); // 预填值在值域内则保留
  });

  testWidgets('fullEdit：编辑态整份提交（后端 update 复用 create 的 fill()，缺键即 422）', (tester) async {
    Map<String, dynamic>? submitted;
    await openForm(
      tester,
      (data) async => submitted = data,
      initial: <String, dynamic>{'name': 'dota', 'slug': 'dota-slug', 'sort': 3, 'status': 0, 'type': 'third_party'},
      fullEdit: true,
    );

    await tester.tap(find.text('Save'));
    await tester.pumpAndSettle();

    // 与上一条（非 fullEdit）的差别就在这里：editableOnEdit=false 的 slug 也必须出现在请求体里
    expect(submitted!.keys.toSet(), <String>{'name', 'slug', 'sort', 'status', 'type'});
    expect(submitted!['slug'], 'dota-slug'); // 值仍是行里的原值（没被抹成空串）
  });

  testWidgets('fullEdit：必填校验与提交同一条件（整份提交时置灰的必填也照查）', (tester) async {
    var calls = 0;
    const requiredButLocked = <CrudField>[
      CrudField('name', 'game.name', required: true, editableOnEdit: false),
    ];
    await openForm(
      tester,
      (data) async => calls++,
      initial: <String, dynamic>{'name': ''},
      formFields: requiredButLocked,
      fullEdit: true,
    );

    await tester.tap(find.text('Save'));
    await tester.pumpAndSettle();

    // 整份提交 ⇒ 这个必填键会被发出去 ⇒ 本地就得拦下（否则服务端 422）。非 fullEdit 时它本就不提交、不该拦。
    expect(calls, 0);
    expect(find.text('Name is required'), findsOneWidget);
  });

  testWidgets('select 的 hint 再长也不溢出（DropdownButton 把 hint 当最宽项来定按钮宽度）', (tester) async {
    await openForm(tester, (data) async {}, formFields: const <CrudField>[
      CrudField('type', 'game.type',
          type: CrudFieldType.select,
          hint: 'Recorded in the operation audit only - risk_log has no review column',
          options: <CrudOption>[CrudOption('a', 'game.self'), CrudOption('b', 'game.third_party')]),
    ]);

    // 溢出是 paint 期报的错（黄黑条），在用例里等价于「unexpected exception」：
    // 不修底座的话这里拿到的就是 RenderFlex overflowed by N pixels。
    expect(tester.takeException(), isNull);
  });

  testWidgets('编辑态遇到值域外的历史值：置顶原样显示、不提交', (tester) async {
    Map<String, dynamic>? submitted;
    await openForm(
      tester,
      (data) async => submitted = data,
      initial: <String, dynamic>{'name': 'dota', 'type': 'maintenance'},
    );

    expect(find.text('maintenance'), findsOneWidget); // 合成项置顶，原值没被悄悄改成第一项
    await tester.tap(find.text('Save'));
    await tester.pumpAndSettle();

    expect(submitted!.containsKey('type'), isFalse); // 不提交 → 后端 in: 规则不 422，库中原值不动
  });

  testWidgets('多选：预填勾选、提交整个勾选集、值域外的历史值不丢', (tester) async {
    Map<String, dynamic>? submitted;
    const multiFields = <CrudField>[
      CrudField('name', 'game.name', required: true),
      CrudField('permission_ids', 'role.permission_ids',
          type: CrudFieldType.multiselect,
          options: <CrudOption>[CrudOption('p1', 'perm.one'), CrudOption('p2', 'perm.two')]),
    ];
    tester.view.physicalSize = const Size(1400, 1200);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.reset);
    await tester.pumpWidget(GetMaterialApp(
      locale: const Locale('en', 'US'),
      home: Builder(
        builder: (context) => ElevatedButton(
          onPressed: () => showCrudForm(
            context,
            title: 'Form',
            fields: multiFields,
            // 后端回传的勾选集里可能带着值域里已经没有的 id（权限被删过）
            initial: <String, dynamic>{'name': 'x', 'permission_ids': <dynamic>['p2', 'gone']},
            onSubmit: (data) async => submitted = data,
          ),
          child: const Text('open'),
        ),
      ),
    ));
    await tester.tap(find.text('open'));
    await tester.pumpAndSettle();

    expect(find.byType(CheckboxListTile), findsNWidgets(3)); // 2 个选项 + 值域外的 gone
    expect(tester.widget<CheckboxListTile>(find.widgetWithText(CheckboxListTile, 'perm.two')).value, isTrue);
    expect(tester.widget<CheckboxListTile>(find.widgetWithText(CheckboxListTile, 'gone')).value, isTrue);

    await tester.tap(find.widgetWithText(CheckboxListTile, 'perm.one'));
    await tester.tap(find.widgetWithText(CheckboxListTile, 'perm.two'));
    await tester.pump();
    await tester.tap(find.text('Save'));
    await tester.pumpAndSettle();

    expect(submitted!['permission_ids'], unorderedEquals(<String>['p1', 'gone']));
  });

  testWidgets('多选: 编辑态没动过不发该字段，清空要显式发空数组', (tester) async {
    final payloads = <Map<String, dynamic>>[];
    const multiFields = <CrudField>[
      CrudField('permission_ids', 'role.permission_ids',
          type: CrudFieldType.multiselect,
          options: <CrudOption>[CrudOption('p1', 'perm.one'), CrudOption('p2', 'perm.two')]),
    ];
    tester.view.physicalSize = const Size(1400, 1200);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.reset);
    await tester.pumpWidget(GetMaterialApp(
      locale: const Locale('en', 'US'),
      home: Builder(
        builder: (context) => ElevatedButton(
          onPressed: () => showCrudForm(
            context,
            title: 'Form',
            fields: multiFields,
            initial: <String, dynamic>{'permission_ids': <dynamic>['p2']},
            onSubmit: (data) async => payloads.add(data),
          ),
          child: const Text('open'),
        ),
      ),
    ));

    // 一下都没动 ⇒ 请求体里不能有这个字段：该字段是整表替换，发了就是把没打算改的授权重写一遍
    await tester.tap(find.text('open'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Save'));
    await tester.pumpAndSettle();
    expect(payloads.single.containsKey('permission_ids'), isFalse);

    // 取消全部勾选 ⇒ 必须显式发 []（sync([]) = 解绑全部；不发则「清空」不生效）
    await tester.tap(find.text('open'));
    await tester.pumpAndSettle();
    await tester.tap(find.widgetWithText(CheckboxListTile, 'perm.two'));
    await tester.pump();
    await tester.tap(find.text('Save'));
    await tester.pumpAndSettle();
    expect(payloads[1]['permission_ids'], <String>[]);
  });

  testWidgets('删除二次确认：文案带对象标识，取消不调后端，确认才调', (tester) async {
    var calls = 0;
    tester.view.physicalSize = const Size(1400, 1200);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.reset);
    await tester.pumpWidget(GetMaterialApp(
      locale: const Locale('en', 'US'),
      home: Builder(
        builder: (context) => ElevatedButton(
          onPressed: () => confirmCrudDelete(context, what: 'dota', onConfirm: () async => calls++),
          child: const Text('del'),
        ),
      ),
    ));

    await tester.tap(find.text('del'));
    await tester.pumpAndSettle();
    expect(find.text('Delete "dota"?'), findsOneWidget);

    await tester.tap(find.text('Cancel'));
    await tester.pumpAndSettle();
    expect(calls, 0);

    await tester.tap(find.text('del'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Delete'));
    await tester.pumpAndSettle();
    expect(calls, 1);
  });

  testWidgets('非删除动作的二次确认：标题/文案/按钮词都可配，且不出现「删除」字样', (tester) async {
    var calls = 0;
    tester.view.physicalSize = const Size(1400, 1200);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.reset);
    await tester.pumpWidget(GetMaterialApp(
      locale: const Locale('en', 'US'),
      home: Builder(
        builder: (context) => ElevatedButton(
          onPressed: () => confirmCrudAction(
            context,
            title: 'Confirm Close',
            message: 'Close ticket "dota"?',
            confirmLabel: 'Close',
            onConfirm: () async => calls++,
          ),
          child: const Text('close'),
        ),
      ),
    ));

    await tester.tap(find.text('close'));
    await tester.pumpAndSettle();
    expect(find.text('Close ticket "dota"?'), findsOneWidget); // 文案带对象标识
    expect(find.text('Delete'), findsNothing); // 关闭不是删除：不许出现删除字样

    await tester.tap(find.text('Cancel'));
    await tester.pumpAndSettle();
    expect(calls, 0);

    await tester.tap(find.text('close'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Close'));
    await tester.pumpAndSettle();
    expect(calls, 1);
  });

  testWidgets('密码二次确认删除：框内出现密码输入，确认时把输入值回传 (onPassword)', (tester) async {
    final passwords = <String>[];
    var calls = 0;
    tester.view.physicalSize = const Size(1400, 1200);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.reset);
    await tester.pumpWidget(GetMaterialApp(
      locale: const Locale('en', 'US'),
      home: Builder(
        builder: (context) => ElevatedButton(
          onPressed: () => confirmCrudDelete(
            context,
            what: 'game.foo',
            onPassword: passwords.add,
            onConfirm: () async => calls++,
          ),
          child: const Text('del'),
        ),
      ),
    ));

    // 传了 onPassword ⇒ 框里多出密码输入（不传时没有这个 TextField，见上一条用例）
    await tester.tap(find.text('del'));
    await tester.pumpAndSettle();
    expect(find.widgetWithText(TextField, 'Admin Password'), findsOneWidget);

    // 空密码也照传：服务端 destroy 的 confirmPassword 会 422，不在这里替它拦
    await tester.tap(find.text('Delete'));
    await tester.pumpAndSettle();
    expect(passwords, <String>['']);
    expect(calls, 1);

    await tester.tap(find.text('del'));
    await tester.pumpAndSettle();
    await tester.enterText(find.byType(TextField), 's3cret');
    await tester.tap(find.text('Delete'));
    await tester.pumpAndSettle();
    expect(passwords, <String>['', 's3cret']);
    expect(calls, 2);
  });
}
