/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import assert from 'node:assert/strict';
import test from 'node:test';
import { api } from '../lib/api.ts';
import { buildPayload, draftFrom, firstMissing } from '../lib/crud.ts';
// 路由表读取器与模块组/风控组共用（见 lib/route-fixtures.ts 的说明）
import { routes } from '../lib/route-fixtures.ts';
import { t, setCode } from '../i18n/index.ts';
import { ADMIN_USER_CRUD, SELF_BLOCK, blockSelf, selfBlock } from './adminUsers.ts';

/**
 * 自我保护提示语的**成品**（en）。`selfBlock` 返回的是 `t(SELF_BLOCK)` 的结果，
 * 而 `SELF_BLOCK` 是键 ⇒ 拿键去比是比两样东西，比对 `t(SELF_BLOCK)` 又是自证；
 * 写成英文成品，键换错、漏取译文、译文被改都会红。
 */
const SELF_BLOCK_TEXT =
  'You cannot disable or delete the admin account you are signed in with; use another admin account.';

/**
 * 本模块要读「当前登录的管理员是谁」（session.user ← localStorage）与 window 上的确认框，
 * 而 node --test 下没有 DOM。这里补最小替身：断言的是**该不该发请求**，不是对话框长什么样。
 */
const ME = 'MEhashid';
const dialogs: string[] = [];

globalThis.localStorage = {
  getItem: (key: string) => (key === 'react_admin_user' ? JSON.stringify({ id: ME, username: 'me', real_name: '我' }) : null),
  setItem: () => {},
  removeItem: () => {},
} as unknown as Storage;

let confirmAnswer = true;
globalThis.window = {
  alert: (message: string) => dialogs.push(`alert:${message}`),
  confirm: (message: string) => {
    dialogs.push(`confirm:${message}`);
    return confirmAnswer;
  },
  prompt: (message: string) => {
    dialogs.push(`prompt:${message}`);
    return 'pw';
  },
} as unknown as Window & typeof globalThis;

/** 发出去的请求长什么样（角色清空必须真的到服务端，别在路上被编码吃掉）。 */
const sent: { url: string; method: string; contentType: string | undefined; body: string | undefined }[] = [];
let answer: unknown = {};
globalThis.fetch = (async (url: unknown, init: { method?: string; headers?: Record<string, string>; body?: string } = {}) => {
  sent.push({ url: String(url), method: init.method ?? 'GET', contentType: init.headers?.['Content-Type'], body: init.body });
  return { json: async () => ({ code: 0, message: 'ok', data: answer }) };
}) as unknown as typeof fetch;

/** 管理员端点的路由形态：`Route::resource('/user')` 自动注册，route.php 里没有逐条 get/put/del。 */
test('管理员：Route::resource 把 index/store 挂在 /user 本身（没有 /list 与 /create）', () => {
  assert.ok(routes.includes("Route::group('/admin/v1'"), '/admin/v1 组前缀变了，下面的相对路径断言会失真');
  assert.ok(routes.includes("Route::resource('/user'"), '/user 不是 Route::resource 注册的');
  assert.equal(ADMIN_USER_CRUD.base, '/admin/v1/user');
  // createPath 缺省会落到 `${base}/create`（RowBrowser 的兜底）—— 那个段不存在，点了必然 404
  assert.equal(ADMIN_USER_CRUD.createPath, ADMIN_USER_CRUD.base);
  for (const ghost of ['/list', '/create']) {
    // 带引号比：'/platform/user/list' 里没有 `'/user/list'`（引号在 /platform 前面），不会误判
    assert.ok(!routes.includes(`'/user${ghost}'`), `路由表里冒出了 /user${ghost}`);
  }
  // 与「平台用户」（C 端玩家）是两个模块：名词不同、路径不同。
  // 名词是**键**（渲染期取译文）⇒ 断言英文成品，键换成平台用户那条就在这里红。
  setCode('en');
  assert.equal(ADMIN_USER_CRUD.noun, 'nav.admins');
  assert.equal(t(ADMIN_USER_CRUD.noun), 'Admins');
  assert.notEqual(ADMIN_USER_CRUD.base, '/admin/v1/platform/user/list');
  // 删除要密码（UserController::destroy 走 confirmPassword）⇒ 必须有确认框这一环
  assert.equal(typeof ADMIN_USER_CRUD.deleteBody, 'function');
  // 无独立启停端点（只有批量 /user/batch/status）：单行启停走 PUT {status}
  assert.equal(ADMIN_USER_CRUD.toggle, 'update');
});

