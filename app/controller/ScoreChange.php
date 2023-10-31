<?php
namespace app\controller;

use app\model\UserScoreChange;
use support\Request;

class ScoreChange extends Base
{
    private function _ajaxParam()
    {
        $where = [];
        $request = request();
        $getData = check_type($request->all());
        extract($getData);
        /*if (empty($startDate)) {
            $startDate = date("Y-m-d");
        }
        if (empty($endDate)) {
            $endDate = date("Y-m-d");
        }
        $startTime = strtotime($startDate);
        $endTime = strtotime("$endDate +1 day");
        if ($startTime >= $endTime) return json(['code' => -1, 'msg' => '请核对开始时间结束时间']);
        $where[] = ['createTime', '>=', $this->formatTimestampToMongo($startTime)];
        $where[] = ['createTime', '<', $this->formatTimestampToMongo($endTime)];*/
        $where['changeType'] = UserScoreChange::CHANGETYPE_SYSTEM_GIVE;

        return $where;
    }

    public function scoreChangeList(Request $request)
    {
       if($request->isAjax()) {
           $where = $this->_ajaxParam();
           if (!is_array($where)) return $where;
           $count = UserScoreChange::where($where)->count();
           $list = UserScoreChange::where($where)->orderBy('createTime', 'desc')->skip($request->skip)->take($request->limit)->get()->toArray();
           foreach ($list as &$item) {
               $item['beforeScore'] = $this->formatMoneyFromMongo($item['beforeScore']);
               $item['addScore'] = $this->formatMoneyFromMongo($item['addScore']);
               $item['afterScore'] = $this->formatMoneyFromMongo($item['afterScore']);
               $item['beforeBankScore'] = $this->formatMoneyFromMongo($item['beforeBankScore']);
               $item['addBankScore'] = $this->formatMoneyFromMongo($item['addBankScore']);
               $item['afterBankScore'] = $this->formatMoneyFromMongo($item['afterBankScore']);

               $item['createTime'] = $this->formatDate($item['createTime']);
               $item['remark'] = $item['remark'] ?? '';
           }
           //dd($list);
           return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $list]);
       }
    }

    public function summary(Request $request)
    {
        if($request->isAjax()) {
            $where = $this->_ajaxParam();
            if (!is_array($where)) return $where;
            $requestMoneySum = RechargeOrder::where($where)->sum('requestMoney');
            $requestMoneySum = $this->formatMoneyFromMongo($requestMoneySum);
            $rechargeMoneySum = RechargeOrder::where($where)->sum('rechargeMoney');
            $rechargeMoneySum = $this->formatMoneyFromMongo($rechargeMoneySum);

            return json(['code' => 0, 'msg' => 'ok', 'data' => ['requestMoneySum' => $requestMoneySum, 'rechargeMoneySum' => $rechargeMoneySum]]);
        }
    }


}
