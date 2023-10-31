<?php
namespace app\controller;

use app\model\TaskConfig as TaskConfigModel;
use support\Request;

class TaskConfig extends Base
{
    public function index(Request $request)
    {
        //return response('hello webman' .$act);
        //return view('index/index', ['name' => 'webman1']);
    }

    public static function taskConfigIdTitleList()
    {
        return TaskConfigModel::all('Id', 'title')->toArray();
    }

    public static function taskConfigIdTitleKV()
    {
        $data = [];
        $list = TaskConfigModel::all('Id', 'title')->toArray();
        foreach ($list as $item) {
            $data[$item['Id']] = $item['title'];
        }
        return $data;
    }

}
