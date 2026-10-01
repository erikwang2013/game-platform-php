/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { t, setCode, type MessageKey } from '../i18n/index.ts';
import { buildPayload, fieldText, type Field } from '../lib/crud.ts';
import { hasRoute } from '../lib/route-fixtures.ts';
import {
  ANTICHEAT_CRUD,
  RISK_CLUSTER_CRUD,
  RISK_CLUSTER_PANEL,
  RISK_DEVICE_CRUD,
  RISK_DEVICE_HIDE,
  RISK_EVENT_CRUD,
  RISK_IP_FLAGS,
  RISK_RULE_CRUD,
  RISK_USER_CRUD,
} from './modules.ts';

/**
 * 风控（批次 5）的路径/枚举/文案对账：路径对 config/route.php，枚举对控制器里的 in_array 与
 * 沙箱注册表，全部从**真值文件**读 —— 抄文档或凭记忆写的断言，漂移了也不会红。
 * 本文件只装批次 5；批次 1~4 的用例在 modules.test.ts（两组共用 lib/route-fixtures.ts）。
 */

/* ------------------------------ 批次 5：风控 ------------------------------ */

const controller = (name: string): string =>
  readFileSync(new URL(`../../../../app/admin/v1/controller/${name}`, import.meta.url), 'utf8');
const sandbox = readFileSync(new URL('../../../../app/service/RiskSandboxService.php', import.meta.url), 'utf8');

/** 取 `const NAME = [ ... ];` 这一段（用「换行 + 4 空格 + ];」收尾，内层缩进不会误截）。 */
const arrayConst = (src: string, name: string): string => {
  const match = src.match(new RegExp(`const ${name} = \\[([\\s\\S]*?)\\n    \\];`));
  assert.ok(match, `控制器里找不到 ${name}（改名了？下面的断言会失真）`);
  return match[1];
};

/** 代码块里的单引号字面量（键名）。 */
const literals = (text: string): string[] => [...text.matchAll(/'([a-z_0-9]+)'/g)].map((match) => match[1]);

/** `in_array($var, [...], true)` 的取值列表 —— 枚举真值只从控制器读，不抄文档、不凭记忆。 */
const inList = (src: string, variable: string): string[] => {
  const match = src.match(new RegExp(`in_array\\(\\$${variable}, \\[([^\\]]+)\\]`));
  assert.ok(match, `控制器里找不到 $${variable} 的 in_array 列表`);
  return match[1]
    .split(',')
    .map((item) => item.trim().replace(/^'|'$/g, ''))
    .filter((item) => item !== '');
};

const fieldOf = (fields: Field[] | undefined, name: string): Field => {
  const field = (fields ?? []).find((item) => item.name === name);
  assert.ok(field, `缺字段 ${name}`);
  return field;
};

/** select 的静态值域（异步值域在这里出现就是写错了）。 */
const optionValues = (field: Field | undefined): string[] => {
  assert.ok(field, '字段不存在');
  assert.ok(Array.isArray(field.options), '该字段的值域应是静态列表');
  return field.options.map((option) => option.value);
};

/**
 * 按**键**取动作，同时把「键 → 英文成品」钉住。
 * 只按键找，源码里把 label 写成成品字符串（本树的 label 一律是键）也会「找不到」而红；
 * 只按成品找则看不出写的是哪条键；两样都比才既认得出键、又咬得住译文。
 */
const actionOf = (
  crud: typeof RISK_RULE_CRUD | typeof RISK_USER_CRUD | typeof RISK_EVENT_CRUD | typeof RISK_DEVICE_CRUD,
  key: MessageKey,
  en: string,
) => {
  setCode('en');
  assert.equal(t(key), en, `动作键 ${key} 的英文成品变了`);
  const found = (crud.actions ?? []).find((item) => item.label === key);
  assert.ok(found, `${t(crud.noun)} 缺动作「${key}」`);
  return found;
};

