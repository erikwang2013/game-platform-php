#!/bin/bash
# 两条「编译器看不见」的 i18n 不变量（C 端 apps/harmonyos）。为什么编译器管不了它们：
#
# ① 某个 @Component 少挂一行 `@StorageLink('app_locale')` —— 语法、类型全合法。
#    ArkUI 只在 build() 里**读过**该状态变量时才把它登记成依赖（见 LocaleController 顶部说明），
#    所以「切了语言这页字没变」在编译期**零症状**：不报错、不崩溃，只是那一块不重绘。
# ② `tables['xx'] = XX;` 漏登记一行，或 `SUPPORTED_LOCALES` 里多/少一项 —— 类型上同样全对：
#    `TABLES[code]` 取不到值只是**回落到 EN**，界面照常渲染、只是变成英文。
#    （真值面是后端 `common\Locale::SUPPORTED`；`i18n/LocaleController.ets` 是它的手工镜像。）
#
# 两条都只能在这里拦。跑法：bash apps/harmonyos/check-i18n-invariants.sh
# 阳性对照（改完脚本请重做）：删/改任一组件的那行 ⇒ ① 报出该组件；把某个 tables['xx'] 改名 ⇒ ② 报红。
set -u

cd "$(dirname "$0")"
ets="entry/src/main/ets"
i18n="$ets/i18n"
fail=0

# ① 逐组件判（不是逐文件）：Components.ets 里 6 个组件只有 5 个渲染文案，
#    按文件计数会把「CopyrightBar 没挂」误报成缺口。
#    匹配 t( 时排除 Text( 这类「词尾恰好是 t」的调用点：要求 t 前面不是字母数字下划线。
comps=$(for f in $(find "$ets" -name '*.ets' | sort); do
  awk -v F="${f#$ets/}" '
    /^@Component$/ { if (n != "") printf "%-24s %s %s %s\n", n, u, l, F; n = ""; u = 0; l = 0; next }
    {
      if (n == "") { if (match($0, /struct[ \t]+[A-Za-z_][A-Za-z0-9_]*/)) n = substr($0, RSTART + 7, RLENGTH - 7) }
      if ($0 ~ /(^|[^A-Za-z0-9_])t1?\(/) u = 1
      if ($0 ~ /StorageLink\(.app_locale.\)/) l = 1
    }
    END { if (n != "") printf "%-24s %s %s %s\n", n, u, l, F }
  ' "$f"
done)

# 「解析到 0 项」绝不能当通过：模式一旦失效，空集比空集永远相等（同族假绿，实测踩过）
renderers=$(printf '%s\n' "$comps" | awk '$2 == 1' | wc -l)
if [ "$renderers" -eq 0 ]; then
  echo "① FAIL: 一个渲染 t() 的组件都没解析到 ⇒ 是脚本的模式失效了，不是「没有缺口」"
  fail=1
else
  echo "① 渲染 t()/t1() 的组件: $renderers 个"
  missing=$(printf '%s\n' "$comps" | awk '$2 == 1 && $3 == 0 { print "     " $1 "  (" $4 ")" }')
  if [ -n "$missing" ]; then
    echo "   FAIL: 下列组件渲染文案但没挂 @StorageLink('app_locale') ⇒ 切换语言它不重绘"
    echo "$missing"
    fail=1
  fi
fi

# ② 两张清单必须**等价**（不是「数了一下都是 13」：数数会把「少一个 + 多一个」读成通过）
tbl=$(grep -o "^[[:space:]]*tables\['[a-z]*'\]" "$i18n/Translations.ets" | sed "s/.*'\([a-z]*\)'.*/\1/" | sort -u)
sup=$(grep -o "^[[:space:]]*new AppLocale('[a-z]*'" "$i18n/LocaleController.ets" | sed "s/.*'\([a-z]*\)'.*/\1/" | sort -u)
if [ -z "$tbl" ] || [ -z "$sup" ]; then
  echo "② FAIL: 两张清单至少一张解析到 0 项（TABLES=$(printf '%s' "$tbl" | wc -l) SUPPORTED_LOCALES=$(printf '%s' "$sup" | wc -l)）⇒ 脚本的模式失效了，不是「没有缺口」"
  fail=1
else
  echo "② TABLES $(printf '%s\n' "$tbl" | wc -l) 项；SUPPORTED_LOCALES $(printf '%s\n' "$sup" | wc -l) 项"
  if [ "$tbl" != "$sup" ]; then
    echo "   FAIL: 两张清单不等价（左＝只在 TABLES / 右＝只在 SUPPORTED_LOCALES）"
    comm -3 <(printf '%s\n' "$tbl") <(printf '%s\n' "$sup") | sed 's/^/     /'
    fail=1
  fi
fi

[ "$fail" = 0 ] && echo "OK: 两条 i18n 静态不变量都成立"
exit "$fail"
