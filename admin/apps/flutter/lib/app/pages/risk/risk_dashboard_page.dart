// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import 'risk_overview_tab.dart';
import 'risk_cluster_tab.dart';
import 'risk_user_tab.dart';
import 'risk_rule_tab.dart';

/// 风控大盘（M6）：总览 / 图谱 / 异常用户 / 规则效果 四页签
class RiskDashboardPage extends StatelessWidget {
  const RiskDashboardPage({super.key});

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 4,
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        const Text('风控大盘', style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
        const SizedBox(height: 8),
        const TabBar(tabs: [
          Tab(text: '总览'),
          Tab(text: '图谱'),
          Tab(text: '异常用户'),
          Tab(text: '规则效果'),
        ]),
        const SizedBox(height: 8),
        const Expanded(child: TabBarView(children: [
          RiskOverviewTab(),
          RiskClusterTab(),
          RiskUserTab(),
          RiskRuleTab(),
        ])),
      ]),
    );
  }
}
