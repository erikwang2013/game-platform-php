/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import UIAbility from '@ohos.app.ability.UIAbility';
import Want from '@ohos.app.ability.Want';
import AbilityConstant from '@ohos.app.ability.AbilityConstant';
import window from '@ohos.window';
import hilog from '@ohos.hilog';
import { TokenManager } from '../utils/TokenManager';
import { LocaleController } from '../i18n/LocaleController';

const TAG = 'EntryAbility';

export default class EntryAbility extends UIAbility {
  onCreate(want: Want, launchParam: AbilityConstant.LaunchParam): void {
    hilog.info(0x0000, TAG, 'Ability onCreate');
    // 必须早于 onWindowStageCreate 的启动页分支：把 TokenManager 接上磁盘并读回已保存的令牌，
    // 否则 isLoggedIn() 冷启动恒 false，永远落到 LoginPage
    TokenManager.init(this.context);
  }

  onWindowStageCreate(windowStage: window.WindowStage): void {
    hilog.info(0x0000, TAG, 'Ability onWindowStageCreate');

    // 语言偏好必须在 loadContent **之前**就位：晚于它，首帧会先按默认语言渲染一屏再跳。
    // LocaleController.init 内部全包 try/catch 且不抛 —— 一旦它 reject，这个 then 不执行，
    // 界面就永远不加载（所以这里能放心用 then 串行，不需要 catch/兜底分支）。
    LocaleController.init(this.context).then(() => {
      this.loadStartPage(windowStage);
    });
  }

  /** 已登录则跳过登录页直接进入游戏大厅 */
  private loadStartPage(windowStage: window.WindowStage): void {
    const startPage = TokenManager.isLoggedIn() ? 'pages/GameHallPage' : 'pages/LoginPage';

    windowStage.loadContent(startPage, (err) => {
      if (err.code) {
        hilog.error(0x0000, TAG, `Failed to load content: ${err.code}`);
        return;
      }
      hilog.info(0x0000, TAG, 'Succeeded in loading content');
    });
  }

  onWindowStageDestroy(): void {
    hilog.info(0x0000, TAG, 'Ability onWindowStageDestroy');
  }

  onForeground(): void {
    hilog.info(0x0000, TAG, 'Ability onForeground');
  }

  onBackground(): void {
    hilog.info(0x0000, TAG, 'Ability onBackground');
  }

  onDestroy(): void {
    hilog.info(0x0000, TAG, 'Ability onDestroy');
  }
}
