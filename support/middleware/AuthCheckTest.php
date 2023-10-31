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

use Webman\MiddlewareInterface;
use Webman\Http\Response;
use Webman\Http\Request;

class AuthCheckTest implements MiddlewareInterface
{
    public function process(Request $request, callable $next) : Response
    {
        //echo $request->controller;
        $notCheck = [
            'app\controller\Login',
            'app\controller\Test',
        ];
        if (!in_array($request->controller, $notCheck)) {
            $session = $request->session();
            //echo $session->get('userId');
            if (!$session->get('userId')) {
                return redirect('/login.html');
            }
        }
        return $next($request);
    }
}