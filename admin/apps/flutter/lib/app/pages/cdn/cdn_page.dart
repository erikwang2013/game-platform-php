// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// CDN 厂商（批次 4）：CRUD + 启停 + 连通测试，全部套 widgets/crud.dart 的底座。
// 凭据（config）列表**不回传**（CdnProviderController::list 里 unset）⇒ 编辑时留空即保持原凭据。
import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../i18n/translations.dart';
import '../../services/api_service.dart';
import '../../widgets/crud.dart';

class CdnController extends GetxController {
  final api = ApiService();
  final providers = <dynamic>[].obs;
  final isLoading = false.obs;

  /// 正在探测的行（图标换成转圈）。一次只允许一个在跑：探测是同步阻塞的 HTTP 请求。
  final testingId = ''.obs;

  @override
  void onInit() {
    super.onInit();
    load();
  }

  /// **不加分页**：/cdn/provider/list 是整表端点（无 total，五厂商封顶），
  /// 且媒体上传路径要按 id 查厂商 ⇒ 分页会让上传线程挑不到 provider。
  Future<void> load() async {
    isLoading.value = true;
    try {
      final resp = await api.get('/admin/v1/cdn/provider/list');
      providers.value = resp['data']['list'] as List<dynamic>? ?? [];
    } catch (e) {
      Get.snackbar('${AppTranslations.t('app.error')}', '${AppTranslations.t('app.loading_failed')}: $e');
    } finally {
      isLoading.value = false;
    }
  }

  // 以下写操作**不吞异常**：异常要冒到通用表单/确认框里显示服务端 message（widgets/crud.dart）。

  Future<void> create(Map<String, dynamic> data) async {
    await api.post('/admin/v1/cdn/provider/create', data: data);
    await load();
  }

  Future<void> updateProvider(String hashid, Map<String, dynamic> data) async {
    await api.put('/admin/v1/cdn/provider/$hashid', data: data);
    await load();
  }

  Future<void> remove(String hashid) async {
    await api.delete('/admin/v1/cdn/provider/$hashid');
    await load();
    Get.snackbar('${AppTranslations.t('app.success')}', '${AppTranslations.t('app.deleted')}');
  }

  /// 启停走独立端点（body 里 id 是 hashid，status 是 0/1）
  Future<void> toggle(String hashid, int status) async {
    await api.post('/admin/v1/cdn/provider/toggle', data: <String, dynamic>{'id': hashid, 'status': status});
    await load();
  }

  /// 连通测试（POST cdn/provider/test，body 里 id 是 hashid）：成功 message 是「连通正常」，
  /// 失败是探测抛出的原话（凭据无效/桶不存在等 422）⇒ 两种都原样显示，不吞不改写。
  Future<String> test(String hashid) async {
    final resp = await api.post('/admin/v1/cdn/provider/test', data: <String, dynamic>{'id': hashid});
    return resp['message']?.toString() ?? '';
  }
}

class CdnPage extends GetView<CdnController> {
  const CdnPage({super.key});

  static const List<CrudField> _fields = <CrudField>[
    CrudField('name', 'cdn.name', required: true, hint: 'cdn.name_hint'),
    CrudField('provider', 'cdn.provider',
        type: CrudFieldType.select,
        required: true,
        hint: 'cdn.provider_hint',
        options: <CrudOption>[
          CrudOption('cloudflare', 'cloudflare'),
          CrudOption('cloudfront', 'cloudfront'),
          CrudOption('aliyun', 'aliyun'),
          CrudOption('tencent', 'tencent'),
          CrudOption('huawei', 'huawei'),
        ]),
    CrudField('config', 'cdn.config', type: CrudFieldType.multiline, hint: 'cdn.config_hint'),
    CrudField('status', 'cdn.status', type: CrudFieldType.toggle),
    CrudField('sort', 'cdn.sort', type: CrudFieldType.number, hint: 'payment.sort_hint'),
  ];

  /// config 留空不提交：update 只在校验通过且非空时才覆盖（否则会把原凭据清掉），create 存 NULL。
  static const Set<String> _omitWhenEmpty = <String>{'config'};

