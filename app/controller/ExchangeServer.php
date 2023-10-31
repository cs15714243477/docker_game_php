<?php
namespace app\controller;

use support\Request;
use app\model\ExchangeService;

class ExchangeServer extends Base
{
    public function exchangeServiceList(Request $request)
    {
        if ($request->isAjax()) {
            $count = ExchangeService::count();
            $list = ExchangeService::select("_id","exchangeServiceId","controllerName","M_IDX","M_URL","status","exchangeType")
                ->orderBy('exchangeType', 'asc')->orderBy('exchangeServiceId', 'asc')->skip($request->skip)->take($request->limit)->get()->toArray();
            return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $list]);
        }
    }

    public function exchangeServiceListStatus(Request $request)
    {
        $postData = $request->post();
        extract($postData);
        $value = (int)$value;
        if (!$field || !$_id) {
            return json(['code' => -1, 'msg' => '参数错误']);
        }
        $updateData = [
            $field => $value
        ];

        if($value == 1)
        {
            $msg = "启用代付接口状态";
            $status = "启用";
        }else
        {
            $msg = "关闭代付接口状态";
            $status = "关闭";
        }

        $row = ExchangeService::where('_id', $_id)->first();
        if(empty($row))return json(['code' => -1, 'msg' => '无该条数据']);
        $updateResult = ExchangeService::where('_id', $_id)->update($updateData);
        if (!$updateResult) return json(['code' => -1, 'msg' => '修改失败']);
        $this->adminLog(["content"=>"代付接口".$row['exchangeServiceId']."的状态修改【".$status."】"]);
        return json(['code' => 0, 'msg' => $msg]);
    }

    public static function getAvailableServiceList($exchangeType = 1)
    {
        $where = [
            'status' => 1,
            'exchangeType' => $exchangeType
        ];
        return ExchangeService::where($where)->get(['exchangeServiceId','controllerName','M_IDX','M_KEY','M_URL','N_URL'])->toArray();
    }
}