const confirmText = (spec: { confirm?: string | ((row: Record<string, unknown>) => string) }, row: Record<string, unknown>): string =>
  typeof spec.confirm === 'function' ? spec.confirm(row) : (spec.confirm ?? '');

/** 动作成功后显示的文案：只认函数形（信封 message 是占位符 success，有用信息在 data 里）。 */
const reportText = (
  spec: { report?: boolean | ((envelope: { code: number; message: string; data: unknown }) => string) },
  data: unknown,
): string => {
  assert.ok(typeof spec.report === 'function', '该动作必须读服务端返回（不做乐观更新）');
  return spec.report({ code: 0, message: 'success', data });
};

test('风控规则：写端点对路由表；启停无请求体、编辑是全量语义、没有删除端点', () => {
  const crud = RISK_RULE_CRUD;
  assert.equal(crud.base, '/admin/v1/risk/rule');
  // update 与 create 共用同一个 fill()（name/type/action 一律从请求体读且必填）⇒ 只发改动字段必然 422
  assert.equal(crud.fullEdit, true);
  // 没有 DELETE 端点 ⇒ 不给 labelKey（界面上就没有删除按钮）
  assert.equal(crud.labelKey, undefined);
  assert.ok(!hasRoute('DELETE', `${crud.base}/{hashid}`), '路由表里冒出了规则的删除端点');

  const endpoints: [string, string][] = [
    ['POST', String(crud.createPath ?? `${crud.base}/create`)],
    ['PUT', `${crud.base}/{hashid}`],
    ['POST', '/admin/v1/risk/rule/{hashid}/toggle'],
    ...(crud.actions ?? []).map((action) => [action.method ?? 'POST', action.path('{hashid}')] as [string, string]),
  ];
  for (const [method, path] of endpoints) {
    assert.ok(hasRoute(method, path), `路由表里没有 ${method} ${path}`);
  }

  // 启停：行 id 在**路径**里、**无请求体**（服务端自己翻转；客户端编一个 status 出来是错的）
  const toggle = crud.toggle;
  assert.ok(typeof toggle === 'function', '规则的启停不是『行 id 进路径、无体』这一口径');
  assert.deepEqual(toggle('Xk9'), { path: '/admin/v1/risk/rule/Xk9/toggle' });

  // 试算的 rule_id 取自被点的那一行；上下文是 JSON 对象（服务端 is_array 才收）
  const test = actionOf(crud, 'f.dry_run', 'Dry Run');
  assert.deepEqual(test.body?.('Xk9'), { rule_id: 'Xk9' });
  const context = fieldOf(test.fields, 'context');
  assert.equal(context.type, 'jsonobj');
  assert.equal(fieldOf(test.fields, 'user_id').required, true);
  assert.deepEqual(optionValues(fieldOf(test.fields, 'check_type')), ['login', 'deposit', 'withdraw', 'exchange']);
});

test('风控规则：type/action/scope 值与控制器同源（类型来自沙箱注册表，枚举来自 in_array）', () => {
  const src = controller('RiskRuleController.php');
  const types = literals(arrayConst(sandbox, 'TYPES'));
  assert.equal(types.length, 8);
  assert.deepEqual(optionValues(fieldOf(RISK_RULE_CRUD.fields, 'type')), types);
  assert.deepEqual(optionValues(fieldOf(RISK_RULE_CRUD.fields, 'action')), inList(src, 'action'));
  assert.deepEqual(optionValues(fieldOf(RISK_RULE_CRUD.fields, 'scope')), inList(src, 'scope'));
  // fill() 的三个必填字段：编辑要发全量，少一个就是 422（与 buildPayload 的 fullEdit 前提绑定）
  for (const name of ['name', 'type', 'action']) {
    assert.equal(fieldOf(RISK_RULE_CRUD.fields, name).required, true, `${name} 必须是必填`);
  }
});