  @override
  Widget build(BuildContext context) {
    if (!Get.isRegistered<CdnController>()) {
      Get.put(CdnController(), permanent: false);
    }
    final ctrl = controller;

    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      CrudHeader(
        title: "${AppTranslations.t('cdn.title')}",
        onCreate: () => _openForm(context, ctrl),
      ),
      const SizedBox(height: 12),
      Expanded(child: Obx(() {
        if (ctrl.isLoading.value) return const Center(child: CircularProgressIndicator());
        if (ctrl.providers.isEmpty) {
          return Center(
            child: Column(mainAxisSize: MainAxisSize.min, children: [
              Image.asset('assets/mascot.png', width: 120),
              const SizedBox(height: 12),
              Text("${AppTranslations.t('app.no_data')}"),
            ]),
          );
        }

        return SingleChildScrollView(
          child: SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            child: DataTable(
              columns: [
                DataColumn(label: Text('${AppTranslations.t('cdn.name')}')),
                DataColumn(label: Text('${AppTranslations.t('cdn.provider')}')),
                DataColumn(label: Text('${AppTranslations.t('cdn.sort')}')),
                DataColumn(label: Text('${AppTranslations.t('cdn.status')}')),
                DataColumn(label: Text('${AppTranslations.t('withdraw.actions')}')),
              ],
              rows: ctrl.providers.map((p) {
                // 列表里的 id 是 hashid：{hashid} 路径与 toggle/test 的 id 都用它
                final id = p['id']?.toString() ?? '';
                final name = p['name']?.toString() ?? '';
                final status = p['status'] is int ? p['status'] as int : 0;

                return DataRow(cells: [
                  DataCell(Text(name)),
                  DataCell(Text(p['provider']?.toString() ?? '')),
                  DataCell(Text(p['sort']?.toString() ?? '0')),
                  DataCell(Chip(
                    label: Text(status == 1
                        ? '${AppTranslations.t('app.enabled')}'
                        : '${AppTranslations.t('app.disabled')}'),
                    color: WidgetStatePropertyAll(status == 1 ? Colors.green.shade50 : Colors.red.shade50),
                  )),
                  DataCell(Row(mainAxisSize: MainAxisSize.min, children: [
                    // 连通测试不是破坏性动作、也不改库：点一下就打，结果原地显示（成功/失败都读服务端 message）
                    IconButton(
                      icon: ctrl.testingId.value == id
                          ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                          : const Icon(Icons.wifi_tethering, size: 18),
                      tooltip: '${AppTranslations.t('cdn.test')}',
                      onPressed: ctrl.testingId.value.isEmpty ? () => _test(context, ctrl, id) : null,
                    ),
                    CrudRowActions(
                      status: status,
                      onToggle: (next) => ctrl.toggle(id, next),
                      onEdit: () => _openForm(context, ctrl, provider: p),
                      onDelete: () => confirmCrudDelete(context, what: name, onConfirm: () => ctrl.remove(id)),
                    ),
                  ])),
                ]);
              }).toList(),
            ),
          ),
        );
      })),
    ]);
  }

  Future<void> _test(BuildContext context, CdnController ctrl, String hashid) async {
    ctrl.testingId.value = hashid;
    try {
      final message = await ctrl.test(hashid);
      Get.snackbar('${AppTranslations.t('app.success')}', message);
    } catch (e) {
      Get.snackbar('${AppTranslations.t('app.error')}', apiErrorMessage(e));
    } finally {
      ctrl.testingId.value = '';
    }
  }

  /// 新建态的 status 初值给 0（禁用）：凭据没测通就对外服务太危险，测通后在列表行里点启用。
  Future<void> _openForm(BuildContext context, CdnController ctrl, {dynamic provider}) async {
    final ok = await showCrudForm(
      context,
      title: provider == null
          ? '${AppTranslations.t('cdn.create_title')}'
          : '${AppTranslations.t('cdn.edit_title')}',
      fields: _fields,
      initial: provider == null ? <String, dynamic>{'status': 0} : Map<String, dynamic>.from(provider as Map),
      onSubmit: (data) {
        data.removeWhere((key, value) => value == '' && _omitWhenEmpty.contains(key));
        return provider == null ? ctrl.create(data) : ctrl.updateProvider(provider['id'].toString(), data);
      },
    );
    if (ok) Get.snackbar('${AppTranslations.t('app.success')}', '${AppTranslations.t('app.saved')}');
  }
}
