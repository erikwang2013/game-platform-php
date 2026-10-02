/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { GameItem, GameCurrency, ExchangeRequest, CaptchaClick } from '../model/DataModels';

/**
 * 兑换页的纯计算 —— 从 `pages/ExchangePage.ets`（565 行，超了 500 行纪律）里整块搬出来。
 *
 * ⚠ 搬不是重写：函数体逐字未改（连注释一起），只是把原来的 `this.xxx` 换成显式入参 ——
 * 那几个字段本来就是这些取值的唯一来源，搬完页面只剩「状态 + 装配 + 网络调用」。
 * 原页面里与之对应的四个私有方法（currentCurrencies / currentSymbol / payload / amountOk）
 * 已一并删除，别再在页面里留同名副本。
 */

/** 当前游戏的可兑换币种（找不到该游戏时返回空数组，与搬之前的 `return []` 一致） */
export function exchangeCurrencies(games: GameItem[], gameId: string): GameCurrency[] {
  for (let i = 0; i < games.length; i++) {
    if (games[i].id === gameId) {
      return games[i].currencies ?? [];
    }
  }
  return [];
}

/** 当前币种的符号（找不到时返回空串，页面用它拼「游戏币数量」那句文案） */
export function exchangeSymbol(games: GameItem[], gameId: string, currencyId: string): string {
  const list = exchangeCurrencies(games, gameId);
  for (let i = 0; i < list.length; i++) {
    if (list[i].id === currencyId) {
      return list[i].symbol;
    }
  }
  return '';
}

/**
 * 兑换请求体（询价/买入/卖出共用）。
 * 验证码两字段可选：只有卖出要（服务端只给 sell 加了强制校验），
 * 不传时 JSON.stringify 会整键省略，询价与买入的报文与从前逐字节一致
 */
export function exchangePayload(
  gameId: string,
  currencyId: string,
  direction: string,
  amount: string,
  captchaKey?: string,
  clicks?: CaptchaClick[]
): ExchangeRequest {
  return {
    game_id: gameId,
    currency_id: currencyId,
    direction: direction,
    platform_amount: amount,
    captcha_key: captchaKey,
    clicks: clicks
  };
}

/** 只做格式正则校验，不做数值转换 */
export function amountFormatOk(amount: string): boolean {
  return /^\d+(\.\d{1,8})?$/.test(amount);
}
