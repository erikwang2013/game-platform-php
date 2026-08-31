<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

return [
    // 合规检查总开关：false=全部跳过（默认，充值与提现流程行为与改造前完全一致）。
    // 开启后仅执行已实现的判定逻辑（当前 KYC/AML 判定为法务定义项，尚未实现，钩子为 no-op 挂载点）。
    'enabled' => (bool) (getenv('COMPLIANCE_ENABLED') ?: false),
];
