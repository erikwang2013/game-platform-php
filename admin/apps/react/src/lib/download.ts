/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
/**
 * 文件下载（导出 Excel / PDF / CSV）。后端这几个端点的成功响应**不是信封**：
 * `response()->download()` 直接回二进制 + `Content-Disposition: attachment`；
 * 而**失败路径回的仍是 `{code,message,data}` 信封**（校验不过、权限不足）。
 * 所以不能只看 HTTP 状态，也不能直接 res.blob() 存盘 —— 那会把一句
 * 「导出失败」的 JSON 存成 .xlsx。分流判据是 content-type（见 downloadFile）。
 *
 * 纯逻辑（文件名解析）与 DOM 分开：前者供 node --test 直接覆盖（本树无 DOM 底座）。
 */
import { ApiError, apiRaw, type Options } from './api.ts';
import { t } from '../i18n/index.ts';

/**
 * `Content-Disposition` → 落盘文件名。
 *
 * 三种写法都要认（同一个后端里都出现过）：`filename="x.xlsx"`、`filename=x.xlsx`、
 * 以及 RFC 5987 的 `filename*=UTF-8''%E6%8A%A5%E8%A1%A8.csv`（非 ASCII 名的正确写法）。
 * 认不出来就退回 fallback —— **绝不用 content-type 猜后缀**：csv/xlsx/pdf 三种都由同一条链路下载，
 * 猜错就是把 CSV 存成 .xlsx。
 *
 * 取到的名字要**剥掉路径分隔符**：名字来自响应头，落盘前不该含目录成分（`../../x` 这种）。
 * 浏览器本身也会净化 `download` 属性，这一层只是不让脏名字进 DOM。
 */
export function filenameFrom(disposition: string | null, fallback: string): string {
  const raw = disposition ?? '';
  const star = /filename\*=(?:UTF-8|utf-8)''([^;]+)/.exec(raw);
  let name = '';
  if (star) {
    try {
      name = decodeURIComponent(star[1].trim());
    } catch {
      name = star[1].trim(); // 百分号转义坏了：原样用，总比没有强
    }
  } else {
    const plain = /filename=(?:"([^"]*)"|([^;]+))/i.exec(raw);
    name = (plain?.[1] ?? plain?.[2] ?? '').trim();
  }
  const safe = name.replace(/[\\/]/g, '_').replace(/^\.+/, '').trim();
  return safe === '' ? fallback : safe;
}

/**
 * 下载一个文件并触发保存，返回落盘文件名（供调用方显示「已下载 xxx」）。
 *
 * 失败（信封）抛 ApiError，message 是**服务端原话**（与资金动作同一口径：不吞成「导出失败」）。
 * 成功但响应体不是文件（网关的 HTML 错误页）也抛，而不是存一个坏文件。
 */
export async function downloadFile(path: string, options: Options = {}, fallback = 'export'): Promise<string> {
  const res = await apiRaw(path, options);
  const type = res.headers.get('content-type') ?? '';
  if (type.includes('json') || type.includes('text/html')) {
    let message = t('export.bad_response', { status: res.status });
    try {
      const payload = (await res.json()) as { code?: number; message?: string };
      if (typeof payload.message === 'string' && payload.message !== '') message = payload.message;
    } catch {
      // 不是信封（HTML 错误页）：用上面那句带状态码的兜底
    }
    throw new ApiError(res.status, message);
  }

  const name = filenameFrom(res.headers.get('content-disposition'), fallback);
  const url = URL.createObjectURL(await res.blob());
  const link = document.createElement('a');
  link.href = url;
  link.download = name;
  link.style.display = 'none';
  document.body.appendChild(link);
  link.click();
  link.remove();
  // 立刻 revoke 会让部分浏览器取消下载：下一轮事件循环再撤（对象 URL 只是一句引用，代价可忽略）
  setTimeout(() => URL.revokeObjectURL(url), 0);
  return name;
}
