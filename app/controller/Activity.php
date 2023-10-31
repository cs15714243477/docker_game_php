<?php
namespace app\controller;

use app\model\Activity as ActivityModel;
use support\Request;

class Activity extends Base
{
    public static function activityIdTitleList()
    {
        return ActivityModel::all('activityId', 'title')->toArray();
    }

    public static function activityIdTitleKV()
    {
        $data = [];
        $list = ActivityModel::all('activityId', 'title')->toArray();
        foreach ($list as $item) {
            $data[$item['activityId']] = $item['title'];
        }
        return $data;
    }

}
