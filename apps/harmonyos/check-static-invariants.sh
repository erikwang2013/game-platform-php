#!/bin/bash
# 两条「编译器看不见」的静态不变量。本版 ArkTS 编译器对这两条都是零灵敏，实测：
#   ① ErrorView 的 onRetry 是可选成员 —— 写成必填 plain 成员会在**声明处**报
#      「Property 'onRetry' has no initializer」；加 @Prop 且不给默认值则 7 个调用点一条都不报。
#   ② EntryAbility 里 init() 与 isLoggedIn() 的先后顺序只管行为，类型上怎么排都合法。
# 所以只能在这里拦。跑法：bash apps/harmonyos/check-static-invariants.sh
set -u

cd "$(dirname "$0")"
pages_dir="entry/src/main/ets/pages"
ability="entry/src/main/ets/entryability/EntryAbility.ets"
fail=0

# ① 每个 ErrorView 调用点都必须传 onRetry，否则「重试」按钮不渲染（见 common/Components.ets）
total=$(grep -oh "ErrorView({" "$pages_dir"/*.ets | wc -l)
hooked=$(grep -oh "onRetry:" "$pages_dir"/*.ets | wc -l)
echo "① ErrorView 调用点: $total ；onRetry: $hooked"
if [ "$total" != "$hooked" ]; then
  echo "   FAIL: 有 ErrorView 漏传 onRetry（重试按钮不会渲染）"
  grep -n "ErrorView({\|onRetry:" "$pages_dir"/*.ets
  fail=1
fi

# ② EntryAbility 必须先 TokenManager.init() 再读 isLoggedIn()：AppStorage 是纯内存，
#    不 init 就接不上 preferences，冷启动恒 false ⇒ 永远落到 LoginPage（GameHallPage 那一支是死代码）
init_line=$(grep -n "TokenManager\.init(" "$ability" | head -1 | cut -d: -f1)
use_line=$(grep -n "TokenManager\.isLoggedIn()" "$ability" | head -1 | cut -d: -f1)
echo "② EntryAbility: init 在第 ${init_line:-无} 行，isLoggedIn() 在第 ${use_line:-无} 行"
if [ -z "$init_line" ] || [ -z "$use_line" ] || [ "$init_line" -ge "$use_line" ]; then
  echo "   FAIL: TokenManager.init() 缺失或晚于 isLoggedIn()，冷启动会丢掉登录态"
  fail=1
fi

[ "$fail" = 0 ] && echo "OK: 两条静态不变量都成立"
exit "$fail"