test('风控规则：config 提示把每个 type 的白名单键与 INT_BOUNDS 的区间说全（阈值就是熔断闸）', () => {
  const src = controller('RiskRuleController.php');
  // 键名：CONFIG_KEYS 的映射键（= type）与每个 type 的白名单键，一个都不能漏
  const configKeys = literals(arrayConst(src, 'CONFIG_KEYS'));
  assert.ok(configKeys.length > 20, 'CONFIG_KEYS 没读全');
  // 区间：INT_BOUNDS 的每个 [下界, 上界] 都要在提示里出现（取 0 会让规则恒命中、连充值一起停）
  const bounds = [...arrayConst(src, 'INT_BOUNDS').matchAll(/'([a-z_0-9]+)'\s*=>\s*\[(\d+),\s*(\d+)\]/g)];
  assert.ok(bounds.length >= 10, 'INT_BOUNDS 没读全');
  // 每种**有表**的语言各查一遍：en 是其余 11 种语言的回落表，只查一种会让另一种悄悄缺键/缺区间。
  // 键名与区间是语言无关的（照抄控制器），末三条是各语言里「特殊读法」那句话的措辞。
  const proseByCode: Record<string, string[]> = {
    en: ['true/false', 'must be greater than 0', '(0, 1]'],
    zh: ['true/false', '必须大于 0', '(0, 1]'],
  };
  for (const [code, prose] of Object.entries(proseByCode)) {
    setCode(code);
    // hint 是**键**（渲染期才取译文）⇒ 走生产同一条解析路径拿到成品再比对控制器真值
    const hint = fieldText(fieldOf(RISK_RULE_CRUD.fields, 'config').hint) ?? '';
    for (const key of configKeys) {
      assert.ok(hint.includes(key), `${code}: config 提示漏了 ${key}（写错键名的值会被服务端拒绝）`);
    }
    for (const [, key, min, max] of bounds) {
      assert.ok(hint.includes(`${min}..${max}`), `${code}: ${key} 的区间 ${min}..${max} 没写进提示`);
    }
    // 布尔键与金额/比率键的特殊读法：服务端 (bool) 读法会让字符串 "false" 恒真
    for (const phrase of prose) assert.ok(hint.includes(phrase), `${code}: config 提示里缺「${phrase}」`);
  }
  setCode('en');
});

