<?php
namespace app\controller;

use support\Request;

class Test
{
    public function ip(Request $request)
    {
        $ip = GetIP();
        return response($ip);
    }

    public function getHeader(Request $request)
    {
        $header = $request->header();
        return response(json_encode($header));
    }

    public function hotUpdate(Request $request)
    {
        return response('我是热梗新');
    }

    public function checkEnv(Request $request)
    {
        $header = $request->header();
        return response(json_encode($header));
    }
}
