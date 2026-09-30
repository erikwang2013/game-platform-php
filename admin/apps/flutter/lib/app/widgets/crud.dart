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

// 上传动作的类型是 showCrudForm 的形参类型 ⇒ 从本库转出（调用方 import crud.dart 就够）
export '../services/image_upload.dart' show CrudImageUpload;

/// 字段控件类型。数量刻意压到够用为止：select 覆盖所有值域固定的枚举，
/// multiselect 用于「值是 N 个 id 的集合」的关联字段（如角色的 permission_ids），
/// image = 文本框（存量手输 URL 照旧可编辑）+「上传」按钮 + 缩略图。
enum CrudFieldType { text, multiline, number, select, toggle, multiselect, image }

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

  const CrudField(
    this.name,
    this.label, {
    this.type = CrudFieldType.text,
    this.required = false,
    this.options = const <CrudOption>[],
    this.hint,
    this.editableOnEdit = true,
    this.maxLines = 4,
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

/// 分页条：`{total, items}` 形状的列表用（风控七个列表的 `size` 都是 20、上限 100）。
/// 只做翻页动作，不持有页码——页码是各控制器自己的状态，改完由调用方重新拉列表。
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

class _CrudFormDialog extends StatefulWidget {
  const _CrudFormDialog({
    required this.title,
    required this.fields,
    required this.onSubmit,
    required this.imageUpload,
    this.initial,
    this.fullEdit = false,
  });

  final String title;
  final List<CrudField> fields;
  final Map<String, dynamic>? initial;
  final Future<void> Function(Map<String, dynamic> data) onSubmit;
  final bool fullEdit;
  final CrudImageUpload imageUpload;

  @override
  State<_CrudFormDialog> createState() => _CrudFormDialogState();
}

class _CrudFormDialogState extends State<_CrudFormDialog> {
  final _texts = <String, TextEditingController>{};
  final _selects = <String, String?>{};
  final _switches = <String, bool>{};
  final _multi = <String, Set<String>>{};
  /// 编辑态进入时的勾选集，用于「没动过就不发」（见 _payload 的 multiselect 分支）。
  final _initialMulti = <String, Set<String>>{};
  /// 正在上传的 image 字段（字段名）——上传期间该字段的按钮置灰并转圈。
  final _uploading = <String>{};
  String? _error;
  bool _submitting = false;

  bool get _isEdit => widget.initial != null;

  @override
  void initState() {
    super.initState();
    for (final field in widget.fields) {
      final raw = widget.initial?[field.name];
      switch (field.type) {
        case CrudFieldType.select:
          // 原样保留（可能不在值域内：历史值/值域收紧过）——_buildField 会为它补一条同值项，
          // 免得 Dropdown 断言崩溃，也免得把行里的值悄悄改成第一项
          final current = raw?.toString();
          _selects[field.name] = current ?? (field.options.isEmpty ? null : field.options.first.value);
        case CrudFieldType.toggle:
          _switches[field.name] = raw == null || raw == 1 || raw == true || raw == '1';
        case CrudFieldType.multiselect:
          final checked = <String>{
            for (final value in raw is List ? raw : const <dynamic>[]) value.toString(),
          };
          _multi[field.name] = checked;
          // 编辑态留一份原值：整表替换的字段「没动过就不发」
          if (_isEdit) _initialMulti[field.name] = Set<String>.of(checked);
        case CrudFieldType.text:
        case CrudFieldType.multiline:
        case CrudFieldType.number:
        case CrudFieldType.image:
          _texts[field.name] = TextEditingController(text: raw?.toString() ?? '');
      }
    }
  }

  @override
  void dispose() {
    for (final controller in _texts.values) {
      controller.dispose();
    }
    super.dispose();
  }

  Map<String, dynamic> _payload() {
    final data = <String, dynamic>{};
    for (final field in widget.fields) {
      if (_isEdit && !field.editableOnEdit && !widget.fullEdit) continue;
      switch (field.type) {
        case CrudFieldType.toggle:
          data[field.name] = _switches[field.name] == true ? 1 : 0;
        case CrudFieldType.number:
          data[field.name] = int.tryParse(_texts[field.name]!.text.trim()) ?? 0;
        case CrudFieldType.select:
          // 值域外的历史值不提交：用户没动过它就不该发出去（update 的 in: 规则会 422），
          // 不发即保持库中原值。用户真改了就会选中值域内的项，那时才提交。
          final selected = _selects[field.name];
          if (selected != null && field.options.any((o) => o.value == selected)) {
            data[field.name] = selected;
          }
        case CrudFieldType.multiselect:
          // 勾选集整体提交（后端 sync 是「整体替换」语义）。两条语义都必须保住：
          // - 编辑态**没动过就不发**：发了就是按当前值把授权整表重写一遍（别人刚改的会被顶掉）；
          // - 动了就发**全量**，包括「全部取消」时的 []（sync([]) = 解绑全部；不发则清空不生效）。
          // 新建态没有原值可比 ⇒ 一律发（空数组 = 先建一个无权限的角色）。
          final checked = _multi[field.name]!;
          final before = _initialMulti[field.name];
          if (before != null && before.length == checked.length && before.containsAll(checked)) break;
          // 值域外的历史值也照发：原样发回去等于不动，比静默丢掉一条关联安全。
          data[field.name] = checked.toList();
        case CrudFieldType.text:
        case CrudFieldType.multiline:
        case CrudFieldType.image:
          // image 与 text 同源（同一个 TextEditingController）：上传回来的绝对 URL 就是这样进
          // payload 的，手输的 URL 也一样——编辑态「没上传就是原值原样发回」，与 text 字段同语义。
          data[field.name] = _texts[field.name]!.text;
      }
    }
    return data;
  }

  String? _missingRequired() {
    for (final field in widget.fields) {
      if (!field.required || field.type == CrudFieldType.toggle) continue;
      // 与 _payload 同一条件：提交什么就校验什么（fullEdit 下整份都发，不该漏掉必填）
      if (_isEdit && !field.editableOnEdit && !widget.fullEdit) continue;
      // 多选的值不在 _texts/_selects 里：按勾选集合判空，否则必填的多选会永远报「必填」
      if (field.type == CrudFieldType.multiselect) {
        if (_multi[field.name]!.isEmpty) {
          return crudText('app.field_required', {'name': crudText(field.label)});
        }
        continue;
      }
      final value = _texts[field.name]?.text.trim() ?? _selects[field.name] ?? '';
      if (value.isEmpty) {
        return crudText('app.field_required', {'name': crudText(field.label)});
      }
    }
    return null;
  }

  Future<void> _submit() async {
    final missing = _missingRequired();
    if (missing != null) {
      setState(() => _error = missing);
      return;
    }

    setState(() {
      _submitting = true;
      _error = null;
    });

    try {
      await widget.onSubmit(_payload());
      if (!mounted) return;
      Navigator.pop(context, true);
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _submitting = false;
        _error = apiErrorMessage(e);
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: Text(widget.title, style: const TextStyle(fontWeight: FontWeight.bold)),
      content: SizedBox(
        width: 450,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              for (final field in widget.fields) _buildField(field),
              if (_error != null)
                Padding(
                  padding: const EdgeInsets.only(top: 12),
                  child: Text(
                    _error!,
                    style: TextStyle(color: Theme.of(context).colorScheme.error),
                  ),
                ),
            ],
          ),
        ),
      ),
      actions: [
        TextButton(
          onPressed: _submitting ? null : () => Navigator.pop(context, false),
          child: Text(crudText('app.cancel')),
        ),
        ElevatedButton(
          onPressed: _submitting ? null : _submit,
          child: _submitting
              ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2))
              : Text(crudText('app.save')),
        ),
      ],
    );
  }

  Widget _buildField(CrudField field) {
    final enabled = (!_isEdit || field.editableOnEdit) && !_submitting;
    final label = crudText(field.label);
    final hint = field.hint == null ? null : crudText(field.hint!);

    switch (field.type) {
      case CrudFieldType.select:
        final current = _selects[field.name];
        // 行里有、但不在值域内的历史值：置顶补一条同值项原样显示
        // （label 直接用原值——crudText 查不到 key 会回退成 key 本身）
        final options = <CrudOption>[
          if (current != null && !field.options.any((o) => o.value == current))
            CrudOption(current, current),
          ...field.options,
        ];
        return Padding(
          padding: const EdgeInsets.only(bottom: 12),
          child: DropdownButtonFormField<String>(
            initialValue: current,
            // isExpanded 不是装饰：DropdownButton 把 hintText 当作**一个菜单项**拼进 IndexedStack，
            // 按钮宽度 = 最宽项（含 hint）⇒ hint 稍长就撑破弹框（黄黑条 + 测试里直接判失败）。
            // 它同时也是 min width 的来源，删 hint 只是把地雷留给了下一个长文案。
            isExpanded: true,
            decoration: InputDecoration(labelText: label, hintText: hint),
            items: [
              for (final option in options)
                DropdownMenuItem(value: option.value, child: Text(crudText(option.label))),
            ],
            onChanged: enabled ? (value) => setState(() => _selects[field.name] = value) : null,
          ),
        );
      case CrudFieldType.multiselect:
        final checked = _multi[field.name]!;
        // 行里有、但值域里没有的历史值：照样列出来并保持勾选（不静默丢弃，语义与 select 一致）
        final options = <CrudOption>[
          for (final value in checked)
            if (!field.options.any((o) => o.value == value)) CrudOption(value, value),
          ...field.options,
        ];
        return Padding(
          padding: const EdgeInsets.only(bottom: 12),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(label, style: Theme.of(context).textTheme.bodyMedium),
            if (hint != null)
              Text(hint, style: Theme.of(context).textTheme.bodySmall, textAlign: TextAlign.start),
            // 树形值域可以很长：限高 + 自己滚动，别把弹框撑爆
            ConstrainedBox(
              constraints: const BoxConstraints(maxHeight: 200),
              child: SingleChildScrollView(
                child: Column(mainAxisSize: MainAxisSize.min, children: [
                  for (final option in options)
                    CheckboxListTile(
                      dense: true,
                      contentPadding: EdgeInsets.zero,
                      controlAffinity: ListTileControlAffinity.leading,
                      value: checked.contains(option.value),
                      onChanged: enabled
                          ? (on) {
                              setState(() {
                                if (on == true) {
                                  checked.add(option.value);
                                } else {
                                  checked.remove(option.value);
                                }
                              });
                            }
                          : null,
                      title: Text(crudText(option.label)),
                    ),
                ]),
              ),
            ),
          ]),
        );
      case CrudFieldType.toggle:
        return SwitchListTile(
          title: Text(label),
          value: _switches[field.name] == true,
          onChanged: enabled ? (value) => setState(() => _switches[field.name] = value) : null,
          contentPadding: EdgeInsets.zero,
        );
      case CrudFieldType.multiline:
        return Padding(
          padding: const EdgeInsets.only(bottom: 12),
          child: TextField(
            controller: _texts[field.name],
            enabled: enabled,
            maxLines: field.maxLines,
            decoration: InputDecoration(labelText: label, hintText: hint),
          ),
        );
      case CrudFieldType.number:
        return Padding(
          padding: const EdgeInsets.only(bottom: 12),
          child: TextField(
            controller: _texts[field.name],
            enabled: enabled,
            keyboardType: const TextInputType.numberWithOptions(decimal: false),
            decoration: InputDecoration(labelText: label, hintText: hint),
          ),
        );
      case CrudFieldType.text:
        return Padding(
          padding: const EdgeInsets.only(bottom: 12),
          child: TextField(
            controller: _texts[field.name],
            enabled: enabled,
            decoration: InputDecoration(labelText: label, hintText: hint),
          ),
        );
      case CrudFieldType.image:
        final uploading = _uploading.contains(field.name);
        final url = _texts[field.name]!.text.trim();
        return Padding(
          padding: const EdgeInsets.only(bottom: 12),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            // 文本框原样保留：存量行里手输的 URL 照旧能改，上传只是把结果写进同一个框
            // （上传期间一并禁用：否则用户正在改的字会被上传结果顶掉）
            TextField(
              controller: _texts[field.name],
              enabled: enabled && !uploading,
              decoration: InputDecoration(labelText: label, hintText: hint),
            ),
            const SizedBox(height: 8),
            Row(children: [
              ElevatedButton.icon(
                onPressed: (enabled && !uploading) ? () => _uploadImage(field) : null,
                icon: uploading
                    ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2))
                    : const Icon(Icons.upload_file, size: 18),
                label: Text(crudText('app.upload')),
              ),
              if (url.isNotEmpty) ...[
                const SizedBox(width: 12),
                // 展示路由公开（不带鉴权）⇒ 直接 Image.network。
                // 坏 URL 只退化成占位图标：缩略图是辅助，不该拦提交。
                ClipRRect(
                  borderRadius: BorderRadius.circular(4),
                  child: Image.network(
                    url,
                    width: 48,
                    height: 48,
                    fit: BoxFit.cover,
                    errorBuilder: (_, _, _) => const Icon(Icons.broken_image_outlined, size: 32),
                  ),
                ),
              ],
            ]),
          ]),
        );
    }
  }

  /// 点「上传」：选图 + 直传（动作由用例可注入），成功后把返回的绝对 URL 写回文本框。
  /// 失败走框内错误位（与提交失败同一处），文案是服务端 `error` 的原文。
  Future<void> _uploadImage(CrudField field) async {
    setState(() {
      _uploading.add(field.name);
      _error = null;
    });

    try {
      final url = await widget.imageUpload();
      if (!mounted) return;
      if (url == null) return; // 用户取消：现值不动
      setState(() => _texts[field.name]!.text = url);
    } catch (e) {
      if (mounted) setState(() => _error = apiErrorMessage(e));
    } finally {
      if (mounted) setState(() => _uploading.remove(field.name));
    }
  }
}
