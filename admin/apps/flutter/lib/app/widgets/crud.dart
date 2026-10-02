// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// 管理端写操作底座：字段描述驱动的通用表单弹框 + 行内动作（编辑/删除二次确认/启用·停用）。
// 每个模块只提供两样东西：一份 CrudField 描述表，四个薄控制器方法（create/update/remove/toggle，
// 失败不吞异常、让异常冒到弹框里）。表单框不再每个页面各写一份。
//
// 约定：CrudField.label / CrudOption.label / CrudField.hint 一律传 **i18n key**（不是成品文案）——
// 弹框在 build 期解析，语言切换后仍跟着变；写死成品的写法会把首次取值时的语言冻结住。
import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../i18n/translations.dart';
import '../services/api_service.dart';
import '../services/image_upload.dart';
import 'permission_tree.dart';

// 上传动作的类型是 showCrudForm 的形参类型 ⇒ 从本库转出（调用方 import crud.dart 就够）
export '../services/image_upload.dart' show CrudImageUpload;

// 表单弹框本体切到 crud_form.dart（本文件此前越过 500 行）。`part` 是**库级**指令，
// 必须排在所有声明之前 —— 放到文件尾部会让整个库编译不过（directive_after_declaration）。
part 'crud_form.dart';

/// 字段控件类型。数量刻意压到够用为止：select 覆盖所有值域固定的枚举，
/// multiselect 用于「值是 N 个 id 的集合」的关联字段（如角色的 permission_ids），
/// tree = 同一类关联字段但候选本身是**树**（勾选带父子联动/半选，见 permission_tree.dart），
/// image = 文本框（存量手输 URL 照旧可编辑）+「上传」按钮 + 缩略图。
enum CrudFieldType { text, multiline, number, select, toggle, multiselect, tree, image }

/// select / multiselect 的一个可选项：value 是提交给后端的字符串，label 是 i18n key
/// （查不到 key 时原样显示——树形字段用它传「缩进 + 名称」的成品文案）。
class CrudOption {
  final String value;
  final String label;

  const CrudOption(this.value, this.label);
}

/// 单个表单字段的描述。值域以控制器 validator 为准，别照抄前端旧代码。
class CrudField {
  final String name;
  final String label;
  final CrudFieldType type;

  /// 本地必填校验（省一次 422 往返）；后端 `sometimes|required` 的字段在编辑态仍会提交，
  /// 所以非空值始终是要求。
  final bool required;
  final List<CrudOption> options;

  /// 占位/提示文案的 i18n key（如 slug 的字符集、target_lang 的「空=全语言」）。
  final String? hint;

  /// 编辑态是否可改。false = 编辑时置灰且**不提交**该字段（用于 update validator 不收的字段，
  /// 如 game 的 slug：后端 PUT 根本没有这条规则，提交了也只是静默丢弃）。
  final bool editableOnEdit;
  final int maxLines;

  /// type == tree 时的候选树（值域不是一维列表，故不能走 options）。
  final List<PermissionNode> tree;

  const CrudField(
    this.name,
    this.label, {
    this.type = CrudFieldType.text,
    this.required = false,
    this.options = const <CrudOption>[],
    this.hint,
    this.editableOnEdit = true,
    this.maxLines = 4,
    this.tree = const <PermissionNode>[],
  });
}

/// 服务端失败信息的唯一取法：ApiException 带后端 message，其余按原样展示。
String apiErrorMessage(Object error) => error is ApiException ? error.message : error.toString();

/// 枚举列的显示：`prefix.value` 有词条就用译文，没有就**原样显示**（后端新增枚举值时不至于变空白）。
String crudEnum(String prefix, Object? value) {
  final raw = value?.toString() ?? '';
  final key = '$prefix.$raw';
  final text = AppTranslations.get(key, Get.locale?.languageCode ?? 'en');
  return text == key ? raw : text;
}

/// 带 `{var}` 占位替换的取词（AppTranslations.t 不做插值）。
String crudText(String key, [Map<String, String> vars = const <String, String>{}]) {
  var text = AppTranslations.get(key, Get.locale?.languageCode ?? 'en');
  vars.forEach((k, v) => text = text.replaceAll('{$k}', v));
  return text;
}

