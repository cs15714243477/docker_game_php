<?php
namespace app\controller;

use app\model\AdminGroup;
use app\model\AdminLog;
use app\model\Menu;
use MongoDB\BSON\UTCDateTime;
use support\Request;
use support\View;

class Base
{
    public function beforeAction(Request $request)
    {
        $request->page = (int)$request->get('page', 0);
        $request->limit = (int)$request->get('limit', 0);
        $request->skip = intval(($request->page - 1) * $request->limit);
        if (!$request->isAjax()) {
            View::assign('JsVersion', JSVERSION);
        }
        View::assign('StatisticalInterval', intval(statisticalInterval()/60));
        View::assign("staticUrl", env('STATIC_URL'));
    }

    public function allMenu(Request $request)
    {
        $groupId = (int)$request->get('groupId', 0);
        $adminGroup = AdminGroup::where('groupId', $groupId)->first();
        $groupMenuPurview = [];
        if ($adminGroup) {
            $groupMenuPurview = explode(",", $adminGroup->menuPurview);
        }

        $returnData = [];
        $data = Menu::where(['pid' => 0, 'status' => 1])->orderBy('sort', 'asc')->get();
        foreach ($data as $k => $v) {
            $ck = '0';
            if (in_array($v['id'], $groupMenuPurview)) $ck = '1';
            $returnData[] = ["id" => $v['id'], "title" => $v['name'], "checkArr" => $ck, "parentId" => $v['pid']];
        }
        foreach ($data as $k => $v) {
            $data2 = Menu::where(['pid' => $v['id'], 'status' => 1])->orderBy('sort', 'asc')->get();
            foreach ($data2 as $kk => $vv) {
                $ck = '0';
                if (in_array($vv['id'], $groupMenuPurview)) $ck = '1';
                $returnData[] = ["id" => $vv['id'], "title" => $vv['name'], "checkArr" => $ck, "parentId" => $vv['pid']];
            }

        }

        return '{"code":0,"msg":"操作成功","data":'. json_encode($returnData) .'}';

        //return json(['code' => 200, 'msg' => '操作成功', 'data' => json_encode($returnData)]);
        //return json(['status' => json_encode(['code' => 200, 'message' => '操作成功']), 'data' => json_encode($returnData)]);
    }

    protected function formatDate($mongoDate, $format = 'Y-m-d H:i:s')
    {
        if (empty($mongoDate)) return '';
        if ($mongoDate == '0') return '';
        if(is_string($mongoDate)) return $mongoDate;
        return $mongoDate->toDateTime()->setTimezone(new \DateTimeZone(date_default_timezone_get()))->format($format);
    }

    protected function formatTimestampToMongo($timestamp)
    {
        return new UTCDateTime($timestamp*1000);
    }

    protected function diffTime($mongoDate1, $mongoDate2, $format="%d天%H:%I:%S")
    {
        if (empty($mongoDate2)) return "暂未处理";
        $time1 = $mongoDate1->toDateTime()->setTimezone(new \DateTimeZone(date_default_timezone_get()));
        $time2 = $mongoDate2->toDateTime()->setTimezone(new \DateTimeZone(date_default_timezone_get()));
        $timediff = $time1->diff($time2);
        $diffResult = $timediff->format($format);
        return $diffResult;
    }

    protected function formatMoneyFromMongoNo($money)
    {
        return (float)sprintf("%.2f",substr(sprintf("%.3f", $money), 0, -1));
    }

    protected function formatMoneyFromMongo($money)
    {
        return round($money*0.01, 2);
    }

    protected function formatMoneytoMongo($number)
    {
        return $number*100;
    }

    protected function formatPercentFromMongo($mongoData)
    {
        return round($mongoData*0.1, 1);
    }

    protected function adminLog($data) {
        $request = request();
        $session = $request->session();
        $logId = AdminLog::max('logId');
        $insertData = [
            'logId' => intval($logId + 1),
            'userId' => $session->get('userId'),
            'ip' => GetIP(),
            'app' => $request->host().$request->uri(),
            'url' => $request->host().$request->uri(),
            'content' => $data["content"],
            'status' => 1,
            'opDate' => new UTCDateTime
        ];
        AdminLog::insert($insertData);
        return true;
    }

    public function download(Request $request)
    {
        $filePath = $request->get('file');
        return response()->download($filePath);
    }
}
