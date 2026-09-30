// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// 风控管理（批次 5）页签宿主：规则 / 事件 / 设备 / IP 信誉 / 异常用户 / 团伙 / 反作弊。
// 与 M6 的「风控大盘」（RiskDashboardPage：总览·图谱·异常用户·规则效果，只读）分开挂：
// 那边是**看板**，这边是**写操作**，两边端点也不同（大盘走 /risk/overview 等聚合端点）。
import 'package:flutter/material.dart';

import '../../widgets/crud.dart';
import 'anticheat_manage_tab.dart';
import 'risk_cluster_manage_tab.dart';
import 'risk_device_manage_tab.dart';
import 'risk_event_manage_tab.dart';
import 'risk_ip_manage_tab.dart';
import 'risk_rule_manage_tab.dart';
import 'risk_user_manage_tab.dart';

class RiskManagePage extends StatelessWidget {
  const RiskManagePage({super.key});

  /// 页签标题 key（顺序 = _tabs 的顺序 = _pages 里 RiskManagePage 的页签语义）
  static const List<String> _keys = <String>[
    'risk.tab_rules',
    'risk.tab_events',
    'risk.tab_devices',
    'risk.tab_ips',
    'risk.tab_users',
    'risk.tab_clusters',
    'risk.tab_anticheat',
  ];

  static const List<Widget> _tabs = <Widget>[
    RiskRuleManageTab(),
    RiskEventManageTab(),
    RiskDeviceManageTab(),
    RiskIpManageTab(),
    RiskUserManageTab(),
    RiskClusterManageTab(),
    AntiCheatManageTab(),
  ];

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: _tabs.length,
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(crudText('risk.page_title'), style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
        const SizedBox(height: 8),
        TabBar(
          // 7 个页签在窄屏放不下：可横滚好过挤成一团
          isScrollable: true,
          tabs: [for (final key in _keys) Tab(text: crudText(key))],
        ),
        const SizedBox(height: 8),
        const Expanded(child: TabBarView(children: _tabs)),
      ]),
    );
  }
}