/// 空列表占位：吉祥物「小骰」+ 一句文案。
///
/// 24 个列表页此前各写一份（连 `width: 120`、`SizedBox(height: 12)` 都逐字相同），
/// 只有文案不同 —— 换图/改宽度要改 24 处，漏一处就是一处不一致。
class CrudEmptyState extends StatelessWidget {
  const CrudEmptyState({super.key, this.text = 'app.no_data'});

  /// i18n key；查不到时原样显示（风控几个页签传的就是成品中文，历史遗留）。
  final String text;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Image.asset('assets/mascot.png', width: 120),
          const SizedBox(height: 12),
          Text(crudText(text)),
        ],
      ),
    );
  }
}

/// 列表页顶部：标题 + 「+ 新建」。
class CrudHeader extends StatelessWidget {
  const CrudHeader({super.key, required this.title, required this.onCreate});

  final String title;
  final VoidCallback onCreate;

  @override
  Widget build(BuildContext context) {
    return Row(children: [
      Text(title, style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
      const Spacer(),
      ElevatedButton.icon(
        onPressed: onCreate,
        icon: const Icon(Icons.add),
        label: Text(crudText('app.create')),
      ),
    ]);
  }
}

/// 每行尾部的「操作」列：状态开关（有 status 才渲染）/ 刷新缓存 / 编辑 / 删除。
class CrudRowActions extends StatelessWidget {
  const CrudRowActions({
    super.key,
    this.status,
    this.onToggle,
    this.onRefresh,
    this.onEdit,
    this.onDelete,
  });

  /// 当前状态（1 启用/上架，0 停用/下架）；null = 该实体无状态，不渲染开关。
  final int? status;

  /// 就地切换，next 为目标状态（0/1）。失败时提示服务端 message，列表维持原值（不做乐观切换）。
  final Future<void> Function(int next)? onToggle;

  /// 需要重算服务端缓存的行才给（排行榜的 refresh 端点）。
  final VoidCallback? onRefresh;
  final VoidCallback? onEdit;
  final VoidCallback? onDelete;

  @override
  Widget build(BuildContext context) {
    return Row(mainAxisSize: MainAxisSize.min, children: [
      if (status != null && onToggle != null)
        Switch(value: status == 1, onChanged: (on) => _toggle(on ? 1 : 0)),
      if (onRefresh != null)
        IconButton(icon: const Icon(Icons.refresh, size: 18), onPressed: onRefresh),
      if (onEdit != null)
        IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: onEdit),
      if (onDelete != null)
        IconButton(icon: const Icon(Icons.delete, size: 18, color: Colors.red), onPressed: onDelete),
    ]);
  }

  Future<void> _toggle(int next) async {
    try {
      await onToggle!(next);
    } catch (e) {
      Get.snackbar(crudText('app.error'), apiErrorMessage(e));
    }
  }
}

/// 分页条：本树所有分页列表（`data.list` 与 `data.items` 两种形状）共用的那一根。
/// 只做翻页动作，不持有页码——页码是各控制器自己的状态，改完由调用方重新拉列表
/// （取数一律走 ApiService.list()，它把 page_size 扇成 limit/size/per_page 三个别名）。
class CrudPager extends StatelessWidget {
  const CrudPager({super.key, required this.page, required this.total, required this.size, required this.onPage});

  /// 当前页（1 起）
  final int page;
  final int total;
  final int size;
  final void Function(int page) onPage;

  @override
  Widget build(BuildContext context) {
    final pages = total <= 0 ? 1 : ((total + size - 1) ~/ size);
    return Row(mainAxisSize: MainAxisSize.min, children: [
      IconButton(
        icon: const Icon(Icons.chevron_left),
        onPressed: page > 1 ? () => onPage(page - 1) : null,
      ),
      Text(crudText('app.pager', {'page': '$page', 'pages': '$pages', 'total': '$total'})),
      IconButton(
        icon: const Icon(Icons.chevron_right),
        onPressed: page < pages ? () => onPage(page + 1) : null,
      ),
    ]);
  }
}

