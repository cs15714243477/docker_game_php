<?php
namespace app\controller;

use app\model\StatPromoterDaily;
use support\Request;
use app\model\Promoter;
use support\Db;

class agentAnalysisCon extends Base
{
    private function _ajaxParam()
    {
        $where = [];
        $request = request();
        $getData = $request->all();
        extract($getData);
        $startTime = !empty($startDate) ? trim($startDate) : date("Y-m-d");
        $endTime = !empty($endDate) ? trim($endDate) : date("Y-m-d");
        $startTime = strtotime($startTime);
        $endTime = strtotime("$endTime +1 day");
        $startTimeMongo = $this->formatTimestampToMongo($startTime);
        $endTimeMongo = $this->formatTimestampToMongo($endTime);
        $searchText = !empty($searchText) ? (int)$searchText : false;

        if($searchText)
            $where['promoterId'] = $searchText;
        $where['createTime'] = ['$gte' => $startTimeMongo, '$lt' => $endTimeMongo];

        return $where;
    }
    
    public function getAgentAnalysis(Request $request)
    {
        if ($request->isAjax()) {
            $where = $this->_ajaxParam();
            if (!is_array($where)) return $where;
            $promoters = Promoter::where($where)->orderBy('createTime', 'asc')->skip($request->skip)->take($request->limit)->get()->toArray();
            $count = Promoter::where($where)->count();
            $date_t = date("Y-m-d");
            $date_y = date("Y-m-d", strtotime('-1 day'));

            $arrays_today = $this->data_promoter_day($date_t);
            $arrays_yes = $this->data_promoter_day($date_y);

            if($promoters){
                foreach ($promoters as &$item) {
                    $item['totalExchange'] = $this->formatMoneyFromMongo($item['totalExchange']);
                    if(isset($item['totalTeamFlowAmount']) && $item['totalTeamFlowAmount']){
                        $item['totalTeamFlowAmount'] = round($item['totalTeamFlowAmount']*0.01, 2);
                    }else{
                        $item['totalTeamFlowAmount'] = 0;
                    }
                    $item['teamRegPeople'] = 0;//当日新增
                    $item['teamRegValidNewBetPeople'] = 0;//当日有效新增
                    $item['teamRechargeAmount'] = 0;//当日下级充值
                    $item['teamProfit'] = 0;//今日佣金

                    $item['teamFlowAmount'] = 0;//今日下注
                    $item['teamFlowAmount_yes'] = 0;//昨日下注


                    $item['totalTeamPlayerCount_team'] = $item['teamPlayerCount']??0;//团队人数
                    $item['totalDirectPlayerCount_team'] = $item['myPlayerCount']??0;//直属人数
                    $item['teamRechargeAmount_yes'] = 0;//昨日下级充值
                    $item['teamProfit_yes'] = 0;//昨日佣金
                    $item['totalTeamAndroidValue'] = $item['totalTeamAndroidValue']??0;//安卓设备
                    $item['android_dvc_rate'] = "0.00%";//安卓设备比例
                    $item['totalTeamIOSValue'] = $item['totalTeamIOSValue']??0;//苹果设备
                    $item['ios_dvc_rate'] = "0.00%";//苹果设备比例

                    foreach ($arrays_today as $dt_item){
                        if($item['promoterId'] == $dt_item['promoterId']){
                            $all_dvc = $dt_item['totalTeamPlayerCount'] ??0;
                            if($all_dvc !== 0){
                                $val_rate_ios = $item['totalTeamIOSValue']/$all_dvc;
                                $val_rate_android = $item['totalTeamAndroidValue']/$all_dvc;
                                $item['ios_dvc_rate'] = sprintf("%01.2f", $val_rate_ios*100).'%';
                                $item['android_dvc_rate'] = sprintf("%01.2f", $val_rate_android*100).'%';
                            }
                            $item['teamRegPeople'] = $dt_item['teamRegPeople']??0;//当日新增
                            $item['teamRegValidNewBetPeople'] = $dt_item['teamRegValidNewBetPeople']??0;//当日有效新增
                            $item['teamRechargeAmount'] = round($dt_item['teamRechargeAmount']*0.01, 2)??0;//当日下级充值
                            $item['teamProfit'] = round($dt_item['teamProfit']*0.01, 2) ??0;//当日佣金
                            $item['teamFlowAmount'] = round($dt_item['teamFlowAmount']*0.01, 2) ??0;//当日下注(流水)
                        }
                    }
                    foreach ($arrays_yes as $dt_item_yes){
                        if($item['promoterId'] == $dt_item_yes['promoterId']){
                            $item['teamRechargeAmount_yes'] = round($dt_item_yes['teamRechargeAmount']*0.01, 2) ??0;//当日下级充值
                            $item['teamProfit_yes'] = round($dt_item_yes['teamProfit']*0.01, 2) ??0;//当日佣金
                            $item['teamFlowAmount_yes'] = round($dt_item_yes['teamFlowAmount']*0.01, 2) ??0;//当日下注(流水)
                        }
                    }
                }
            }
            return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $promoters]);
        }
    }

    public function summary(Request $request)
    {
        if($request->isAjax()) {
            $where = $this->_ajaxParam();
            if (!is_array($where)) return $where;
            $promotersArr = [];
            $promoters = Promoter::where($where)->select('promoterId')->skip($request->skip)->take($request->limit)->get()->toArray();
            foreach ($promoters as $item){
                $promotersArr[] = (int)$item['promoterId'];
            }
            //今日
            $date = date("Y-m-d");
            $startTime = strtotime(trim($date));
            $endTime = strtotime(trim($date))+ 86400;
            $startTimeMongo = $this->formatTimestampToMongo($startTime);
            $endTimeMongo = $this->formatTimestampToMongo($endTime);
            $whereSum['date'] = ['$gte' => $startTimeMongo, '$lt' => $endTimeMongo];
            $addSum = StatPromoterDaily::whereIn('promoterId',$promotersArr)->where($whereSum)->sum('teamRegPeople');

            $effectAddSum = StatPromoterDaily::whereIn('promoterId',$promotersArr)->where($whereSum)->sum('teamRegValidNewBetPeople');


            $downLevelSum = StatPromoterDaily::whereIn('promoterId',$promotersArr)->where($whereSum)->sum('teamRechargeAmount');
            $downLevelSum = $this->formatMoneyFromMongo($downLevelSum);
            $todayProfitSum = StatPromoterDaily::whereIn('promoterId',$promotersArr)->where($whereSum)->sum('teamProfit');
            $todayProfitSum = $this->formatMoneyFromMongo($todayProfitSum);

            $todayBetSum = StatPromoterDaily::whereIn('promoterId',$promotersArr)->where($whereSum)->sum('teamFlowAmount');
            $todayBetSum = $this->formatMoneyFromMongo($todayBetSum);
            $totalAllBetSum = Promoter::whereIn('promoterId',$promotersArr)->where($where)->sum('totalTeamFlowAmount');
            $totalAllBetSum = $this->formatMoneyFromMongo($totalAllBetSum);

            $getProfitSum = Promoter::whereIn('promoterId',$promotersArr)->where($where)->sum('totalExchange');
            $getProfitSum = $this->formatMoneyFromMongo($getProfitSum);
            //昨日
            $date = date("Y-m-d", strtotime('-1 day'));
            $startTime = strtotime(trim($date));
            $endTime = strtotime(trim($date))+ 86400;
            $startTimeMongo = $this->formatTimestampToMongo($startTime);
            $endTimeMongo = $this->formatTimestampToMongo($endTime);
            $whereSum['date'] = ['$gte' => $startTimeMongo, '$lt' => $endTimeMongo];

            $yesBetSum = StatPromoterDaily::whereIn('promoterId',$promotersArr)->where($whereSum)->sum('teamFlowAmount');
            $yesBetSum = $this->formatMoneyFromMongo($yesBetSum);
            $yesDownRechargeSum = StatPromoterDaily::whereIn('promoterId',$promotersArr)->where($whereSum)->sum('teamRechargeAmount');
            $yesDownRechargeSum = $this->formatMoneyFromMongo($yesDownRechargeSum);
            $yesProfitSum = StatPromoterDaily::whereIn('promoterId',$promotersArr)->where($whereSum)->sum('teamProfit');
            $yesProfitSum = $this->formatMoneyFromMongo($yesProfitSum);

            $assignData = [
                'addSum' => $addSum,
                'effectAddSum' => $effectAddSum,
                'downLevelSum' => $downLevelSum,
                'todayProfitSum' => $todayProfitSum,
                'todayBetSum' => $todayBetSum,
                'totalAllBetSum' => $totalAllBetSum,
                'getProfitSum' => $getProfitSum,
                'yesBetSum' => $yesBetSum,
                'yesDownRechargeSum' => $yesDownRechargeSum,
                'yesProfitSum' => $yesProfitSum
            ];
            return json(['code' => 0, 'msg' => 'ok','data' => $assignData]);
        }
    }

    public function data_promoter_day($date){
        $startTimeFom = $date ?? date("Y-m-d");
        $startTime = strtotime(trim($startTimeFom));
        $endTime = strtotime(trim($startTimeFom))+ 86400;

        $where = [];
        $startTimeMongo = $this->formatTimestampToMongo($startTime);
        $endTimeMongo = $this->formatTimestampToMongo($endTime);
        $where['date'] = ['$gte' => $startTimeMongo, '$lt' => $endTimeMongo];

        $result_array = Db::connection('mongodb_main')->collection('stat_promoter_daily')->raw()->aggregate([
            [
                '$match' => $where
            ],
            [
                '$project' =>
                    [
                        'totalDirectPlayerCount' => 1,//直属人数
                        'totalTeamPlayerCount' => 1,//团队人数
                        'teamRegPeople' =>1,//今日新增
                        'teamRegValidNewBetPeople' =>1,//今日有效新增
                        'teamRechargeAmount' =>1,//今日下级充值
                        'teamProfit' =>1,//今日佣金
                        'promoterId' => 1,
                        'totalTeamIOSValue' =>1,
                        'totalTeamAndroidValue' =>1,
                        'totalTeamSimulatorValue' =>1,
                        'totalTeamPlayerCount' =>1,
                        'teamFlowAmount' =>1,
                        'totalTeamFlowAmount' =>1
                    ]
            ]
        ])->toArray();
        return $result_array;
    }
}
