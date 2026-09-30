/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import assert from 'node:assert/strict';
import test from 'node:test';
import { buildPayload, draftFrom, type Field } from '../lib/crud.ts';
// 路线表读取器与风控那组用例共用（文件按批次拆开，见 lib/route-fixtures.ts 的说明）
import { hasRoute, rel, routes } from '../lib/route-fixtures.ts';
import {
  ACHIEVEMENT_CRUD,
  CATEGORY_CRUD,
  CDN_CRUD,
  COUPON_CRUD,
  GAME_CRUD,
  IDENTITY_CRUD,
  PAYMENT_CRUD,
  PERMISSION_CRUD,
  ROLE_CRUD,
  TICKET_CRUD,
  WITHDRAW_LIMIT_CRUD,
  WITHDRAW_ORDER_CRUD,
} from './modules.ts';

/**
 * 本批模块的路径/方法与后端路由表对账 —— 对不上就是「按钮点了必然 404」，只能靠人眼发现的那种。
 * 路由表里的路径是相对 `Route::group('/admin/v1')` 的，故这里比相对段（前缀本身也一并钉住）。
 */

test('角色 / 权限：Route::resource 把 index 与 store 都挂在资源名本身，没有 /list 与 /create', () => {
  assert.ok(routes.includes("Route::group('/admin/v1'"), '/admin/v1 组前缀变了，下面的相对路径断言会失真');
  for (const [resource, crud] of [
    ['role', ROLE_CRUD],
    ['permission', PERMISSION_CRUD],
  ] as const) {
    assert.ok(routes.includes(`Route::resource('/${resource}'`), `/${resource} 不是 Route::resource 注册的`);
    // 新建 POST 到资源名本身：/role/create 这种段不存在（ConfigController 同款口径）
    assert.equal(crud.createPath, crud.base);
    for (const ghost of ['/list', '/create']) {
      assert.ok(!routes.includes(`'/${resource}${ghost}'`), `路由表里冒出了 /${resource}${ghost}`);
    }
  }
});

test('身份审核 / 工单：是动作型（无增改删端点 ⇒ 不该长出按钮），三个动作路径与方法逐个对上', () => {
  for (const crud of [IDENTITY_CRUD, TICKET_CRUD]) {
    // 缺 fields ⇒ 不显示「+ 新建」「编辑」；缺 labelKey ⇒ 不显示「删除」
    assert.equal(crud.fields, undefined);
    assert.equal(crud.labelKey, undefined);
  }
  const endpoints = [
    ...(IDENTITY_CRUD.actions ?? []).map((action) => [action.method ?? 'POST', action.path('{hashid}')] as const),
    ...(TICKET_CRUD.actions ?? []).map((action) => [action.method ?? 'POST', action.path('{hashid}')] as const),
  ];
  assert.deepEqual(
    endpoints,
    [
      ['PUT', '/admin/v1/identity/review'],
      ['PUT', '/admin/v1/identity/review'],
      ['POST', '/admin/v1/ticket/{hashid}/reply'],
      ['POST', '/admin/v1/ticket/{hashid}/close'],
      ['POST', '/admin/v1/ticket/{hashid}/assign'],
    ],
  );
  for (const [method, path] of endpoints) {
    assert.ok(routes.includes(`Route::${method.toLowerCase()}('${rel(path)}'`), `路由表里没有 ${method} ${path}`);
  }
});

test('角色的「权限」多选、权限的「父权限」：值域异步拉，且父权限只在新建时给', () => {
  const names = (fields: Field[] | undefined): string[] => (fields ?? []).map((field) => field.name);

  const multi = (ROLE_CRUD.fields ?? []).find((field) => field.name === 'permission_ids');
  assert.ok(multi, '角色表单缺 permission_ids');
  assert.equal(multi.type, 'multi');
  // 值域来自权限树端点，只可能异步给（模块级常量里拿不到数据）
  assert.equal(typeof multi.options, 'function');
  assert.ok(names(ROLE_CRUD.editFields).includes('permission_ids'), '编辑态要能改权限（行里的 permission_ids 回填）');

  assert.ok(names(PERMISSION_CRUD.fields).includes('parent_id'), '权限表单缺 parent_id');
  assert.ok(
    !names(PERMISSION_CRUD.editFields).includes('parent_id'),
    'parent_id 必须 createOnly：update 不收它，摆上去就是骗人',
  );
});

