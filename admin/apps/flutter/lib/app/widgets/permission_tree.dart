/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

// 权限树底座：节点模型 + 勾选/半选/授权的纯逻辑 + 两个渲染件（展示树 / 勾选树）。
//
// 纯逻辑一律是顶层函数、不碰 widget（test/permission_tree_test.dart 直接跑）：
// 勾选树的坑全在级联与半选上，那些必须先能离线验，再接界面。
import 'package:flutter/material.dart';

/// 权限树节点。
///
/// 形状来自 PermissionController::buildTree（GET /admin/v1/permission）：
/// - `id` / `parent_id` 是 hashid，但**根节点的 `parent_id` 是数字 0**（只对 >0 的编码）；
///   层级由 `children` 表达，`parent_id` 只在下拉里用，不参与建树。
/// - `children` **只在非空时才有这个键**（叶子没有该键，不是 `[]`）⇒ 解析必须容错。
class PermissionNode {
  final String id;
  final String label;
  final List<PermissionNode> children;

  /// 原始行：页面上要显示/回填 slug、type、path、sort（模型只留树需要的三个字段）。
  final Map<String, dynamic>? raw;

  const PermissionNode({required this.id, required this.label, this.children = const <PermissionNode>[], this.raw});

  bool get hasChildren => children.isNotEmpty;

  /// 解析接口的 `data`（裸数组，元素是树节点）。认不出的行（不是 Map / 没有 id）直接跳过 ——
  /// 少显示一个节点，好过整页崩在解析上。
  static List<PermissionNode> parse(dynamic data) {
    if (data is! List) return const <PermissionNode>[];
    return <PermissionNode>[
      for (final raw in data)
        if (_parseOne(raw) case final PermissionNode node) node,
    ];
  }

  static PermissionNode? _parseOne(dynamic raw) {
    if (raw is! Map) return null;
    final id = raw['id']?.toString() ?? '';
    if (id.isEmpty) return null;
    return PermissionNode(
      id: id,
      label: raw['name']?.toString() ?? '',
      children: raw['children'] is List ? parse(raw['children']) : const <PermissionNode>[],
      raw: Map<String, dynamic>.from(raw),
    );
  }

  /// 深度优先展平（父在前、子紧随其后），depth 供缩进用。
  List<(int, PermissionNode)> flatten([int depth = 0]) => <(int, PermissionNode)>[
        (depth, this),
        for (final child in children) ...child.flatten(depth + 1),
      ];

  /// 树上所有节点的 id（授权集里「不在树上」的历史值靠它认出来）。
  Set<String> get ids => <String>{id, for (final child in children) ...child.ids};
}

/// 当前可见的一行：`(node, depth, expanded)`。`expanded` 只对**有子节点**的节点有意义。
typedef PermissionTreeRow = ({PermissionNode node, int depth, bool expanded});

/// 按收起集算出当前要渲染的行（深度优先；收起的节点整棵子树都不出现）。
List<PermissionTreeRow> visibleRows(List<PermissionNode> nodes, Set<String> collapsed) {
  final rows = <PermissionTreeRow>[];
  void walk(List<PermissionNode> list, int depth) {
    for (final node in list) {
      final expanded = !collapsed.contains(node.id);
      rows.add((node: node, depth: depth, expanded: expanded));
      if (node.hasChildren && expanded) walk(node.children, depth + 1);
    }
  }

  walk(nodes, 0);
  return rows;
}

// ── 勾选逻辑 ────────────────────────────────────────────────────────────────
//
// 勾选集的值语义 = 「**整棵子树都被勾中**」的节点 id；三态由它推导：
//   勾中 = id 在集合里 ／ 半选 = 不在集合里但有后代在（isIndeterminate）／ 未勾 = 都没有。
//
// 提交给后端的是 grantedIds()，**不是**原始勾选集：半选的祖先（典型是菜单类权限）
// 必须一起授出去，否则会出现「有子权限但看不到菜单」。

/// 勾中/取消一个节点**及其全部后代**。
Set<String> toggleSubtree(Set<String> checked, PermissionNode node, {required bool on}) {
  final next = Set<String>.of(checked);
  void walk(PermissionNode n) {
    on ? next.add(n.id) : next.remove(n.id);
    for (final child in n.children) {
      walk(child);
    }
  }

  walk(node);
  return next;
}

/// 自下而上重算父级：孩子全勾中 ⇒ 父级也算勾中；否则父级不勾（有勾中的后代就是半选）。
///
/// 只对**有子节点**的节点生效 —— 叶子不参与，否则「所有孩子都勾中」对叶子恒真，
/// 一次重算就把整棵树点亮了。
Set<String> reconcile(Set<String> checked, List<PermissionNode> roots) {
  final next = Set<String>.of(checked);
  bool walk(PermissionNode n) {
    if (!n.hasChildren) return next.contains(n.id);
    var all = true;
    for (final child in n.children) {
      if (!walk(child)) all = false;
    }
    all ? next.add(n.id) : next.remove(n.id);
    return all;
  }

  for (final root in roots) {
    walk(root);
  }
  return next;
}

