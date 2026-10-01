/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import test from 'node:test';

/**
 * Excel 导入那条链的**接线**：表单里的 File → 请求体 → multipart → 结果报表。
 *
 * 为什么读源码：本树没有 DOM 底座（无 jsdom / 无 testing-library，`npm test` 跑的是
 * `node --test`，全部用例都是纯逻辑层）。这条链的每一跳都在**组件内部**，页级用例够不到：
 * `adminUsers.test.ts` 里那条 multipart 用例是**自己**拼的 FormData，它证明的是 api 层与
 * FormData 序列化没问题 —— **不能**证明 ImportPanel 拼对了、FormModal 把 File 塞进去了。
 * 真实覆盖靠 CDP 真机（DOM.setFileInputFiles + 拦请求看多部分体）；本文件是它在 CI 里的钉子：
 * 真机是手工跑的，接线一旦被改回去不会有人知道。
 *
 * 同款先例：`components/rowActions.guard.test.ts`（那边记着“把守卫改成 null 仍 65/65 全绿”的实测）。
 * 顺序是判据的一部分：`buildPayload` → 塞 File → `onSubmit`，挪一步整条链就断了。
 */
const read = (name: string): string => readFileSync(fileURLToPath(new URL(name, import.meta.url)), 'utf8');

const FORM = read('./FormModal.tsx');
const PANEL = read('./ImportPanel.tsx');
const BROWSER = read('./RowBrowser.tsx');

/** 断言 a 在 b 之前出现（都在同一份源码里），并把两处位置报出来好定位。 */
const before = (source: string, a: string, b: string, why: string): void => {
  const ia = source.indexOf(a);
  const ib = source.indexOf(b);
  assert.notEqual(ia, -1, `找不到 ${a}：改名了就同步改本文件`);
  assert.notEqual(ib, -1, `找不到 ${b}：改名了就同步改本文件`);
  assert.ok(ia < ib, `${why}（${a} 在 ${ib} 处，${b} 在 ${ia} 处）`);
};

test('FormModal：草稿里只有文件名，File 在 buildPayload 之后、onSubmit 之前塞进请求体', () => {
  // 恰好一处：两处就是两条路，改了一条另一条照旧发字符串上去
  assert.equal(
    FORM.split('body[field.name] = file;').length - 1,
    1,
    'File 必须恰好被塞进请求体一次 —— 少了它，multipart 里就只有个文件名的字符串段',
  );
  before(FORM, 'const body = buildPayload(fields, draft, row, fullEdit);', 'body[field.name] = file;',
    'File 必须在 buildPayload 之后覆盖那个文件名串（在前面会被 buildPayload 的结果盖掉）');
  before(FORM, 'body[field.name] = file;', 'await onSubmit(body);',
    'File 必须在 onSubmit 之前塞好（在后面就是发完才塞，等于没塞）');
  // 判据带类型判断：只认同名且真选了文件的字段，否则「没选文件」会塞一个 undefined 上去
  assert.ok(FORM.includes("if (field.type === 'file' && file)"), '缺「是 file 字段且有文件」这个判据');
});

/** 切出 file 那个分支（到下一个控件分支的注释为止）。切不出两端的任何一端就报错 ——
 *  悄悄退化成 -1 的话 slice 会给出别的区间，后面的断言就成了**恒真的假绿**。 */
const fileBranch = (): string => {
  const start = FORM.indexOf("if (field.type === 'file')");
  const end = FORM.indexOf('json / jsonobj / jsonarr / lines 与 textarea 同形');
  assert.ok(start !== -1 && end !== -1 && start < end, `file 分支切不出来（${start}..${end}）：注释或顺序变了`);
  return FORM.slice(start, end);
};

test('FormModal：file 控件用隐藏的原生选择器 + 只读文本框（不能手输文件名）', () => {
  const branch = fileBranch();
  // 原生 input 是 CDP `DOM.setFileInputFiles` 的落点：换成自绘控件真机就选不了文件了
  assert.ok(branch.includes('type="file"') && branch.includes('ref={file}') && branch.includes('hidden'), '缺隐藏的原生 file 控件');
  assert.ok(branch.includes("accept={field.accept}"), 'accept 来自字段描述（选文件对话框的过滤提示），不能写死');
  assert.ok(branch.includes('const selected = event.target.files?.[0];'), '选完文件要取第一个 File');
  assert.ok(branch.includes('onPick?.(selected)'), '选中的文件要交给 FormModal 记下来');
  assert.ok(branch.includes("event.target.value = '';"), '清空选择：连选同一个文件也要再触发一次 change');
  // 文本框只读：手输一个名字会造出「有名字没文件」的草稿，提交时服务端只收到一段文本
  assert.ok(branch.includes('readOnly'), '文件名文本框必须 readOnly');
  assert.ok(!branch.includes('onChange={(event) => onChange('), '这个分支不该有可写的 onChange');
});

test('ImportPanel：只有一条提交路径，且走 multipart（FormData + method POST + form）', () => {
  assert.equal(PANEL.split('new FormData()').length - 1, 1, 'FormData 只能有一处');
  assert.equal(
    PANEL.split("apiEnvelope<Partial<ImportReport>>(spec.path, { method: 'POST', form })").length - 1,
    1,
    '导入必须走 apiEnvelope + {form}：换成 body（JSON）文件就上不去，换成 rawPost 则丢信封',
  );
  // 端点路径来自配置（pages/adminUsers.ts），组件里不写死 —— 写死就等于多一个真值源
  assert.ok(!PANEL.includes('/admin/v1/import/users'), '端点路径不该出现在组件里');
});

test('ImportPanel：结果报表两个分支都在（成功读数 + 逐行失败表），失败提示交给 FormModal', () => {
  assert.ok(PANEL.includes("t('import.summary'"), '缺成功/失败读数那一行');
  assert.ok(PANEL.includes("label: 'f.row'") && PANEL.includes("label: 'f.reason'"), '逐行失败表要有行号与原因两列');
  assert.ok(PANEL.includes('report.errors.length > 0'), '全成功时不摆空表');
  // 契约变更（2026-10-01）：原先只断言 `t('import.no_roles')` 存在，但那条文案把恢复路径
  // 指向「行内动作」—— 本树管理员列表的行内动作**只有**「重置密码」，没有分配角色那一项
  // ⇒ 运营照做会找不到入口。现在必须**点名编辑表单里的角色字段**，且 {roles} 由 f.roles 注入
  // （与表单标签同源，改标签这里跟着变）。
  assert.ok(
    PANEL.includes("t('import.no_roles', { roles: t('f.roles') })"),
    '无角色这件事要说出来，且必须指明编辑表单里的角色字段（行内动作里没有分配角色）',
  );
  // 失败路径不另写：FormModal 自己把服务端 message 显示在框内且不关框
  assert.ok(!PANEL.includes('catch'), 'ImportPanel 不该吞异常：FormModal 的 catch 负责显示服务端原话');
});

test('RowBrowser：importExcel 真的挂进了工具条（配置键不是死键）', () => {
  assert.ok(BROWSER.includes('<ImportPanel spec={imports} onDone={reload} />'), 'ImportPanel 没挂上');
  assert.ok(BROWSER.includes('const imports = config?.importExcel;'), '缺配置读取');
  assert.ok(BROWSER.includes('{canCreate || batch || pdf || imports ? ('), '工具条条件漏了 imports ⇒ 只有导入的页不渲染工具条');
  // 导入写库 ⇒ 成功必须回读（不刷新的话页面还是旧列表，看着像没导进去）
  assert.ok(BROWSER.includes('onDone={reload}'), '导入成功后要回读列表');
});
