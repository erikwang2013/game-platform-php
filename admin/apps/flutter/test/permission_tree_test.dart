// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// 权限树的纯逻辑自检：解析容错 / 级联勾选 / 半选判定 / 授权集往返 / 可见行。
// 全是顶层函数，不需要 widget 绑定。
import 'package:flutter_test/flutter_test.dart';
import 'package:admin_app/app/widgets/permission_tree.dart';

/// 后端 PermissionController::buildTree 的真实形状：
/// 根节点 `parent_id` 是**数字 0**（非根的 hashid 字符串），叶子**没有 `children` 键**。
const List<Map<String, dynamic>> _wire = <Map<String, dynamic>>[
  <String, dynamic>{
    'id': 'm1',
    'name': 'Game',
    'parent_id': 0,
    'children': <Map<String, dynamic>>[
      <String, dynamic>{'id': 'm1a', 'name': 'Game List', 'parent_id': 'm1'},
      <String, dynamic>{'id': 'm1b', 'name': 'Game Detail', 'parent_id': 'm1'},
    ],
  },
  <String, dynamic>{
    'id': 'm2',
    'name': 'User',
    'parent_id': 0,
    'children': <Map<String, dynamic>>[
      <String, dynamic>{'id': 'm2a', 'name': 'User List', 'parent_id': 'm2'},
    ],
  },
  // 无 children 键的根叶子
  <String, dynamic>{'id': 'm3', 'name': 'Dashboard', 'parent_id': 0},
];

List<PermissionNode> _tree() => PermissionNode.parse(_wire);

PermissionNode _find(List<PermissionNode> roots, String id) {
  for (final root in roots) {
    for (final row in root.flatten()) {
      if (row.$2.id == id) return row.$2;
    }
  }
  throw StateError('测试数据里没有 $id');
}