test('多选字段往返：行数组 → 草稿一行一值；未改动不发、清空发空数组、新建留空不发', () => {
  const field = (ROLE_CRUD.fields ?? []).find((item) => item.name === 'permission_ids');
  assert.ok(field);
  const row = { permission_ids: ['p1', 'p2'] };
  const draft = draftFrom([field], row);
  assert.deepEqual(draft, { permission_ids: 'p1\np2' });
  // 没动过 ⇒ 不发：后端 sync 是整表替换，空发一次会把「没打算改的授权」重写一遍
  assert.deepEqual(buildPayload([field], draft, row), {});
  // 全清 ⇒ 发空数组（后端 sync([]) 解绑全部），不是「留空不提交」
  assert.deepEqual(buildPayload([field], { permission_ids: '' }, row), { permission_ids: [] });
  // 新建留空 ⇒ 不发（后端 $request->has 为假，整段 sync 跳过）
  assert.deepEqual(buildPayload([field], { permission_ids: '' }), {});
});

/* ------------------------------ 批次 4：资金 ------------------------------ */

/**
 * 每个写端点（新建/更新/删除/启停/动作/只读视图）逐个对路由表。
 * 这是「按钮点了必然 404」的唯一自动护栏 —— 配置里的路径是拼出来的，肉眼看不见。
 */
test('资金模块：写端点与只读视图逐个对上路由表（路径 + 方法）', () => {
  const endpoints: [string, string][] = [
    ...[PAYMENT_CRUD, CDN_CRUD, COUPON_CRUD].flatMap((crud) => [
      ['POST', String(crud.createPath)],
      ['PUT', `${crud.base}/{hashid}`],
      ['DELETE', `${crud.base}/{hashid}`],
    ] as [string, string][]),
    ['POST', String(PAYMENT_CRUD.toggle)],
    ['POST', String(CDN_CRUD.toggle)],
    ['POST', '/admin/v1/withdraw/limits/set'],
    ['PUT', '/admin/v1/withdraw/limits/{hashid}'],
  ];
  // 动作与视图的路径直接取自配置（拼错就跟路由表对不上）
  for (const crud of [WITHDRAW_ORDER_CRUD, CDN_CRUD]) {
    for (const action of crud.actions ?? []) {
      endpoints.push([action.method ?? 'POST', action.path('{hashid}')]);
    }
    for (const view of crud.views ?? []) {
      endpoints.push(['GET', view.path('{hashid}')]);
    }
  }
  for (const [method, path] of endpoints) {
    assert.ok(hasRoute(method, path), `路由表里没有 ${method} ${path}`);
  }
});

test('金额字段一律 text —— number 控件会吃掉小数位（禁止浮点参与金额）', () => {
  // 只取用到的那两个字段：这里不该 import components 的类型（RowBrowser 是 .tsx，node 的
  // --test 走 type-strip 解析不了 JSX，import type 会把整个模块拖进来）
  const cases: [{ noun: string; fields?: Field[] }, string[]][] = [
    [WITHDRAW_LIMIT_CRUD, ['single_min', 'single_max', 'daily_limit', 'monthly_limit', 'fee_pct', 'fee_max', 'auto_approve_threshold']],
    [PAYMENT_CRUD, ['min_amount', 'max_amount']],
    [COUPON_CRUD, ['value', 'min_amount', 'max_discount']],
  ];
  for (const [crud, names] of cases) {
    for (const name of names) {
      const field = (crud.fields ?? []).find((item) => item.name === name);
      assert.ok(field, `${crud.noun} 缺字段 ${name}`);
      assert.equal(field.type, 'text', `${crud.noun} 的 ${name} 必须是 text（金额/费率）`);
    }
  }
});

/**
 * 资金动作的二次确认：文案里必须同时认得出**对象**与**金额** —— 少了任一项，
 * 运营就是在给一个看不见的东西签字。同样钉住「确认后读服务端 message」。
 */
