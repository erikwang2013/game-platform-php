/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import UIAbility from '@ohos.app.ability.UIAbility';
import window from '@ohos.window';
import hilog from '@ohos.hilog';
import { LocaleController } from '../i18n/LocaleController';

const TAG = 'EntryAbility';

export default class EntryAbility extends UIAbility {
  onCreate(want, launchParam): void {
    hilog.info(0x0000, TAG, 'Ability onCreate');
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

  /** 已登录则跳过登录页直接进入仪表盘 */
  private loadStartPage(windowStage: window.WindowStage): void {
    const accessToken = AppStorage.get('access_token') as string ?? '';
    const startPage = accessToken.length > 0 ? 'pages/DashboardPage' : 'pages/LoginPage';

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
