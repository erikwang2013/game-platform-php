<?php
/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace support;

/**
 * Class Request
 * @package support
 */
class Request extends \Webman\Http\Request
{
    /**
     * 判断请求是否携带该字段（get + post 合并，语义与框架 all()/only() 一致）
     * 框架本身不提供 has()，且无 __call 兜底，缺它会让 24 处控制器调用 500
     */
    public function has(string $name): bool
    {
        return array_key_exists($name, $this->all());
    }
}