test('提现资金动作：确认文案带订单标识 + 金额，且 report 走服务端 message', () => {
  const row = {
    id: 'Xk9hashid',
    order_no: 'W20260930001',
    platform_amount: '100.0000',
    fiat_amount: '14.0000',
    currency: 'USD',
    status: 'approved',
    payout_batch_id: 'BATCH-1',
    reviewer_id: 7,
  };
  const actions = WITHDRAW_ORDER_CRUD.actions ?? [];
  // 动作型：没有增改删端点 ⇒ 一个按钮都不该长（长了就是必然 404）
  assert.equal(WITHDRAW_ORDER_CRUD.fields, undefined);
  assert.equal(WITHDRAW_ORDER_CRUD.labelKey, undefined);
  assert.equal(WITHDRAW_ORDER_CRUD.toggle, undefined);

  for (const label of ['通过', '驳回', '二次确认', '执行打款']) {
    const action = actions.find((item) => item.label === label);
    assert.ok(action, `提现订单缺动作「${label}」`);
    assert.ok(action.report, `「${label}」必须读服务端 message（不做乐观更新）`);
    const text = typeof action.confirm === 'function' ? action.confirm(row) : action.confirm;
    assert.ok(text, `「${label}」没有二次确认文案`);
    assert.ok(text.includes(row.order_no), `「${label}」的确认文案缺订单标识`);
    assert.ok(text.includes(row.platform_amount), `「${label}」的确认文案缺金额`);
  }

  // sync-payout 的 message 是占位符 success，文案改由 data 拼 —— 但仍是服务端返回的状态
  const sync = actions.find((item) => item.label === '同步打款');
  assert.ok(sync && typeof sync.report === 'function');
  const text = sync.report({ code: 0, message: 'success', data: { payout_status: 'success', order_status: 'completed', synced_status: 'SUCCESS' } });
  assert.ok(text.includes('completed') && text.includes('SUCCESS'), '同步结果必须显示服务端回来的三个状态');
});

test('提现订单：动作按行状态过滤，不摆点了必然 422 的按钮', () => {
  const actions = WITHDRAW_ORDER_CRUD.actions ?? [];
  const shows = (label: string, row: Record<string, unknown>): boolean => {
    const action = actions.find((item) => item.label === label);
    assert.ok(action?.when, `「${label}」没有 when 过滤`);
    return action.when(row) === true;
  };

  assert.equal(shows('执行打款', { status: 'pending' }), false);
  assert.equal(shows('执行打款', { status: 'approved' }), true);
  // 没有批次号 = 还没提交给 PayPal，同步必然 422
  assert.equal(shows('同步打款', { status: 'processing', payout_batch_id: '' }), false);
  assert.equal(shows('同步打款', { status: 'processing', payout_batch_id: 'B1' }), true);
  // 双审：已有第一审核人 ⇒「通过」再点必 422，改点「二次确认」
  assert.equal(shows('通过', { status: 'pending', reviewer_id: 7 }), false);
  assert.equal(shows('通过', { status: 'pending', reviewer_id: 0 }), true);
  assert.equal(shows('二次确认', { status: 'pending', reviewer_id: 7 }), true);
  assert.equal(shows('二次确认', { status: 'pending', reviewer_id: 0 }), false);
  // 驳回对任何 pending 都成立（后端 CAS 只看 status）
  assert.equal(shows('驳回', { status: 'pending', reviewer_id: 7 }), true);
});

test('阶梯限额：预置档位只有 PUT ⇒ 无「+ 新建」；没有删除端点 ⇒ 无 labelKey', () => {
  assert.equal(WITHDRAW_LIMIT_CRUD.createPath, null);
  assert.equal(WITHDRAW_LIMIT_CRUD.labelKey, undefined);
  const level = (WITHDRAW_LIMIT_CRUD.fields ?? []).find((field) => field.name === 'user_level');
  assert.ok(level?.readOnly, '档位标识是唯一键，只读展示');
});

test('图片字段：只有游戏封面 / 分类图标 / 成就图标三处，且新建与编辑表单都挂上', () => {
  for (const [crud, name] of [
    [GAME_CRUD, 'cover_image'],
    [CATEGORY_CRUD, 'icon'],
    [ACHIEVEMENT_CRUD, 'icon'],
  ] as const) {
    // 编辑态才是换图的主路径：两个表单都得是 image，光新建挂上等于没挂
    for (const list of [crud.fields ?? [], crud.editFields ?? []]) {
      assert.equal(list.find((field) => field.name === name)?.type, 'image', `${crud.noun}的 ${name}`);
    }
  }
  // 权限的 icon 是菜单图标名（VARCHAR(50)），不是图片 URL —— 别顺手也挂上传
  assert.ok(!(PERMISSION_CRUD.fields ?? []).some((field) => field.type === 'image'));
});

test('优惠券统计走只读视图（没有单条详情端点，detailBase 会 404）', () => {
  const views = COUPON_CRUD.views ?? [];
  assert.equal(views.length, 1);
  assert.equal(views[0].path('Xk9'), '/admin/v1/coupon/Xk9/stats');
  // create 硬编码 status=1 ⇒ 开关只在编辑表单里
  assert.ok(!(COUPON_CRUD.fields ?? []).some((field) => field.name === 'status'));
  assert.ok((COUPON_CRUD.editFields ?? []).some((field) => field.name === 'status'));
});