test('自我保护：停用/删除当前登录的自己被挡（只提示，一个请求都不发）', () => {
  setCode('en');
  // 唯一那一条提示语确实是「自我保护」那条键
  assert.equal(SELF_BLOCK, 'admins.self_guard');
  // 纯函数：同一个 hashid = 自己；任一边取不到就不挡（拿不准别把别人的行锁死）
  assert.equal(selfBlock(ME, ME), SELF_BLOCK_TEXT);
  assert.equal(selfBlock('other', ME), null);
  assert.equal(selfBlock('', ME), null);
  assert.equal(selfBlock(ME, ''), null);
  // 行 id 与登录回包的 user.id 同源（都是 hashid），行级守卫直接比字符串
  assert.equal(blockSelf({ id: ME, username: 'me' }), SELF_BLOCK_TEXT);
  assert.equal(blockSelf({ id: 'other', username: 'bob' }), null);

  // 启停：守卫在 RowBrowser 发请求之前调用，拦住即无请求
  assert.equal(ADMIN_USER_CRUD.toggleBlock?.({ id: ME }), SELF_BLOCK_TEXT);
  assert.equal(ADMIN_USER_CRUD.toggleBlock?.({ id: 'other' }), null);
  // 启停**只有**行内按钮这一条路：编辑框里若再摆一个 status 开关，就是绕过守卫的第二条路
  // （对自己翻开、保存即把自己停用），且那条路一个提示都不会给
  assert.ok(
    !(ADMIN_USER_CRUD.editFields ?? []).some((field) => field.name === 'status'),
    'status 不能出现在编辑表单里',
  );
  assert.ok((ADMIN_USER_CRUD.fields ?? []).some((field) => field.name === 'status'), '新建时仍可指定初始状态');

  // 删除：deleteBody 返回 null = RowBrowser 连确认框都不弹、一个请求都不发
  dialogs.length = 0;
  assert.equal(ADMIN_USER_CRUD.deleteBody?.({ id: ME, username: 'me' }), null);
  assert.deepEqual(dialogs, [`alert:${SELF_BLOCK_TEXT}`]);

  // 反面对照：不是自己 ⇒ 确认 + 要密码，body 里带 password（后端 confirmPassword 收的就是它）
  dialogs.length = 0;
  confirmAnswer = true;
  assert.deepEqual(ADMIN_USER_CRUD.deleteBody?.({ id: 'other', username: 'bob' }), { password: 'pw' });
  assert.equal(dialogs.length, 2);
  assert.ok(dialogs[0].startsWith('confirm:'), '删除必须先二次确认');
  assert.ok(dialogs[0].includes('bob'), '确认文案要认得出删的是谁');
  assert.ok(dialogs[1].startsWith('prompt:'), '删除要输入当前登录密码');

  // 确认框点了取消 ⇒ 也不发（连密码都不问）
  dialogs.length = 0;
  confirmAnswer = false;
  assert.equal(ADMIN_USER_CRUD.deleteBody?.({ id: 'other', username: 'bob' }), null);
  assert.equal(dialogs.length, 1);
  confirmAnswer = true;
});

test('重置密码：同一个 PUT 同时带 password（新密码）与 admin_password（当前操作者密码）', () => {
  setCode('en');
  // 动作 label 是键（按钮渲染时才 t()）⇒ 按**成品**找，键写错就找不到这条动作
  const action = (ADMIN_USER_CRUD.actions ?? []).find((item) => t(item.label) === 'Reset password');
  assert.ok(action, '缺「重置密码」动作');
  // 没有独立的重置端点：走的就是 Route::resource 的 PUT /user/{hashid}
  assert.equal(action.method, 'PUT');
  assert.equal(action.path('Xk9hashid'), `${ADMIN_USER_CRUD.base}/Xk9hashid`);

  const fields = action.fields ?? [];
  assert.deepEqual(
    fields.map((field) => field.name),
    ['password', 'admin_password'],
  );
  for (const field of fields) {
    assert.ok(field.required, `${field.name} 必须必填：缺一个后端就 422，前端先挡住`);
    assert.equal(field.type, 'password', `${field.name} 要遮挡输入`);
  }
  // 提交体里两个键同时在：不是二选一，也不是同一个字段名（password 已被新密码占用）
  assert.deepEqual(buildPayload(fields, { password: 'NewPass123', admin_password: 'OldPass123' }), {
    password: 'NewPass123',
    admin_password: 'OldPass123',
  });
  // 只填新密码：必填校验先挡住（真发出去会被 confirmPassword 按空密码 422 拒）
  assert.ok(firstMissing(fields, { password: 'NewPass123', admin_password: '' }));

  // 编辑表单里不能再出现 password：那条路不带 admin_password，必然会 422
  assert.ok(!(ADMIN_USER_CRUD.editFields ?? []).some((field) => field.name === 'password'));
  // 新建表单里有 password（store 必填）
  assert.ok((ADMIN_USER_CRUD.fields ?? []).some((field) => field.name === 'password' && field.required));
  // 用户名创建后不可改（update 的 validator 里没有它）——只读展示才说明得了这件事
  assert.ok((ADMIN_USER_CRUD.editFields ?? []).some((field) => field.name === 'username' && field.readOnly));
  // 确认文案认得出对象是谁
  const text = typeof action.confirm === 'function' ? action.confirm({ id: 'Xk9hashid', username: 'bob' }) : action.confirm;
  assert.ok(text?.includes('bob'), '重置密码的确认文案要认得出是谁');
});