/// 提交给后端的授权集 = 勾中的节点 ∪ 半选的祖先。
///
/// 半选祖先必须带上：菜单类权限（type=1）本身不参与 method.path 鉴权，但前端要靠它显示入口
/// —— 只授子权限而不授父菜单，拿到该角色的运营会「有权限但看不到菜单」。
Set<String> grantedIds(Set<String> checked, List<PermissionNode> roots) {
  final granted = Set<String>.of(checked);
  bool walk(PermissionNode n) {
    var any = checked.contains(n.id);
    for (final child in n.children) {
      if (walk(child)) any = true;
    }
    if (any) granted.add(n.id);
    return any;
  }

  for (final root in roots) {
    walk(root);
  }
  return granted;
}

/// 半选：自己没勾中，但有后代被勾中。
bool isIndeterminate(Set<String> checked, PermissionNode node) {
  if (checked.contains(node.id)) return false;
  bool hasChecked(PermissionNode n) => checked.contains(n.id) || n.children.any(hasChecked);
  return node.children.any(hasChecked);
}

/// 从后端回填的授权集反推勾选集（编辑态打开表单时用）。
///
/// 规则：授权集里的节点算勾中，除了「有后代被授」的那种父级 —— 那种父级要等**孩子全被授**
/// 才算勾中（否则显示半选）。一个后代都没被授的授权节点按「勾中它自己」处理：
/// 历史数据里可能只授了一个菜单而没有它的子权限，回填时不能把它丢掉。
///
/// 树里没有的 id（已删权限的历史授权）留在集合里原样提交：原样发回去等于没动，
/// 比静默丢掉一条关联安全（与 crud.dart 的 multiselect 同款约定）。
Set<String> checkedFromGranted(Set<String> granted, List<PermissionNode> roots) {
  final checked = <String>{};
  final known = <String>{for (final root in roots) ...root.ids};
  checked.addAll(granted.difference(known));

  bool walk(PermissionNode n) {
    var subtreeHasChecked = false;
    var allChildrenChecked = true;
    for (final child in n.children) {
      if (walk(child)) subtreeHasChecked = true;
      if (!checked.contains(child.id)) allChildrenChecked = false;
    }
    if (!granted.contains(n.id)) return subtreeHasChecked;
    // 有授权：孩子全勾中、或它底下压根没有勾中的东西（历史数据里的「只授父菜单」）⇒ 勾中它自己
    if (!n.hasChildren || allChildrenChecked || !subtreeHasChecked) {
      checked.add(n.id);
      return true;
    }
    return subtreeHasChecked;
  }

  for (final root in roots) {
    walk(root);
  }
  return checked;
}

// ── 渲染 ────────────────────────────────────────────────────────────────────

/// 展开/收起箭头（叶子留同宽占位，同层对齐）。两个使用方（权限页与角色表单）共用。
Widget treeExpandIcon(PermissionTreeRow row, VoidCallback onToggle) => row.node.hasChildren
    ? IconButton(
        icon: Icon(row.expanded ? Icons.expand_more : Icons.chevron_right, size: 20),
        onPressed: onToggle,
        padding: EdgeInsets.zero,
        constraints: const BoxConstraints(minWidth: 40, minHeight: 40),
      )
    : const SizedBox(width: 40);

/// 勾选式权限树（角色表单的 permission_ids 字段）。**受控组件**：勾选集由调用方持有，
/// 本组件只负责渲染与事件（值怎么提交由调用方决定，见 crud.dart 的 tree 字段）。
class PermissionTreePicker extends StatefulWidget {
  const PermissionTreePicker({super.key, required this.nodes, required this.checked, required this.onChanged, this.enabled = true});

  final List<PermissionNode> nodes;

  /// 「整棵子树都勾中」的节点 id 集合（checkedFromGranted 的产物）。
  final Set<String> checked;
  final ValueChanged<Set<String>> onChanged;
  final bool enabled;

  @override
  State<PermissionTreePicker> createState() => _PermissionTreePickerState();
}

class _PermissionTreePickerState extends State<PermissionTreePicker> {
  /// 收起的分支；默认全展开 —— 树是用来选权限的，收着看不见已授权状态。
  final _collapsed = <String>{};

  @override
  Widget build(BuildContext context) {
    // ponytail: 整棵树一次性 build（权限树量级 ~200 节点）。真到上万节点再换 sliver 懒构建。
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        for (final row in visibleRows(widget.nodes, _collapsed))
          Padding(
            padding: EdgeInsets.only(left: row.depth * 20.0),
            child: Row(children: [
              treeExpandIcon(row, () => setState(() => _collapsed.contains(row.node.id) ? _collapsed.remove(row.node.id) : _collapsed.add(row.node.id))),
              Expanded(
                child: CheckboxListTile(
                  dense: true,
                  contentPadding: EdgeInsets.zero,
                  controlAffinity: ListTileControlAffinity.leading,
                  // 半选 = value 为 null（tristate 下才画得出「横杠」这一态）
                  value: isIndeterminate(widget.checked, row.node) ? null : widget.checked.contains(row.node.id),
                  tristate: true,
                  // 不看回调带回来的值，直接按自己这边的状态决定方向：tristate 的 Checkbox 是按
                  // false→true→null→false 循环给值的，跟着它走会让半选的父级第一下反而变「清空」。
                  // 「已整棵子树勾中 ⇒ 取消整棵；否则 ⇒ 勾中整棵」才是树上该有的手感。
                  onChanged: widget.enabled
                      ? (_) => widget.onChanged(reconcile(
                            toggleSubtree(widget.checked, row.node, on: !widget.checked.contains(row.node.id)),
                            widget.nodes,
                          ))
                      : null,
                  title: Text(row.node.label),
                ),
              ),
            ]),
          ),
      ],
    );
  }
}
