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

use Webman\Route;


Route::any('/test', function ($request) {
    return response('test');
});

Route::any('/apps/platform.php?act=platformView', function ($request) {
    return response('apps/platform.php?act=platformView');
});

Route::any('/apps/home.php?act=global_more', function ($request) {
    return response('apps/home.php?act=global_more');
});


Route::any('/route-test', [app\controller\Index::class, 'index']);

Route::group('/apps', function () {
    Route::any('/home.php', [app\controller\Home::class, 'index']);
    Route::any('/platform.php', [app\controller\Platform::class, 'index']);
    Route::any('/order.php', [app\controller\Order::class, 'index']);
    Route::any('/agent.php', [app\controller\Agent::class, 'index']);
    Route::any('/config.php', [app\controller\Config::class, 'index']);
    Route::any('/player.php', [app\controller\Player::class, 'index']);
    Route::any('/data.php', [app\controller\Data::class, 'index']);
    Route::any('/admin.php', [app\controller\Admin::class, 'index']);
    Route::any('/promotion.php', [app\controller\Promotion::class, 'index']);
});