test('风控用户：hashid 在 user_id 列、冻结无请求体、解冻金额原样上送（前端不碰 Number）', () => {
  const crud = RISK_USER_CRUD;
  assert.equal(crud.base, '/admin/v1/risk/users');
  // 列表把 hashid 放在 user_id 列（没有 id 列）：不指认就取不到 id，行内动作整排不渲染
  assert.equal(crud.rowKey, 'user_id');
  assert.equal(crud.fields, undefined);
  assert.equal(crud.labelKey, undefined);
  assert.equal(crud.toggle, undefined);

  const endpoints: [string, string][] = [
    ...(crud.actions ?? []).map((action) => [action.method ?? 'POST', action.path('{hashid}')] as [string, string]),
    // 时间线视图：段名是 {hashid}（route.php 里就这么写的）——见下面图谱那条的说明
    ['GET', crud.views![0].path('{hashid}')],
  ];
  assert.deepEqual(endpoints, [
    ['POST', '/admin/v1/risk/users/{hashid}/hold'],
    ['POST', '/admin/v1/risk/users/{hashid}/release'],
    ['GET', '/admin/v1/risk/users/{hashid}/timeline'],
  ]);
  for (const [method, path] of endpoints) {
    assert.ok(hasRoute(method, path), `路由表里没有 ${method} ${path}`);
  }

  // 关联图谱视图（本批新增）：**必须**是 /risk/graph/{userId} 这条，别写成同名的 /risk/clusters
  // （后者是已确认团伙的 CRUD，收的是团伙 id，拿用户 hashid 去查是另一个资源）。
  // ⚠ 段名照 route.php 逐字写：`hasRoute` 比的是**字面串**（`Route::get('/risk/graph/{userId}'`），
  // 拿 {hashid} 去比会假报「路由表里没有」—— 占位符名不参与服务端匹配，但参与这条断言。
  const graph = (crud.views ?? []).find((view) => view.label === 'f.risk_graph');
  assert.ok(graph, '风控用户少了「关联图谱」视图');
  assert.equal(graph.path('{userId}'), '/admin/v1/risk/graph/{userId}');
  assert.ok(hasRoute('GET', '/admin/v1/risk/graph/{userId}'), '路由表里没有 GET /risk/graph/{userId}');

  const row = { user_id: 'Uk9hashid', username: 'alice', score: 40, band: 'watch' };
  const hold = actionOf(crud, 'f.freeze', 'Freeze');
  // 全额冻结由服务端算：客户端**不发金额**（发了反而会被忽略/对不上）
  assert.equal(hold.body, undefined);
  // 资金动作的确认文案必须认得出「是谁」（列表里摆的是 username）
  assert.ok(confirmText(hold, row).includes('alice'), '冻结的二次确认认不出用户');
  assert.ok(reportText(hold, { user_id: 'Uk9hashid', frozen_amount: '12.34000000' }).includes('12.34000000'));

  const release = actionOf(crud, 'f.unfreeze', 'Unfreeze');
  const amount = fieldOf(release.fields, 'amount');
  // 金额一律 text：number 控件与 Number() 会吃掉小数位（bcmath 串原样进出）
  assert.equal(amount.type, 'text');
  assert.deepEqual(buildPayload(release.fields ?? [], { amount: '10.50000000' }), { amount: '10.50000000' });
  assert.deepEqual(buildPayload(release.fields ?? [], { amount: '' }), {}, '留空 = 全额，不该发空串');
  assert.ok(confirmText(release, row).includes('alice'));
  assert.ok(reportText(release, { user_id: 'Uk9hashid', released_amount: '1.00000000' }).includes('1.00000000'));
});

test('风控事件 / 反作弊：动作型（无增改删、不做 0/1 启停），枚举与控制器 in_array 同源', () => {
  for (const crud of [RISK_EVENT_CRUD, ANTICHEAT_CRUD]) {
    assert.equal(crud.fields, undefined);
    assert.equal(crud.labelKey, undefined);
    // 事件没有可写的状态列、反作弊是字符串枚举 ⇒ 两者都不该有 0/1 启停（会把 2/whitelisted 压成 0）
    assert.equal(crud.toggle, undefined);
    for (const action of crud.actions ?? []) {
      const [method, path] = [action.method ?? 'POST', action.path('{hashid}')] as [string, string];
      assert.ok(hasRoute(method, path), `路由表里没有 ${method} ${path}`);
      // handle 不写库、review 只改事件行：处置痕迹在操作审计里，故不做 when 过滤（每行都收）
      assert.equal(action.when, undefined);
    }
  }

  const eventSrc = controller('RiskEventController.php');
  const decisions = (RISK_EVENT_CRUD.actions ?? []).map((action) => String(action.body?.('Xk9').decision));
  assert.deepEqual(decisions, ['approve', 'reject']);
  assert.deepEqual(decisions, inList(eventSrc, 'decision'));
  // 判误报是危险语义：确认文案要认出对象，且把「不改面板口径」说出来
  const reject = actionOf(RISK_EVENT_CRUD, 'f.mark_as_false_positive', 'Mark as False Positive');
  const text = confirmText(reject, { id: 'E1', rule_name: '频率限制', type: 'frequency' });
  assert.ok(text.includes('频率限制') && text.includes('frequency'), '判误报的确认认不出事件');

  const cheatSrc = controller('AntiCheatController.php');
  const review = (ANTICHEAT_CRUD.actions ?? [])[0];
  assert.ok(review, '反作弊缺复核动作');
  assert.deepEqual(optionValues(fieldOf(review.fields, 'status')), inList(cheatSrc, 'status'));
  assert.equal(fieldOf(review.fields, 'status').required, true);
  assert.ok(reportText(review, { id: 'A1', status: 'whitelisted', message: '审核已记录' }).includes('whitelisted'));
});

