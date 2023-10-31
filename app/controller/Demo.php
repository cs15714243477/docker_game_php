<?php
namespace app\controller;

use support\Request;
use support\Db;
use support\bootstrap\Redis;
use app\model\GameUser;
use IpLocation;
use PHPGangsta_GoogleAuthenticator;

class Demo
{
    public function db(Request $request)
    {
        //Db::connection('mongodb')->collection('counters')->get();
        return json(Db::connection('mongodb_main')->collection('counters')->get());
    }

    public function gameuser(Request $request)
    {
        //Db::connection('mongodb')->collection('counters')->get();
        $user = GameUser::all();
        return json($user);
    }

    public function redis(Request $request)
    {
        $key = 'test_key';
        Redis::setEx($key, 30, rand());
        return response(Redis::get($key));
    }

    public function redis2(Request $request)
    {
        $redis = Redis::connection('cache');
        $rs = $redis->get('test_key');
        return response($rs);
    }

    public function ip(Request $request)
    {
        $b = new IpLocation();dd($b);dd('pppp');
        $a = new PHPGangsta_GoogleAuthenticator();dd($a);


    }

    private function view(Request $request)
    {
        return view('index/view', ['name' => 'webman2']);
    }

    private function file(Request $request)
    {
        $file = $request->file('upload');
        if ($file && $file->isValid()) {
            $file->move(public_path().'/files/myfile.'.$file->getUploadExtension());
            return json(['code' => 0, 'msg' => 'upload success']);
        }
        return json(['code' => 1, 'msg' => 'file not found']);
    }

    
}
