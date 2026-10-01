/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { describe, expect, it } from 'vitest';
import { PNode, checkStates, flatten, toggle, toTree } from './tree';

/**
 * 权限树 fixture 按**后端真实形状**摆：
 * - 根节点的 parent_id 是数字 0（不是 hashid）；
 * - 叶子**没有 children 键**（不是 []），P3 显式带了 children 数组；
 * - 一个兄弟节点（P2）挂在 P1 下，用它验「勾满才补父级」。
 */
const RAW = [
  {
    id: 'P1',
    name: '系统',
    slug: 'system',
    type: 1,
    parent_id: 0,
    children: [
      { id: 'P2', name: '用户', slug: 'user', type: 2, parent_id: 'P1' },
      {
        id: 'P3',
        name: '角色',
        slug: 'role',
        type: 2,
        parent_id: 'P1',
        children: [{ id: 'P4', name: '改角色', slug: 'role.edit', type: 3, parent_id: 'P3' }],
      },
    ],
  },
];

const tree = (): PNode[] => toTree(RAW);
const ids = (s: ReadonlySet<string>): string[] => [...s].sort();
const sel = (...ids: string[]): ReadonlySet<string> => new Set(ids);
const states = (s: ReadonlySet<string>): Record<string, string> =>
  Object.fromEntries(checkStates(tree(), s));

describe('toTree（原始节点 → 规范树）', () => {
  it('按 children 递归建树：叶子缺 children 键 ⇒ 空数组，不炸', () => {
    const t = tree();
    expect(t.length).toBe(1);
    expect(t[0]!.children.map((c) => c.id)).toEqual(['P2', 'P3']);
    expect(t[0]!.children[0]!.children).toEqual([]);
    expect(t[0]!.children[1]!.children[0]!.id).toBe('P4');
  });

  it('根的 parent_id 是数字 0 也照建（树靠 children 嵌套，不看 parent_id）', () => {
    const t = toTree([{ id: 'R', name: '根', parent_id: 0 }]);
    expect(t.map((n) => n.id)).toEqual(['R']);
  });

  it('children 不是数组（脏数据）⇒ 当叶子；没有 hashid 的行丢掉；整体不是数组 ⇒ 空', () => {
    expect(toTree([{ id: 'A', name: 'a', children: 'oops' }])[0]!.children).toEqual([]);
    expect(toTree([{ id: 'A', name: 'a', children: { id: 'B' } }])[0]!.children).toEqual([]);
    expect(toTree([{ name: '没有 id' }, null, 'x', { id: 'B', name: 'b' }]).map((n) => n.id)).toEqual(
      ['B'],
    );
    expect(toTree(null)).toEqual([]);
    expect(toTree({ id: 'A' })).toEqual([]);
  });

  it('原始行原样保留（name/slug/type 被改写的话，编辑一次就把改动写回库里）', () => {
    const p2 = tree()[0]!.children[0]!;
    expect(p2.row['slug']).toBe('user');
    expect(p2.name).toBe('用户');
  });
});

describe('flatten（DFS 平铺 + 折叠）', () => {
  it('depth 从 0 起、parent 是父节点名（根为空串）', () => {
    expect(flatten(tree()).map((f) => [f.node.id, f.depth, f.parent])).toEqual([
      ['P1', 0, ''],
      ['P2', 1, '系统'],
      ['P3', 1, '系统'],
      ['P4', 2, '角色'],
    ]);
  });

  it('折叠 P1：自己留着（hasKids 仍为 true，还能再展开），子树整段不见', () => {
    const out = flatten(tree(), sel('P1'));
    expect(out.map((f) => f.node.id)).toEqual(['P1']);
    expect(out[0]!.hasKids).toBe(true);
  });

  it('折叠叶子（没有子节点）等于没有折叠', () => {
    expect(flatten(tree(), sel('P2')).map((f) => f.node.id)).toEqual(['P1', 'P2', 'P3', 'P4']);
  });
});

describe('checkStates（三态判定）', () => {
  it('空集：全 off', () => {
    expect(states(sel())).toEqual({ P1: 'off', P2: 'off', P3: 'off', P4: 'off' });
  });

  it('只勾叶子 P4：自己 on，祖先 half（有后代勾了但没满）', () => {
    expect(states(sel('P4'))).toEqual({ P1: 'half', P2: 'off', P3: 'half', P4: 'on' });
  });

  it('子节点全勾 ⇒ 父级 on（P3 的子级只有 P4）；P1 因 P2 没勾仍是 half', () => {
    expect(states(sel('P3', 'P4'))).toEqual({ P1: 'half', P2: 'off', P3: 'on', P4: 'on' });
  });

  it('整棵勾满 ⇒ 全 on', () => {
    expect(states(sel('P1', 'P2', 'P3', 'P4'))).toEqual({
      P1: 'on',
      P2: 'on',
      P3: 'on',
      P4: 'on',
    });
  });

  /**
   * 存量值：某角色只有父节点（后端 sync 只存提交过的 id，老界面可以勾出这种组合）。
   * 它既不是 on（子级没勾满）也不是 off（自己确实在集合里）⇒ half。
   * 这一格决定「提交时补 hidden input 兜住」（见 tree-select）：判成 off 就会被静默吊销。
   */
  it('集合里有父节点、子级没勾 ⇒ half（不是 off：否则保存时会当成被取消）', () => {
    expect(states(sel('P1'))).toEqual({ P1: 'half', P2: 'off', P3: 'off', P4: 'off' });
  });
});

describe('toggle（父子联动）', () => {
  it('勾父级 ⇒ 连带整棵子树', () => {
    expect(ids(toggle(tree(), sel(), 'P1'))).toEqual(['P1', 'P2', 'P3', 'P4']);
  });

  it('勾中间节点 ⇒ 只连带自己的子树，父级没勾满不动', () => {
    expect(ids(toggle(tree(), sel(), 'P3'))).toEqual(['P3', 'P4']);
  });

  it('取消勾选 ⇒ 整棵子树取消，祖先没勾满的跟着取消（只剩没被碰过的 P2）', () => {
    expect(ids(toggle(tree(), sel('P1', 'P2', 'P3', 'P4'), 'P4'))).toEqual(['P2']);
  });

  it('半选的点一下就勾满（各家树控件同款），兄弟已勾满时父级自动补上', () => {
    // P3/P4 已勾（P1 half）⇒ 再勾 P2 恰好把 P1 的子节点勾全 ⇒ P1 自己进集合
    expect(ids(toggle(tree(), sel('P3', 'P4'), 'P2'))).toEqual(['P1', 'P2', 'P3', 'P4']);
  });

  it('取消父级 ⇒ 整棵子树取消', () => {
    expect(ids(toggle(tree(), sel('P1', 'P2', 'P3', 'P4'), 'P1'))).toEqual([]);
  });

  it('用户没碰过的分支原样保留（只沿点中的那条链收敛）', () => {
    const twoRoots = toTree([
      { id: 'A', name: 'A', children: [{ id: 'A1', name: 'A1' }, { id: 'A2', name: 'A2' }] },
      { id: 'B', name: 'B', children: [{ id: 'B1', name: 'B1' }, { id: 'B2', name: 'B2' }] },
    ]);
    // B 分支是存量值 {B}（half），点 A 分支不该动它
    const next = toggle(twoRoots, sel('B'), 'A1');
    expect(ids(next)).toEqual(['A1', 'B']);
  });

  it('点不存在的 id ⇒ 原集合原样返回（不炸、不空）', () => {
    expect(ids(toggle(tree(), sel('P2'), 'NOPE'))).toEqual(['P2']);
  });
});