test('团伙：端点没有 /list 段、状态是三态 PUT（不是 0/1 翻转）、成员只读、确认面板字段对控制器', () => {
  const crud = RISK_CLUSTER_CRUD;
  assert.equal(crud.base, '/admin/v1/risk/clusters');
  assert.ok(!crud.base.endsWith('/list'), '团伙列表挂的是 /risk/clusters 本身，没有 /list 段');
  // confirm 的形状与 create 不同（type/fingerprint/member_ids），摆「+ 新建」就是点了必然 404
  assert.equal(crud.fields, undefined);
  assert.equal(crud.labelKey, undefined);
  assert.equal(crud.toggle, undefined);

  const status = (crud.actions ?? [])[0];
  assert.ok(status);
  assert.equal(status.method, 'PUT');
  assert.equal(status.path('Xk9'), '/admin/v1/risk/clusters/Xk9/status');
  // 三态枚举：0/1 翻转会把「2 已处置」静默压成 0
  const statusField = fieldOf(status.fields, 'status');
  // 值域与控制器同源（比集合不比顺序：in_array 里的字面量顺序是实现细节）
  assert.deepEqual([...optionValues(statusField)].sort(), [...inList(controller('RiskClusterController.php'), 'status')].sort());
  // 界面上刻意把「观察中」摆在最前（最常见的落点），顺序在此钉住
  assert.deepEqual(optionValues(statusField), ['1', '2', '0']);
  assert.equal(statusField.required, true);

  const endpoints: [string, string][] = [
    ['GET', `${crud.base}`],
    ['PUT', `${crud.base}/{hashid}/status`],
    ['GET', '/admin/v1/risk/clusters/{hashid}/members'],
    ['POST', RISK_CLUSTER_PANEL.detect],
    ['POST', RISK_CLUSTER_PANEL.confirm],
  ];
  for (const [method, path] of endpoints) {
    assert.ok(hasRoute(method, path), `路由表里没有 ${method} ${path}`);
  }
  assert.equal((crud.views ?? [])[0].path('Xk9'), '/admin/v1/risk/clusters/Xk9/members');

  // 确认面板：member_ids 收 **hashid**（服务端逐个 decodeId，非法值 400 fail-fast —— 原 `(int) $raw`
  // 会把调用方手里的 hashid 静默丢成 0，成员空着落库还不报错）。提示与控件形态都得跟着改：
  const clusterSrc = controller('RiskClusterController.php');
  const fields = RISK_CLUSTER_PANEL.fields;
  assert.equal(fieldOf(fields, 'name').required, true);
  assert.deepEqual(optionValues(fieldOf(fields, 'type')), inList(clusterSrc, 'type'));
  const members = fieldOf(fields, 'member_ids');
  assert.equal(members.type, 'lines', 'hashid 是字符串：不能是 number 控件/数字转型');
  const hint = members.hint ?? '';
  assert.ok(hint.includes('hashid'), 'member_ids 必须写明收 hashid');
  assert.ok(!hint.includes('数字 user_id'), '「收数字 user_id」是过期口径，不能留在提示里');
  // 提示声称「非法值 400」——这条得对控制器为真，不然就是编的（同族：B3 的 role.permission_ids）
  const parse = clusterSrc.match(/foreach \(\(array\) \$request->post\('member_ids'[\s\S]*?\n        \}/);
  assert.ok(parse, '控制器里找不到 member_ids 的解析循环（改名了？下面的断言会失真）');
  assert.ok(parse[0].includes('decodeId'), 'member_ids 必须走 decodeId');
  assert.ok(!parse[0].includes('(int) $raw'), 'member_ids 不能退回 (int) 静默转型');
  // hashid 原样上送：buildPayload 不做任何数字转型（值里带字母就是这条的牙齿）
  assert.deepEqual(buildPayload(fields, { type: 'manual', name: 'X', member_ids: 'Uk9abc\nVk8def\nUk9abc' }), {
    type: 'manual',
    name: 'X',
    member_ids: ['Uk9abc', 'Vk8def', 'Uk9abc'],
  });

  // 确认表单的值是**从候选预填**的（row = 候选快照）⇒ 必须全量提交：
  // 表单缺省只发改动字段，而预填值恰好等于原值 ⇒「点开候选一个字不改直接写入」会发空请求体，
  // 服务端缺 type/fingerprint 直接拒绝（真机验收抓到的就是这个）。
  assert.equal(RISK_CLUSTER_PANEL.fullEdit, true, '团伙确认表单必须全量提交');
  const prefill = { type: 'same_ip', fingerprint: 'a'.repeat(64), user_count: 7, name: '同 IP团伙 aaaaaaaa' };
  const draft = { type: 'same_ip', fingerprint: String(prefill.fingerprint), name: prefill.name, user_count: '7' };
  assert.deepEqual(buildPayload(fields, draft, prefill, RISK_CLUSTER_PANEL.fullEdit), {
    type: 'same_ip',
    fingerprint: prefill.fingerprint,
    name: prefill.name,
    user_count: 7,
  });
  // 反证：缺省（只发改动）在这份预填上就是空体 —— 这条断言与上面那条一起把语义钉死
  assert.deepEqual(buildPayload(fields, draft, prefill), {});
});

test('设备名单：拉黑/解封是行内动作（rowKey 指 fp_hash 列、when 互斥、fp_hash 不进列）', () => {
  const crud = RISK_DEVICE_CRUD;
  assert.equal(crud.base, '/admin/v1/risk/device');
  // 行里没有 id：hashid 位在 fp_hash 列（不指认 ⇒ RowActions 取不到 id，整行动作不渲染）
  assert.equal(crud.rowKey, 'fp_hash');
  // 后端只有 block/unblock 两个状态端点 ⇒ 动作型：不给 fields / labelKey（给了就长出必然 404 的按钮）
  assert.equal(crud.fields, undefined);
  assert.equal(crud.labelKey, undefined);
  assert.equal(crud.toggle, undefined);
  // 列表回完整 fp_hash，但它是**提交参数**不是列（真机断言列表表头里没有它）
  assert.deepEqual(RISK_DEVICE_HIDE, ['fp_hash']);

  const actions = crud.actions ?? [];
  // label 是**键**（两行的按钮文案各一条）⇒ 键与英文成品一起钉：键串在源码里写死，
  // 成品来自 en 表 ⇒ 谁写错都红。
  assert.deepEqual(actions.map((action) => action.label), ['f.blocklist', 'f.unblock']);
  setCode('en');
  assert.deepEqual(actions.map((action) => t(action.label)), ['Blocklist', 'Unblock']);
  for (const action of actions) {
    const path = action.path('Xk9');
    assert.ok(hasRoute(action.method ?? 'POST', path), `路由表里没有 ${action.method ?? 'POST'} ${path}`);
  }
  assert.deepEqual(actions.map((action) => action.path('Xk9')), [
    '/admin/v1/risk/device/block',
    '/admin/v1/risk/device/unblock',
  ]);

  // 两个动作互斥：同排摆两个按钮必有一个点了白点（服务端不校验当前状态，重复拉黑只会延 TTL）
  const block = actionOf(crud, 'f.blocklist', 'Blocklist');
  const unblock = actionOf(crud, 'f.unblock', 'Unblock');
  assert.equal(block.when?.({ blocked: false }), true);
  assert.equal(block.when?.({ blocked: true }), false);
  assert.equal(unblock.when?.({ blocked: true }), true);
  assert.equal(unblock.when?.({ blocked: false }), false);
  // 坏行（没有 blocked 字段）：只认 true 才算已拉黑，否则两个按钮都不出现、整行变成死行
  assert.equal(block.when?.({}), true);
  assert.equal(unblock.when?.({}), false);

  // 请求体收的是**行上的完整 fp_hash**（不是路径参数、不是掩码 —— 掩码提交会被服务端 400 挡下）
  const fp = 'a1b2c3d4'.repeat(8);
  const row = { fp_hash: fp, fp_masked: 'a1b2c3d4****', blocked: false };
  assert.deepEqual(block.body?.(fp), { fp_hash: fp });
  assert.deepEqual(unblock.body?.(fp), { fp_hash: fp });
  // 二次确认必须认出「改的是哪台设备」：列表里看得见的是掩码，文案就得带掩码
  assert.ok(confirmText(block, row).includes('a1b2c3d4****'), '拉黑的确认文案没带掩码');
  assert.ok(confirmText(unblock, row).includes('a1b2c3d4****'), '解封的确认文案没带掩码');
  // 拉黑是真阻断（RiskService::check() 在规则循环前对所有规则短路）⇒ 旧的「不阻断任何请求」口径必须消失。
  // 两种有表的语言各查一遍：en 是其余 11 种语言的回落表，只查 zh 会让 en 那半漏掉这句。
  setCode('zh');
  assert.ok(confirmText(block, row).includes('阻断'), 'zh 拉黑文案必须说清会阻断');
  setCode('en');
  assert.ok(confirmText(block, row).includes('**blocked outright**'), 'en 拉黑文案必须说清会阻断');
  for (const stale of ['不会真正阻断', 'does not actually block']) {
    for (const code of ['zh', 'en']) {
      setCode(code);
      assert.ok(!confirmText(block, row).includes(stale), `${code}: 「不阻断任何请求」是过期口径`);
    }
  }
  setCode('en');
  assert.ok(reportText(block, { fp_masked: 'a1b2c3d4****' }).includes('a1b2c3d4****'));
});

test('IP 名单：粘贴式动作逐个对路由表，且确认文案带粘贴的标识', () => {
  const paths: string[] = [];
  for (const flag of RISK_IP_FLAGS) {
    paths.push(flag.path);
    assert.ok(hasRoute('POST', flag.path), `路由表里没有 POST ${flag.path}`);
    assert.equal(flag.field.required, true, `${t(flag.label)} 的标识必须必填（服务端 decode 不了就 400）`);
    // 确认文案必须认出这次要改的是哪个标识 —— 名单类动作看不见落点，全靠这句话
    assert.ok(flag.confirm('1.2.3.4').includes('1.2.3.4'), `${t(flag.label)} 的确认文案没带上标识`);
  }
  assert.deepEqual(paths, [
    '/admin/v1/risk/ip/block',
    '/admin/v1/risk/ip/whitelist',
    '/admin/v1/risk/ip/recheck',
  ]);
  // IP 端点收的是**原文** ip（服务端自己算 sha256）；列表只回掩码 ⇒ 这页保留粘贴表单
  assert.equal(RISK_IP_FLAGS[0].field.name, 'ip');
  assert.ok(!paths.some((path) => path.endsWith('/unblock')), 'IP 没有解封端点（撤销 = 再写一次覆盖）');
  // appeal 与 whitelist 在控制器里逐字节同效（都写 internal_whitelist/100）⇒ 本批故意不摆第二个按钮。
  // 路由存在故这里断言「存在」：这是书面记录，不是漏接。
  assert.ok(hasRoute('POST', '/admin/v1/risk/ip/appeal'), 'appeal 路由应存在（本批故意不接）');
  assert.ok(!paths.some((path) => path.endsWith('/appeal')));
});
