<?php
namespace app\controller;

use app\model\Menu;
use support\Request;

class Index
{
    public function index(Request $request)
    {
        //return response('hello webman');
        $userName = session("userName");
        return view('index/index', ['userName' => $userName, 'staticUrl' => env('STATIC_URL')]);
    }

    public function view(Request $request)
    {
        return view('index/view', ['name' => 'webman2']);
    }

    public function menu(Request $request)
    {
        $menu = [];
        $menuPurview = $request->session()->get('menuPurview');
        $data = Menu::where(['pid' => 0, 'status' => 1])->orderBy('sort', 'asc')->get();
        foreach ($data as $k => &$v) {
            if (!in_array($v["id"], $menuPurview)) {
                unset($data[$k]);
                continue;
            }
        }
        foreach ($data as $k => $v) {
            $data[$k]["children"] = Menu::where(['pid' => $v['id'], 'status' => 1])->orderBy('sort', 'asc')->get();
        }
        foreach ($data as $k => &$v) {
            $temp = [];
            $temp['id'] = $v['id'];
            $temp['title'] = $v['name'];
            $temp['type'] = 0;
            $temp['icon'] = 'layui-icon ' . $v['iconFont'];
            $temp['href'] = '';
            $temp['children'] = [];
            //$menu[] = $temp;
            foreach($v["children"] as $kk => &$vv){
                if (!in_array($vv["id"], $menuPurview)) {
                    unset($data[$k]["children"][$kk]);
                    continue;
                }
                $temp2 = [];
                $temp2['id'] = $vv['id'];
                $temp2['title'] = $vv['name'];
                $temp2['type'] = 1;
                $temp2['openType'] = '_iframe';
                $temp2['icon'] = 'layui-icon ' . $vv['iconFont'];
                $temp2['href'] = $vv['url'];
                $temp['children'][] = $temp2;
            }
            $menu[] = $temp;
        }
        return json($menu);
    }

    public function file(Request $request)
    {
        $file = $request->file('upload');
        if ($file && $file->isValid()) {
            $file->move(public_path().'/files/myfile.'.$file->getUploadExtension());
            return json(['code' => 0, 'msg' => 'upload success']);
        }
        return json(['code' => 1, 'msg' => 'file not found']);
    }

    public function phpinfo(Request $request)
    {
        return response(phpinfo());
    }

    public function login(Request $request)
    {
        return json(['code' => 0, 'msg' => 'ok']);
    }
    
}
