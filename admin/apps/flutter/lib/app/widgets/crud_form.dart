// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// 通用表单弹框的对话框本体。与 crud.dart 是**同一个 library**（part of）——
// 跨文件复用私有类只能这样，切分纯粹是为了两个文件都别越过 500 行。
// 对外的入口仍是 crud.dart 的 showCrudForm()，调用方不需要知道这个文件。
part of 'crud.dart';

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
        case CrudFieldType.tree:
          final granted = <String>{
            for (final value in raw is List ? raw : const <dynamic>[]) value.toString(),
          };
          // 行里回填的是**授权集**（含半选的祖先），反推成勾选集，否则父级会错误地显示成全勾
          final checked = checkedFromGranted(granted, field.tree);
          _multi[field.name] = checked;
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
        case CrudFieldType.tree:
          // 与 multiselect 同语义（整表替换、没动过就不发），只是提交的是**授权集**：
          // 勾中 ∪ 半选祖先 —— 只授子权限不授父菜单，角色会「有权限但看不到菜单」。
          final checked = _multi[field.name]!;
          final before = _initialMulti[field.name];
          if (before != null && before.length == checked.length && before.containsAll(checked)) break;
          if (field.tree.isEmpty) break; // 候选树没取到就不该有这个字段（调用方的责任），兜一层防空写
          data[field.name] = grantedIds(checked, field.tree).toList();
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
      // 多选/树的值不在 _texts/_selects 里：按勾选集合判空，否则必填的多选会永远报「必填」
      if (field.type == CrudFieldType.multiselect || field.type == CrudFieldType.tree) {
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
      case CrudFieldType.tree:
        return Padding(
          padding: const EdgeInsets.only(bottom: 12),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(label, style: Theme.of(context).textTheme.bodyMedium),
            if (hint != null)
              Text(hint, style: Theme.of(context).textTheme.bodySmall, textAlign: TextAlign.start),
            // 与 multiselect 同款：限高 + 自己滚动，别把弹框撑爆
            ConstrainedBox(
              constraints: const BoxConstraints(maxHeight: 200),
              child: SingleChildScrollView(
                child: PermissionTreePicker(
                  nodes: field.tree,
                  checked: _multi[field.name]!,
                  enabled: enabled,
                  onChanged: (next) => setState(() => _multi[field.name] = next),
                ),
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
