/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 资源模块注册表（批次 2 建「内容与配置」8 个，批次 3 加「用户与权限」3 个，
 * 批次 4 加「资金」4 个，批次 5 加「风控」3 个）。
 * 描述契约与共用小工具见 ResourceSchema.ets，模块定义见 ContentModules.ets / ConfigModules.ets /
 * UserModules.ets / FundModules.ets / RiskModules.ets。
 *
 * 索引页与详情页都只经这里取模块：新增一个模块 = 在对应分册里加一份描述 + 挂进下面的分组数组，
 * 不迁移任何调用点（漏挂入口在类型上就写不出来）。
 */

import { ResourceModule } from './ResourceSchema';
import { CONTENT_MODULES } from './ContentModules';
import { CONFIG_MODULES } from './ConfigModules';
import { USER_MODULES } from './UserModules';
import { FUND_MODULES } from './FundModules';
import { RISK_MODULES } from './RiskModules';

/** 索引页顺序：内容四个，配置四个，用户与权限三个，资金四个，风控三个（提现订单与风控四块是行动作页、不在注册表里） */
const MODULES: ResourceModule[] = CONTENT_MODULES.concat(CONFIG_MODULES)
  .concat(USER_MODULES)
  .concat(FUND_MODULES)
  .concat(RISK_MODULES);

/** 索引页用：全部模块（按清单顺序） */
export function allModules(): ResourceModule[] {
  return MODULES;
}

/** 路由参数 key → 模块描述；未知 key 返回 null（页面据此报错，而不是静默空白） */
export function moduleByKey(key: string): ResourceModule | null {
  for (let i = 0; i < MODULES.length; i++) {
    if (MODULES[i].key === key) {
      return MODULES[i];
    }
  }
  return null;
}