void main() {
  group('解析容错', () {
    test('根 parent_id 是数字 0、叶子没有 children 键：都解析得出', () {
      final roots = _tree();
      expect(roots.map((n) => n.id).toList(), <String>['m1', 'm2', 'm3']);
      expect(_find(roots, 'm1').label, 'Game');
      expect(_find(roots, 'm1').hasChildren, isTrue);
      // 叶子没有 children 键 ⇒ children 是空表而不是 null
      expect(_find(roots, 'm1a').children, isEmpty);
      expect(_find(roots, 'm1a').hasChildren, isFalse);
      // children 只在非空时才有：根叶子 m3 与嵌套叶子同款
      expect(_find(roots, 'm3').hasChildren, isFalse);
      expect(_find(roots, 'm3').raw?['name'], 'Dashboard');
    });

    test('认不出的行跳过，不整页崩：非数组 / 非 Map / 缺 id', () {
      expect(PermissionNode.parse(null), isEmpty);
      expect(PermissionNode.parse('nope'), isEmpty);
      final roots = PermissionNode.parse(<dynamic>['str', 42, <String, dynamic>{'name': '没有 id'}, _wire.first]);
      expect(roots.map((n) => n.id).toList(), <String>['m1']);
      expect(roots.single.children.length, 2);
    });

    test('flatten 深度优先、depth 递增；ids 收全树', () {
      final flat = _find(_tree(), 'm1').flatten();
      expect(flat.map((r) => '${r.$1}:${r.$2.id}').toList(), <String>['0:m1', '1:m1a', '1:m1b']);
      expect(_find(_tree(), 'm1').ids, <String>{'m1', 'm1a', 'm1b'});
    });
  });

  group('级联勾选 toggleSubtree', () {
    test('勾父节点 = 整棵子树进集合（含没有 children 键的叶子）', () {
      final next = toggleSubtree(<String>{}, _find(_tree(), 'm1'), on: true);
      expect(next, <String>{'m1', 'm1a', 'm1b'});
    });

    test('取消父节点 = 整棵子树出集合，兄弟分支不受影响', () {
      final next = toggleSubtree(<String>{'m1', 'm1a', 'm1b', 'm2a'}, _find(_tree(), 'm1'), on: false);
      expect(next, <String>{'m2a'});
    });

    test('勾单个叶子只动它自己（祖先由 reconcile 负责）', () {
      final next = toggleSubtree(<String>{}, _find(_tree(), 'm1a'), on: true);
      expect(next, <String>{'m1a'});
    });
  });

  group('自下而上重算 reconcile', () {
    test('孩子全勾中 ⇒ 父级跟着变勾中', () {
      final next = reconcile(<String>{'m1a', 'm1b'}, _tree());
      expect(next.contains('m1'), isTrue);
    });

    test('只勾一个孩子 ⇒ 父级不勾（留给 isIndeterminate 判半选）', () {
      final next = reconcile(<String>{'m1a'}, _tree());
      expect(next.contains('m1'), isFalse);
      expect(isIndeterminate(next, _find(_tree(), 'm1')), isTrue);
    });

    test('从全勾中退掉一个孩子 ⇒ 父级也被撤掉', () {
      final next = reconcile(<String>{'m1', 'm1a', 'm1b'}, _tree()).difference(<String>{'m1b'});
      final rec = reconcile(next, _tree());
      expect(rec.contains('m1'), isFalse);
      expect(isIndeterminate(rec, _find(_tree(), 'm1')), isTrue);
    });

    test('叶子不参与重算：空集重算不会把整棵树点亮', () {
      // 「孩子全勾中」对叶子恒真 —— 少了 hasChildren 这个闸，一次 reconcile 就全树勾中
      expect(reconcile(<String>{}, _tree()), isEmpty);
    });
  });

  group('半选判定 isIndeterminate', () {
    test('自己勾中 ⇒ 不是半选（哪怕孩子一个没勾）', () {
      expect(isIndeterminate(<String>{'m1'}, _find(_tree(), 'm1')), isFalse);
    });

    test('自己没勾、有后代勾中 ⇒ 半选', () {
      expect(isIndeterminate(<String>{'m1b'}, _find(_tree(), 'm1')), isTrue);
      // 三层也只认「有后代」，中间层不勾不影响
      expect(isIndeterminate(<String>{'m1a'}, _find(_tree(), 'm1')), isTrue);
    });

    test('没人勾中 ⇒ 不是半选', () {
      expect(isIndeterminate(<String>{}, _find(_tree(), 'm1')), isFalse);
      expect(isIndeterminate(<String>{'m2a'}, _find(_tree(), 'm1')), isFalse);
    });
  });

  group('提交集 grantedIds：半选祖先要一起授出去', () {
    test('只勾一个子权限 ⇒ 授权里带上父菜单', () {
      expect(grantedIds(<String>{'m1a'}, _tree()), <String>{'m1', 'm1a'});
    });

    test('全勾中 ⇒ 就是原集合', () {
      expect(grantedIds(<String>{'m1', 'm1a', 'm1b'}, _tree()), <String>{'m1', 'm1a', 'm1b'});
    });

    test('空勾选 ⇒ 空授权（不退化成「全授」）', () {
      expect(grantedIds(<String>{}, _tree()), isEmpty);
    });
  });

  group('回填 checkedFromGranted', () {
    test('父菜单 + 一个子权限 ⇒ 勾选集只有那个子权限（父显示半选）', () {
      final checked = checkedFromGranted(<String>{'m1', 'm1a'}, _tree());
      expect(checked, <String>{'m1a'});
      expect(isIndeterminate(checked, _find(_tree(), 'm1')), isTrue);
      // 往返稳定：回填后再提交，集合回到原样
      expect(grantedIds(checked, _tree()), <String>{'m1', 'm1a'});
    });

    test('父菜单 + 全部子权限 ⇒ 父也算勾中', () {
      final checked = checkedFromGranted(<String>{'m1', 'm1a', 'm1b'}, _tree());
      expect(checked, <String>{'m1', 'm1a', 'm1b'});
      expect(isIndeterminate(checked, _find(_tree(), 'm1')), isFalse);
      expect(grantedIds(checked, _tree()), <String>{'m1', 'm1a', 'm1b'});
    });

    test('历史数据「只授父菜单、子权限一个没授」⇒ 按勾中它自己处理，不丢', () {
      final checked = checkedFromGranted(<String>{'m1'}, _tree());
      expect(checked, <String>{'m1'});
      expect(grantedIds(checked, _tree()), <String>{'m1'});
    });

    test('树里没有的历史 id 原样留在集合里（原样发回去 = 没动）', () {
      final checked = checkedFromGranted(<String>{'m1a', 'gone-1'}, _tree());
      expect(checked, <String>{'m1a', 'gone-1'});
      expect(grantedIds(checked, _tree()).contains('gone-1'), isTrue);
    });

    test('空授权 ⇒ 空勾选', () {
      expect(checkedFromGranted(<String>{}, _tree()), isEmpty);
    });
  });

  group('可见行 visibleRows', () {
    test('默认全展开：父在前、子紧随其后', () {
      final rows = visibleRows(_tree(), <String>{});
      expect(rows.map((r) => r.node.id).toList(), <String>['m1', 'm1a', 'm1b', 'm2', 'm2a', 'm3']);
      expect(rows.map((r) => r.depth).toList(), <int>[0, 1, 1, 0, 1, 0]);
      expect(rows.every((r) => r.expanded), isTrue);
    });

    test('收起的节点：自己还在，子树整棵不出现', () {
      final rows = visibleRows(_tree(), <String>{'m1'});
      expect(rows.map((r) => r.node.id).toList(), <String>['m1', 'm2', 'm2a', 'm3']);
      expect(rows.first.expanded, isFalse);
    });

    test('没有子节点的节点被「收起」也无差别（没有子树可藏）', () {
      final rows = visibleRows(_tree(), <String>{'m3'});
      expect(rows.map((r) => r.node.id).toList(), <String>['m1', 'm1a', 'm1b', 'm2', 'm2a', 'm3']);
      expect(rows.last.expanded, isFalse);
    });
  });
}