test('role_ids：清空角色要照发空数组（urlencoded 会把这个键整个丢掉 ⇒ 后端 has() 判假、静默不 sync）', () => {
  const field = (ADMIN_USER_CRUD.editFields ?? []).find((item) => item.name === 'role_ids');
  assert.ok(field, '缺 role_ids 字段');
  // 多选列表（角色之间没有父子关系，不是权限树）；值域只能异步拉（模块级常量里拿不到角色表）
  assert.equal(field.type, 'multi');
  assert.equal(typeof field.options, 'function');

  const row = { id: 'Xk9', username: 'bob', role_ids: ['r1', 'r2'] };
  const draft = draftFrom([field], row);
  assert.equal(draft.role_ids, 'r1\nr2'); // 行数组 → 一行一值
  assert.deepEqual(buildPayload([field], draft, row), {}); // 没动过 ⇒ 不发（sync 是整表替换）
  assert.deepEqual(buildPayload([field], { role_ids: '' }, row), { role_ids: [] }); // 全清 ⇒ 发空数组
  assert.deepEqual(buildPayload([field], { role_ids: '' }), {}); // 新建留空 ⇒ 不发
  assert.deepEqual(buildPayload([field], { role_ids: 'r1' }, row), { role_ids: ['r1'] }); // 挑掉一个 ⇒ 只剩它
});

test('角色多选的值域：拉 /role 并在开框时才解析（hashid 当值、名称+标识当标签、停用要标注）', async () => {
  const field = (ADMIN_USER_CRUD.fields ?? []).find((item) => item.name === 'role_ids');
  assert.ok(field && typeof field.options === 'function');
  answer = {
    list: [
      { id: 'r1', name: '超级管理员', slug: 'super_admin', status: 1 },
      { id: 'r2', name: '运营', slug: 'ops', status: 0 },
      { id: '', name: '没有 id 的行', slug: 'x', status: 1 }, // 取不到 hashid 的行不能进值域（选了也提交不了）
    ],
  };
  const options = await (field.options as () => Promise<{ value: string; label: string; params?: Record<string, unknown> }[]>)();
  // name/slug 是**动态参数**，不能冻进键里（否则每个角色都要一条键）⇒ 断言「键 + params」两件套，
  // 再按 en 表渲染一遍看成品：键换错（启停两条混用）或参数名写错都会红。
  assert.deepEqual(options, [
    { value: 'r1', label: 'admins.role_option', params: { name: '超级管理员', slug: 'super_admin' } },
    // 停用的角色照给但标注出来：服务端 sync 照样收，只是不授予任何权限
    { value: 'r2', label: 'admins.role_option_off', params: { name: '运营', slug: 'ops' } },
  ]);
  setCode('en');
  assert.deepEqual(
    options.map((option) => t(option.label, option.params)),
    ['超级管理员 (super_admin)', '运营 (ops) (disabled)'],
  );
  // 分页参数取大值：走默认 15 条时，第 16 个角色起会从下拉里凭空消失
  assert.ok(sent.at(-1)?.url.includes('/admin/v1/role?'), '角色候选取自 GET /admin/v1/role');
  assert.ok(sent.at(-1)?.url.includes('limit=200'));
});

test('role_ids 走 JSON 请求体（空数组也照发）：urlencoded 里 [] 表达不出来，键会整个消失', async () => {
  await api(`${ADMIN_USER_CRUD.base}/Xk9hashid`, { method: 'PUT', body: { role_ids: [] } });
  const last = sent.at(-1);
  assert.ok(last, '没有发出请求');
  assert.equal(last.method, 'PUT');
  assert.equal(last.contentType, 'application/json');
  assert.equal(last.body, '{"role_ids":[]}');
  assert.ok(!String(last.body).includes('role_ids%5B%5D'), '不能是 urlencoded 形态');
});
