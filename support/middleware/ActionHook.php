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

namespace support\middleware;

use support\bootstrap\Container;
use Webman\MiddlewareInterface;
use Webman\Http\Response;
use Webman\Http\Request;

class ActionHook implements MiddlewareInterface
{
    public function process(Request $request, callable $next) : Response
    {
        if ($request->controller) {
            // 禁止直接访问beforeAction afterAction
            if ($request->action === 'beforeAction' || $request->action === 'afterAction') {
                return response('<h1>404 Not Found</h1>', 404);
            }
            $controller = Container::get($request->controller);
            if (method_exists($controller, 'beforeAction')) {
                $before_response = call_user_func([$controller, 'beforeAction'], $request);
                if ($before_response instanceof Response) {
                    return $before_response;
                }
            }
            $response = $next($request);
            if (method_exists($controller, 'afterAction')) {
                $after_response = call_user_func([$controller, 'afterAction'], $request, $response);
                if ($after_response instanceof Response) {
                    return $after_response;
                }
            }
            return $response;
        }
        return $next($request);
    }
}