/// 破坏性动作的二次确认（删除以外的：工单关闭等）。返回是否真的执行了（取消或失败都返回 false）。
///
/// `title` / `message` / `confirmLabel` 一律传 i18n key（confirmLabel 传成品文案或 null 取默认）；
/// `message` 里用 `{name}` 占位对象标识，由 `vars` 填 —— 确认文案必须能看清是对谁下手。
///
/// `onPassword` 非空 = 服务端要求密码二次确认（RoleController::destroy 等）：
/// 弹框里多一个密码输入框，确认时先把输入值回传给它，再调 onConfirm。
Future<bool> confirmCrudAction(
  BuildContext context, {
  required String title,
  required String message,
  required Future<void> Function() onConfirm,
  String? confirmLabel,
  void Function(String password)? onPassword,
}) async {
  // 不建 controller：TextField 无 controller 时自管输入态，输入值经 onChanged 回传。
  // 弹框关闭动画期间 TextField 仍会重建，此处持有 controller 会在 dispose 后被用到。
  var password = '';
  final confirmed = await showDialog<bool>(
    context: context,
    builder: (ctx) => AlertDialog(
      title: Text(title),
      content: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(message),
        if (onPassword != null)
          Padding(
            padding: const EdgeInsets.only(top: 12),
            child: TextField(
              obscureText: true,
              autofocus: true,
              onChanged: (v) => password = v,
              decoration: InputDecoration(
                labelText: crudText('app.delete_password'),
                hintText: crudText('app.delete_password_hint'),
              ),
            ),
          ),
      ]),
      actions: [
        TextButton(onPressed: () => Navigator.pop(ctx, false), child: Text(crudText('app.cancel'))),
        ElevatedButton(
          onPressed: () => Navigator.pop(ctx, true),
          style: ElevatedButton.styleFrom(backgroundColor: Colors.red, foregroundColor: Colors.white),
          child: Text(confirmLabel ?? crudText('app.confirm')),
        ),
      ],
    ),
  );
  if (confirmed != true) return false;

  try {
    onPassword?.call(password);
    await onConfirm();
    return true;
  } catch (e) {
    Get.snackbar(crudText('app.error'), apiErrorMessage(e));
    return false;
  }
}

/// 二次确认删除。`what` 是对象标识（名称/标题），确认文案里必须带上它。
/// 返回是否真的删掉了（取消或失败都返回 false）。删除专用文案，其余破坏性动作用 confirmCrudAction。
Future<bool> confirmCrudDelete(
  BuildContext context, {
  required String what,
  required Future<void> Function() onConfirm,
  void Function(String password)? onPassword,
}) =>
    confirmCrudAction(
      context,
      title: '${crudText('app.confirm')} ${crudText('app.delete')}',
      message: crudText('app.delete_confirm_target', {'name': what}),
      confirmLabel: crudText('app.delete'),
      onPassword: onPassword,
      onConfirm: onConfirm,
    );

/// 通用表单弹框。返回是否提交成功（取消/失败都返回 false）。
///
/// `initial` 非空 = 编辑态：预填并只提交 editableOnEdit 的字段（后端 update 是局部更新）。
/// `fullEdit` = 编辑态也**整份提交**所有字段：后端 update 复用 create 的 fill()、每次都重读
/// name/type/action 这类必填键（风控规则就是这种「整单替换」语义）时，「只发改动字段」会被 422。
/// `onSubmit` 抛异常 = 失败：服务端 message 显示在框内、**不关框**，用户可改后重试。
/// `imageUpload` = image 字段点「上传」时执行的动作（默认走真实的选图 + 直传）；
/// 用例注入假实现即可离线跑通「上传中 → 写回 URL → 提交」的全流程。
Future<bool> showCrudForm(
  BuildContext context, {
  required String title,
  required List<CrudField> fields,
  Map<String, dynamic>? initial,
  required Future<void> Function(Map<String, dynamic> data) onSubmit,
  bool fullEdit = false,
  CrudImageUpload? imageUpload,
}) async {
  final result = await showDialog<bool>(
    context: context,
    builder: (_) => _CrudFormDialog(
      title: title,
      fields: fields,
      initial: initial,
      onSubmit: onSubmit,
      fullEdit: fullEdit,
      imageUpload: imageUpload ?? pickAndUploadImage,
    ),
  );
  return result ?? false;
}
