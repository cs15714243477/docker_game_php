<?php
namespace app\controller;

use app\model\ClubPlatformDataRecord;
use app\model\ClubPromoterMain;
use app\model\ClubRewardOrder;
use app\model\PlayRecord;
use app\model\PromoterExchangeOrder;
use app\model\GameKind;
use app\model\ClubPlayRecord;
use app\model\GameUser;
use app\model\Promoter;
use app\model\PromoterDetail;
use app\model\ClubMo;
use app\model\MyClubs;
use app\model\PromoterMain;
//use app\model\StatPromoterDaily;
//use app\model\StatPartnerDaily;
use app\model\ClubUserOnline;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use support\bootstrap\Container;
use support\Db;
use support\functionUpload;
use support\Request;
use IpLocation;

class Club extends Base
{
    public function index(Request $request)
    {
        $act = $request->get('act');
        $actMap = ['global_more' => 'globalData'];
        if (isset($actMap[$act])) $act = $actMap[$act];
        $controller = Container::get($request->controller);
        if (method_exists($controller, $act)) {
            $before_response = call_user_func([$controller, $act], $request);
            return $before_response;
            if ($before_response instanceof Response) {
                dd('yes');
                return $before_response;
            }
        }
    }

    public function survey(Request $request)
    {
        $date = date("Y-m-d");
        $startTime = strtotime($date);
        $startTimeMongo = $this->formatTimestampToMongo($startTime);
        $endTime = $startTime + 86400;
        $endTimeMongo = $this->formatTimestampToMongo($endTime);
        $where['date'] = ['$gte' => $startTimeMongo, '$lt' => $endTimeMongo];
        $platformData = ClubPlatformDataRecord::where($where)->first();
        $data = !is_null($platformData) ? $platformData->toArray() : [];
        $newClubs = ClubMo::count();
        $newPromoters = ClubPromoterMain::count();
        $assignData = [
            'newPlayers' => $data['newPlayerCount']??0,
            'gamePlayers' => $data['gamePlayerCount']??0,
            'newClubs' =>  $newClubs??0,
            'newPromoters' =>  $newPromoters??0,
        ];
        return view('club/survey/list', $assignData);
    }

    public function surveyToday(Request $request)
    {
        if ($request->method() == 'GET') {
            $getData = check_type($request->all());
            extract($getData);
            if (empty($startDate)) {
                $startTime = strtotime(date("Y-m-d"));
            }else{
                $startTime = strtotime(trim($startDate));
            }

            if (empty($endDate)) {
                $endTime = strtotime(date("Y-m-d"))+ 24*3600;
            }else{
                $endTime = strtotime(trim($endDate));
            }

            if ($startTime >= $endTime) return json(['code' => -1, 'msg' => '请核对开始时间结束时间']);
            $where['date'] = ['$gte' => $this->formatTimestampToMongo($startTime), '$lt' => $this->formatTimestampToMongo($endTime)];
            $where['clubId'] = ['$ne'=>-1000];
            $where1['clubId'] = ['$ne'=>1000];
            $data = Db::connection('mongodb_club')->collection('platform_data_record')->raw()->aggregate([
                [
                    '$match' => $where
                ],
                [
                    '$match' => $where1
                ],
                [
                    '$project' =>
                        [
                            'clubId'           => 1,
                            'newPlayerCount'   => 1,
                            'gamePlayerCount'  => 1,
                            'platformProfit'   => 1,
                            'platformWinScore' => 1,
                            'agentRevenue'     => 1,
                            'revenue'          => 1,
                            'rewardScore'      => 1,
                            'validBet'         => 1
                        ]
                ],
                [
                    '$group' =>
                        [
                            '_id' => '$clubId',
                            'newPlayerCount'   => ['$sum' => '$newPlayerCount'],
                            'gamePlayerCount'  => ['$sum' => '$gamePlayerCount'],
                            'platformProfit'   => ['$sum' => '$platformProfit'],
                            'platformWinScore' => ['$sum' => '$platformWinScore'],
                            'agentRevenue'     => ['$sum' => '$agentRevenue'],
                            'revenue'          => ['$sum' => '$revenue'],
                            'rewardScore'      => ['$sum' => '$rewardScore'],
                            'validBet'         => ['$sum' => '$validBet']
                        ]
                ],
                ['$skip' => 0],
            ])->toArray();
            $total = Db::connection('mongodb_club')->collection('platform_data_record')->raw()->aggregate([
                [
                    '$match' => $where
                ],
                [
                    '$match' => $where1
                ],
                [
                    '$project' =>
                        [
                            'clubId'           => 1,
                            'newPlayerCount'   => 1,
                            'gamePlayerCount'  => 1,
                            'platformProfit'   => 1,
                            'platformWinScore' => 1,
                            'agentRevenue'     => 1,
                            'revenue'          => 1,
                            'rewardScore'      => 1,
                            'validBet'         => 1
                        ]
                ],
                [
                    '$group' =>
                        [
                            '_id' => null,
                            'newPlayerCount'   => ['$sum' => '$newPlayerCount'],
                            'gamePlayerCount'  => ['$sum' => '$gamePlayerCount'],
                            'platformProfit'   => ['$sum' => '$platformProfit'],
                            'platformWinScore' => ['$sum' => '$platformWinScore'],
                            'agentRevenue'     => ['$sum' => '$agentRevenue'],
                            'revenue'          => ['$sum' => '$revenue'],
                            'rewardScore'      => ['$sum' => '$rewardScore'],
                            'validBet'         => ['$sum' => '$validBet']
                        ]
                ],
                ['$skip' => 0],
            ])->toArray();

            $list = ClubMo::where('clubId','<>',1000) -> skip($request->skip)->take($request->limit)-> select('clubId','clubName') -> get()->toArray();
            $count = ClubMo::where('clubId','<>',1000) -> count();
            $allAgentRevenue = 0;
            $totalPlayerCount = 0;
            $newPlayerCount = 0;
            $gamePlayerCount = 0;
            $platformProfit = 0;
            $platformWinScore = 0;
            $agentRevenue = 0;
            $agentCommission = 0;
            $revenue = 0;
            $validBet = 0;
            $pureRevenue = 0;
            $rewardScore = 0;
            if ($list) {
                foreach ($list as $k => &$v) {
                    $list[$k]["totalPlayerCount"] = 0;
                    $list[$k]["newPlayerCount"] = 0;
                    $list[$k]["gamePlayerCount"] = 0;
                    $list[$k]["platformProfit"] = 0;
                    $list[$k]["platformWinScore"] = 0;
                    $list[$k]["agentRevenue"] = 0;
                    $list[$k]["agentCommission"] = 0;
                    $list[$k]["revenue"] = 0;
                    $list[$k]["validBet"] = 0;
                    $list[$k]["pureRevenue"] = 0;
                    $list[$k]["rewardScore"] = 0;

                    $v['agentCommission'] = 0;
                    $clubsMember = MyClubs::where(['clubId' => $v['clubId']]) -> where('userId','>',10000000)->get()->toArray();
                    //$v['newPlayerCount'] = count($clubsMember).' / 0';
                    $v['totalPlayerCount'] = count($clubsMember);
                    $v['newPlayerCount'] = $v['gamePlayerCount'] = $v['platformProfit'] = $v['platformWinScore'] = $v['agentRevenue'] = $v['revenue'] = $v['validBet'] = 0;
                    $v['pureRevenue'] = $v['rewardScore'] = 0;
                    foreach ($data as $key => $val){
                        if($v['clubId'] == $val['_id']){
                            $where3['date'] = ['$gte' => $this->formatTimestampToMongo($startTime), '$lt' => $this->formatTimestampToMongo($endTime)];
                            $where3['promoterId'] = ['$eq'=>$v['clubId']];
                            $clubProData = Db::connection('mongodb_club')->collection('stat_promoter_daily')->raw()->aggregate([
                                [
                                    '$match' => $where3
                                ],
                                [
                                    '$project' =>
                                        [
                                            'promoterId'           => 1,
                                            'agentRevenue'   => 1,
                                            'teamAgentRevenue'  => 1
                                        ]
                                ],
                                [
                                    '$group' =>
                                        [
                                            '_id' => '$promoterId',
                                            'agentRevenue'   => ['$sum' => '$agentRevenue'],
                                            'teamAgentRevenue'  => ['$sum' => '$teamAgentRevenue'],
                                        ]
                                ],
                            ])->toArray();
                            $totalAgentRevenue = 0;
                            foreach ($clubProData as $keyClub =>$valClub){
                                $agentRevenuePro = !empty($valClub['agentRevenue']) ? $this -> formatMoneyFromMongo($valClub['agentRevenue']) : 0;
                                $teamAgentRevenuePro = !empty($valClub['teamAgentRevenue']) ? $this -> formatMoneyFromMongo($valClub['teamAgentRevenue']) : 0;
                                $totalAgentRevenue = $this -> formatMoneyFromMongoNo($agentRevenuePro + $teamAgentRevenuePro);
                                $allAgentRevenue += $totalAgentRevenue;
                            }

                             //$v['newPlayerCount']   = count($clubsMember).' / '.$val['newPlayerCount'];
                            //$v['totalPlayerCount']   = count($clubsMember);
                             $v['newPlayerCount']   = $val['newPlayerCount'];
                             $v['gamePlayerCount']  = $val['gamePlayerCount'];
                             $v['platformProfit']   = $this -> formatMoneyFromMongo($val['platformProfit']);
                             $v['platformWinScore'] = $this -> formatMoneyFromMongo($val['platformWinScore']);
                             $v['agentRevenue']     = $totalAgentRevenue;
                             $promoterDetail = PromoterDetail::where(['clubId' => $v['clubId'],'promoterId' => $v['clubId']])->select('setRate')->first()->toArray();
                             $setRate = !empty($promoterDetail['setRate']) ? ($promoterDetail['setRate'] * 0.01) : 0;
                             if(!empty($v['agentRevenue'])){
                                 $v['agentCommission']  = round($v['agentRevenue']*$setRate, 2);
                             }else{
                                 $v['agentCommission']  =  0;
                             }
                             //$v['agentCommission']  =  ? $this -> formatMoneyFromMongoNo($v['agentRevenue']*$setRate) :0;
                             $v['revenue']          = $this -> formatMoneyFromMongo($val['revenue']);
                             $v['validBet']         = $this -> formatMoneyFromMongo($val['validBet']);
                             //$v['pureRevenue'] = $this -> formatMoneyFromMongo($val['revenue'] - (($val['agentRevenue']??0)*$setRate));
                             $v['pureRevenue'] = round(($v['revenue'] - $v['agentCommission']), 2);
                            $v['rewardScore']          = $this -> formatMoneyFromMongo($val['rewardScore']);
                        }
                    }
                    $totalPlayerCount += $list[$k]["totalPlayerCount"];
                    $newPlayerCount += $list[$k]["newPlayerCount"];
                    $gamePlayerCount += $list[$k]["gamePlayerCount"];
                    $platformProfit += $list[$k]["platformProfit"];
                    $platformWinScore += $list[$k]["platformWinScore"];
                    $agentRevenue += $list[$k]["agentRevenue"];
                    $agentCommission += $list[$k]["agentCommission"];
                    $revenue += $list[$k]["revenue"];
                    $validBet += $list[$k]["validBet"];
                    $pureRevenue += $list[$k]["pureRevenue"];
                    $rewardScore += $list[$k]["rewardScore"];
                }
                $tmparr = array(
                    "clubName" => "总计",
                    "totalPlayerCount" => $totalPlayerCount,
                    "newPlayerCount" => $newPlayerCount,
                    "gamePlayerCount" => $gamePlayerCount,
                    "platformProfit" => $platformProfit,
                    "platformWinScore" => $platformWinScore,
                    "agentRevenue" => $agentRevenue,
                    "agentCommission" => $agentCommission,
                    "revenue" => $revenue,
                    "validBet" => $validBet,
                    "pureRevenue" => $pureRevenue,
                    "rewardScore" => $rewardScore,
                );
            }


            array_push($list, $tmparr);

//            $num = $newPlayerCount = 0;
//            $club = ClubMo::where('clubId','<>',1000) -> select('clubId') -> get()->toArray();
//            if($club){
//                foreach ($club as $k1 => $v1){
//                    $clubsMember = MyClubs::where(['clubId' => $v1['clubId']])-> where('userId','>',10000000)->get()->toArray();
//                    $num += count($clubsMember);
//                    foreach ($data as $key => $val){
//                        if($v1['clubId'] == $val['_id']){
//                            $newPlayerCount += $val['newPlayerCount'];
//                        }
//                    }
//                }
//            }

//            $state = $total ? true : false;
//            $total[0]['clubName'] = '总计';
//            //$total[0]['newPlayerCount'] = $num .'/'. $newPlayerCount;
//            $total[0]['totalPlayerCount'] = $num;
//            $total[0]['newPlayerCount'] = $newPlayerCount;

//            if($state) {
//                $total = json_decode(json_encode($total), true);
//                if($total){
//                    foreach ($total as &$val){
//                        $val['platformProfit']   = $this -> formatMoneyFromMongo($val['platformProfit']);
//                        $val['platformWinScore'] = $this -> formatMoneyFromMongo($val['platformWinScore']);
//                        $val['agentRevenue']     = $this -> formatMoneyFromMongoNo($allAgentRevenue);
//                        $val['agentCommission']  = $this -> formatMoneyFromMongo($val['agentCommission']);
//                        $val['revenue']          = $this -> formatMoneyFromMongo($val['revenue']);
//                        $val['validBet']         = $this -> formatMoneyFromMongo($val['validBet']);
//                        $val['pureRevenue'] = round($val['revenue'] - $val['agentCommission'], 2);
//                        $val['rewardScore']          = $this -> formatMoneyFromMongo($val['rewardScore']);
//                    }
//                }
//                $list[count($list)] = $total[0];
//            }else{
//                $total[0] += [
//                    'gamePlayerCount' => 0,
//                    'platformProfit' => 0,
//                    'platformWinScore' => 0,
//                    'agentRevenue' => 0,
//                    'agentCommission' => 0,
//                    'revenue' => 0,
//                    'validBet' => 0,
//                    'pureRevenue' => 0,
//                    'rewardScore' => 0,
//                ];
//                $list[count($list)] = $total[0];
//            }
            return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $list]);
        }

        return view('club/survey/surveyToday/list');

    }

    public function surveyTodaySummary(Request $request)
    {
            $getData = check_type($request->all());
            extract($getData);
            if (empty($startDate)) {
                $startTime = strtotime(date("Y-m-d"));
            }else{
                $startTime = strtotime(trim($startDate));
            }

            if (empty($endDate)) {
                $endTime = strtotime(date("Y-m-d"))+ 24*3600;
            }else{
                $endTime = strtotime(trim($endDate));
            }

            if ($startTime >= $endTime) return json(['code' => -1, 'msg' => '请核对开始时间结束时间']);
            $where['date'] = ['$gte' => $this->formatTimestampToMongo($startTime), '$lt' => $this->formatTimestampToMongo($endTime)];
            $where['clubId'] = ['$eq'=>-1000];
            $data = Db::connection('mongodb_club')->collection('platform_data_record')->raw()->aggregate([
                [
                    '$match' => $where
                ],
                [
                    '$project' =>
                        [
                            'clubId'           => 1,
                            'newPlayerCount'   => 1,
                            'gamePlayerCount'  => 1,
                            'platformProfit'   => 1,
                            'totalPlatformProfit' => 1,
                            'platformWinScore' => 1,
                            'agentRevenue'     => 1,
                            'revenue'          => 1,
                            'rewardScore'      => 1,
                            'totalRewardScore' => 1,
                            'validBet'         => 1
                        ]
                ],
                [
                    '$group' =>
                        [
                            '_id' => '$clubId',
                            'newPlayerCount'   => ['$sum' => '$newPlayerCount'],
                            'gamePlayerCount'  => ['$sum' => '$gamePlayerCount'],
                            'platformProfit'   => ['$sum' => '$platformProfit'],
                            'totalPlatformProfit'   => ['$sum' => '$totalPlatformProfit'],
                            'platformWinScore' => ['$sum' => '$platformWinScore'],
                            'agentRevenue'     => ['$sum' => '$agentRevenue'],
                            'revenue'          => ['$sum' => '$revenue'],
                            'rewardScore'      => ['$sum' => '$rewardScore'],
                            'totalRewardScore'      => ['$sum' => '$totalRewardScore'],
                            'validBet'         => ['$sum' => '$validBet']
                        ]
                ],
                ['$skip' => 0],
            ])->toArray();

        $rewardScoreSum = isset($data[0]['rewardScore']) ? $this->formatMoneyFromMongo($data[0]['rewardScore']) : 0;
        $platformProfitSum = $this->formatMoneyFromMongo($data[0]['platformProfit'] - $data[0]['rewardScore']);

        return json(['code' => 0, 'msg' => 'ok', 'data' => ['rewardScoreSum' => $rewardScoreSum, 'platformProfitSum' => $platformProfitSum]]);
    }

    public function surveyYestodayAndToday(Request $request)
    {
        if ($request->method() == 'GET') {
            $date_t = date("Y-m-d");
            $date_y = date("Y-m-d", strtotime('-1 day'));
            $platform_data_record = $this->get_day_data($date_t);
            foreach ($platform_data_record as &$item) {
                $item['allBet'] = $this->formatMoneyFromMongo($item['allBet']??0);
                $item['platformWinScore'] = $this->formatMoneyFromMongo($item['platformWinScore']??0);
                $item['revenue'] = $this->formatMoneyFromMongo($item['revenue']??0);
                $item['userWinLostScore'] = $this->formatMoneyFromMongo($item['userWinLostScore']??0);
                $item['userWinScore'] = $this->formatMoneyFromMongo($item['userWinScore']??0);
                $item['validBet'] = $this->formatMoneyFromMongo($item['validBet']??0);
                $item['platformProfit'] = $this->formatMoneyFromMongo($item['platformProfit']??0);
                $item['promoterScore'] = $this->formatMoneyFromMongo($item['promoterScore']??0);
            }
            //dd($platform_data_record);
            $list = [
                ['name' => '有效新增用户', 'today' => $platform_data_record[$date_t]['newPlayerCount']??0, 'yestoday' => $platform_data_record[$date_y]['newPlayerCount']??0],
                ['name' => '游戏人数', 'today' => $platform_data_record[$date_t]['gamePlayerCount']??0, 'yestoday' => $platform_data_record[$date_y]['gamePlayerCount']??0],
                ['name' => '游戏输赢', 'today' => $platform_data_record[$date_t]['platformWinScore']??0, 'yestoday' => $platform_data_record[$date_y]['platformWinScore']??0],
                ['name' => '官方税收', 'today' => $platform_data_record[$date_t]['revenue']??0, 'yestoday' => $platform_data_record[$date_y]['revenue']??0],
                ['name' => '官方盈亏', 'today' => $platform_data_record[$date_t]['platformProfit']??0, 'yestoday' => $platform_data_record[$date_y]['platformProfit']??0],
                ['name' => '总有效投注', 'today' => $platform_data_record[$date_t]['validBet']??0, 'yestoday' => $platform_data_record[$date_y]['validBet']??0],
                //['name' => '代理可提取余额', 'today' => $platform_data_record[$date_t]['promoterScore']??0, 'yestoday' => $platform_data_record[$date_y]['promoterScore']??0],
            ];
            return json(['code' => 0, 'msg' => 'ok', 'count' => 0, 'data' => $list]);
        }
        if ($request->method() == 'POST') {
            $assignData = [];
            return view('club/survey/surveyYestodayAndToday/list', $assignData);
        }
    }

    public function get_day_data($endDate, $startDate = '')
    {
        $endTime = $endDate ?? date("Y-m-d", strtotime('-1 day'));
        $endTime = strtotime(trim($endTime))+ 86400;
        $endTimeMongo = $this->formatTimestampToMongo($endTime);

        $where = [];
        if (!empty($startDate)) {
            $startTime = strtotime(trim($startDate));
            $startTimeMongo = $this->formatTimestampToMongo($startTime);
            $where['date'] = ['$gte' => $startTimeMongo, '$lt' => $endTimeMongo];
            $limit = 20;
        } else {
            $where['date'] = ['$lt' => $endTimeMongo];
            $limit = 2;
        }
        $where['clubId'] = ['$ne'=>1000];
        $where1['clubId'] = ['$ne'=>-1000];
        $data_array = Db::connection('mongodb_club')->collection('platform_data_record')->raw()->aggregate([
            [
                '$match' => $where
            ],
            [
                '$match' => $where1
            ],
            [
                '$project' =>
                    [
                        '_id' => 0,
                        'Date' => ['$substr' => [['$add' => ['$date',28800000]], 0, 10]],
                        'newPlayerCount' => 1,
                        'gamePlayerCount' => 1,
                        'allBet' =>1,
                        'platformWinScore'=>1,
                        'revenue'=>1,
                        'userWinLostScore'=>1,
                        'userWinScore'=>1,
                        'validBet'=>1,
                        'platformProfit'=>1,
                        'agentRevenue'=>1,
                        'promoterScore'=>1
                    ],
            ],
            [
                '$group' => [
                    '_id'=>[
                        'Date'=>'$Date'
                    ],
                    'Date'=> ['$first'=>'$Date'],
                    'newPlayerCount' => ['$sum'=>'$newPlayerCount'],
                    'gamePlayerCount' => ['$sum'=>'$gamePlayerCount'],
                    'allBet' => ['$sum'=>'$allBet'],
                    'platformWinScore' => ['$sum'=>'$platformWinScore'],
                    'revenue' => ['$sum'=>'$revenue'],
                    'userWinLostScore' => ['$sum'=>'$userWinLostScore'],
                    'userWinScore' => ['$sum'=>'$userWinScore'],
                    'validBet' => ['$sum'=>'$validBet'],
                    'platformProfit' => ['$sum'=>'$platformProfit'],
                    'agentRevenue'=>['$sum'=>'$agentRevenue'],
                    'promoterScore'=>['$sum'=>'$promoterScore']
                ]
            ],
            [
                '$sort' => ['Date'=>-1]
            ],
            [
                '$limit' => $limit
            ]
        ])->toArray();
        $result_array = [];
        if($data_array){
            foreach ($data_array as $item){
                $result_array[$item['Date']] = $item;
            }
        }
        return $result_array;
    }
    private function get_retention_day_data($endDate, $startDate = '')
    {
        $endTime = $endDate ?? date("Y-m-d", strtotime('-1 day'));
        $endTime = strtotime(trim($endTime))+ 86400;
        $endTimeMongo = $this->formatTimestampToMongo($endTime);

        $where = [];
        if (!empty($startDate)) {
            $startTime = strtotime(trim($startDate));
            $startTimeMongo = $this->formatTimestampToMongo($startTime);
            $where['date'] = ['$gte' => $startTimeMongo, '$lt' => $endTimeMongo];
            $limit = 20;
        } else {
            $where['date'] = ['$lt' => $endTimeMongo];
            $limit = 2;
        }

        $data_array = Db::connection('mongodb_friend')->collection('stat_user_retention')->raw()->aggregate([
            [
                '$match' => $where
            ],
            [
                '$project' =>
                    [
                        '_id' => 0,
                        'Date' => ['$substr' => [['$add' => ['$date',28800000]], 0, 10]],
                        'retention1Day' => 1,
                        'retention3Day' => 1,
                        'retention7Day' =>1,
                        'retention15Day'=>1,
                        'retention30Day'=>1,
                    ],
            ],
            [
                '$sort' => ['Date'=>-1]
            ],
            [
                '$limit' => $limit
            ]
        ])->toArray();
        $result_array = [];
        if($data_array){
            foreach ($data_array as $item){
                $result_array[$item['Date']] = $item;
            }
        }
        return $result_array;
    }
    private function get_month_data($startDate,$endDate)
    {
        $startTime = $startDate ?? date("Y-m-d");
        $endTime = $endDate ?? date("Y-m-d");
        $startTime = strtotime(trim($startTime));
        $endTime = strtotime(trim($endTime))+ 86400;
        $startTimeMongo = $this->formatTimestampToMongo($startTime);
        $endTimeMongo = $this->formatTimestampToMongo($endTime);

        $where = [];
        $where['date'] = ['$gte' => $startTimeMongo, '$lt' => $endTimeMongo];

        $where['clubId'] = ['$ne'=>1000];
        $where1['clubId'] = ['$ne'=>-1000];
        return Db::connection('mongodb_club')->collection('platform_data_record')->raw()->aggregate([
            [
                '$match' => $where
            ],
            [
                '$match' => $where1
            ],
            [
                '$project' =>
                    [
                        '_id' => 0,
                        'newPlayerCount' => 1,
                        'gamePlayerCount' => 1,
                        'allBet' =>1,
                        'platformWinScore'=>1,
                        'revenue'=>1,
                        'userWinLostScore'=>1,
                        'userWinScore'=>1,
                        'validBet'=>1,
                        'platformProfit'=>1,
                    ],
            ],
            [
                '$group' =>
                    [
                        '_id' => null,
                        'newPlayerCount' => ['$sum' => '$newPlayerCount'],
                        'gamePlayerCount' => ['$sum' => '$gamePlayerCount'],
                        'allBet' => ['$sum' => '$allBet'],
                        'platformWinScore' => ['$sum' => '$platformWinScore'],
                        'revenue' => ['$sum' => '$revenue'],
                        'userWinLostScore' => ['$sum' => '$userWinLostScore'],
                        'userWinScore' => ['$sum' => '$userWinScore'],
                        'validBet' => ['$sum' => '$validBet'],
                        'platformProfit' => ['$sum' => '$platformProfit']
                    ]
            ]
        ])->toArray();
    }

    public function surveyLastMonthAndThisMonth(Request $request)
    {
        if ($request->method() == 'GET') {
            $begin_time_t=mktime(0,0,0,date('m'),1,date('Y'));
            $begin_time_y=mktime(23,59,59,date('m'),date('t'),date('Y'));

            $date_t_m_s = date("Y-m-d",$begin_time_t);//本月开始日期
            $date_t_m_e = date("Y-m-d",$begin_time_y);//本月结束日期


            $begin_time_y = strtotime(date('Y-m-01 00:00:00',strtotime('-1 month')));
            $end_time_y = strtotime(date("Y-m-d 23:59:59", strtotime(-date('d').'day')));

            $date_y_m_s = date("Y-m-d",$begin_time_y);//上月开始日期
            $date_y_m_e = date("Y-m-d",$end_time_y);//上月结束日期

            $thisMonthData = $this->get_month_data($date_t_m_s,$date_t_m_e);
            foreach ($thisMonthData as &$item) {
                $item['allBet'] = $this->formatMoneyFromMongo($item['allBet']??0);
                $item['platformWinScore'] = $this->formatMoneyFromMongo($item['platformWinScore']??0);
                $item['revenue'] = $this->formatMoneyFromMongo($item['revenue']??0);
                $item['userWinLostScore'] = $this->formatMoneyFromMongo($item['userWinLostScore']??0);
                $item['userWinScore'] = $this->formatMoneyFromMongo($item['userWinScore']??0);
                $item['validBet'] = $this->formatMoneyFromMongo($item['validBet']??0);
                $item['platformProfit'] = $this->formatMoneyFromMongo($item['platformProfit']??0);
                $item['promoterScore'] = $this->formatMoneyFromMongo($item['promoterScore']??0);
            }
            $lastMonthData = $this->get_month_data($date_y_m_s,$date_y_m_e);
            foreach ($lastMonthData as &$item) {
                $item['allBet'] = $this->formatMoneyFromMongo($item['allBet']??0);
                $item['platformWinScore'] = $this->formatMoneyFromMongo($item['platformWinScore']??0);
                $item['revenue'] = $this->formatMoneyFromMongo($item['revenue']??0);
                $item['userWinLostScore'] = $this->formatMoneyFromMongo($item['userWinLostScore']??0);
                $item['userWinScore'] = $this->formatMoneyFromMongo($item['userWinScore']??0);
                $item['validBet'] = $this->formatMoneyFromMongo($item['validBet']??0);
                $item['platformProfit'] = $this->formatMoneyFromMongo($item['platformProfit']??0);
                $item['promoterScore'] = $this->formatMoneyFromMongo($item['promoterScore']??0);
            }

            $list = [
                ['name' => '有效新增', 'today' => $thisMonthData[0]['newPlayerCount']??0, 'yestoday' => $lastMonthData[0]['newPlayerCount']??0],
                ['name' => '游戏人数', 'today' => $thisMonthData[0]['gamePlayerCount']??0, 'yestoday' => $lastMonthData[0]['gamePlayerCount']??0],

                ['name' => '游戏输赢', 'today' => $thisMonthData[0]['platformWinScore']??0, 'yestoday' => $lastMonthData[0]['platformWinScore']??0],
                ['name' => '官方税收', 'today' => $thisMonthData[0]['revenue']??0, 'yestoday' => $lastMonthData[0]['revenue']??0],
                ['name' => '官方盈亏', 'today' => $thisMonthData[0]['platformProfit']??0, 'yestoday' => $lastMonthData[0]['platformProfit']??0],
                ['name' => '总有效投注', 'today' => $thisMonthData[0]['validBet']??0, 'yestoday' => $lastMonthData[0]['validBet']??0],
                //['name' => '代理可提取金额', 'today' => $thisMonthData[0]['promoterScore']??0, 'yestoday' => $lastMonthData[0]['promoterScore']??0],
            ];
            return json(['code' => 0, 'msg' => 'ok', 'count' => 0, 'data' => $list]);
        }
        if ($request->method() == 'POST') {
            $assignData = [];
            return view('club/survey/surveyLastMonthAndThisMonth/list', $assignData);
        }
    }
    public function surveyPlayerScale(Request $request)
    {
        if ($request->method() == 'GET') {
            $getData = $request->get();
            extract($getData);
            $new_data = [];
            $dateValue = !empty($dateRange) ? trim($dateRange) : '';
            $userType = !empty($type) ? (int)$type : 3;
            if ($dateValue) {
                $dateValue = explode('~', $dateValue);
                $date_start = $dateValue[0] ?? date("Y-m-d");//"2020-12-28";//date("Y-m-d");
                $date_end = $dateValue[1] ?? date("Y-m-d");//"2020-12-27";//date("Y-m-d", strtotime('-1 day'));
            }else{
                //默认最近一周
                $date_start = date("Y-m-d", strtotime('-7 day'));
                $date_end = date("Y-m-d");
            }

            //日期列表
            $date_range = getDateFromRange($date_start,$date_end);
            $platform_data_record = $this->get_day_data($date_end, $date_start);
            $retention_data_record = $this->get_retention_day_data($date_end, $date_start);
            //dd($platform_data_record);
            $title = $series_name = "时间段";
            switch ($userType) {
                case 3:
                    foreach ($date_range as $item_date){
                        $new_data[$item_date] = $platform_data_record[$item_date]['newPlayerCount']??0;
                    }
                    break;
                case 4:
                    foreach ($date_range as $item_date){
                        $new_data[$item_date] = $platform_data_record[$item_date]['gamePlayerCount']??0;
                    }
                    break;
                case 6:
                    foreach ($date_range as $item_date){
                        $new_data[$item_date] = $platform_data_record[$item_date]['cumulatelGamePlayer']??0;
                    }
                    break;
                case 7:
                    foreach ($date_range as $item_date){
                        $new_data[$item_date] = $retention_data_record[$item_date]['retention1Day']??0;
                    }
                    break;
                case 8:
                    foreach ($date_range as $item_date){
                        $new_data[$item_date] = $retention_data_record[$item_date]['retention7Day']??0;
                    }
                    break;
                case 9:
                    foreach ($date_range as $item_date){
                        $new_data[$item_date] = $retention_data_record[$item_date]['retention15Day']??0;
                    }
                    break;
                case 10:
                    foreach ($date_range as $item_date){
                        $new_data[$item_date] = $retention_data_record[$item_date]['retention30Day']??0;
                    }
                    break;
                case 19:
                    foreach ($date_range as $item_date){
                        $new_data[$item_date] = $retention_data_record[$item_date]['retention3Day']??0;
                    }
                    break;
                case 100:
                    $ytitle = "数量";
                    foreach ($date_range as $item_date){
                        $new_data[$item_date] = $this->formatMoneyFromMongo($platform_data_record[$item_date]['revenue']??0);
                    }
                    break;
                case 101:
                    $ytitle = "数量";
                    foreach ($date_range as $item_date){
                        $new_data[$item_date] = $this->formatMoneyFromMongo($platform_data_record[$item_date]['platformWinScore']??0);
                    }
                    break;
                case 102:
                    $ytitle = '数量';
                    foreach ($date_range as $item_date){
                        $new_data[$item_date] = $this->formatMoneyFromMongo($platform_data_record[$item_date]['platformProfit']??0);
                    }
                    break;
                case 103:
                    $ytitle = '数量';
                    foreach ($date_range as $item_date){
                        $new_data[$item_date] = $this->formatMoneyFromMongo($platform_data_record[$item_date]['playerLeftCardNum']??0);
                    }
                    break;
                case 104:
                    foreach ($date_range as $item_date){
                        $new_data[$item_date] = $platform_data_record[$item_date]['rechargeUser']??0;
                    }
                    break;
                case 105:
                    $ytitle = '数量';
                    foreach ($date_range as $item_date){
                        $new_data[$item_date] = $platform_data_record[$item_date]['rechargeTimes']??0;
                    }
                    break;
                case 106:
                    $ytitle = '数量';
                    foreach ($date_range as $item_date){
                        $new_data[$item_date] = $this->formatMoneyFromMongo($platform_data_record[$item_date]['firstRechargeAvgScore']??0);
                    }
                    break;
                case 107:
                    $ytitle = '数量';
                    foreach ($date_range as $item_date){
                        $new_data[$item_date] = $this->formatMoneyFromMongo($platform_data_record[$item_date]['totalSalesScore']??0);
                    }
                    break;
                case 108:
                    $ytitle = '数量';
                    foreach ($date_range as $item_date){
                        $new_data[$item_date] = $this->formatMoneyFromMongo($platform_data_record[$item_date]['promoterSalesScore']??0);
                    }
                    break;
                case 109:
                    $ytitle = '数量';
                    foreach ($date_range as $item_date){
                        $new_data[$item_date] = $this->formatMoneyFromMongo($platform_data_record[$item_date]['mallSalesScore']??0);
                    }
                    break;
            }
            $categories = array_keys($new_data);
            $series_data = array_values($new_data);
            $return = [
                'data' => [
                    'categories' => $categories,
                    'series_data' => $series_data,
                    'series_name' => $ytitle??'人数',
                    'title' => $ytitle??'人数',
                    'ytitle' => $ytitle??'人数',
                ]
            ];
            return json($return);
        }
        if ($request->method() == 'POST') {
            $assignData = [];
            return view('club/survey/surveyPlayerScale/list', $assignData);
        }
    }
    public function surveyRevenueScale(Request $request)
    {
        if ($request->method() == 'GET') {
            $return = [
                'data' => [
                    'categories' => ['20210515','20210516','20210517','20210518','20210519','20210520','20210521'],
                    'series_data' => [1,2,3,4,10,20,50],
                    'series_name' => '时间段',
                    'title' => "时间段",
                    'ytitle' => '人数',
                ]
            ];
            return json($return);
        }
        if ($request->method() == 'POST') {
            $assignData = [];
            return view('club/survey/surveyRevenueScale/list', $assignData);
        }
    }
    private function getYestodayData()
    {

    }


    public function playerCount(Request $request)
    {
        if ($request->isAjax()) {
            $where = [];
            $getData = check_type($request->all());
            extract($getData);

            if (empty($gameId)) {
                return json(['code' => -1, 'msg' => '游戏ID不能为空']);
            }
            if ($gameId != 999) $where['gameId'] = $gameId;
            if (empty($dateRange)) {
                $startDate = date("Y-m-d", time() - 7*24*3600);
                $endDate = date("Y-m-d");
            } else {
                $dateArr = explode("~", $dateRange);
                $startDate = trim($dateArr[0]);
                $endDate = trim($dateArr[1]);
            }
            //dd($startDate);dd($endDate);dd($where);
            $startTime = strtotime($startDate);
            $endTime = strtotime("$endDate +1 day");
            $where['optTime'] = ['$gte' => $this->formatTimestampToMongo($startTime), '$lt' => $this->formatTimestampToMongo($endTime)];
            $userOnline = Db::connection('mongodb_friend')->collection('user_online')->raw()->aggregate([
                [
                    '$match' => $where
                ],
                [
                    '$project' =>
                        [
                            'optTime' => 1,
                            'day' => ['$dateToString' => ['format' => "%Y-%m-%d", 'date' => '$optTime']],
                            'gameId' => 1,
                        ]
                ],
                [
                    '$group' =>
                        [
                            '_id' => '$day',
                            'onlineCount' => ['$sum' => 1]
                        ]
                ],
                [
                    '$project' =>
                        [
                            //'day' => '$_id',
                            'onlineCount' => 1
                        ]
                ]
            ])->toArray();
            while($startTime < $endTime){
                $dateArr[] = date('Y-m-d',$startTime);
                $startTime =$startTime + 3600*24;
            }
            if (!empty($userOnline)) {
                $userOnlineArr = array_column($userOnline, 'onlineCount', '_id');
            }
            foreach ($dateArr as $date) {
                if (isset($userOnlineArr[$date])) {
                    $dateData[] = $userOnlineArr[$date];
                } else {
                    $dateData[] = 0;
                }
            }
            //dd($dateArr);dd($userOnlineArr);dd($dateData);
            $return = [
                'data' => [
                    'categories' => $dateArr,
                    'series_data' => $dateData,
                    'series_name' => '时间段',
                    'title' => "{$startDate}~{$endDate}时间段",
                    'ytitle' => '人数',
                ]
            ];
            return json($return);
        }

        $assignData = [
            'gameList' => getClubGameInfoGameRoomInfo(),

        ];
        return view('club/playerCount/list', $assignData);
    }

    public function overViewEcharts(Request $request)
    {
        $roomId = (int)$request->get('roomId', 0);
        $nowDate = date("Y-m-d");
        $startTime = strtotime($nowDate);
        $endTime = strtotime("$nowDate +1 day");
        $where = [];
        $where['roomId'] = $roomId;
        $where['statTime'] = ['$gte' => $this->formatTimestampToMongo($startTime), '$lt' => $this->formatTimestampToMongo($endTime)];
        $sro_user_list = Db::connection('mongodb_club')->collection('stat_room_online')->raw()->aggregate([
            [
                '$match' => $where
            ],
            [
                '$project' =>
                    [
                        'statValue' => 1,
                        'Date' => ['$substr' => [['$add' => ['$statTime', 28800000]], 0, 10]],
                        'Hour' => ['$hour' => ['$add' =>['$statTime', 28800000]]]
                    ]
            ]
        ])->toArray();
        $new_data = [];
        for ($i=0;$i<24;$i++){
            if(isset($sro_user_list[0]['Hour']) && $sro_user_list[0]['Hour'] == $i){
                $new_data[$i.':00'] = $sro_user_list[0]['statValue'];
            }else{
                $new_data[$i.':00'] = 0;
            }
        }
        $categories = array_keys($new_data);
        $series_data = array_values($new_data);

        $data['categories'] = $categories;
        $data['series_data'] = $series_data;
        $data['series_name'] = "今天";

        return json(['code' => 0, 'msg' => 'ok', 'count' => 0, 'data' => $data]);

    }

    public function onlineView(Request $request)
    {
        if ($request->isAjax()) {
            $where = [];
            $getData = $request->get();
            extract($getData);
            if (empty($startDate)) $startDate = date("Y-m-d", strtotime('-14 day'));
            if (empty($endDate)) $endDate = date("Y-m-d");
            $startTime = strtotime($startDate);
            $endTime = strtotime("$endDate +1 day");
            $roomId = 999;
            $where['roomId'] = $roomId;
            $where['statTime'] = ['$gte' => $this->formatTimestampToMongo($startTime), '$lt' => $this->formatTimestampToMongo($endTime)];
            $count_array = Db::connection('mongodb_club')->collection('stat_room_online')->raw()->aggregate([
                [
                    '$match' => $where
                ],
                [
                    '$project' =>
                        [
                            'Date' => ['$substr' => [['$add' => ['$statTime',28800000]], 0, 10]],
                            //'Date' => ['$substr' => ['$statTime', 0, 10]],
                        ]
                ],
                [
                    '$group' =>
                        [
                            '_id' => '$Date',
                        ]
                ],
                [
                    '$group' =>
                        [
                            '_id' => null,
                            'count' => ['$sum' => 1]
                        ]
                ]
            ])->toArray();
            $count = 0;
            if($count_array && sizeof($count_array) > 0) $count = $count_array[0]['count'];
            $stat_room_online_list = Db::connection('mongodb_club')->collection('stat_room_online')->raw()->aggregate([
                [
                    '$match' => $where
                ],
                [
                    '$project' =>
                        [
                            //'Date' => ['$substr' => ['$statTime', 0, 10]],
                            'Date' => ['$substr' => [['$add' => ['$statTime',28800000]], 0, 10]],
                            'Hour' => ['$hour' => ['$add' => ['$statTime',28800000]]],
                            //'Hour' => ['$hour' => ['$statTime']],
//                    'statTime' => 1,
                            'statValue' => 1,
//                    'iosValue' => 1,
//                    'androidValue' => 1,
//                    'simulatorValue' => 1
                        ]
                ],
                [
                    '$group' =>
                        [
                            '_id' => '$Date',
                            'avgV' => ['$avg' => '$statValue'],
                            'minV' => ['$min' => '$statValue'],
                            'maxV' => ['$max' => '$statValue'],
                            'H00' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 0]], 'then'=>'$statValue', 'else'=>0]]],
                            'H01' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 1]], 'then'=>'$statValue', 'else'=>0]]],
                            'H02' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 2]], 'then'=>'$statValue', 'else'=>0]]],
                            'H03' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 3]], 'then'=>'$statValue', 'else'=>0]]],
                            'H04' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 4]], 'then'=>'$statValue', 'else'=>0]]],
                            'H05' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 5]], 'then'=>'$statValue', 'else'=>0]]],
                            'H06' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 6]], 'then'=>'$statValue', 'else'=>0]]],
                            'H07' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 7]], 'then'=>'$statValue', 'else'=>0]]],
                            'H08' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 8]], 'then'=>'$statValue', 'else'=>0]]],
                            'H09' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 9]], 'then'=>'$statValue', 'else'=>0]]],
                            'H10' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 10]], 'then'=>'$statValue', 'else'=>0]]],
                            'H11' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 11]], 'then'=>'$statValue', 'else'=>0]]],
                            'H12' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 12]], 'then'=>'$statValue', 'else'=>0]]],
                            'H13' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 13]], 'then'=>'$statValue', 'else'=>0]]],
                            'H14' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 14]], 'then'=>'$statValue', 'else'=>0]]],
                            'H15' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 15]], 'then'=>'$statValue', 'else'=>0]]],
                            'H16' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 16]], 'then'=>'$statValue', 'else'=>0]]],
                            'H17' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 17]], 'then'=>'$statValue', 'else'=>0]]],
                            'H18' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 18]], 'then'=>'$statValue', 'else'=>0]]],
                            'H19' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 19]], 'then'=>'$statValue', 'else'=>0]]],
                            'H20' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 20]], 'then'=>'$statValue', 'else'=>0]]],
                            'H21' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 21]], 'then'=>'$statValue', 'else'=>0]]],
                            'H22' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 22]], 'then'=>'$statValue', 'else'=>0]]],
                            'H23' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 23]], 'then'=>'$statValue', 'else'=>0]]],

                            'H00Count' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 0]], 'then'=>1, 'else'=>0]]],
                            'H01Count' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 1]], 'then'=>1, 'else'=>0]]],
                            'H02Count' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 2]], 'then'=>1, 'else'=>0]]],
                            'H03Count' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 3]], 'then'=>1, 'else'=>0]]],
                            'H04Count' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 4]], 'then'=>1, 'else'=>0]]],
                            'H05Count' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 5]], 'then'=>1, 'else'=>0]]],
                            'H06Count' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 6]], 'then'=>1, 'else'=>0]]],
                            'H07Count' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 7]], 'then'=>1, 'else'=>0]]],
                            'H08Count' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 8]], 'then'=>1, 'else'=>0]]],
                            'H09Count' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 9]], 'then'=>1, 'else'=>0]]],
                            'H10Count' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 10]], 'then'=>1, 'else'=>0]]],
                            'H11Count' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 11]], 'then'=>1, 'else'=>0]]],
                            'H12Count' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 12]], 'then'=>1, 'else'=>0]]],
                            'H13Count' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 13]], 'then'=>1, 'else'=>0]]],
                            'H14Count' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 14]], 'then'=>1, 'else'=>0]]],
                            'H15Count' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 15]], 'then'=>1, 'else'=>0]]],
                            'H16Count' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 16]], 'then'=>1, 'else'=>0]]],
                            'H17Count' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 17]], 'then'=>1, 'else'=>0]]],
                            'H18Count' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 18]], 'then'=>1, 'else'=>0]]],
                            'H19Count' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 19]], 'then'=>1, 'else'=>0]]],
                            'H20Count' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 20]], 'then'=>1, 'else'=>0]]],
                            'H21Count' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 21]], 'then'=>1, 'else'=>0]]],
                            'H22Count' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 22]], 'then'=>1, 'else'=>0]]],
                            'H23Count' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 23]], 'then'=>1, 'else'=>0]]],
                        ]
                ],
                [
                    '$sort' => ['_id'=>-1]
                ],
                [
                    '$skip' => $request->skip
                ],
                [
                    '$limit' => $request->limit
                ]
            ])->toArray();
            foreach ($stat_room_online_list AS $key => $value) {
                $stat_room_online_list[$key]['statTime'] = $stat_room_online_list[$key]['_id'];
                $stat_room_online_list[$key]['avgV'] = round($stat_room_online_list[$key]['avgV'], 2);
                if($stat_room_online_list[$key]['H00Count'] > 0)
                    $stat_room_online_list[$key]['H00'] = round($stat_room_online_list[$key]['H00'] / $stat_room_online_list[$key]['H00Count'], 2);
                if($stat_room_online_list[$key]['H01Count'] > 0)
                    $stat_room_online_list[$key]['H01'] = round($stat_room_online_list[$key]['H01'] / $stat_room_online_list[$key]['H01Count'], 2);
                if($stat_room_online_list[$key]['H02Count'] > 0)
                    $stat_room_online_list[$key]['H02'] = round($stat_room_online_list[$key]['H02'] / $stat_room_online_list[$key]['H02Count'], 2);
                if($stat_room_online_list[$key]['H03Count'] > 0)
                    $stat_room_online_list[$key]['H03'] = round($stat_room_online_list[$key]['H03'] / $stat_room_online_list[$key]['H03Count'], 2);
                if($stat_room_online_list[$key]['H04Count'] > 0)
                    $stat_room_online_list[$key]['H04'] = round($stat_room_online_list[$key]['H04'] / $stat_room_online_list[$key]['H04Count'], 2);
                if($stat_room_online_list[$key]['H05Count'] > 0)
                    $stat_room_online_list[$key]['H05'] = round($stat_room_online_list[$key]['H05'] / $stat_room_online_list[$key]['H05Count'], 2);
                if($stat_room_online_list[$key]['H06Count'] > 0)
                    $stat_room_online_list[$key]['H06'] = round($stat_room_online_list[$key]['H06'] / $stat_room_online_list[$key]['H06Count'], 2);
                if($stat_room_online_list[$key]['H07Count'] > 0)
                    $stat_room_online_list[$key]['H07'] = round($stat_room_online_list[$key]['H07'] / $stat_room_online_list[$key]['H07Count'], 2);
                if($stat_room_online_list[$key]['H08Count'] > 0)
                    $stat_room_online_list[$key]['H08'] = round($stat_room_online_list[$key]['H08'] / $stat_room_online_list[$key]['H08Count'], 2);
                if($stat_room_online_list[$key]['H09Count'] > 0)
                    $stat_room_online_list[$key]['H09'] = round($stat_room_online_list[$key]['H09'] / $stat_room_online_list[$key]['H09Count'], 2);
                if($stat_room_online_list[$key]['H10Count'] > 0)
                    $stat_room_online_list[$key]['H10'] = round($stat_room_online_list[$key]['H10'] / $stat_room_online_list[$key]['H10Count'], 2);
                if($stat_room_online_list[$key]['H11Count'] > 0)
                    $stat_room_online_list[$key]['H11'] = round($stat_room_online_list[$key]['H11'] / $stat_room_online_list[$key]['H11Count'], 2);
                if($stat_room_online_list[$key]['H12Count'] > 0)
                    $stat_room_online_list[$key]['H12'] = round($stat_room_online_list[$key]['H12'] / $stat_room_online_list[$key]['H12Count'], 2);
                if($stat_room_online_list[$key]['H13Count'] > 0)
                    $stat_room_online_list[$key]['H13'] = round($stat_room_online_list[$key]['H13'] / $stat_room_online_list[$key]['H13Count'], 2);
                if($stat_room_online_list[$key]['H14Count'] > 0)
                    $stat_room_online_list[$key]['H14'] = round($stat_room_online_list[$key]['H14'] / $stat_room_online_list[$key]['H14Count'], 2);
                if($stat_room_online_list[$key]['H15Count'] > 0)
                    $stat_room_online_list[$key]['H15'] = round($stat_room_online_list[$key]['H15'] / $stat_room_online_list[$key]['H15Count'], 2);
                if($stat_room_online_list[$key]['H16Count'] > 0)
                    $stat_room_online_list[$key]['H16'] = round($stat_room_online_list[$key]['H16'] / $stat_room_online_list[$key]['H16Count'], 2);
                if($stat_room_online_list[$key]['H17Count'] > 0)
                    $stat_room_online_list[$key]['H17'] = round($stat_room_online_list[$key]['H17'] / $stat_room_online_list[$key]['H17Count'], 2);
                if($stat_room_online_list[$key]['H18Count'] > 0)
                    $stat_room_online_list[$key]['H18'] = round($stat_room_online_list[$key]['H18'] / $stat_room_online_list[$key]['H18Count'], 2);
                if($stat_room_online_list[$key]['H19Count'] > 0)
                    $stat_room_online_list[$key]['H19'] = round($stat_room_online_list[$key]['H19'] / $stat_room_online_list[$key]['H19Count'], 2);
                if($stat_room_online_list[$key]['H20Count'] > 0)
                    $stat_room_online_list[$key]['H20'] = round($stat_room_online_list[$key]['H20'] / $stat_room_online_list[$key]['H20Count'], 2);
                if($stat_room_online_list[$key]['H21Count'] > 0)
                    $stat_room_online_list[$key]['H21'] = round($stat_room_online_list[$key]['H21'] / $stat_room_online_list[$key]['H21Count'], 2);
                if($stat_room_online_list[$key]['H22Count'] > 0)
                    $stat_room_online_list[$key]['H22'] = round($stat_room_online_list[$key]['H22'] / $stat_room_online_list[$key]['H22Count'], 2);
                if($stat_room_online_list[$key]['H23Count'] > 0)
                    $stat_room_online_list[$key]['H23'] = round($stat_room_online_list[$key]['H23'] / $stat_room_online_list[$key]['H23Count'], 2);
            }
            return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $stat_room_online_list]);
        }
        $assignData = [];
        return view('club/onlineView/list', $assignData);
    }

    public function modifyCodeRate(Request $request)
    {
        if ($request->isAjax()) {
            $where = [];
            $where['status'] = 1;
            $getData = check_type($request->all());
            extract($getData);
            if (!empty($promoterId)) {
                $where['promoterId'] = (int)$promoterId;
            }
            if (!empty($clubId)) {
                $where['clubId'] = (int)$clubId;
            }
            $count = PromoterDetail::where($where)->count();
            $list = PromoterDetail::where($where)->orderBy('createTime', 'asc')->skip($request->skip)->take($request->limit)->select('promoterId','clubId','invitationCode','setRate')->get()->toArray();
            $clubIdArr = array_column($list, 'clubId');
            $clubsName = ClubMo::where(['clubId' => ['$in'=>$clubIdArr]])->select('clubName','clubId')->get()->toArray();
            $clubsName = unsetFieldFromArray($clubsName);
            $list = merge_array($list, $clubsName, 'clubId');

            /*foreach ($list as &$item) {
                $item['createTime'] = $this->formatDate($item['createTime']);
                $item['score'] = $this->formatMoneyFromMongo($item['score']);
                $item['totalExchange'] = $this->formatMoneyFromMongo($item['totalExchange']);
            }*/
            return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $list]);
        }

        $where = ['status' => 1];
        $list = ClubMo::where($where)->select('clubId','clubName')->get()->toArray();
        $clubs = [];
        foreach ($list as $item) {
            $clubs[$item['clubId']] = $item['clubName'];
        }
        $assignData = [
            'clubs'=>$clubs
        ];
        return view('club/modifyCodeRate/list',$assignData);
    }

    public function modifyCodeRate2(Request $request)
    {
        if ($request->isAjax()) {
            $postData = $request->post();
            extract($postData);
            $value = isset($value) ? (int)$value : 0;
            if (!$field || !$_id || !$value) {
                return json(['code' => -1, 'msg' => '参数错误']);
            }
            $where = ['_id' => $_id];

            /*if (in_array($field, ['topUp', 'giveRate']) && !in_array(session('groupId'), [1, 8])) {
                return json(['code' => -1, 'msg' => '没有操作权限!']);
            }*/
            if ($field == 'invitationCode') {
                if (strlen($value) != 6) return json(['code' => -1, 'msg' => '邀请码是6位数字!']);
                $rs = PromoterDetail::where(["invitationCode" => $value])->select('invitationCode','promoterId')->get()->toArray();
                if (!empty($rs)) return json(['code' => -1, 'msg' => '邀请码已存在 请换一个!']);
            }
            if ($field == 'setRate') {
                if (($value < 0) || ($value > 100)) return json(['code' => -1, 'msg' => '分成比例是 0--100!']);
                //和上级比
                $rs = PromoterDetail::where('_id', $_id)->first();
                if ($rs->pid > 0) {
                    $rs1 = PromoterDetail::where(["promoterId" => $rs->pid, "clubId" => $rs->clubId])->select('setRate')->get()->toArray();
                    if (!empty($rs1) && ($rs1[0]['setRate'] < $value)) return json(['code' => -1, 'msg' => "分成比例不能大于上级({$rs1[0]['setRate']} %)!"]);
                }
                //和下级比
                $rs2 = PromoterDetail::where(["pid" => $rs->promoterId, "clubId" => $rs->clubId])->select('setRate')->get()->toArray();
                if (!empty($rs2)) {
                    $setRateArr = array_column($rs2, "setRate");
                    $maxSetRate = max($setRateArr);
                    if ($value < $maxSetRate) return json(['code' => -1, 'msg' => "分成比例不能小于下级({$maxSetRate} %)!"]);
                }

            }
            $updateData = [
                $field=> $value
            ];
            $updateResult = PromoterDetail::where($where)->update($updateData);
            if (!$updateResult) return json(['code' => -1, 'msg' => '修改失败']);
            $msg = "修改为： {$field} = {$value}";
            $this->adminLog(["content"=>$msg]);
            return json(['code' => 0, 'msg' => '修改成功']);
        }
    }

    public function member(Request $request)
    {
        if ($request->isAjax()) {
            $where = [];
            $getData = check_type($request->all());
            extract($getData);
            if (empty($startDate)) {
                $startDate = date("Y-m-d");
            }
            if (empty($endDate)) {
                $endDate = date("Y-m-d");
                $endTime = strtotime($endDate) + 86400;
            }else{
                $endTime = strtotime($endDate);
            }
            $startTime = strtotime($startDate);


            if ($startTime >= $endTime) return json(['code' => -1, 'msg' => '请核对开始时间结束时间']);
            $where[] = ['createTime', '>=', $this->formatTimestampToMongo($startTime)];
            $where[] = ['createTime', '<', $this->formatTimestampToMongo($endTime)];

            if (!empty($status)) {
                if ($status == 2) {
                    $where['withdrawLocked'] = 1;
                } else {
                    $where['status'] = $status;
                }
            }
            if (!empty($searchType) && !empty($searchValue)) {
                if ($searchType == 1) {
                    $where['promoterId'] = (int)$searchValue;
                }
            }

            if (!empty($clubName)) {
                $clubInfos = static::getClubs(['clubName' => $clubName]);
                if($clubInfos){
                    $promoterArr = [];
                    foreach ($clubInfos as $itemClub){
                        $promoters = PromoterDetail::where(['clubId' => $itemClub['clubId']])->select('promoterId')->get()->toArray();
                        foreach ($promoters as $promoterItem){
                            $promoterArr[] = $promoterItem['promoterId'];
                        }
                    }

                    if (!empty($where['promoterId'])) {
                        foreach ($promoterArr as $k => $itemtrim){
                            if($where['promoterId'] !== $itemtrim){
                                unset($promoterArr[$k]);
                            }
                        }
                    }
                    $promoterArr = array_values($promoterArr);
                    $where['promoterId'] = ['$in'=>$promoterArr];
                }else{
                    return json(['code' => -1, 'msg' => '无此俱乐部']);
                }
            }


            $count = PromoterMain::where($where)->count();
            $list = PromoterMain::where($where)->orderBy('createTime', 'asc')->skip($request->skip)->take($request->limit)->get()->toArray();
            foreach ($list as $k => &$item){
                if(empty($item['myPlayerTotalCount'])){
                    $list[$k]['myPlayerTotalCount'] = 0;
                }
                if(empty($item['myPlayerMemberCount'])){
                    $list[$k]['myPlayerMemberCount'] = 0;
                }
            }

//            $filter = [];
//            $filter ['date']= ['$gte' => $this->formatTimestampToMongo($startTime), '$lt' => $this->formatTimestampToMongo($endTime)];
//            $directTeamData = Db::connection('mongodb_club')->collection('stat_promoter_daily')->raw()->aggregate([
//                [
//                    '$match' => $filter
//                ],
//                [
//                    '$project' =>
//                        [
//                            'promoterId' => 1,
//                            'agentRevenue' => 1,//直属代理税收
//                            'revenue' => 1,//直属税收
//                            'myProfit' => 1,//直属分成
//                            'teamAgentRevenue' => 1,//团队代理税收
//                            'teamRevenue' => 1,//合伙团队税收
//                            'teamProfit' => 1,//合伙团队分成
//                            'myTeamProfit' => 1,//预计佣金
//                            'teamDevoteProfit' => 1,//代理团队贡献
//                            'mySelfAgentRevenue' => 1,//自营代理税收
//                            'date' =>1
//                        ]
//                ],
//                [
//                    '$sort' => ['date'=>-1]
//                ],
//                [
//                    '$group' =>
//                        [
//                            '_id' => '$promoterId',
//                            'promoterId'=> ['$first'=>'$promoterId'],
//                            'agentRevenue'=> ['$sum'=>'$agentRevenue'],//代理直属税收
//                            //'revenue'=> ['$sum'=>'$revenue'],//直属税收
//                            'myProfit'=> ['$sum'=>'$myProfit'],//直属分成
//                            'teamAgentRevenue'=> ['$sum'=>'$teamAgentRevenue'],//代理团队税收
//                            //'teamRevenue'=> ['$sum'=>'$teamRevenue'],//合伙团队税收
//                            'teamProfit'=> ['$sum'=>'$teamProfit'],//合伙团队分成
//                            'myTeamProfit'=> ['$sum'=>'$myTeamProfit'],//预计佣金
//                            'teamDevoteProfit' => ['$sum'=>'$teamDevoteProfit'],//代理团队贡献
//                            'mySelfAgentRevenue' => ['$sum'=>'$mySelfAgentRevenue'],//自营代理税收
//                        ]
//                ],
//            ])->toArray();

            foreach ($list as &$item) {
                //所属俱乐部
                $clubIdArr = [];
                $clubsNameStr = "";
                $clubs = PromoterDetail::where('promoterId', $item['promoterId'])->select('promoterId','clubId','setRate','pid','invitationCode')->get()->toArray();
                foreach ($clubs as $clubItem){
                    $item['pid'] = $clubItem['pid'];
                    $clubIdArr[] = $clubItem['clubId'];
                }

                $clubsName = ClubMo::where(['clubId' => ['$in'=>$clubIdArr]])->select('clubName','clubId')->get()->toArray();
                $clubsName = merge_array($clubsName, $clubs, 'clubId');

                foreach ($clubsName as $clubNameItem){
                    $clubsNameStr .= $clubNameItem['clubName'].'('.$clubNameItem['setRate'].'%),';
                }
                $clubsNameStr = rtrim($clubsNameStr, ",");
                unset($clubIdArr);


                $item['clubsNameStr'] = $clubsNameStr;
                $item['createTime'] = $this->formatDate($item['createTime']);
                $item['score'] = $this->formatMoneyFromMongo($item['score']);
                $item['totalExchange'] = $this->formatMoneyFromMongo($item['totalExchange']);
//                $item['totalRevenue'] = 0;
//                $item['totalMyProfit'] = 0;
//                $item['totalTeamRevenue'] = 0;
//                $item['totalTeamProfit'] = 0;
//                $item['totalMyTeamProfit'] = 0;
//                $item['totalTeamDevoteProfit'] = 0;
//                $item['totalMySelfAgentRevenue'] = 0;
//                $item['totalTeamContri'] = 0;
//                $item['totalAgentRevenue'] = 0;
//                $item['totalTeamAgentRevenue'] = 0;
//                foreach ($directTeamData as $dt_item){
//                    if($item['promoterId'] == $dt_item['promoterId']){
//                        $item['totalAgentRevenue'] = $this->formatMoneyFromMongo($dt_item['agentRevenue']);//直属代理税收
//                        //$item['totalRevenue'] = $this->formatMoneyFromMongo($dt_item['revenue']);//直属税收
//                        $item['totalMyProfit'] = $this->formatMoneyFromMongo($dt_item['myProfit']);//直属分成
//                        $item['totalTeamAgentRevenue'] = $this->formatMoneyFromMongo($dt_item['teamAgentRevenue']);//合伙团队代理税收
//                        //$item['totalTeamRevenue'] = $this->formatMoneyFromMongo($dt_item['teamRevenue']);//合伙团队税收
//                        $item['totalTeamProfit'] = $this->formatMoneyFromMongo($dt_item['teamProfit']);//合伙团队分成
//                        $item['totalMyTeamProfit'] = $this->formatMoneyFromMongo($dt_item['myTeamProfit']);//预计佣金
//                        $item['totalTeamContri'] = $this->formatMoneyFromMongoNo($item['totalMyTeamProfit'] - $item['totalMyProfit']);
//                        if($item['totalTeamContri'] == 0.00) $item['totalTeamContri'] = 0;
//                        if(!empty($dt_item['teamDevoteProfit'])) $item['totalTeamDevoteProfit'] = $this->formatMoneyFromMongo($dt_item['teamDevoteProfit']);//代理团队贡献
//                        $item['totalMySelfAgentRevenue'] = $this->formatMoneyFromMongo($dt_item['mySelfAgentRevenue']);//自营代理税收
//                    }
//                }
            }

            return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $list]);
        }


        $getData = check_type($request->all());
        extract($getData);
        $promoterId = !empty($promoterId) ? (int)$promoterId : '';
        $assignData = [
            'promoterId'=>$promoterId
        ];
        return view('club/clubMemberList/list',$assignData);
    }


    public function memberSummary(Request $request)
    {
        if ($request->isAjax()) {
            $where = $where2 = [];
            $getData = check_type($request->all());
            extract($getData);
            if (empty($startDate)) {
                $startDate = date("Y-m-d");
            }
            if (empty($endDate)) {
                $endDate = date("Y-m-d");
                $endTime = strtotime($endDate) + 86400;
            }else{
                $endTime = strtotime($endDate);
            }
            $startTime = strtotime($startDate);


            if ($startTime >= $endTime) return json(['code' => -1, 'msg' => '请核对开始时间结束时间']);
            $where[] = ['createTime', '>=', $this->formatTimestampToMongo($startTime)];
            $where[] = ['createTime', '<', $this->formatTimestampToMongo($endTime)];

            if (!empty($status)) {
                if ($status == 2) {
                    $where['withdrawLocked'] = 1;
                    $where2['withdrawLocked'] = 1;
                } else {
                    $where['status'] = $status;
                    $where2['status'] = $status;
                }
            }
            if (!empty($searchType) && !empty($searchValue)) {
                if ($searchType == 1) {
                    $where['promoterId'] = (int)$searchValue;
                    $where2['promoterId'] = (int)$searchValue;
                }
            }

            if (!empty($clubName)) {
                $clubInfos = static::getClubs(['clubName' => $clubName]);
                if($clubInfos){
                    $promoterArr = [];
                    foreach ($clubInfos as $itemClub){
                        $promoters = PromoterDetail::where(['clubId' => $itemClub['clubId']])->select('promoterId')->get()->toArray();
                        foreach ($promoters as $promoterItem){
                            $promoterArr[] = $promoterItem['promoterId'];
                        }
                    }

                    if (!empty($where['promoterId'])) {
                        foreach ($promoterArr as $k => $itemtrim){
                            if($where['promoterId'] !== $itemtrim){
                                unset($promoterArr[$k]);
                            }
                        }
                    }
                    $promoterArr = array_values($promoterArr);
                    $where['promoterId'] = ['$in'=>$promoterArr];
                    $where2['promoterId'] = ['$in'=>$promoterArr];
                }else{
                    return json(['code' => -1, 'msg' => '无此俱乐部']);
                }
            }



            $list = PromoterMain::where($where)->orderBy('createTime', 'asc')->skip($request->skip)->take($request->limit)->get()->toArray();
            foreach ($list as $k => &$item){
                if(empty($item['myPlayerTotalCount'])){
                    $list[$k]['myPlayerTotalCount'] = 0;
                }
                if(empty($item['myPlayerMemberCount'])){
                    $list[$k]['myPlayerMemberCount'] = 0;
                }
            }
            $scoreSum = 0;
            $totalExchangeSum = 0;
            $myPlayerTotalCountSum = 0;
            $myPlayerMemberCountSum = 0;

            foreach ($list as &$item) {

                $score = $this->formatMoneyFromMongo($item['score']);
                $totalExchange = $this->formatMoneyFromMongo($item['totalExchange']);
                $myPlayerTotalCount = $item['myPlayerTotalCount'];
                $myPlayerMemberCount = $item['myPlayerMemberCount'];
                $scoreSum += $score;
                $totalExchangeSum += $totalExchange;
                $myPlayerTotalCountSum += $myPlayerTotalCount;
                $myPlayerMemberCountSum += $myPlayerMemberCount;
            }
            $scoreSum = $this->formatMoneyFromMongoNo($scoreSum);
            $totalExchangeSum = $this->formatMoneyFromMongoNo($totalExchangeSum);
            //新加开始 替换代理可提取金额汇总、代理已提取金额汇总
            $list = PromoterMain::where($where2)->orderBy('createTime', 'asc')->skip($request->skip)->take($request->limit)->get()->toArray();
            $filter = [];
            $filter ['date']= ['$gte' => $this->formatTimestampToMongo($startTime), '$lt' => $this->formatTimestampToMongo($endTime)];
            $directTeamData = Db::connection('mongodb_club')->collection('stat_promoter_daily')->raw()->aggregate([
                [
                    '$match' => $filter
                ],
                [
                    '$project' =>
                        [
                            'promoterId' => 1,
                            'myProfit' => 1,//直属分成
                            'teamProfit' => 1,//合伙团队分成
                            'myTeamProfit' => 1,//预计佣金
                            'transferToScoreAmount' =>1,//代理已提取金额
                            'date' =>1
                        ]
                ],
                [
                    '$sort' => ['date'=>-1]
                ],
                [
                    '$group' =>
                        [
                            '_id' => '$promoterId',
                            'promoterId'=> ['$first'=>'$promoterId'],
                            'myProfit'=> ['$sum'=>'$myProfit'],//直属分成
                            'teamProfit'=> ['$sum'=>'$teamProfit'],//合伙团队分成
                            'myTeamProfit'=> ['$sum'=>'$myTeamProfit'],//预计佣金
                            'transferToScoreAmount' => ['$sum'=>'$transferToScoreAmount'],//代理已提取金额
                        ]
                ],
            ])->toArray();
            $transferToScoreAmountSum = 0;
            $agentCanWithdrawSum = 0;
            foreach ($list as &$item) {
                $item['totalMyProfit'] = 0;
                $item['totalTeamProfit'] = 0;
                $item['totalMyTeamProfit'] = 0;//预计佣金
                $item['totalTeamDevoteProfit'] = 0;
                $item['teamTransferToScoreAmount'] = 0;
                $item['agentCanWithdraw'] = 0;
                foreach ($directTeamData as $dt_item){
                    if($item['promoterId'] == $dt_item['promoterId']){
                        $item['totalMyProfit'] = $this->formatMoneyFromMongo($dt_item['myProfit']);//直属分成
                        $item['totalTeamProfit'] = $this->formatMoneyFromMongo($dt_item['teamProfit']);//合伙团队分成
                        $item['totalMyTeamProfit'] = $this->formatMoneyFromMongo($dt_item['myTeamProfit']);//预计佣金
                        $item['transferToScoreAmount'] = $this->formatMoneyFromMongo($dt_item['transferToScoreAmount']);//代理已提取余额
                        $item['agentCanWithdraw'] = $this->formatMoneyFromMongoNo($item['totalMyTeamProfit'] - $item['transferToScoreAmount']);
                        $transferToScoreAmountSum += $item['transferToScoreAmount'];
                        //$agentCanWithdrawSum += $item['agentCanWithdraw'];
                    }
                }
                //新修改的
                $agentCanWithdrawSum += $item['score'];
            }
            $transferToScoreAmountSum = $this->formatMoneyFromMongoNo($transferToScoreAmountSum);
            $agentCanWithdrawSum = $this->formatMoneyFromMongo($agentCanWithdrawSum);
            //新加结束

            return json(['code' => 0, 'msg' => 'ok', 'data' => ['scoreSum' => $agentCanWithdrawSum,'totalExchangeSum' => $transferToScoreAmountSum, 'myPlayerTotalCountSum' => $myPlayerTotalCountSum, 'myPlayerMemberCountSum' => $myPlayerMemberCountSum]]);
        }


        $getData = check_type($request->all());
        extract($getData);
        $promoterId = !empty($promoterId) ? (int)$promoterId : '';
        $assignData = [
            'promoterId'=>$promoterId
        ];
        return view('club/clubMemberList/list',$assignData);
    }

    public function memberPerformance(Request $request)
    {
        if ($request->isAjax()) {
            $where = [];
            $getData = check_type($request->all());
            extract($getData);
            if (empty($startDate)) {
                $startDate = date("Y-m-d");
            }
            if (empty($endDate)) {
                $endDate = date("Y-m-d");
                $endTime = strtotime($endDate) + 86400;
            }else{
                $endTime = strtotime($endDate);
            }
            $startTime = strtotime($startDate);

            if ($startTime >= $endTime) return json(['code' => -1, 'msg' => '请核对开始时间结束时间']);
            if (!empty($status)) {
                if ($status == 2) {
                    $where['withdrawLocked'] = 1;
                } else {
                    $where['status'] = $status;
                }
            }
            if (!empty($searchType) && !empty($searchValue)) {
                if ($searchType == 1) {
                    $where['promoterId'] = (int)$searchValue;
                }
            }

            if (!empty($clubName)) {
                $clubInfos = static::getClubs(['clubName' => $clubName]);
                if($clubInfos){
                    $promoterArr = [];
                    foreach ($clubInfos as $itemClub){
                        $promoters = PromoterDetail::where(['clubId' => $itemClub['clubId']])->select('promoterId')->get()->toArray();
                        foreach ($promoters as $promoterItem){
                            $promoterArr[] = $promoterItem['promoterId'];
                        }
                    }

                    if (!empty($where['promoterId'])) {
                        foreach ($promoterArr as $k => $itemtrim){
                            if($where['promoterId'] !== $itemtrim){
                                unset($promoterArr[$k]);
                            }
                        }
                    }
                    $promoterArr = array_values($promoterArr);
                    $where['promoterId'] = ['$in'=>$promoterArr];
                }else{
                    return json(['code' => -1, 'msg' => '无此俱乐部']);
                }
            }


            $count = PromoterMain::where($where)->count();
            $list = PromoterMain::where($where)->orderBy('createTime', 'asc')->skip($request->skip)->take($request->limit)->get()->toArray();

            $filter = [];
            $filter ['date']= ['$gte' => $this->formatTimestampToMongo($startTime), '$lt' => $this->formatTimestampToMongo($endTime)];
            $directTeamData = Db::connection('mongodb_club')->collection('stat_promoter_daily')->raw()->aggregate([
                [
                    '$match' => $filter
                ],
                [
                    '$project' =>
                        [
                            'promoterId' => 1,
                            'agentRevenue' => 1,//直属代理税收
                            'revenue' => 1,//直属税收
                            'myProfit' => 1,//直属分成
                            'teamAgentRevenue' => 1,//团队代理税收
                            'teamRevenue' => 1,//合伙团队税收
                            'teamProfit' => 1,//合伙团队分成
                            'myTeamProfit' => 1,//预计佣金
                            'teamDevoteProfit' => 1,//代理团队贡献
                            'mySelfAgentRevenue' => 1,//自营代理税收
                            'transferToScoreAmount' =>1,//代理已提取金额
                            'date' =>1
                        ]
                ],
                [
                    '$sort' => ['date'=>-1]
                ],
                [
                    '$group' =>
                        [
                            '_id' => '$promoterId',
                            'promoterId'=> ['$first'=>'$promoterId'],
                            'agentRevenue'=> ['$sum'=>'$agentRevenue'],//代理直属税收
                            'myProfit'=> ['$sum'=>'$myProfit'],//直属分成
                            'teamAgentRevenue'=> ['$sum'=>'$teamAgentRevenue'],//代理团队税收
                            'teamProfit'=> ['$sum'=>'$teamProfit'],//合伙团队分成
                            'myTeamProfit'=> ['$sum'=>'$myTeamProfit'],//预计佣金
                            'teamDevoteProfit' => ['$sum'=>'$teamDevoteProfit'],//代理团队贡献
                            'mySelfAgentRevenue' => ['$sum'=>'$mySelfAgentRevenue'],//自营代理税收
                            'transferToScoreAmount' => ['$sum'=>'$transferToScoreAmount'],//代理已提取金额
                        ]
                ],
            ])->toArray();

            foreach ($list as &$item) {
                $item['totalRevenue'] = 0;
                $item['totalMyProfit'] = 0;
                $item['totalTeamRevenue'] = 0;
                $item['totalTeamProfit'] = 0;
                $item['totalMyTeamProfit'] = 0;
                $item['totalTeamDevoteProfit'] = 0;
                $item['totalMySelfAgentRevenue'] = 0;
                $item['totalTeamContri'] = 0;
                $item['totalAgentRevenue'] = 0;
                $item['totalTeamAgentRevenue'] = 0;
                $item['totalTransferToScoreAmount'] = 0;
                $item['transferToScoreAmount'] = 0;
                $item['agentCanWithdraw'] = 0;
                foreach ($directTeamData as $dt_item){
                    if($item['promoterId'] == $dt_item['promoterId']){
                        $item['totalAgentRevenue'] = $this->formatMoneyFromMongo($dt_item['agentRevenue']);//直属代理税收
                        $item['totalMyProfit'] = $this->formatMoneyFromMongo($dt_item['myProfit']);//直属分成
                        $item['totalTeamAgentRevenue'] = $this->formatMoneyFromMongo($dt_item['teamAgentRevenue']);//合伙团队代理税收
                        $item['totalTeamProfit'] = $this->formatMoneyFromMongo($dt_item['teamProfit']);//合伙团队分成
                        $item['totalMyTeamProfit'] = $this->formatMoneyFromMongo($dt_item['myTeamProfit']);//预计佣金
                        $item['totalTeamContri'] = $this->formatMoneyFromMongoNo($item['totalMyTeamProfit'] - $item['totalMyProfit']);
                        if($item['totalTeamContri'] == 0.00) $item['totalTeamContri'] = 0;
                        if(!empty($dt_item['teamDevoteProfit'])) $item['totalTeamDevoteProfit'] = $this->formatMoneyFromMongo($dt_item['teamDevoteProfit']);//代理团队贡献
                        $item['totalMySelfAgentRevenue'] = $this->formatMoneyFromMongo($dt_item['mySelfAgentRevenue']);//自营代理税收
                        $item['transferToScoreAmount'] = $this->formatMoneyFromMongo($dt_item['transferToScoreAmount']);//代理已提取余额
                        $item['agentCanWithdraw'] = $this->formatMoneyFromMongoNo($item['totalMyTeamProfit'] - $item['transferToScoreAmount']);
                    }
                }
            }

            return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $list]);
        }
        $getData = check_type($request->all());
        extract($getData);
        $promoterId = !empty($promoterId) ? (int)$promoterId : '';
        $assignData = [
            'promoterId'=>$promoterId
        ];
        return view('club/clubPerformanceList/list',$assignData);
    }

    public function memberPerSummary(Request $request)
    {
        if ($request->isAjax()) {
            $where = [];
            $getData = check_type($request->all());
            extract($getData);
            if (empty($startDate)) {
                $startDate = date("Y-m-d");
            }
            if (empty($endDate)) {
                $endDate = date("Y-m-d");
                $endTime = strtotime($endDate) + 86400;
            }else{
                $endTime = strtotime($endDate);
            }
            $startTime = strtotime($startDate);

            if ($startTime >= $endTime) return json(['code' => -1, 'msg' => '请核对开始时间结束时间']);
            if (!empty($status)) {
                if ($status == 2) {
                    $where['withdrawLocked'] = 1;
                } else {
                    $where['status'] = $status;
                }
            }
            if (!empty($searchType) && !empty($searchValue)) {
                if ($searchType == 1) {
                    $where['promoterId'] = (int)$searchValue;
                }
            }

            if (!empty($clubName)) {
                $clubInfos = static::getClubs(['clubName' => $clubName]);
                if($clubInfos){
                    $promoterArr = [];
                    foreach ($clubInfos as $itemClub){
                        $promoters = PromoterDetail::where(['clubId' => $itemClub['clubId']])->select('promoterId')->get()->toArray();
                        foreach ($promoters as $promoterItem){
                            $promoterArr[] = $promoterItem['promoterId'];
                        }
                    }

                    if (!empty($where['promoterId'])) {
                        foreach ($promoterArr as $k => $itemtrim){
                            if($where['promoterId'] !== $itemtrim){
                                unset($promoterArr[$k]);
                            }
                        }
                    }
                    $promoterArr = array_values($promoterArr);
                    $where['promoterId'] = ['$in'=>$promoterArr];
                }else{
                    return json(['code' => -1, 'msg' => '无此俱乐部']);
                }
            }

            $list = PromoterMain::where($where)->orderBy('createTime', 'asc')->skip($request->skip)->take($request->limit)->get()->toArray();
            $filter = [];
            $filter ['date']= ['$gte' => $this->formatTimestampToMongo($startTime), '$lt' => $this->formatTimestampToMongo($endTime)];
            $directTeamData = Db::connection('mongodb_club')->collection('stat_promoter_daily')->raw()->aggregate([
                [
                    '$match' => $filter
                ],
                [
                    '$project' =>
                        [
                            'promoterId' => 1,
                            'myProfit' => 1,//直属分成
                            'teamProfit' => 1,//合伙团队分成
                            'myTeamProfit' => 1,//预计佣金
                            'transferToScoreAmount' =>1,//代理已提取金额
                            'date' =>1
                        ]
                ],
                [
                    '$sort' => ['date'=>-1]
                ],
                [
                    '$group' =>
                        [
                            '_id' => '$promoterId',
                            'promoterId'=> ['$first'=>'$promoterId'],
                            'myProfit'=> ['$sum'=>'$myProfit'],//直属分成
                            'teamProfit'=> ['$sum'=>'$teamProfit'],//合伙团队分成
                            'myTeamProfit'=> ['$sum'=>'$myTeamProfit'],//预计佣金
                            'transferToScoreAmount' => ['$sum'=>'$transferToScoreAmount'],//代理已提取金额
                        ]
                ],
            ])->toArray();
            $transferToScoreAmountSum = 0;
            $agentCanWithdrawSum = 0;
            foreach ($list as &$item) {
                $item['totalMyProfit'] = 0;
                $item['totalTeamProfit'] = 0;
                $item['totalMyTeamProfit'] = 0;//预计佣金
                $item['totalTeamDevoteProfit'] = 0;
                $item['teamTransferToScoreAmount'] = 0;
                $item['agentCanWithdraw'] = 0;
                foreach ($directTeamData as $dt_item){
                    if($item['promoterId'] == $dt_item['promoterId']){
                        $item['totalMyProfit'] = $this->formatMoneyFromMongo($dt_item['myProfit']);//直属分成
                        $item['totalTeamProfit'] = $this->formatMoneyFromMongo($dt_item['teamProfit']);//合伙团队分成
                        $item['totalMyTeamProfit'] = $this->formatMoneyFromMongo($dt_item['myTeamProfit']);//预计佣金
                        $item['transferToScoreAmount'] = $this->formatMoneyFromMongo($dt_item['transferToScoreAmount']);//代理已提取余额
                        $item['agentCanWithdraw'] = $this->formatMoneyFromMongoNo($item['totalMyTeamProfit'] - $item['transferToScoreAmount']);
                        $transferToScoreAmountSum += $item['transferToScoreAmount'];
                        $agentCanWithdrawSum += $item['agentCanWithdraw'];
                    }
                }
            }
            $transferToScoreAmountSum = $this->formatMoneyFromMongoNo($transferToScoreAmountSum);
            $agentCanWithdrawSum = $this->formatMoneyFromMongoNo($agentCanWithdrawSum);
            return json(['code' => 0, 'msg' => 'ok', 'data' => ['transferToScoreAmountSum' => $transferToScoreAmountSum,'agentCanWithdrawSum' => $agentCanWithdrawSum]]);
        }
    }

    public function memberChild(Request $request)
    {
        if ($request->isAjax()) {
            $newList = [];
            $where = [];
            $getData = check_type($request->all());
            extract($getData);
            if (empty($startDate)) {
                $startDate = date("Y-m-d");
            }
            if (empty($endDate)) {
                $endDate = date("Y-m-d");
                $endTime = strtotime($endDate) + 86400;
            }else{
                $endTime = strtotime($endDate);
            }
            $startTime = strtotime($startDate);


            if ($startTime >= $endTime) return json(['code' => -1, 'msg' => '请核对开始时间结束时间']);
            if (!empty($status)) {
                if ($status == 2) {
                    $where['withdrawLocked'] = 1;
                } else {
                    $where['status'] = $status;
                }
            }

            if (!empty($promoterId)) {
                $promoterId = (int)$promoterId;
            }else{
                return json(['code' => -1, 'msg' => '请输入代理ID']);
            }

            if (!empty($clubName)) {
                $clubInfos = static::getClubs(['clubName' => $clubName]);
                if($clubInfos){
                    $promoterArr = [];
                    foreach ($clubInfos as $itemClub){
                        $promoters = PromoterDetail::where(['clubId' => $itemClub['clubId']])->select('promoterId')->get()->toArray();
                        foreach ($promoters as $promoterItem){
                            $promoterArr[] = $promoterItem['promoterId'];
                        }
                    }

                    if (!empty($where['promoterId'])) {
                        foreach ($promoterArr as $k => $itemtrim){
                            if($where['promoterId'] !== $itemtrim){
                                unset($promoterArr[$k]);
                            }
                        }
                    }
                    $promoterArr = array_values($promoterArr);
                    $where['promoterId'] = ['$in'=>$promoterArr];
                }else{
                    return json(['code' => -1, 'msg' => '无此俱乐部']);
                }
            }

            //$count = PromoterMain::where($where)->count();
            $list = PromoterMain::where($where)->orderBy('createTime', 'asc')->get()->toArray();
            foreach ($list as $k => &$item){
                if(empty($item['myPlayerTotalCount'])){
                    $list[$k]['myPlayerTotalCount'] = 0;
                }
                if(empty($item['myPlayerMemberCount'])){
                    $list[$k]['myPlayerMemberCount'] = 0;
                }
                //增加直属下级合伙人ids
                $whereStatus['status'] = 2;
                $whereStatus['promoterId'] = $promoterId;
                $clubMembers = MyClubs::where($whereStatus)->get()->toArray();
                foreach ($clubMembers as $itemMerber){
                    $clubRole = getClubRole($itemMerber);
                    if($clubRole == "合伙人"){
                        if($item['promoterId'] == $itemMerber['userId']){
                            $list[$k]['clubId'] = $itemMerber['clubId'];
                            $newList[] = $list[$k];
                        }
                    }
                }
            }
            $count = count($newList);

            //$statData = StatPartnerDaily::where('date', date('Y-m-d H:i:s'))->get()->toArray();
            //$list = merge_array($newList, $statData, 'promoterId');

            $clubArr = [];
            $promoterArr = [];
            foreach ($newList as $newKey => $newItem){
                $clubArr [] = $newItem['clubId'];
                $promoterArr [] = $newItem['promoterId'];
//                foreach ($statData as $statDataKey => $statDataItem){
//                    if($newItem['promoterId'] == $statDataItem['promoterId'] && $newItem['clubId'] == $statDataItem['clubId']){
//                        $newList[$newKey] = array_merge($newList[$newKey],$statData[$statDataKey]);
//                    }
//                }
            }
            $list = $newList;

            $whereProClub['clubId'] = ['$in' => $clubArr];
            $whereProClub['promoterId'] = ['$in' => $promoterArr];
            $whereProClub['date'] = ['$gte' => $this->formatTimestampToMongo($startTime), '$lt' => $this->formatTimestampToMongo($endTime)];
            $directTeamData = Db::connection('mongodb_club')->collection('stat_partner_daily')->raw()->aggregate([
                [
                    '$match' => $whereProClub
                ],
                [
                    '$project' =>
                        [
                            'promoterId' => 1,
                            'clubId' => 1,
                            'agentRevenue' => 1,//直属代理税收
                            'revenue' => 1,//直属税收
                            'myProfit' => 1,//直属分成
                            'teamAgentRevenue' => 1,//团队代理税收
                            'teamRevenue' => 1,//合伙团队税收
                            'teamProfit' => 1,//合伙团队分成
                            'myTeamProfit' => 1,//预计佣金
                            'teamDevoteProfit' => 1,//代理团队贡献
                            'mySelfAgentRevenue' => 1,//自营代理税收
                            'date' =>1
                        ]
                ],
                [
                    '$sort' => ['date'=>-1]
                ],
                [
                    '$group' =>
                        [
                            '_id' => '$promoterId',
                            'promoterId'=> ['$first'=>'$promoterId'],
                            'clubId'=> ['$first'=>'$clubId'],
                            'agentRevenue'=> ['$sum'=>'$agentRevenue'],//代理直属税收
                            //'revenue'=> ['$sum'=>'$revenue'],//直属税收
                            'myProfit'=> ['$sum'=>'$myProfit'],//直属分成
                            'teamAgentRevenue'=> ['$sum'=>'$teamAgentRevenue'],//代理团队税收
                            //'teamRevenue'=> ['$sum'=>'$teamRevenue'],//合伙团队税收
                            'teamProfit'=> ['$sum'=>'$teamProfit'],//合伙团队分成
                            'myTeamProfit'=> ['$sum'=>'$myTeamProfit'],//预计佣金
                            'teamDevoteProfit' => ['$sum'=>'$teamDevoteProfit'],//代理团队贡献
                            'mySelfAgentRevenue' => ['$sum'=>'$mySelfAgentRevenue'],//自营代理税收
                        ]
                ],
            ])->toArray();
            foreach ($list as &$item) {
                //所属俱乐部
                $clubIdArr = [];
                $clubsNameStr = "";
                $clubs = PromoterDetail::where('promoterId', $item['promoterId'])->select('promoterId','clubId','setRate')->get()->toArray();
                foreach ($clubs as $clubItem){
                    $clubIdArr[] = $clubItem['clubId'];
                }

                $clubsName = ClubMo::where(['clubId' => ['$in'=>$clubIdArr]])->select('clubName','clubId')->get()->toArray();
                $clubsName = merge_array($clubsName, $clubs, 'clubId');

                foreach ($clubsName as $clubNameItem){
                    $clubsNameStr .= $clubNameItem['clubName'].'('.$clubNameItem['setRate'].'%),';
                }
                $clubsNameStr = rtrim($clubsNameStr, ",");
                unset($clubIdArr);


                $item['clubsNameStr'] = $clubsNameStr;
                $item['createTime'] = $this->formatDate($item['createTime']);
                $item['score'] = $this->formatMoneyFromMongo($item['score']);
                $item['totalExchange'] = $this->formatMoneyFromMongo($item['totalExchange']);
                $item['totalRevenue'] = 0;
                $item['totalMyProfit'] = 0;
                $item['totalTeamRevenue'] = 0;
                $item['totalTeamProfit'] = 0;
                $item['totalMyTeamProfit'] = 0;
                $item['totalTeamDevoteProfit'] = 0;
                $item['totalMySelfAgentRevenue'] = 0;
                $item['totalTeamContri'] = 0;
                $item['totalAgentRevenue'] = 0;
                $item['totalTeamAgentRevenue'] = 0;


                foreach ($directTeamData as $dt_item){
                    if($item['promoterId'] == $dt_item['promoterId'] && $item['clubId'] == $dt_item['clubId']){
                        $item['totalAgentRevenue'] = $this->formatMoneyFromMongo($dt_item['agentRevenue']);//直属代理税收
                        //$item['totalRevenue'] = $this->formatMoneyFromMongo($dt_item['revenue']);//直属税收
                        $item['totalMyProfit'] = $this->formatMoneyFromMongo($dt_item['myProfit']);//直属分成
                        $item['totalTeamAgentRevenue'] = $this->formatMoneyFromMongo($dt_item['teamAgentRevenue']);//合伙团队代理税收
                        //$item['totalTeamRevenue'] = $this->formatMoneyFromMongo($dt_item['teamRevenue']);//合伙团队税收
                        $item['totalTeamProfit'] = $this->formatMoneyFromMongo($dt_item['teamProfit']);//合伙团队分成
                        $item['totalMyTeamProfit'] = $this->formatMoneyFromMongo($dt_item['myTeamProfit']);//预计佣金
                        $item['totalTeamContri'] = $this->formatMoneyFromMongoNo($item['totalMyTeamProfit'] - $item['totalMyProfit']);
                        if($item['totalTeamContri'] == 0.00) $item['totalTeamContri'] = 0;
                        if(!empty($dt_item['teamDevoteProfit'])) $item['totalTeamDevoteProfit'] = $this->formatMoneyFromMongo($dt_item['teamDevoteProfit']);//代理团队贡献
                        $item['totalMySelfAgentRevenue'] = $this->formatMoneyFromMongo($dt_item['mySelfAgentRevenue']);//自营代理税收
                    }
                }
            }
            return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $list]);
        }


        $getData = check_type($request->all());
        extract($getData);
        $clubName = !empty($clubName) ? trim($clubName) : "";
        $startDate = !empty($startDate) ? trim($startDate) : "";
        $endDate = !empty($endDate) ? trim($endDate) : "";
        $promoterId = !empty($promoterId) ? (int)$promoterId : '';
        $status = !empty($status) ? (int)$status : '';
        $assignData = [
            'promoterId'=>$promoterId,
            'status'=>$status,
            'startDate'=>$startDate,
            'endDate'=>$endDate,
            'clubName'=>$clubName
        ];
        return view('club/clubMemberList/listChild',$assignData);
    }

    public function memberList(Request $request)
    {
        if ($request->isAjax()) {
            $where = [];
            $getData = $request->all();
            extract($getData);

            if (isset($status) && $status !== "") {
                $where['status'] = (int)$status;
            }
            if (!empty($searchType) && !empty($searchValue)) {
                if ($searchType == 1) {
                    $where['clubId'] = (int)$searchValue;
                }
            }

            $count = ClubMo::where($where)->count();
            $list = ClubMo::where($where)->skip($request->skip)->take($request->limit)->get()->toArray();
            foreach ($list as &$item) {
                if (!empty($item['tel'])) $item['tel'] = mobileShow(mobile($item['tel']));
            }
            return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $list]);
        }
        return view('club/memberList/list');
    }

    public function clubList(Request $request)
    {
        if ($request->isAjax()) {
            $where = [];
            $getData = $request->all();
            extract($getData);

            if (isset($status) && $status !== "") {
                $where['status'] = (int)$status;
            }
            if (!empty($searchType) && !empty($searchValue)) {
                if ($searchType == 1) {
                    $where['clubId'] = (int)$searchValue;
                }
            }

            $count = ClubMo::where($where)->count();
            $list = ClubMo::where($where)->skip($request->skip)->take($request->limit)->get()->toArray();
            foreach ($list as &$item) {
                if (!empty($item['tel'])) $item['tel'] = mobileShow(mobile($item['tel']));
            }
            return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $list]);
        }
        return view('club/clubList/list');
    }

    public function clubIconUpload(Request $request)
    {
        $file = $request->file('file');
        $getData = $request->all();
        extract($getData);
        $clubId = !empty($clubId) ? (int) $clubId : 0;
        if (!$clubId) {
            return json(['code' => -1, 'msg' => '参数错误']);
        }
        if ($file && $file->isValid()) {
            $uploadFile = $file->move(UPFILE_PATH.$file->getUploadName());
            if(!$uploadFile) return json(['code' => -1, 'msg' => '上传图片失败']);
            $upload = new functionUpload();
            $rs = $upload->aliOssUpload(1, $uploadFile->getFilename());
            if ($rs) {
                $where = [];
                $where['clubId'] = $clubId;
                $updateData = [
                    'clubIconUrl'=> $rs
                ];
                $updateResult = ClubMo::where($where)->update($updateData);
                if (!$updateResult) return json(['code' => -1, 'msg' => '上传失败']);
                $this->adminLog(["content"=>"俱乐部上传图标【".$clubId."】"]);
                return json(['code' => 0, 'msg' => '上传成功']);
            }
        }else{
            return json(['code' => -1, 'msg' => '文件验证失败']);
        }
    }

    public function clubDetail(Request $request)
    {
        $getData = check_type($request->all());
        extract($getData);
        if ($request->isAjax()) {
            $where = [];
            $whereCode = [];
            if (empty($clubId)) return json(['code' => -1, 'msg' => '数据错误']);
            $where['clubId'] = $clubId;
            $whereCode['clubId'] = $clubId;
            if (!empty($userId)) $where['userId'] = $userId;
            if (!empty($promoterId)) $where['promoterId'] = $promoterId;
            $count = MyClubs::where($where)->count();
            $clubMembers = MyClubs::where($where)->skip($request->skip)->take($request->limit)->get();
            $clubMembersCode = PromoterDetail::where($whereCode)->skip($request->skip)->take($request->limit)->select('invitationCode','promoterId')->get();
            //dd($clubMembers);
            $data = [];
            foreach ($clubMembers as $k => $member) {
                foreach ($clubMembersCode as $code){
                    if($member['userId'] == $code['promoterId']){
                        $data[$k]['invitationCode'] = $code['invitationCode'];
                    }
                }
                $data[$k]['userId'] = $member['userId'];

                $data[$k]['level'] = getClubRole($member);
//                if($member['status'] == 1){
//                    $data[$k]['level'] = "会员";
//                }else if ($member['status'] == 2 && $member['clubId'] !== $member['userId']){
//                    $data[$k]['level'] = "合伙人";
//                }else{
//                    $data[$k]['level'] = "盟主";
//                }
                $data[$k]['contribute'] = $this->formatMoneyFromMongo($member['totalAgentRevenue']);
                $data[$k]['promoterId'] = $member['promoterId'];
            }
            return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $data]);
        }

        $assignData = [
            'clubId' => $clubId
        ];
        return view('club/clubDetail/list', $assignData);
    }

    public function clubStatus(Request $request)
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
            $msg = "启用俱乐部";
            $status = "启用";
        }else
        {
            $msg = "关闭俱乐部";
            $status = "关闭";
        }

        $row = ClubMo::where('_id', $_id)->first();
        if(empty($row))return json(['code' => -1, 'msg' => '无该条数据']);
        $updateResult = ClubMo::where('_id', $_id)->update($updateData);
        if (!$updateResult) return json(['code' => -1, 'msg' => '修改失败']);
        $this->adminLog(["content"=>"俱乐部".$row['clubId']."的状态修改为【".$status."】"]);
        return json(['code' => 0, 'msg' => $msg]);
    }

    public function clubRobotStatus(Request $request)
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
            $msg = "启用俱乐部机器人";
            $status = "启用";
        }else
        {
            return json(['code' => -1, 'msg' => '不允许关闭俱乐部机器人!!!']);
            $msg = "关闭俱乐部机器人";
            $status = "关闭";
        }

        $row = ClubMo::where('_id', $_id)->first();
        if(empty($row))return json(['code' => -1, 'msg' => '无该条数据']);
        $updateResult = ClubMo::where('clubId', $row['clubId'])->update($updateData);
        if (!$updateResult) return json(['code' => -1, 'msg' => '修改失败']);
        $this->adminLog(["content"=>"俱乐部机器人".$row['clubId']."的状态修改为【".$status."】"]);
        return json(['code' => 0, 'msg' => $msg]);
    }

    public function clubGlobalMatch(Request $request)
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
            $msg = "开启全局匹配";
            $status = "启用";
        }else
        {
            $msg = "关闭全局匹配";
            $status = "关闭";
        }

        $row = ClubMo::where('_id', $_id)->first();
        if(empty($row))return json(['code' => -1, 'msg' => '无该条数据']);
        if($row['clubId'] == 1000) return json(['code' => -1, 'msg' => '盟主ID为1000的不允许修改全局配置!!!']);
        $updateResult = ClubMo::where('clubId', $row['clubId'])->update($updateData);
        if (!$updateResult) return json(['code' => -1, 'msg' => '修改失败']);
        $this->adminLog(["content"=>"俱乐部全局匹配".$row['clubId']."的状态修改为【".$status."】"]);
        return json(['code' => 0, 'msg' => $msg]);
    }

    public function editMoTabNum(Request $request)
    {
        if ($request->isAjax()) {
            $postData = check_type($request->post());
            extract($postData);
            if (!$field || !$clubId) {
                return json(['code' => -1, 'msg' => '信息输入不正确']);
            }
            if($field == "minAndroidTable" || $field == "maxAndroidTable"){
                $value = (int)$value;
            }

            $updateData = [
                $field=> $value
            ];
            $updateResult = ClubMo::where('clubId', $clubId)->update($updateData);
            if (!$updateResult) return json(['code' => -1, 'msg' => '修改失败']);
            return json(['code' => 0, 'msg' => '修改成功']);
        }
    }

    public function addClub(Request $request)
    {
        if ($request->isAjax()) {
            $postData = check_type($request->post());
            extract($postData);

            if (!$clubId || !$tel || !$clubName || !$qq) {
                return json(['code' => -1, 'msg' => '盟主ID,手机号码,QQ,名称为必填项']);
            }
            //俱乐部名字6个字以内
            $clubNameLen = mb_strwidth($clubName);
            if ($clubNameLen > 12) return json(['code' => -1, 'msg' => '俱乐部名称上限为6个汉字,或者12个字节(每个数字,字母算一个字节,每个汉字两个字节)']);
            $promoter = static::getPromoter(['promoterId' => $clubId]);
            if (empty($promoter)) return json(['code' => -1, 'msg' => '无该代理']);

            $isTel = preg_match_all('/(13\d|14[579]|15[^4\D]|17[^49\D]|18\d)\d{8}/', $tel, $matches_mobile);
            if($isTel == 0 || strlen($tel) != 11){
                return json(['code' => -1, 'msg' => '手机格式错误']);
            }
            //一个代理只能创建一个俱乐部
            $club = static::getClub(['clubId' => $clubId]);
            if (!empty($club)) return json(['code' => -1, 'msg' => '该代理已有俱乐部']);
            //俱乐部名称不能重复
            $club = static::getClub(['clubName' => $clubName]);
            if (!empty($club)) return json(['code' => -1, 'msg' => '俱乐部名称已被使用']);
            //手机号不能重复
            $tel = mobile_en($tel);
            $club = static::getClub(['tel' => $tel]);
            if (!empty($club)) return json(['code' => -1, 'msg' => '该手机号已被使用']);
            //最小桌数不能大于最大桌数
            if($minAndroidTable > $maxAndroidTable){
                return json(['code' => -1, 'msg' => '最小桌数不能大于最大桌数']);
            }

            $session = DB::connection('mongodb_club')->getMongoClient()->startSession();
            $session->startTransaction();
            $clubIconId = rand(1,8);
            $qq = (string)$qq;
            //增加俱乐部游戏开关,默认配置所有的
            $games = [];
            $whereCon['status'] = 1;
            $gameData = GameKind::where($whereCon)->orderBy('sort', 'asc')->get()->toArray();
            foreach ($gameData as $value){
                $aa['gameId'] = (int)$value['gameId'];
                $aa['enableAndroid'] = 1;
                $games[] = $aa;
            }
            $insertDataClub = [
                'clubId' => $clubId,
                'clubName' => $clubName,
                'clubIconId' => $clubIconId,
                'platformId' => $promoter['platformId'],
                'channelId' => $promoter['channelId'],
                'enableAndroid' => $enableAndroid,
                'minAndroidTable' => $minAndroidTable,
                'maxAndroidTable' => $maxAndroidTable,
                'qq' => $qq,
                'tel' => $tel,
                'status' => 1,
                'memberNum'=>1,
                'games'=>$games
            ];
            $insertClub = ClubMo::insert($insertDataClub);
            if (!$insertClub) {
                $session->abortTransaction();
                return json(['code' => -1, 'msg' => '添加俱乐部失败']);
            }
            //myClubs
            $insertDataMyClubs = [
                'userId' => $clubId,
                'clubId' => $clubId,
                'promoterId' => $clubId,
                'directParent' => $clubId,
                'platformId' => $promoter['platformId'],
                'channelId' => $promoter['channelId'],
                //'clubIconId' => $clubIconId,
                'status' => 2,
                'createTime' => new \MongoDB\BSON\UTCDateTime
            ];
            $insertResultMyClub = MyClubs::insert($insertDataMyClubs);
            if (!$insertResultMyClub) {
                $session->abortTransaction();
                return json(['code' => -1, 'msg' => '添加加入的俱乐部失败']);
            }
            //promoterMain
            $thisWeek['newPlayerCount'] = 1;
            $insertDataPromoterMain = [
                'promoterId' => $clubId,
                'promoterName' => $promoter['promoterName'],
                'platformId' => $promoter['platformId'],
                'channelId' => $promoter['channelId'],
                'mobile' => $tel,
                'withdrawLocked' => 0,
                'score' => 0,
                'totalExchange' => 0,
                'myPlayerMemberCount' => 1,
                'myPlayerTotalCount' => 1,
                'teamPlayerCount' => 1,
                'thisWeek' =>$thisWeek,
                'enableMail' => (bool)0,
                'note' => '',
                'status' => 1,
                'createTime' => new \MongoDB\BSON\UTCDateTime
            ];
            $insertResultPromoterMain = PromoterMain::insert($insertDataPromoterMain);
            if (!$insertResultPromoterMain) {
                $session->abortTransaction();
                return json(['code' => -1, 'msg' => '添加俱乐部代理总表失败']);
            }
            //promoterDetail
            $invitationCode = rand(100000,999999);
            $insertDataPromoterDetail = [
                'promoterId' => $clubId,
                'clubId' => $clubId,
                'platformId' => $promoter['platformId'],
                'channelId' => $promoter['channelId'],
                'pid' => 0,
                'invitationCode' => $invitationCode,
                'myPlayerMemberCount' => 1,
                'myPlayerTotalCount' => 1,
                'teamPlayerCount' => 1,
                'thisWeek' =>$thisWeek,
                'setRate' => 90,
                'autoPartnerRate' => 0,
                //'URL' => $promoter['URL'],
                'note' => '',
                'status' => 1,
                'createTime' => new \MongoDB\BSON\UTCDateTime
            ];
            $insertResultPromoterDetail = PromoterDetail::insert($insertDataPromoterDetail);
            if (!$insertResultPromoterDetail) {
                $session->abortTransaction();
                return json(['code' => -1, 'msg' => '添加俱乐部代理详细表失败']);
            }

            $session->commitTransaction();
            $this->adminLog(["content" => session('userName') . "添加俱乐部【".$clubId."】"]);
            sendData2(['clubId'=>$clubId, 'clubName'=>$clubName, 'status'=>1],'ApplyClubToClubServerMessage');

            return json(['code' => 0, 'msg' => '添加成功']);
        }
        return view('club/clubList/add', []);
    }

    private function _userGameDetailAjaxParam()
    {
        $where = [];
        $request = request();
        $getData = check_type($request->all());
        extract($getData);
        if (empty($startDate)) {
            $startDate = date("Y-m-d");
        }
        if (empty($endDate)) {
            $endDate = date("Y-m-d");
            $endTime = strtotime("$endDate +1 day");
        }else{
            $endTime = strtotime($endDate);
        }
        $startTime = strtotime($startDate);
//        $aa = date('Y-m-d H:i:s',$startTime);
//        $bb = date('Y-m-d H:i:s',$endTime);
//        dd($aa."&&".$bb);

        if ($startTime >= $endTime) return json(['code' => -1, 'msg' => '请核对开始时间结束时间']);
        $where[] = ['endTime', '>=', $this->formatTimestampToMongo($startTime)];
        $where[] = ['endTime', '<', $this->formatTimestampToMongo($endTime)];

        if (!empty($userId)) {
            $where['userId'] = $userId;
        }else{
            if (!empty($isSys)) {
                if ($isSys == GameUser::COMMON_ACCOUNT) {
                    $where[] = ['userId', '>=', GameUser::COMMON_ACCOUNT_START_ID];
                } elseif ($isSys == GameUser::SYSTEM_ACCOUNT) {
                    $where[] = ['userId', '<', GameUser::COMMON_ACCOUNT_START_ID];
                }
            }
        }
        if (!empty($gameInfoId)) {
            $where['gameInfoId'] = $gameInfoId;
        }
        if (!empty($gameId)) {
            $where['gameId'] = $gameId;
        }
        if (!empty($roomId)) {
            $where['roomId'] = $roomId;
        }
        if (!empty($clubId)) {
            $where['clubId'] = $clubId;
        }
        return $where;
    }

    public function userGameDetail(Request $request)
    {
        if ($request->isAjax()) {
            $where = $this->_userGameDetailAjaxParam();
            if (!is_array($where)) return $where;
            $orderType = $request->get('orderType');
            $order = 'desc';
            if(empty($orderType)) $orderType = 'leaveTime';
            $field = match ($orderType) {
                'leaveTime' => 'endTime',
                'earnScore' => 'winScore',
                'revenue' => 'revenue',
            };

            $count = ClubPlayRecord::where($where)->count();
            $list = ClubPlayRecord::where($where)->orderBy($field, $order)->skip($request->skip)->take($request->limit)->get()->toArray();
            $gameRoomInfo = getClubGameRoomInfo();
            $list = merge_array($list, $gameRoomInfo, 'roomId');

            $culbNames = ClubMo::orderBy('clubId', 'desc')->select('clubName','clubId')->get()->toArray();
            $list = merge_array($list, $culbNames, 'clubId');

            //$ipLocation = new IpLocation();
            foreach ($list as &$item) {
                if($item['gameId'] == 900){
                    $he = $item['cellScore'][0];
                    $long = $item['cellScore'][1];
                    $hu = $item['cellScore'][2];
                    $item['he'] = $this->formatMoneyFromMongo($he);
                    $item['long'] = $this->formatMoneyFromMongo($long);
                    $item['hu'] = $this->formatMoneyFromMongo($hu);
                }elseif ($item['gameId'] == 720){
                    $shun = $item['cellScore'][0];
                    $tian = $item['cellScore'][1];
                    $di = $item['cellScore'][2];
                    $item['shun'] = $this->formatMoneyFromMongo($shun);
                    $item['tian'] = $this->formatMoneyFromMongo($tian);
                    $item['di'] = $this->formatMoneyFromMongo($di);
                }elseif ($item['gameId'] == 210){
                    $bei = $item['cellScore'][0];
                    $hei = $item['cellScore'][1];
                    $hong = $item['cellScore'][2];
                    $item['bei'] = $this->formatMoneyFromMongo($bei);
                    $item['hei'] = $this->formatMoneyFromMongo($hei);
                    $item['hong'] = $this->formatMoneyFromMongo($hong);
                }elseif ($item['gameId'] == 930 || $item['gameId'] == 920){
                    $tian = $item['cellScore'][0];
                    $di = $item['cellScore'][1];
                    $xuan = $item['cellScore'][2];
                    $huang = $item['cellScore'][3];
                    $item['tian'] = $this->formatMoneyFromMongo($tian);
                    $item['di'] = $this->formatMoneyFromMongo($di);
                    $item['xuan'] = $this->formatMoneyFromMongo($xuan);
                    $item['huang'] = $this->formatMoneyFromMongo($huang);
                }elseif ($item['gameId'] == 950){
                    $bens = $item['cellScore'][0];
                    $bmw = $item['cellScore'][1];
                    $audi = $item['cellScore'][2];
                    $jaguar = $item['cellScore'][3];
                    $porsche = $item['cellScore'][4];
                    $maserati = $item['cellScore'][5];
                    $lamborghini = $item['cellScore'][6];
                    $ferrari = $item['cellScore'][7];

                    $item['bens'] = $this->formatMoneyFromMongo($bens);
                    $item['bmw'] = $this->formatMoneyFromMongo($bmw);
                    $item['audi'] = $this->formatMoneyFromMongo($audi);
                    $item['jaguar'] = $this->formatMoneyFromMongo($jaguar);
                    $item['porsche'] = $this->formatMoneyFromMongo($porsche);
                    $item['maserati'] = $this->formatMoneyFromMongo($maserati);
                    $item['lamborghini'] = $this->formatMoneyFromMongo($lamborghini);
                    $item['ferrari'] = $this->formatMoneyFromMongo($ferrari);
                }

                $item['beforeScore'] = $this->formatMoneyFromMongo($item['beforeScore']);
                $item['score'] = $this->formatMoneyFromMongo($item['score']);
                $item['allBet'] = $this->formatMoneyFromMongo($item['allBet']);
                $item['validBet'] = $this->formatMoneyFromMongo($item['validBet']);
                $item['winScore'] = $this->formatMoneyFromMongo($item['winScore']);
                $item['platformWinScore'] = $this->formatMoneyFromMongo($item['platformWinScore']??0);
                $item['revenue'] = $this->formatMoneyFromMongo($item['revenue']);
              if(!empty($item['agentRevenue'])){
                    $item['agentRevenue'] = $this->formatMoneyFromMongo($item['agentRevenue']);
                }else{
                    $item['agentRevenue'] = 0;
                }

                if(isset($item['winLostScore'])) {
                    $item['earnScore'] = $this->formatMoneyFromMongo($item['winLostScore']);
                } else {
                    $item['earnScore'] = $item['winScore'] + $item['revenue'];
                }
                //$item['ptIncome'] = $this->formatMoneyFromMongo($item['platformWinScore']*100 + $item['revenue']*100);
                //$item['ptIncome'] = $this->formatMoneyFromMongoNo($item['platformWinScore'] + $item['agentRevenue']*0.1);
                //$item['ptIncome'] = $this->formatMoneyFromMongoNo($item['platformWinScore'] + $item['revenue']-$item['agentRevenue']*0.9);
                $item['ptIncome'] = $this->formatMoneyFromMongoNo($item['platformWinScore'] + $item['revenue']);//名词变了，公式变了

                $item['playTime'] = $this->diffTime($item['startTime'], $item['endTime'], '%I:%S');
                $item['startTime'] = $this->formatDate($item['startTime']);
                $item['endTime'] = $this->formatDate($item['endTime']);
                $item['isBanker'] = $item['isBanker']??0;

            }

            return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $list]);
        }


        $getData = check_type($request->all());
        extract($getData);
        $startDate = !empty($startDate) ? trim($startDate) : "";
        $endDate = !empty($endDate) ? trim($endDate) : "";
        $userId = !empty($userId) ? (int)$userId : '';

        $assignData = [
            'orderType' => ['leaveTime' => '结算时间', 'earnScore' => '平台营收', 'revenue' => '平台税收'],
            //'gameList' => GameKind::where(['status' => 1, 'rooms.status' => 1])->orderBy('sort', 'asc')->get()->toArray(),
            'gameList' => getClubGameInfoGameRoomInfo(),
            'clubList' => ClubMo::where(['status' => 1])->orderBy('clubId', 'desc')->get()->toArray(),
            'startDate'=>$startDate,
            'endDate'=>$endDate,
            'userId'=>$userId,
            'isSys' => GameUser::ACCOUNT_CLASSIFY,
        ];
        //dd($assignData['gameList']);
        return view('club/gameDetail/list', $assignData);
    }

    public function userGameDetailSummary(Request $request)
    {
        if($request->isAjax()) {
            $where = $this->_userGameDetailAjaxParam();
            if (!is_array($where)) return $where;
            $where[] = ['userId', '>=', GameUser::COMMON_ACCOUNT_START_ID];
            $request = request();
            $getData = check_type($request->all());
            extract($getData);
            if (empty($startDate)) {
                $startDate = date("Y-m-d");
            }
            if (empty($endDate)) {
                $endDate = date("Y-m-d");
                $endTime = strtotime("$endDate +1 day");
            }else{
                $endTime = strtotime($endDate);
            }
            $startTime = strtotime($startDate);
            if ($startTime >= $endTime) return json(['code' => -1, 'msg' => '请核对开始时间结束时间']);
            $where2[] = ['createTime', '>=', $this->formatTimestampToMongo($startTime)];
            $where2[] = ['createTime', '<', $this->formatTimestampToMongo($endTime)];

            $where2[] = ['userId', '>=', GameUser::COMMON_ACCOUNT_START_ID];
            $winScoreSum = ClubPlayRecord::where($where)->sum('winScore');
            $winScoreSum = $this->formatMoneyFromMongo($winScoreSum);
            $platformWinScoreSum = ClubPlayRecord::where($where)->sum('platformWinScore');
            $platformWinScoreSum = $this->formatMoneyFromMongo($platformWinScoreSum);

            $revenueSum = ClubPlayRecord::where($where)->sum('revenue');
            $revenueSum = $this->formatMoneyFromMongo($revenueSum);

            $validBetSum = ClubPlayRecord::where($where)->sum('validBet');
            $validBetSum = $this->formatMoneyFromMongo($validBetSum);

            $allBetSum = ClubPlayRecord::where($where)->sum('allBet');
            $allBetSum = $this->formatMoneyFromMongo($allBetSum);

            //$ptIncomeSum = $platformWinScoreSum + $revenueSum;
            $agentRevenueSum = ClubPlayRecord::where($where)->sum('agentRevenue');
            $agentRevenueSum = $this->formatMoneyFromMongo($agentRevenueSum);
            //$ptIncomeSum = $this->formatMoneyFromMongoNo($platformWinScoreSum + $revenueSum - $agentRevenueSum*0.9);
            $ptIncomeSum = $this->formatMoneyFromMongoNo($platformWinScoreSum + $revenueSum);//名词变了，公式变了

            $rechargeMoneySum = ClubRewardOrder::where($where2)->sum('rechargeMoney');
            $rechargeMoneySum = $this->formatMoneyFromMongo($rechargeMoneySum);

            $ptIncomeSum = $this->formatMoneyFromMongoNo($ptIncomeSum - $rechargeMoneySum);

            return json(['code' => 0, 'msg' => 'ok', 'data' => ['winScoreSum' => $winScoreSum,'platformWinScoreSum' => $platformWinScoreSum, 'revenueSum' => $revenueSum, 'ptIncomeSum' => $ptIncomeSum, 'agentRevenueSum' => $agentRevenueSum, 'validBetSum' => $validBetSum, 'allBetSum' => $allBetSum]]);
        }
    }

    public function agentExchangeList(Request $request)
    {
        return view('club/agentExchange/list', ['data' => '']);
    }

    private function _ajaxParamExchange()
    {
        $where = [];
        $request = request();
        $getData = check_type($request->all());dd($getData);
        extract($getData);
        if (empty($startDate)) {
            $startDate = date("Y-m-d");
        }
        if (empty($endDate)) {
            $endDate = date("Y-m-d");
        }
        $startTime = strtotime($startDate);
        $endTime = strtotime("$endDate +1 day");
        if ($startTime >= $endTime) return json(['code' => -1, 'msg' => '请核对开始时间结束时间']);
        $where[] = ['createTime', '>=', $this->formatTimestampToMongo($startTime)];
        $where[] = ['createTime', '<', $this->formatTimestampToMongo($endTime)];

        //输入框优先于下拉框
        if (!empty($searchText)) {
            $searchTextLen = strlen($searchText);
            if(in_array($searchTextLen, [6, 8])) {
                $where['promoterId'] = (int)$searchText;
            }elseif ($searchTextLen == 37) {
                $where['orderId'] = $searchText;
            }else {
                return json(['code' => -1, 'msg' => '会员ID长度是6位或者8位数字，订单号是37位字符']);
            }
        }
        return $where;
    }

    public function summary(Request $request)
    {
        if($request->isAjax()) {
            $where = $this->_ajaxParamExchange();
            if (!is_array($where)) return $where;
            $requestMoneySum = PromoterExchangeOrder::where($where)->sum('requestMoney');
            $requestMoneySum = $this->formatMoneyFromMongo($requestMoneySum);

            return json(['code' => 0, 'msg' => 'ok', 'data' => ['requestMoneySum' => $requestMoneySum, 'payMoneySum' => $requestMoneySum]]);
        }
    }

    public function exchangeList(Request $request)
    {
        if($request->isAjax()) {
            $where = $this->_ajaxParamExchange();
            if (!is_array($where)) return $where;
            $count = PromoterExchangeOrder::where($where)->count();
            $list = PromoterExchangeOrder::where($where)->orderBy('createTime', 'desc')->skip($request->skip)->take($request->limit)->get()->toArray();
            $userIdArr = array_column($list, 'promoterId'); $userIdArr = array_unique($userIdArr);
            $userList = GameUser::whereIn('userId', $userIdArr)->select('userId','score','bankScore','rechargeAmount','exchangeAmount','regInfo','trueName','nickName')->get()->toArray();
            $list = merge_array($list, $userList, 'promoterId', 'userId');
            $ipLocation = new IpLocation();
            foreach ($list as &$item) {
                $location = $ipLocation->getlocation($item['regInfo']['ip']??'');
                $item['address'] = $location['country'] . $location['area'];
                $item['rechargeAmount'] = $this->formatMoneyFromMongo($item['rechargeAmount']??0);
                $item['exchangeAmount'] = $this->formatMoneyFromMongo($item['exchangeAmount']??0);
                $item['requestMoney'] = $this->formatMoneyFromMongo($item['requestMoney']);
                $item['score'] = $this->formatMoneyFromMongo($item['score']??0);
                $item['bankScore'] = $this->formatMoneyFromMongo($item['bankScore']??0);
                $item['usdt'] = $this->formatMoneyFromMongo($item['usdt']??0);
                $item['usdtRate'] = $this->formatMoneyFromMongo($item['usdtRate']??0);
                $actTimeResult = $this->diffTime($item['createTime'], $item['applyTime']);
                $item['serviceTime'] = Sec3Time($actTimeResult);
                $item['createTime'] = $this->formatDate($item['createTime']);
                $item['applyTime'] = $this->formatDate($item['applyTime']);
                $item['remark'] = $item['remark'] ?? '';
                $item['statusCn'] = PromoterExchangeOrder::STATUS[$item['status']]??'未知状态';
            }
            return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $list]);
        }
    }

    public function exportExchange(Request $request)
    {
        $where = $this->_ajaxParamExchange();
        if (!is_array($where)) return $where;
        $cursor = PromoterExchangeOrder::where($where)->orderBy('createTime', 'desc')->cursor();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', '订单号');
        $sheet->setCellValue('B1', '会员ID');
        $sheet->setCellValue('C1', '提现类型');
        $sheet->setCellValue('D1', '提交金额');
        $sheet->setCellValue('E1', '实际付款');
        $sheet->setCellValue('F1', '姓名');
        $sheet->setCellValue('G1', '订单状态');
        $sheet->setCellValue('H1', '总充');
        $sheet->setCellValue('I1', '总提');
        $sheet->setCellValue('J1', '提交时间');
        $sheet->setCellValue('K1', '处理时间');
        $sheet->setCellValue('L1', '耗时');
        $sheet->setCellValue('M1', '操作记录');
        $sheet->setCellValue('N1', '备注');
        $num = 2;
        foreach ($cursor as $item) {
            $user = GameUser::where('userId', $item->promoterId)->select('userId','score','bankScore','rechargeAmount','exchangeAmount','regInfo','trueName','nickName')->first();
            if (empty($user)) continue;
            if ($item->applyTime == '0') {
                $item->applyTime = '暂未处理';
                $serviceTime = '暂未处理';
            } else {
                $actTimeResult = $this->diffTime($item->createTime, $item->applyTime);
                $serviceTime = Sec3Time($actTimeResult);
                $item->applyTime = $this->formatDate($item->applyTime);
            }
            $sheet->setCellValue("A{$num}", $item->orderId);
            $sheet->setCellValue("B{$num}", $item->promoterId);
            $sheet->setCellValue("C{$num}", '代理转余额');
            $sheet->setCellValue("D{$num}", $this->formatMoneyFromMongo($item->requestMoney));
            $sheet->setCellValue("E{$num}", $this->formatMoneyFromMongo($item->requestMoney));
            $sheet->setCellValue("F{$num}", $user->trueName??$user->nickName);
            $sheet->setCellValue("G{$num}", PromoterExchangeOrder::STATUS[$item->status]);
            $sheet->setCellValue("H{$num}", $this->formatMoneyFromMongo($user->rechargeAmount));
            $sheet->setCellValue("I{$num}", $this->formatMoneyFromMongo($user->exchangeAmount));
            $sheet->setCellValue("J{$num}", $this->formatDate($item->createTime));
            $sheet->setCellValue("K{$num}", $item->applyTime);
            $sheet->setCellValue("L{$num}", $serviceTime);
            $sheet->setCellValue("M{$num}", $item->reason??'');
            $sheet->setCellValue("N{$num}", $item->remark??'');

            $num++;
        }
        $writer = new Xlsx($spreadsheet);
        $file_path = public_path().'/agentExchange.xlsx';
        // 保存文件到 public 下
        $writer->save($file_path);

        return json(['code' => 0, 'msg' => 'ok', 'file' => $file_path]);
    }

    public static function getPromoter($where, $column = ['*'])
    {
        return Promoter::where($where)->first($column);//get
    }

    public function userStatus(Request $request)
    {
        $_ids = $request->post('_ids');
        $_idsArr = explode(",", $_ids);
        if (empty($_idsArr)) return json(['code' => -1, 'msg' => '参数错误']);
        foreach ($_idsArr as &$val) {
            $val = (int)$val;
        }
        $value = (int)$request->post('value');
        $updateResult = PromoterMain::whereIn('promoterId', $_idsArr)->update(['withdrawLocked' => $value]);
        if ($updateResult) {
            return json(['code' => 0, 'msg' => '操作成功']);
        }
        return json(['code' => -1, 'msg' => '操作失败']);
    }

    public static function getClub($where, $column = ['*'])
    {
        return ClubMo::where($where)->first($column);//get
    }

    public static function getClubs($where, $column = ['*'])
    {
        return ClubMo::where($where)->get($column);//get
    }

    private function _userGameStatAjaxParam()
    {
        $where = ['isAndroid' => false];
        $status = "";
        $groupId = [];
        $request = request();
        $getData = check_type($request->all());
        extract($getData);
        if (empty($startDate)) {
            $startDate = date("Y-m-d");
        }
        if (empty($endDate)) {
            $endDate = date("Y-m-d");
            $endTime = strtotime("$endDate +1 day");
        }else{
            $endTime = strtotime($endDate);
        }

        $state = 0;
        if(!empty($userId) || !empty($roomId) || !empty($status) || !empty($gameId)){
            $state = 1;
        }

        $startTime = strtotime($startDate);



        if ($startTime >= $endTime) return json(['code' => -1, 'msg' => '请核对开始时间结束时间']);
        $where['endTime'] = ['$gte' => $this->formatTimestampToMongo($startTime), '$lt' => $this->formatTimestampToMongo($endTime)];
        /*if (empty($isSys)) $isSys = GameUser::COMMON_ACCOUNT;
        if ($isSys == GameUser::COMMON_ACCOUNT) {
            $where['userId'] = ['$gte' => GameUser::COMMON_ACCOUNT_START_ID];
        } elseif ($isSys == GameUser::SYSTEM_ACCOUNT) {
            $where['userId'] = ['$lt' => GameUser::COMMON_ACCOUNT_START_ID];
        }*/
        if (!empty($zhishu)) {
            $zhishu = $zhishu;
        }else{
            $zhishu = 0;
        }
        if (!empty($userId)) {
            $where['userId'] = $userId;
        }
        if (!empty($clubId)) {
            $where['clubId'] = $clubId;
        }
        if (!empty($promoterId)) {
            $where['promoterId'] = $promoterId;
        }
        $groupId = ['userId' => '$userId','clubId' => '$clubId'];
        if (!empty($roomId)) {
            $where['roomId'] = $roomId;
            $groupId = ['userId' => '$userId','clubId' => '$clubId', 'gameId' => '$gameId', 'roomId' => '$roomId'];
        } elseif (!empty($gameId)) {
            $where['gameId'] = $gameId;
            $groupId = ['userId' => '$userId','clubId' => '$clubId', 'gameId' => '$gameId'];
        }
        if (empty($orderType)) $orderType = 'allBet';
        if ($orderType == "winScore") {
            $options = ['$sort' => ['winScore' => -1]];
        } elseif ($orderType == "revenue") {
            $options = ['$sort' => ['revenue' => -1]];
        } elseif ($orderType == "allBet") {
            $options = ['$sort' => ['allBet' => -1]];
        }
        return ['where' => $where, 'group' => $groupId, 'options' => $options,'status' => $status,'state' => $state,'zhishu' => $zhishu];
    }

    public function userGameStat(Request $request)
    {
        if ($request->isAjax()) {
            $where = $this->_userGameStatAjaxParam();
            if (!is_array($where)) return $where;

            $field = $request->get('field');
            $order = $request->get('order');
            if (empty($field)) $field = 'allBet';
            if (empty($order)) $order = 'desc';

            if(!empty($where['where']['promoterId'])){
                //查询该代理所有直属的userId
                $userIds = [];
                $clubIds = [];
                $list = $this->getDirectMember($request,$where);
                $count = $this->getDirectMemberCount($where);
                foreach ($list as &$item){
                    $userIds[] = $item['userId'];
                    $clubIds[] = $item['clubId'];
                }
                $where['where']['userId'] = ['$in' => $userIds];
                $where['where']['clubId'] = ['$in' => $clubIds];
                unset($where['where']['promoterId']);
                $listRecord = $this->getRecordListByCon($where,'','',2);
                $list = merge_array($list,$listRecord, 'userId');
                //数组排序(代理下的所有直接会员)start
                $list = arraySort($list,$field,$order);
                //带promoterId查询情况下,分页显示数据...
                foreach ($list as $rkey => $rval){
                    $dest = $request->skip+$request->limit;
                    if(($rkey < $request->skip) ||  ($rkey >$dest -1)){
                        unset($list[$rkey]);
                    }
                }
                $list = array_values($list);
                //end
            }else{
                //亏盈统计排序
                if($order == "desc"){
                    $sort = -1;
                }else{
                    $sort = 1;
                }
                $newOptions = ['$sort' => [$field => $sort]];
                $where['options'] = $newOptions;
                $list = $this->getRecordListByCon($where,$request->skip,$request->limit,1);
                $listCount = $this->getRecordCountByCon($where);
                $count = count($listCount);
            }

            //检测$whereCon['promoterId']是否为合伙人,如果为合伙人则加上自己的数据
//            if(!empty($where['where']['promoterId']) && empty($where['where']['userId'])){
//                $isLeader = $this->ifLeader($where['where']);
//                if($isLeader == false){//合伙人
//                    $where['where']['userId'] = $where['where']['promoterId'];
//                    unset($where['where']['promoterId']);
//                    $partner = $this->getRecordListByCon($where);
//                    $list = array_merge($partner,$list);
//                    $count = $count + 1;
//                }
//            }
            //end
            if (isset($where['where']['roomId'])) {
                $gameRoomInfo = getClubGameRoomInfo();
                $list = merge_array($list, $gameRoomInfo, 'roomId');
            } elseif (isset($where['where']['gameId'])) {
                $gameInfo = getGameInfo($where['where']['gameId']);
                $list = merge_array($list, $gameInfo, 'gameId');
            }

            foreach ($list as $k => &$item) {
                $item['winScore'] = !empty($item['winScore']) ? $item['winScore'] : 0;
                $item['revenue'] = !empty($item['revenue']) ? $item['revenue'] : 0;
                if (!empty($item['winLostScore'])) {
                    $item['earnScore'] = $this->formatMoneyFromMongo($item['winLostScore']);
                } else {
                    $item['earnScore'] = $this->formatMoneyFromMongo($item['winScore'] + $item['revenue']);
                }
                $item['gameRound'] = !empty($item['gameRound'])? $item['gameRound']: 0;
                $item['winScore'] = !empty($item['winScore'])? $this->formatMoneyFromMongo($item['winScore']) : 0;
                $item['revenue'] = !empty($item['revenue'])? $this->formatMoneyFromMongo($item['revenue']) : 0;
                if(empty($where['where']['promoterId'])){
                    $whereStatus['clubId'] = $item['clubId'];
                    $whereStatus['userId'] = $item['userId'];
                    $myClubsObj = MyClubs::where($whereStatus)->first();
                    $clubMember = !is_null($myClubsObj) ? $myClubsObj->toArray() : [];
                    //$clubMember = MyClubs::where($whereStatus)->first()->toArray();
                    $item['typeString'] = getClubRole($clubMember);
                }

                //增加判断
//                if($where['status'] == 1){
//                    if($item['typeString'] !== "盟主") unset($list[$k]);
//                }
//                if($where['status'] == 2){
//                    if($item['typeString'] !== "合伙人") unset($list[$k]);
//                }
//                if($where['status'] == 3){
//                    if($item['typeString'] !== "会员") unset($list[$k]);
//                }
                $item['allBet'] = !empty($item['allBet'])? $this->formatMoneyFromMongo($item['allBet']) : 0;
                $item['validBet'] = !empty($item['validBet'])? $this->formatMoneyFromMongo($item['validBet']) : 0;

                $item['platformWinScore'] = !empty($item['platformWinScore'])? $this->formatMoneyFromMongo($item['platformWinScore']) : 0;
                $item['agentRevenue'] = !empty($item['agentRevenue']) ? $this->formatMoneyFromMongo($item['agentRevenue']) : 0;
//                $item['ptIncome'] = $this->formatMoneyFromMongoNo($item['platformWinScore'] + $item['agentRevenue']*0.1);
                //$item['ptIncome'] = $this->formatMoneyFromMongoNo($item['platformWinScore'] + $item['revenue']-$item['agentRevenue']*0.9);
                $item['ptIncome'] = $this->formatMoneyFromMongoNo($item['platformWinScore'] + $item['revenue']);//名词变了，公式变了
                $item['playTime'] = !empty($item['playTime']) ? Sec2Time($item['playTime']) : 0;
            }
            //查找所有直属id

            //补齐所有直属数据 通过foreach添加 增加字段

            //重新count
            //每个玩家加入总充值、总兑换、俱乐部奖励
            if (!empty($list)) {
                $userIdArr =  array_column($list, 'userId');
                $users = GameUser::whereIn('userId', $userIdArr)->get(['userId', 'rechargeAmount', 'exchangeAmount', 'clubRewardScore','score','bankScore'])->toArray();
                foreach ($users as &$user) {
                    unset($user['_id']);
                    $user['rechargeAmount'] = !empty($user['rechargeAmount']) ? $this->formatMoneyFromMongo($user['rechargeAmount']) : 0;
                    $user['exchangeAmount'] = !empty($user['exchangeAmount']) ? $this->formatMoneyFromMongo($user['exchangeAmount']) : 0;
                    $user['clubRewardScore'] = !empty($user['clubRewardScore']) ? $this->formatMoneyFromMongo($user['clubRewardScore']): 0;
                    $user['score'] = $this->formatMoneyFromMongo($user['score']);
                    $user['bankScore'] = $this->formatMoneyFromMongo($user['bankScore']);
                }
                $list = merge_array($list, $users, 'userId');
            }
            foreach ($list as &$item) {
                if (!isset($item['rechargeAmount'])) $item['rechargeAmount'] = 0;
                if (!isset($item['exchangeAmount'])) $item['exchangeAmount'] = 0;
                if (!isset($item['clubRewardScore'])) $item['clubRewardScore'] = 0;
                if (!isset($item['score'])) $item['score'] = 0;
                if (!isset($item['bankScore'])) $item['bankScore'] = 0;
            }
            return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $list]);
        }
        $getData = check_type($request->all());
        extract($getData);
        $zhishu = !empty($zhishu) ? (int)$zhishu : '';
        $promoterId = !empty($promoterId) ? (int)$promoterId : '';
        $startDate = !empty($startDate) ? trim($startDate) : "";
        $endDate = !empty($endDate) ? trim($endDate) : "";
        $clubId = $clubId ?? 0;
        $assignData = [
            //'orderType' => ['allBet' => '会员压分', 'winScore' => '会员输赢', 'revenue' => '游戏税收'],
            //'isSys' => GameUser::ACCOUNT_CLASSIFY,
            //'gameList' => GameKind::where(['status' => 1, 'rooms.status' => 1])->orderBy('sort', 'asc')->get()->toArray(),
            'status' => $request -> get("status",0),
            'startDate' => $startDate,
            'endDate' => $endDate,
            'clubRole' => MyClubs::CLUB_ROLE,
            'gameList' => getClubGameInfoGameRoomInfo(),
            'promoterId'=>$promoterId,
            'zhishu'=>$zhishu,
            'clubId'=>(int)$clubId,
            'clubList' => ClubMo::where(['status' => 1])->orderBy('clubId', 'desc')->get()->toArray(),
        ];
        //dd($assignData['gameList']);
        return view('club/gameStat/list', $assignData);
    }

    public function userGameStat2(Request $request)
    {
        if ($request->isAjax()) {
            $where = $this->_userGameStatAjaxParam();
            if (!is_array($where)) return $where;

            $field = $request->get('field');
            $order = $request->get('order');
            if (empty($field)) $field = 'allBet';
            if (empty($order)) $order = 'desc';

            if(!empty($where['where']['promoterId'])){
                //查询该代理所有直属的userId
                $userIds = [];
                $clubIds = [];
                $list = $this->getDirectMember($request,$where);
                $count = $this->getDirectMemberCount($where);
                foreach ($list as &$item){
                    $userIds[] = $item['userId'];
                    $clubIds[] = $item['clubId'];
                }
                $where['where']['userId'] = ['$in' => $userIds];
                $where['where']['clubId'] = ['$in' => $clubIds];
                unset($where['where']['promoterId']);
                $listRecord = $this->getRecordListByCon($where,'','',2);
                $list = merge_array($list,$listRecord, 'userId');
                //数组排序(代理下的所有直接会员)start
                $list = arraySort($list,$field,$order);
                //带promoterId查询情况下,分页显示数据...
                foreach ($list as $rkey => $rval){
                    $dest = $request->skip+$request->limit;
                    if(($rkey < $request->skip) ||  ($rkey >$dest -1)){
                        unset($list[$rkey]);
                    }
                }
                $list = array_values($list);
                //end
            }else{
                //亏盈统计排序
                if($order == "desc"){
                    $sort = -1;
                }else{
                    $sort = 1;
                }
                $newOptions = ['$sort' => [$field => $sort]];
                $where['options'] = $newOptions;
                $list = $this->getRecordListByCon($where,$request->skip,$request->limit,1);
                $listCount = $this->getRecordCountByCon($where);
                $count = count($listCount);
            }

            //检测$whereCon['promoterId']是否为合伙人,如果为合伙人则加上自己的数据
//            if(!empty($where['where']['promoterId']) && empty($where['where']['userId'])){
//                $isLeader = $this->ifLeader($where['where']);
//                if($isLeader == false){//合伙人
//                    $where['where']['userId'] = $where['where']['promoterId'];
//                    unset($where['where']['promoterId']);
//                    $partner = $this->getRecordListByCon($where);
//                    $list = array_merge($partner,$list);
//                    $count = $count + 1;
//                }
//            }
            //end
            /*if (isset($where['where']['roomId'])) {
                $gameRoomInfo = getClubGameRoomInfo();
                $list = merge_array($list, $gameRoomInfo, 'roomId');
            } elseif (isset($where['where']['gameId'])) {
                $gameInfo = getGameInfo($where['where']['gameId']);
                $list = merge_array($list, $gameInfo, 'gameId');
            }*/

            foreach ($list as $k => &$item) {
                $item['winScore'] = !empty($item['winScore']) ? $item['winScore'] : 0;
                $item['revenue'] = !empty($item['revenue']) ? $item['revenue'] : 0;
                if (!empty($item['winLostScore'])) {
                    $item['earnScore'] = $this->formatMoneyFromMongo($item['winLostScore']);
                } else {
                    $item['earnScore'] = $this->formatMoneyFromMongo($item['winScore'] + $item['revenue']);
                }
                $item['gameRound'] = !empty($item['gameRound'])? $item['gameRound']: 0;
                $item['winScore'] = !empty($item['winScore'])? $this->formatMoneyFromMongo($item['winScore']) : 0;
                $item['revenue'] = !empty($item['revenue'])? $this->formatMoneyFromMongo($item['revenue']) : 0;
                if(empty($where['where']['promoterId'])){
                    $whereStatus['clubId'] = $item['clubId'];
                    $whereStatus['userId'] = $item['userId'];
                    $myClubsObj = MyClubs::where($whereStatus)->first();
                    $clubMember = !is_null($myClubsObj) ? $myClubsObj->toArray() : [];
                    //$clubMember = MyClubs::where($whereStatus)->first()->toArray();
                    //$item['typeString'] = getClubRole($clubMember);
                    $item['typeString'] = "会员";
                }

                //增加判断
//                if($where['status'] == 1){
//                    if($item['typeString'] !== "盟主") unset($list[$k]);
//                }
//                if($where['status'] == 2){
//                    if($item['typeString'] !== "合伙人") unset($list[$k]);
//                }
//                if($where['status'] == 3){
//                    if($item['typeString'] !== "会员") unset($list[$k]);
//                }
                $item['allBet'] = !empty($item['allBet'])? $this->formatMoneyFromMongo($item['allBet']) : 0;
                $item['validBet'] = !empty($item['validBet'])? $this->formatMoneyFromMongo($item['validBet']) : 0;

                $item['platformWinScore'] = !empty($item['platformWinScore'])? $this->formatMoneyFromMongo($item['platformWinScore']) : 0;
                $item['agentRevenue'] = !empty($item['agentRevenue']) ? $this->formatMoneyFromMongo($item['agentRevenue']) : 0;
//                $item['ptIncome'] = $this->formatMoneyFromMongoNo($item['platformWinScore'] + $item['agentRevenue']*0.1);
                //$item['ptIncome'] = $this->formatMoneyFromMongoNo($item['platformWinScore'] + $item['revenue']-$item['agentRevenue']*0.9);
                $item['ptIncome'] = $this->formatMoneyFromMongoNo($item['platformWinScore'] + $item['revenue']);//名词变了，公式变了
                $item['playTime'] = !empty($item['playTime']) ? Sec2Time($item['playTime']) : 0;
            }
            //查找所有直属id

            //补齐所有直属数据 通过foreach添加 增加字段

            //重新count
            //每个玩家加入总充值、总兑换、俱乐部奖励
            if (!empty($list)) {
                $userIdArr =  array_column($list, 'userId');
                $users = GameUser::whereIn('userId', $userIdArr)->get(['userId', 'rechargeAmount', 'exchangeAmount', 'clubRewardScore','score','bankScore'])->toArray();
                foreach ($users as &$user) {
                    unset($user['_id']);
                    $user['rechargeAmount'] = !empty($user['rechargeAmount']) ? $this->formatMoneyFromMongo($user['rechargeAmount']) : 0;
                    $user['exchangeAmount'] = !empty($user['exchangeAmount']) ? $this->formatMoneyFromMongo($user['exchangeAmount']) : 0;
                    $user['clubRewardScore'] = !empty($user['clubRewardScore']) ? $this->formatMoneyFromMongo($user['clubRewardScore']): 0;
                    $user['score'] = $this->formatMoneyFromMongo($user['score']);
                    $user['bankScore'] = $this->formatMoneyFromMongo($user['bankScore']);
                }
                $list = merge_array($list, $users, 'userId');
            }
            foreach ($list as &$item) {
                if (!isset($item['rechargeAmount'])) $item['rechargeAmount'] = 0;
                if (!isset($item['exchangeAmount'])) $item['exchangeAmount'] = 0;
                if (!isset($item['clubRewardScore'])) $item['clubRewardScore'] = 0;
                if (!isset($item['score'])) $item['score'] = 0;
                if (!isset($item['bankScore'])) $item['bankScore'] = 0;
            }
            return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $list]);
        }
        $getData = check_type($request->all());
        extract($getData);
        $zhishu = !empty($zhishu) ? (int)$zhishu : '';
        $promoterId = !empty($promoterId) ? (int)$promoterId : '';
        $startDate = !empty($startDate) ? trim($startDate) : "";
        $endDate = !empty($endDate) ? trim($endDate) : "";
        $clubId = $clubId ?? 0;
        $assignData = [
            //'orderType' => ['allBet' => '会员压分', 'winScore' => '会员输赢', 'revenue' => '游戏税收'],
            //'isSys' => GameUser::ACCOUNT_CLASSIFY,
            //'gameList' => GameKind::where(['status' => 1, 'rooms.status' => 1])->orderBy('sort', 'asc')->get()->toArray(),
            'status' => $request -> get("status",0),
            'startDate' => $startDate,
            'endDate' => $endDate,
            'clubRole' => MyClubs::CLUB_ROLE,
            'gameList' => getClubGameInfoGameRoomInfo(),
            'promoterId'=>$promoterId,
            'zhishu'=>$zhishu,
            'clubId'=>(int)$clubId,
            'clubList' => ClubMo::where(['status' => 1])->orderBy('clubId', 'desc')->get()->toArray(),
        ];
        //dd($assignData['gameList']);
        return view('club/gameStat2/list', $assignData);
    }

    public function userGameStatSurvey(Request $request)
    {
        if ($request->isAjax()) {
            $where = $this->_userGameStatAjaxParam();
            if (!is_array($where)) return $where;
            $clubId = isset($where['where']['clubId']) ? $where['where']['clubId']:0;
            $userId = isset($where['where']['userId']) ? $where['where']['userId']:0;
            $clubsMember = $userIds = $nowUid = [];
            if(!empty($clubId) && !$where['state']){
                $field = ['status','userId','clubId'];
                $clubsMember= MyClubs::select($field) -> where(['clubId' => $clubId]) -> where('userId','>',10000000)->get()->toArray();
                if($clubsMember){
                    foreach ($clubsMember as &$val){
                        $val['gameRound'] = $val['allBet'] = $val['winScore'] = $val['platformWinScore'] = $val['revenue'] = $val['playTime'] = $val['agentRevenue'] = $val['ptIncome'] = 0;
                        $userIds[] = $val['userId'];
                        $val['typeString'] = getClubRole($val);
                    }
                    if($userId){
                        $where['where']['userId'] = ['$in' => $userIds];
                    }
                }
            }else{
                if($where['status'] > 0 && $where['status'] < 3){
                    $filter = ['status' => $where['status'],'clubId' => $clubId,['userId','>',10000000]];
                    $clubsMember = MyClubs:: select('status','userId','clubId') ->where($filter)->get()->toArray();
                    foreach ($clubsMember as $k1 => $v1){
                        if($where['status'] == 2){
                            if($v1['userId'] != $clubId){
                                $userIds[] = $v1['userId'];
                            }
                        }else{
                            $userIds[] = $v1['userId'];
                        }
                    }

                    if($userId){
                        $where['where']['userId'] = ['$eq' => $userId];
                        $where['where']['userId'] += ['$in' => $userIds,'$ne' => $clubId];
                    }else{
                        $where['where']['userId'] = ['$in' => $userIds,'$ne' => $clubId];
                    }
                }

                if($where['status'] == 3){
                    if($userId){
                        $where['where']['userId'] = ['$eq' => $clubId];
                    }else{
                        $where['where']['userId'] = $clubId;
                    }
                }
            }

            $list = $this->getRecordListByCon($where,$request->skip,$request->limit,1);
            $listCount = $this->getRecordCountByCon($where);
            $count = count($listCount);

            if (isset($where['where']['roomId'])) {
                $gameRoomInfo = getClubGameRoomInfo();
                $list = merge_array($list, $gameRoomInfo, 'roomId');
            } elseif (isset($where['where']['gameId'])) {
                $gameInfo = getGameInfo($where['where']['gameId']);
                $list = merge_array($list, $gameInfo, 'gameId');
            }

            foreach ($list as $k => &$item) {
                $item['winScore'] = !empty($item['winScore']) ? $item['winScore'] : 0;
                $item['revenue'] = !empty($item['revenue']) ? $item['revenue'] : 0;
                if (!empty($item['winLostScore'])) {
                    $item['earnScore'] = $this->formatMoneyFromMongo($item['winLostScore']);
                } else {
                    $item['earnScore'] = $this->formatMoneyFromMongo($item['winScore'] + $item['revenue']);
                }
                $item['gameRound'] = !empty($item['gameRound'])? $item['gameRound']: 0;
                $item['winScore'] = !empty($item['winScore'])? $this->formatMoneyFromMongo($item['winScore']) : 0;
                $item['revenue'] = !empty($item['revenue'])? $this->formatMoneyFromMongo($item['revenue']) : 0;

                if(empty($where['where']['promoterId'])){
                    $whereStatus['clubId'] = $item['clubId'];
                    $whereStatus['userId'] = $item['userId'];
                    $clubMember = MyClubs::where($whereStatus)->first();
                    if(!empty($clubMember)){
                        $clubMember = $clubMember->toArray();
                        $item['typeString'] = getClubRole($clubMember);
                    }else{
                        $item['typeString'] = "";
                        dd($item);
                    }

                }

                $item['allBet'] = !empty($item['allBet'])? $this->formatMoneyFromMongo($item['allBet']) : 0;

                $item['platformWinScore'] = !empty($item['platformWinScore'])? $this->formatMoneyFromMongo($item['platformWinScore']) : 0;
                $item['agentRevenue'] = !empty($item['agentRevenue']) ? $this->formatMoneyFromMongo($item['agentRevenue']) : 0;

                $item['ptIncome'] = $this->formatMoneyFromMongoNo($item['platformWinScore'] + $item['revenue']-$item['agentRevenue']*0.9);
                $item['playTime'] = !empty($item['playTime']) ? Sec2Time($item['playTime']) : 0;

                $nowUid[] = $item['userId'];
            }

            if($clubsMember && !$where['state']){
                $promoterNum = 0;
                foreach ($clubsMember as $k => $v){
                    if(!in_array($v['userId'],$nowUid)){
                        $list[] = $v;
                        $promoterNum++;
                    }
                }
                $total = $count + $promoterNum;
            }else{
                $total = $count;
            }
            //每个玩家加入总充值、总兑换、俱乐部奖励
            if (!empty($list)) {
                $userIdArr =  array_column($list, 'userId');
                $users = GameUser::whereIn('userId', $userIdArr)->get(['userId', 'rechargeAmount', 'exchangeAmount', 'clubRewardScore'])->toArray();
                foreach ($users as &$user) {
                    unset($user['_id']);
                    $user['rechargeAmount'] = $this->formatMoneyFromMongo($user['rechargeAmount']??0);
                    $user['exchangeAmount'] = $this->formatMoneyFromMongo($user['exchangeAmount']??0);
                    $user['clubRewardScore'] = $this->formatMoneyFromMongo($user['clubRewardScore']??0);
                }
                $list = merge_array($list, $users, 'userId');
            }

            return json(['code' => 0, 'msg' => 'ok', 'count' => $total, 'data' => $list]);
        }
    }

    public function userGameStatSummary(Request $request)
    {
        if($request->isAjax()) {
            $where = $this->_userGameStatAjaxParam();
            if (!is_array($where)) return $where;
            //是否有会员角色,有的话先过滤
            $whereCon = $where['where'];
            if(!empty($where['status'])){
                $userIds = [];
                $userList = Db::connection('mongodb_club')->collection('play_record')->raw()->aggregate([
                    [
                        '$match' => $where['where']
                    ],
                    [
                        '$project' =>
                            [
                                'userId'=>1,
                                'clubId'=>1,
                                'gameId'=>1,
                                'roomId'=>1
                            ]
                    ],
                    [
                        '$group' =>
                            [
                                '_id' => $where['group']
                            ]
                    ],
                    $where['options'],
                    [
                        '$project' =>
                            [
                                'userId'=>'$_id.userId',
                                'clubId'=>'$_id.clubId',
                                'gameId'=>'$_id.gameId',
                                'roomId'=>'$_id.roomId',
                            ]
                    ]
                ])->toArray();
                foreach ($userList as $k => &$item) {
                    $whereStatus['clubId'] = $item['clubId'];
                    $whereStatus['userId'] = $item['userId'];
                    $clubMember = MyClubs::where($whereStatus)->first()->toArray();
                    $item['typeString'] = getClubRole($clubMember);
                    if($where['status'] == 3){
                        if($item['typeString'] !== "盟主") unset($userList[$k]);
                    }
                    if($where['status'] == 2){
                        if($item['typeString'] !== "合伙人") unset($userList[$k]);
                    }
                    if($where['status'] == 1) {
                        if ($item['typeString'] !== "会员") unset($userList[$k]);
                    }
                }
                foreach ($userList as $k => &$item) {
                    $userIds [] = $item['userId'];
                }
                $whereCon['userId'] = ['$in' => $userIds];
            }
            //end

            $listCount = Db::connection('mongodb_club')->collection('play_record')->raw()->aggregate([
                [
                    '$match' => $whereCon
                ],
                [
                    '$project' =>
                        [
                            'userId'=>1,
                            'gameId'=>1,
                            'roomId'=>1,
                            'allBet'=>1,
                            'winScore'=>1,
                            'platformWinScore' => 1,
                            'revenue' => 1,
                            'agentRevenue' => 1,
                            'playTime'=>1
                        ]
                ],
                /* [
                     '$group' =>
                         [
                             '_id' => $where['group'],
                             'gameRound' => ['$sum' => 1],
                             'allBet' => ['$sum' => '$allBet'],
                             'winScore' => ['$sum' => '$winScore'],
                             'platformWinScore' => ['$sum' => 'platformWinScore'],
                             'revenue' => ['$sum' => '$revenue'],
                             'playTime' => ['$sum' => '$playTime']
                         ]
                 ],*/
                [
                    '$group' =>
                        [
                            '_id' => null,

                            'allBet' => ['$sum' => '$allBet'],
                            'winScore' => ['$sum' => '$winScore'],
                            'platformWinScore' => ['$sum' => '$platformWinScore'],
                            'revenue' => ['$sum' => '$revenue'],
                            'agentRevenue' => ['$sum' => '$agentRevenue'],
                        ]
                ],
                [
                    '$project' =>
                        [
                            'allBet'=>1,
                            'winScore'=>1,
                            'platformWinScore' => 1,
                            'revenue' => 1,
                            'agentRevenue' => 1,
                        ]
                ]
            ])->toArray();

            if(!empty($listCount)){
                $agentRevenueSum = $this->formatMoneyFromMongo($listCount[0]['agentRevenue']);
                $revenueSum = $this->formatMoneyFromMongo($listCount[0]['revenue']);
                $allBetSum = $this->formatMoneyFromMongo($listCount[0]['allBet']);
                $winScoreSum = $this->formatMoneyFromMongo($listCount[0]['winScore']);
                $platformWinScoreSum = $this->formatMoneyFromMongo($listCount[0]['platformWinScore']);
                $earnScoreSum = $this->formatMoneyFromMongo($listCount[0]['winScore'] + $listCount[0]['revenue']);
                //$ptIncomeSum = $this->formatMoneyFromMongo($listCount[0]['platformWinScore'] + $listCount[0]['revenue']);
                //$ptIncomeSum = $this->formatMoneyFromMongoNo($platformWinScoreSum + $agentRevenueSum*0.1);
                //$ptIncomeSum = $this->formatMoneyFromMongoNo($platformWinScoreSum + $revenueSum - $agentRevenueSum*0.9);
                $ptIncomeSum = $this->formatMoneyFromMongoNo($platformWinScoreSum + $revenueSum);//名词变了，公式变了
            }else{
                $agentRevenueSum = 0;
                $revenueSum = 0;
                $allBetSum = 0;
                $winScoreSum = 0;
                $platformWinScoreSum = 0;
                $earnScoreSum = 0;
                $ptIncomeSum = 0;
            }
            //检测$whereCon['promoterId']是否为合伙人,如果为合伙人则加上自己的数据,并且不是直属的情况(直属情况下,盟主包括自己,合伙人不包括自己)
            if($where['status'] !== 1 && $where['status'] !== 3 && $where['zhishu'] != 1){
                if(!empty($whereCon['promoterId']) && empty($where['where']['userId'])){
                    $isLeader = $this->ifLeader($whereCon);
                    if($isLeader == false){

                        $where['where']['userId'] = $where['where']['promoterId'];
                        unset($where['where']['promoterId']);
                        $partner = $this->getRecordListByCon($where);
                        $revenueSum += !empty($partner) ? $this->formatMoneyFromMongo($partner[0]['revenue']) : 0;
                        $revenueSum = $this->formatMoneyFromMongoNo($revenueSum);
                        $agentRevenueSum += !empty($partner) ? $this->formatMoneyFromMongo($partner[0]['agentRevenue']) : 0;
                        $agentRevenueSum = $this->formatMoneyFromMongoNo($agentRevenueSum);
                        $allBetSum += !empty($partner) ? $this->formatMoneyFromMongo($partner[0]['allBet']) : 0;
                        $allBetSum = $this->formatMoneyFromMongoNo($allBetSum);
                        $winScoreSum += !empty($partner) ?$this->formatMoneyFromMongo($partner[0]['winScore']) :0;
                        $winScoreSum = $this->formatMoneyFromMongoNo($winScoreSum);
                        $platformWinScoreSum += !empty($partner) ? $this->formatMoneyFromMongo($partner[0]['platformWinScore']) : 0;
                        $platformWinScoreSum = $this->formatMoneyFromMongoNo($platformWinScoreSum);
                        $earnScoreSum += !empty($partner) ? $this->formatMoneyFromMongo($partner[0]['winScore'] + $partner[0]['revenue']) : 0;
                        $earnScoreSum = $this->formatMoneyFromMongoNo($earnScoreSum);
                        //$ptIncomeSum += $this->formatMoneyFromMongo($partner[0]['platformWinScore'] + $partner[0]['revenue']);
                        //$ptIncomeSum += $this->formatMoneyFromMongoNo($platformWinScoreSum + $agentRevenueSum*0.1);
                        $ptIncomeSum += $this->formatMoneyFromMongoNo($platformWinScoreSum);//名词变了，公式变了
                        $ptIncomeSum = $this->formatMoneyFromMongoNo($ptIncomeSum);
                    }
                }
                //end
            }


            return json(['code' => 0, 'msg' => 'ok', 'data' => ['earnScoreSum' => $earnScoreSum, 'ptIncomeSum' => $ptIncomeSum, 'revenueSum' => $revenueSum, 'agentRevenueSum' => $agentRevenueSum, 'allBetSum' => $allBetSum, 'winScoreSum' => $winScoreSum, 'platformWinScoreSum' => $platformWinScoreSum]]);
        }
    }

    public function exportGameStat(Request $request)
    {
        $where = $this->_userGameStatAjaxParam();
        if (!is_array($where)) return $where;
        $list = Db::connection('mongodb_club')->collection('play_record')->raw()->aggregate([
            [
                '$match' => $where['where']
            ],
            [
                '$project' =>
                    [
                        'userId'=>1,
                        'gameId'=>1,
                        'roomId'=>1,
                        'allBet'=>1,
                        'winScore'=>1,
                        'platformWinScore' => 1,
                        'revenue' => 1,
                        'playTime'=>1
                    ]
            ],
            [
                '$group' =>
                    [
                        '_id' => $where['group'],
                        'gameRound' => ['$sum' => 1],
                        'allBet' => ['$sum' => '$allBet'],
                        'winScore' => ['$sum' => '$winScore'],
                        'platformWinScore' => ['$sum' => 'platformWinScore'],
                        'revenue' => ['$sum' => '$revenue'],
                        'playTime' => ['$sum' => '$playTime']
                    ]
            ],
            $where['options']
            ,
            [
                '$skip' => 0
            ],
            [
                '$project' =>
                    [
                        'userId'=>'$_id.userId',
                        'gameId'=>'$_id.gameId',
                        'roomId'=>'$_id.roomId',
                        'allBet'=>1,
                        'gameRound'=>1,
                        'winScore'=>1,
                        'platformWinScore' => 1,
                        'revenue' => 1,
                        'playTime'=>1
                    ]
            ]
        ])->toArray();
        $count = count($list);
        if (isset($where['where']['roomId'])) {
            $gameRoomInfo = getGameRoomInfo();
            $list = merge_array($list, $gameRoomInfo, 'roomId');
        } elseif (isset($where['where']['gameId'])) {
            $gameInfo = getGameInfo($where['where']['gameId']);
            $list = merge_array($list, $gameInfo, 'gameId');
        }
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', '会员ID');
        $sheet->setCellValue('B1', '游戏名称');
        $sheet->setCellValue('C1', '会员压分');
        $sheet->setCellValue('D1', '会员得分');
        $sheet->setCellValue('E1', '会员输赢');
        $sheet->setCellValue('F1', '游戏输赢');
        $sheet->setCellValue('G1', '平台税收');
        $sheet->setCellValue('H1', '平台营收');
        $sheet->setCellValue('I1', '会员时长');
        $sheet->setCellValue('J1', '会员局数');
        $num = 2;
        foreach ($list as $item) {
            if (isset($item['winLostScore'])) {
                $item['earnScore'] = $this->formatMoneyFromMongo($item['winLostScore']);
            } else {
                $item['earnScore'] = $this->formatMoneyFromMongo($item['winScore'] + $item['revenue']);
            }
            $item['ptIncome'] = $this->formatMoneyFromMongo($item['platformWinScore'] + $item['revenue']);

            $item['allBet'] = $this->formatMoneyFromMongo($item['allBet']);
            $item['winScore'] = $this->formatMoneyFromMongo($item['winScore']);
            $item['platformWinScore'] = $this->formatMoneyFromMongo($item['platformWinScore']);
            $item['revenue'] = $this->formatMoneyFromMongo($item['revenue']);
            $item['playTime'] = Sec2Time($item['playTime']);

            $sheet->setCellValue("A{$num}", $item['userId']);
            $sheet->setCellValue("B{$num}", $item['roomName']??$item['gameName']??'-');
            $sheet->setCellValue("C{$num}", $item['allBet']);
            $sheet->setCellValue("D{$num}", $item['earnScore']);
            $sheet->setCellValue("E{$num}", $item['winScore']);
            $sheet->setCellValue("F{$num}", $item['platformWinScore']);
            $sheet->setCellValue("G{$num}", $item['revenue']);
            $sheet->setCellValue("H{$num}", $item['ptIncome']);
            $sheet->setCellValue("I{$num}", $item['playTime']);
            $sheet->setCellValue("J{$num}", $item['gameRound']);

            $num++;
        }
        $writer = new Xlsx($spreadsheet);
        $file_path = public_path().'/exportClubGameStat.xlsx';
        // 保存文件到 public 下
        $writer->save($file_path);

        return json(['code' => 0, 'msg' => 'ok', 'file' => $file_path]);
    }

    public function userIncomeStat(Request $request)
    {
        if($request->isAjax()) {
            $getData = check_type($request->get());

            extract($getData);
            if (empty($startDate)) {
                $startDate = date("Y-m-d");
            }
            if (empty($endDate)) {
                $endDate = date("Y-m-d");
            }
            $startDate = strtotime($startDate);
            $endDate = strtotime("$endDate +1 day");

            $filter = [];
            $filter += ['endTime' => ['$gte' => $this->formatTimestampToMongo($startDate), '$lt' => $this->formatTimestampToMongo($endDate)]];

            if(!empty($userId)){
                $filter += ['userId' => $userId];
            }else{
                $filter += ['userId' => ['$gte' => 10000000]];
            }

//            $usersOnline = Db::connection('mongodb_main')->collection('game_user')->raw()->aggregate([
//                [
//                    '$match' =>
//                        [
//                            'onlineLogin' => ['$gt' => 0],
//                            'onlineGame' => ['$gt' => 0]
//                        ]
//                ],
//                [
//                    '$match' => [
//                        'userId' => [
//                            '$gte' => 10000000,
//                        ]
//                    ]
//                ],
//                [
//                    '$project' =>
//                        [
//                            'onlineGame' => 1,
//                        ]
//                ],
//                [
//                    '$group' =>
//                        [
//                            '_id' => '$onlineGame',
//                            'onlineCount' => ['$sum' => 1]
//                        ]
//                ],
//                [
//                    '$project' =>
//                        [
//                            'roomId' => '$_id',
//                            'onlineCount' => 1
//                        ]
//                ]
//            ])->toArray();

            $play_game_room_info_arrays = Db::connection('mongodb_club')->collection('play_record')->raw()->aggregate([
//                [
//                    '$match' => ['endTime' =>
//                        ['$gte' => $this->formatTimestampToMongo($startDate), '$lt' => $this->formatTimestampToMongo($endDate)]]
//                ],
//                [
//                    '$match' => [
//                        'userId' => [
//                            '$gte' => 10000000,
//                        ]
//                    ]
//                ],
                [
                    '$match' => $filter
                ],
                [
                    '$project' =>
                        [
                            'userId' => 1,
                            'gameId' => 1,
                            'roomId' => 1,
                            'allBet' => 1,
                            'winScore' => 1,
                            'platformWinScore' => 1,
                            'revenue' => 1,
                            'agentRevenue' => 1
//                    'playTime'=>1
                        ]
                ],
                [
                    '$group' =>
                        [
                            '_id' => ['gameId' => '$gameId', 'roomId' => '$roomId', 'userId' => '$userId'],
                            'gameRound' => ['$sum' => 1],
                            'winTimes' =>
                                ['$sum' =>
                                    [
                                        '$cond' =>
                                            [
                                                'if' => ['$gt' => ['$winScore', 0]],
                                                'then' => 1,
                                                'else' => 0,
                                            ]
                                    ]
                                ],
                            'lostTimes' =>
                                ['$sum' =>
                                    [
                                        '$cond' =>
                                            [
                                                'if' => ['$lt' => ['$winScore', 0]],
                                                'then' => 1,
                                                'else' => 0,
                                            ]
                                    ]
                                ],
                            'drawTimes' =>
                                ['$sum' =>
                                    [
                                        '$cond' =>
                                            [
                                                'if' => ['$eq' => ['$winScore', 0]],
                                                'then' => 1,
                                                'else' => 0,
                                            ]
                                    ]
                                ],
                            'allBet' => ['$sum' => '$allBet'],
                            'winScore' => ['$sum' => '$winScore'],
                            'platformWinScore' => ['$sum' => '$platformWinScore'],
                            'revenue' => ['$sum' => '$revenue'],
                            'agentRevenue' => ['$sum' => '$agentRevenue']
//                    'playTime' => ['$sum' => '$playTime']
                        ]
                ],
                [
                    '$group' =>
                        [
                            '_id' => ['gameId' => '$_id.gameId', 'roomId' => '$_id.roomId'],
                            'userCount' => ['$sum' => 1],
                            'gameRound' => ['$sum' => '$gameRound'],
                            'winTimes' => ['$sum' => '$winTimes'],
                            'lostTimes' => ['$sum' => '$lostTimes'],
                            'drawTimes' => ['$sum' => '$drawTimes'],
                            'allBet' => ['$sum' => '$allBet'],
                            'winScore' => ['$sum' => '$winScore'],
                            'platformWinScore' => ['$sum' => '$platformWinScore'],
                            'revenue' => ['$sum' => '$revenue'],
                            'agentRevenue' => ['$sum' => '$agentRevenue']
//                    'playTime' => ['$sum' => '$playTime']
                        ]
                ],
                [
                    '$project' =>
                        [
                            'gameId' => '$_id.gameId',
                            'roomId' => '$_id.roomId',
                            'gameRound' => 1,
                            'userCount' => 1,
                            'winTimes' => 1,
                            'lostTimes' => 1,
                            'drawTimes' => 1,
                            'allBet' => 1,
                            'winScore' => 1,
                            'platformWinScore' => 1,
                            'revenue' => 1,
                            'agentRevenue' => 1
//                    'playTime'=>1
                        ]
                ]
            ])->toArray();
            $game_room_info = getClubGameRoomInfo();
            $play_game_room_info_arrays = merge_array($play_game_room_info_arrays, $game_room_info, 'roomId');

            //=======统计======
            $onlineCountTotal = 0;
            $userCountTotal = 0;
            $gameRoundTotal = 0;

            $winTimesTotal = 0;
            $lostTimesTotal = 0;
            $drawTimesTotal = 0;

            $allBetTotal = 0;
            $winScoreTotal = 0;
            $platformWinScoreTotal = 0;
            $revenueTotal = 0;
            $agentRevenueTotal = 0;
//    $playTimeTotal = 0;

            $ptIncomeTotal = 0;
            //=================

            $game_info_list = getClubGameInfo();
            $num = count($game_info_list);
            foreach ($game_info_list as $key => $value) {
                $game_info_list[$key]["pid"] = 0;
                $game_info_list[$key]["id"] = $value['gameId'];
                $game_info_list[$key]["title"] = $value['gameId'] . '_' . $value['gameName'];

                $game_info_list[$key]["onlineCount"] = 0;
                $game_info_list[$key]["userCount"] = 0;
                $game_info_list[$key]["gameRound"] = 0;

                $game_info_list[$key]["winTimes"] = 0;
                $game_info_list[$key]["lostTimes"] = 0;
                $game_info_list[$key]["drawTimes"] = 0;
                $game_info_list[$key]["lostWinRate"] = 0;

                $game_info_list[$key]["allBet"] = 0;
                $game_info_list[$key]["winScore"] = 0;
                $game_info_list[$key]["platformWinScore"] = 0;
                $game_info_list[$key]["revenue"] = 0;
                $game_info_list[$key]["agentRevenue"] = 0;

//        $game_info_list[$key]["playTime"] = 0;

                $game_info_list[$key]["ptIncome"] = 0;
                $game_info_list[$key]["ptIncomeRate"] = 0;
                foreach ($play_game_room_info_arrays as $kk => $vv) {
                    //unset($play_game_room_info_arrays[$kk]['_id']);
                    if ($game_info_list[$key]["gameId"] == $play_game_room_info_arrays[$kk]['gameId']) {
                        if (!isset($play_game_room_info_arrays[$kk]['onlineCount']))
                            $play_game_room_info_arrays[$kk]['onlineCount'] = 0;
                        $game_info_list[$key]["onlineCount"] += $play_game_room_info_arrays[$kk]['onlineCount'];
                        $game_info_list[$key]["userCount"] += $play_game_room_info_arrays[$kk]['userCount'];
                        $game_info_list[$key]["gameRound"] += $play_game_room_info_arrays[$kk]['gameRound'];

                        $game_info_list[$key]["winTimes"] += $play_game_room_info_arrays[$kk]['winTimes'];
                        $game_info_list[$key]["lostTimes"] += $play_game_room_info_arrays[$kk]['lostTimes'];
                        $game_info_list[$key]["drawTimes"] += $play_game_room_info_arrays[$kk]['drawTimes'];
//                if($play_game_room_info_arrays[$kk]['lostTimes'] > 0)
//                    $play_game_room_info_arrays[$kk]["lostWinRate"] = round($play_game_room_info_arrays[$kk]['winTimes'] / $play_game_room_info_arrays[$kk]['lostTimes'], 2);
//                else
//                    $play_game_room_info_arrays[$kk]["lostWinRate"] = 0;

                        $play_game_room_info_arrays[$kk]["allBet"] = round($play_game_room_info_arrays[$kk]["allBet"] * 0.01, 2);
                        $play_game_room_info_arrays[$kk]["winScore"] = round($play_game_room_info_arrays[$kk]["winScore"] * 0.01, 2);
                        $play_game_room_info_arrays[$kk]["platformWinScore"] = round($play_game_room_info_arrays[$kk]["platformWinScore"] * 0.01, 2);
                        $play_game_room_info_arrays[$kk]["revenue"] = round($play_game_room_info_arrays[$kk]["revenue"] * 0.01, 2);
                        $play_game_room_info_arrays[$kk]["agentRevenue"] = round($play_game_room_info_arrays[$kk]["agentRevenue"] * 0.01, 2);

//                $play_game_room_info_arrays[$kk]["earnScore"] = round(($play_game_room_info_arrays[$kk]["revenue"]+$play_game_room_info_arrays[$kk]["winScore"])*0.01, 2);
                        //$play_game_room_info_arrays[$kk]["ptIncome"] = round(($play_game_room_info_arrays[$kk]["revenue"]-$play_game_room_info_arrays[$kk]["winScore"])*0.01, 2);
                        //$play_game_room_info_arrays[$kk]["ptIncome"] = round(($play_game_room_info_arrays[$kk]["revenue"]-$play_game_room_info_arrays[$kk]["winScore"]), 2);

                        //$play_game_room_info_arrays[$kk]["ptIncome"] = round(($play_game_room_info_arrays[$kk]["platformWinScore"] + $play_game_room_info_arrays[$kk]["revenue"]), 2);
                        //$play_game_room_info_arrays[$kk]["ptIncome"] = $this->formatMoneyFromMongoNo($play_game_room_info_arrays[$kk]["platformWinScore"] + $play_game_room_info_arrays[$kk]["agentRevenue"]*0.1);
                        $play_game_room_info_arrays[$kk]["ptIncome"] = $this->formatMoneyFromMongoNo($play_game_room_info_arrays[$kk]["platformWinScore"] + $play_game_room_info_arrays[$kk]["revenue"] - $play_game_room_info_arrays[$kk]["agentRevenue"]*0.9);

                        $game_info_list[$key]["allBet"] += $play_game_room_info_arrays[$kk]['allBet'];
                        $game_info_list[$key]["winScore"] += $play_game_room_info_arrays[$kk]['winScore'];
                        $game_info_list[$key]["revenue"] += $play_game_room_info_arrays[$kk]['revenue'];
                        $game_info_list[$key]["agentRevenue"] += $play_game_room_info_arrays[$kk]['agentRevenue'];
                        $game_info_list[$key]["platformWinScore"] += $play_game_room_info_arrays[$kk]["platformWinScore"];
//                $game_info_list[$key]["earnScore"] += $play_game_room_info_arrays[$kk]['earnScore'];
                        //$game_info_list[$key]["ptIncome"] += $play_game_room_info_arrays[$kk]['ptIncome'];
                        $game_info_list[$key]["ptIncome"] += $play_game_room_info_arrays[$kk]['ptIncome'];

                        array_push($game_info_list, $play_game_room_info_arrays[$kk]);
                        $game_info_list[$num]["id"] = $play_game_room_info_arrays[$kk]["roomId"];
                        $game_info_list[$num]["pid"] = $game_info_list[$key]["id"];
                        $game_info_list[$num]["title"] = $play_game_room_info_arrays[$kk]["roomId"] . '_' . $play_game_room_info_arrays[$kk]["roomName"];
                        unset($play_game_room_info_arrays[$kk]);
                        $num++;
                    }
                }

                $onlineCountTotal += $game_info_list[$key]["onlineCount"];
                $userCountTotal += $game_info_list[$key]["userCount"];
                $gameRoundTotal += $game_info_list[$key]["gameRound"];

                $winTimesTotal += $game_info_list[$key]["winTimes"];
                $lostTimesTotal += $game_info_list[$key]["lostTimes"];
                $drawTimesTotal += $game_info_list[$key]["drawTimes"];

                $allBetTotal += $game_info_list[$key]["allBet"];
                $winScoreTotal += $game_info_list[$key]["winScore"];
                $platformWinScoreTotal += $game_info_list[$key]["platformWinScore"];
                $revenueTotal += $game_info_list[$key]["revenue"];
                $agentRevenueTotal += $game_info_list[$key]["agentRevenue"];

//        $playTimeTotal += $game_info_list[$key]["playTime"];

                $ptIncomeTotal += $game_info_list[$key]["ptIncome"];
            }

            foreach ($game_info_list as $key => $value) {
                $game_info_list[$key]["allBet"] = round($game_info_list[$key]["allBet"], 2);
                $game_info_list[$key]["winScore"] = round($game_info_list[$key]["winScore"], 2);
                $game_info_list[$key]["revenue"] = round($game_info_list[$key]["revenue"], 2);
                $game_info_list[$key]["agentRevenue"] = round($game_info_list[$key]["agentRevenue"], 2);
                $game_info_list[$key]["platformWinScore"] = round($game_info_list[$key]["platformWinScore"], 2);
                $game_info_list[$key]["ptIncome"] = round($game_info_list[$key]["ptIncome"], 2);


                if ($game_info_list[$key]["lostTimes"]) {
                    $game_info_list[$key]["lostWinRate"] = round($game_info_list[$key]["winTimes"] / $game_info_list[$key]["lostTimes"], 2);
                } else {
                    $game_info_list[$key]["lostWinRate"] = $game_info_list[$key]["winTimes"];
                }

                if ($ptIncomeTotal) {
                    if ($game_info_list[$key]["ptIncome"] > 0) {
                        $game_info_list[$key]["ptIncomeRate"] = round($game_info_list[$key]["ptIncome"] / abs($ptIncomeTotal), 2);
                    } else {
                        $game_info_list[$key]["ptIncomeRate"] = 0;
                    }
                } else {
                    $game_info_list[$key]["ptIncomeRate"] = 0;
                }
            }

            $tmparr = array(
                "pid" => 0,
                "id" => 999999,
                "title" => "总计",

                "onlineCount" => $onlineCountTotal,
                "userCount" => $userCountTotal,
                "gameRound" => $gameRoundTotal,

                "winTimes" => $winTimesTotal,
                "lostTimes" => $lostTimesTotal,
                "drawTimes" => $drawTimesTotal,
                "lostWinRate" => $lostTimesTotal ? round($winTimesTotal / $lostTimesTotal, 2) : 0,

                "allBet" => round($allBetTotal, 2),
                "winScore" => round($winScoreTotal, 2),//$winScoreTotal,
                "platformWinScore" => round($platformWinScoreTotal, 2),
                "revenue" => round($revenueTotal, 2),
                "agentRevenue" => round($agentRevenueTotal, 2),

//        "playTime" => $playTimeTotal,

//        "earnScore" => $onlineCountTotal,
                //"ptIncome" => round(($ptIncomeTotal)*0.01, 2),
                "ptIncome" => $this->formatMoneyFromMongoNo($ptIncomeTotal),
                "ptIncomeRate" => '/',
            );

            array_push($game_info_list, $tmparr);
            return json(['code' => 0, 'msg' => 'ok', 'count' => 0, 'data' => $game_info_list]);
        }


        $getData = check_type($request->all());
        extract($getData);
        $startDate = !empty($startDate) ? trim($startDate) : "";
        $endDate = !empty($endDate) ? trim($endDate) : "";
        $userId = !empty($userId) ? (int)$userId : '';

        $assignData = [
            'startDate'=>$startDate,
            'endDate'=>$endDate,
            'userId'=>$userId
        ];

//        $assignData = [
//            //'orderType' => ['leaveTime' => '结算时间', 'earnScore' => '平台营收', 'revenue' => '平台税收'],
//            //'gameList' => GameKind::where(['status' => 1, 'rooms.status' => 1])->orderBy('sort', 'asc')->get()->toArray(),
//        ];
        //dd($assignData['gameList']);
        return view('club/incomeStat/list', $assignData);
    }

    protected function getDirectMember($request,$where){
        $whereCon = [];
        $isLeaderCon = [];

        if(!empty($where['where']['clubId'])){
            $whereCon['clubId'] = $where['where']['clubId'];
        }

        if(!empty($where['where']['promoterId'])){
            //if(!empty($where['zhishu'])){
               // $whereCon['$expr'] = ['$eq'=>['$clubId','$userId']];
//                $whereCon['$or'] = [
//                    ['status'=>1],
//                    ['$expr'=>['$eq'=>['$clubId','$userId']]]
//                ];
            //}
            $isLeaderCon['promoterId'] = $where['where']['promoterId'];
            $isLeader = $this->ifLeader($isLeaderCon);
            if($isLeader == false){
                $idsArr = [];
                //$whereCon['directParent'] = $where['where']['promoterId'];
                //如果是合伙人,有可能下级依然是合伙人(下级的合伙人的directParent是自己),所以在这里要补下级合伙人的id
                //$promoterData = MyClubs::where('promoterId',$where['where']['promoterId'])->select('userId','clubId','status')->skip($request->skip)->take($request->limit)->get()->toArray();//备用
                $promoterData = MyClubs::where('promoterId',$where['where']['promoterId'])->select('userId','clubId','status')->get()->toArray();
                if(!empty($promoterData)){
                    foreach ($promoterData as $proDataVal){
                        $idsArr[] = $proDataVal['userId'];
                    }
                }
                array_push($idsArr,$where['where']['promoterId']);//加上合伙人自己
                $whereCon['userId'] = ['$in' => $idsArr];
            }else{
                $whereCon['promoterId'] = $where['where']['promoterId'];
            }
        }
        if(!empty($where['status'])){
            $whereCon['status'] = $where['status'];
            if($whereCon['status'] == 2 || $whereCon['status'] == 1){
                $whereCon['status'] = $whereCon['status'];
                if($whereCon['status'] == 2){
                    //$whereCon['clubId'] = ['$ne'=>'$userId'];
                    $whereCon['$expr'] = ['$ne'=>['$clubId','$userId']];
                }
            }elseif ($whereCon['status'] == 3){
                $whereCon['status'] = 2;
                $whereCon['$expr'] = ['$eq'=>['$clubId','$userId']];
            }
        }
        //$clubMembers = MyClubs::where($whereCon)->select('userId','clubId','status')->skip($request->skip)->take($request->limit)->get()->toArray();//备用
        $clubMembers = MyClubs::where($whereCon)->select('userId','clubId','status')->get()->toArray();
        return $clubMembers;
    }

    protected function getDirectMemberCount($where){
        $whereCon = [];
        $isLeaderCon = [];

        if(!empty($where['where']['clubId'])){
            $whereCon['clubId'] = $where['where']['clubId'];
        }

        if(!empty($where['where']['promoterId'])){
//            if(!empty($where['zhishu'])){
//                $whereCon['$or'] = [
//                    ['status'=>1],
//                    ['$expr'=>['$eq'=>['$clubId','$userId']]]
//                ];
//            }
            $isLeaderCon['promoterId'] = $where['where']['promoterId'];
            $isLeader = $this->ifLeader($isLeaderCon);
            if($isLeader == false){
                $idsArr = [];
                //$whereCon['directParent'] = $where['where']['promoterId'];
                //如果是合伙人,有可能下级依然是合伙人(下级的合伙人的directParent是自己),所以在这里要补下级合伙人的id
                $promoterData = MyClubs::where('promoterId',$where['where']['promoterId'])->select('userId','clubId','status')->get()->toArray();
                if(!empty($promoterData)){
                    foreach ($promoterData as $proDataVal){
                        $idsArr[] = $proDataVal['userId'];
                    }
                }
                array_push($idsArr,$where['where']['promoterId']);//加上合伙人自己
                $whereCon['userId'] = ['$in' => $idsArr];
            }else{
                $whereCon['promoterId'] = $where['where']['promoterId'];
            }
        }

        if(!empty($where['status'])){
            $whereCon['status'] = $where['status'];
            if($whereCon['status'] == 2 || $whereCon['status'] == 1){
                $whereCon['status'] = $whereCon['status'];
                if($whereCon['status'] == 2){
                    $whereCon['$expr'] = ['$ne'=>['$clubId','$userId']];
                }
            }elseif ($whereCon['status'] == 3){
                $whereCon['status'] = 2;
                $whereCon['$expr'] = ['$eq'=>['$clubId','$userId']];
            }
        }

        $count = MyClubs::where($whereCon)->count();
        return $count;
    }

    protected function ifLeader($where){
        $whereCon['userId'] = $where['promoterId'];
        $whereCon['status'] = 2;
        $clubMembers = MyClubs::where($whereCon)->get()->toArray();
        foreach ($clubMembers as $item){
            if($item['userId'] == $item['clubId']){
                return true;
            }
        }
        return false;
    }

    protected function getRecordListByCon($where,$skip=0,$limit=1,$type=1){
        if($type == 1){
            $list = Db::connection('mongodb_club')->collection('play_record')->raw()->aggregate([
                [
                    '$match' => $where['where']
                ],
                [
                    '$project' =>
                        [
                            'userId'=>1,
                            'clubId'=>1,
                            'gameId'=>1,
                            'roomId'=>1,
                            'allBet'=>1,
                            'validBet'=>1,
                            'winScore'=>1,
                            'platformWinScore' => 1,
                            'revenue' => 1,
                            'agentRevenue' => 1,
                            'playTime'=>1
                        ]
                ],
                [
                    '$group' =>
                        [
                            '_id' => $where['group'],
                            'gameRound' => ['$sum' => 1],
                            'allBet' => ['$sum' => '$allBet'],
                            'validBet' => ['$sum' => '$validBet'],
                            'winScore' => ['$sum' => '$winScore'],
                            'platformWinScore' => ['$sum' => '$platformWinScore'],
                            'revenue' => ['$sum' => '$revenue'],
                            'agentRevenue' => ['$sum' => '$agentRevenue'],
                            'playTime' => ['$sum' => '$playTime']
                        ]
                ],
                $where['options']
                ,
                ['$skip' =>$skip],
                ['$limit' =>$limit],
                [
                    '$project' =>
                        [
                            'userId'=>'$_id.userId',
                            'clubId'=>'$_id.clubId',
                            'gameId'=>'$_id.gameId',
                            'roomId'=>'$_id.roomId',
                            'allBet'=>1,
                            'validBet'=>1,
                            'gameRound'=>1,
                            'winScore'=>1,
                            'platformWinScore' => 1,
                            'revenue' => 1,
                            'agentRevenue' => 1,
                            'playTime'=>1
                        ]
                ]
            ])->toArray();
        }else{
            $list = Db::connection('mongodb_club')->collection('play_record')->raw()->aggregate([
                [
                    '$match' => $where['where']
                ],
                [
                    '$project' =>
                        [
                            'userId'=>1,
                            'clubId'=>1,
                            'gameId'=>1,
                            'roomId'=>1,
                            'allBet'=>1,
                            'validBet'=>1,
                            'winScore'=>1,
                            'platformWinScore' => 1,
                            'revenue' => 1,
                            'agentRevenue' => 1,
                            'playTime'=>1
                        ]
                ],
                [
                    '$group' =>
                        [
                            '_id' => $where['group'],
                            'gameRound' => ['$sum' => 1],
                            'allBet' => ['$sum' => '$allBet'],
                            'validBet' => ['$sum' => '$validBet'],
                            'winScore' => ['$sum' => '$winScore'],
                            'platformWinScore' => ['$sum' => '$platformWinScore'],
                            'revenue' => ['$sum' => '$revenue'],
                            'agentRevenue' => ['$sum' => '$agentRevenue'],
                            'playTime' => ['$sum' => '$playTime']
                        ]
                ],
                $where['options']
                ,
                [
                    '$project' =>
                        [
                            'userId'=>'$_id.userId',
                            'clubId'=>'$_id.clubId',
                            'gameId'=>'$_id.gameId',
                            'roomId'=>'$_id.roomId',
                            'allBet'=>1,
                            'validBet'=>1,
                            'gameRound'=>1,
                            'winScore'=>1,
                            'platformWinScore' => 1,
                            'revenue' => 1,
                            'agentRevenue' => 1,
                            'playTime'=>1
                        ]
                ]
            ])->toArray();
        }

        return $list;
    }

    protected function getRecordCountByCon($where){
        $listCount = Db::connection('mongodb_club')->collection('play_record')->raw()->aggregate([
            [
                '$match' => $where['where']
            ],
            [
                '$project' =>
                    [
                        'userId'=>1,
                        'clubId'=>1,
                        'gameId'=>1,
                        'roomId'=>1,
                        'allBet'=>1,
                        'validBet'=>1,
                        'winScore'=>1,
                        'platformWinScore' => 1,
                        'revenue' => 1,
                        'agentRevenue' => 1,
                        'playTime'=>1
                    ]
            ],
            [
                '$group' =>
                    [
                        '_id' => $where['group'],
                        'gameRound' => ['$sum' => 1],
                        'allBet' => ['$sum' => '$allBet'],
                        'validBet' => ['$sum' => '$validBet'],
                        'winScore' => ['$sum' => '$winScore'],
                        'platformWinScore' => ['$sum' => 'platformWinScore'],
                        'revenue' => ['$sum' => '$revenue'],
                        'agentRevenue' => ['$sum' => '$agentRevenue'],
                        'playTime' => ['$sum' => '$playTime']
                    ]
            ],
            $where['options']
            ,
            [
                '$skip' => 0
            ],
            [
                '$project' =>
                    [
                        'userId'=>'$_id.userId',
                        'clubId'=>'$_id.clubId',
                        'gameId'=>'$_id.gameId',
                        'roomId'=>'$_id.roomId',
                        'allBet'=>1,
                        'validBet'=>1,
                        'gameRound'=>1,
                        'winScore'=>1,
                        'platformWinScore' => 1,
                        'revenue' => 1,
                        'agentRevenue' => 1,
                        'playTime'=>1
                    ]
            ]
        ])->toArray();
        return $listCount;
    }

    protected function getUserIdsByCon($where){
        $list = Db::connection('mongodb_club')->collection('play_record')->raw()->aggregate([
            [
                '$match' => $where['where']
            ],
            [
                '$project' =>
                    [
                        'userId'=>1
                    ]
            ],
            [
                '$group' =>
                    [
                        '_id' => $where['group']
                    ]
            ],
            $where['options']
            ,
            [
                '$project' =>
                    [
                        'userId'=>'$_id.userId'
                    ]
            ]
        ])->toArray();
        return $list;
    }

    public function clubGameCon(Request $request)
    {
        $getData = check_type($request->all());
        $hundredGame = [];
        $whereHun['status'] = 1;
        $whereHun['type'] = 0;
        $gameData = GameKind::where($whereHun)->orderBy('sort', 'asc')->select('gameId','gameName')->get()->toArray();
        foreach ($gameData as $value){
            $hundredGame[$value['gameId']] = $value['gameName'];
        }
        $keysHun = array_keys($hundredGame);
        if ($request->isAjax()) {
            if($getData['clubId'] == 1000){
                return json(['code' => -1, 'msg' => '盟主ID为1000不允许修改!!!']);
            }
            $games = [];
            if(!empty($getData['game'])){
                foreach ($getData['game'] as $k => $gameItem){
                    $aa['gameId'] = (int)$gameItem;
                    if(!empty($getData['robot'][$k])){
                        $aa['enableAndroid'] = 1;
                    }else{
                        $aa['enableAndroid'] = 0;
                    }
                    $games[] = $aa;
                }
            }
            if(empty($games)){
                return json(['code' => -1, 'msg' => '修改失败']);
            }

            foreach ($games as $item){
                if(in_array($item['gameId'],$keysHun)){
                    if($item['enableAndroid'] == 0){
                        return json(['code' => -1, 'msg' => '修改失败,'.$hundredGame[$item['gameId']].'为百人场游戏,机器人必须开启!!!']);
                    }
                }
            }

            $updateData = [
                'games' => $games
            ];
            $updateResult = ClubMo::where('clubId', $getData['clubId'])->update($updateData);
            if (!$updateResult) return json(['code' => -1, 'msg' => '修改失败']);
            return json(['code' => 0, 'msg' => '修改成功']);
        }
        //获取所有游戏列表
        $where['status'] = 1;
        $gameList = GameKind::where($where)->orderBy('sort', 'asc')->get()->toArray();
        //获取当前俱乐部游配置
        $clubsGameCon = ClubMo::where(['clubId' => $getData['clubId']])->select('games')->get()->toArray();
        if(!empty($clubsGameCon[0]['games'])){
            foreach ($gameList as $k =>$v){
                foreach ($clubsGameCon[0]['games'] as $vv){
                    if($vv['gameId'] == $v['gameId']){
                        $gameList[$k]['gameChecked'] = 1;
                        $gameList[$k]['enableAndroid'] = $vv['enableAndroid'];
                    }
                }
            }
        }
        $assignData = [
            'clubId' => $getData['clubId'],
            'gameList' => $gameList,
            'keysHun' => $keysHun
        ];
        return view('club/clubGameCon/add', $assignData);
    }

    public function onlineList(Request $request)
    {
        if ($request->isAjax()) {
            $where = $where1 = [];
            $getData = check_type($request->all());
            extract($getData);
            if (!empty($roomId)) {
                $where['roomId'] = $roomId;
                $where1['roomId'] = $roomId;
            } else {
                $where['roomId'] = ['$gt' => 0];
                $where1[] = ['roomId', '>', 0];
            }
            $order = ['user.regInfo.time' => 1];
            if (!empty($orderType)) {
                $order = match ($orderType) {
                    '1' => ['user.regInfo.ip' => 1],
                    '2' => ['user.lastLogin.ip' => 1],
                    '3' => ['user.promoterId' => 1],
                };
            }

            $count = ClubUserOnline::where($where1)->count();

            $users = Db::connection('mongodb_club')->collection('user_online')->raw()->aggregate([
                [
                    '$match'=> $where
                ],
                [
                    '$project' =>
                        [
                            'userId' => 1
                        ]
                ],
                [
                    '$sort' => $order,
                ],
                [
                    '$skip' => $request->skip
                ],
                [
                    '$limit' => $request->limit
                ]
            ])->toArray();
            //关联user表数据
            $userIdArr = [];
            foreach ($users as $value){
                $userIdArr[] = $value['userId'];
            }
            $userData = GameUser::where(['userId' => ['$in'=>$userIdArr]])->get()->toArray();
            $users = merge_array($users, $userData, 'userId');
            $promoterIdArr = array_column($users, 'promoterId'); $promoterIdArr = array_unique($promoterIdArr);
            $promoterList = Promoter::whereIn('promoterId', $promoterIdArr)->select('promoterId','promoterName')->get()->toArray();
            $list = merge_array($users, $promoterList, 'promoterId');

            $userIdArr = array_column($users, 'userId'); $userIdArr = array_unique($userIdArr);
            $startDate = date("Y-m-d");
            $startTime = strtotime($startDate);
            $endTime = strtotime("$startDate +1 day");
            $where3 = ["userId" => ['$in' => $userIdArr]];
            $where3['endTime'] = ['$gte' => $this->formatTimestampToMongo($startTime), '$lt' => $this->formatTimestampToMongo($endTime)];
            $data = Db::connection('mongodb_club')->collection('play_record')->raw()->aggregate([
                [
                    '$match' => $where3
                ],
                [
                    '$project' =>
                        [
                            'userId'=>1,
                            'clubId'=>1,
                            'gameId'=>1,
                            'roomId'=>1,
                            'allBet'=>1,
                            'validBet'=>1,
                            'winScore'=>1,
                            'winLostScore'=>1,
                            'platformWinScore' => 1,
                            'revenue' => 1,
                            'agentRevenue' => 1,
                            'playTime'=>1
                        ]
                ],
                [
                    '$group' =>
                        [
                            '_id' => ['userId' => '$userId'],
                            'gameRound' => ['$sum' => 1],
                            'allBet' => ['$sum' => '$allBet'],
                            'validBet' => ['$sum' => '$validBet'],
                            'winScore' => ['$sum' => '$winScore'],
                            'winLostScore' => ['$sum' => '$winLostScore'],
                            'platformWinScore' => ['$sum' => '$platformWinScore'],
                            'revenue' => ['$sum' => '$revenue'],
                            'agentRevenue' => ['$sum' => '$agentRevenue'],
                            'playTime' => ['$sum' => '$playTime']
                        ]
                ],
                [
                    '$project' =>
                        [
                            'userId'=>'$_id.userId',
                            'clubId'=>'$_id.clubId',
                            'gameId'=>'$_id.gameId',
                            'roomId'=>'$_id.roomId',
                            'allBet'=>1,
                            'validBet'=>1,
                            'gameRound'=>1,
                            'winScore'=>1,
                            'winLostScore'=>1,
                            'platformWinScore' => 1,
                            'revenue' => 1,
                            'agentRevenue' => 1,
                            'playTime'=>1
                        ]
                ]
            ])->toArray();

            $ipLocation = new IpLocation();
            foreach ($list as &$item) {
                $item['score'] = $this->formatMoneyFromMongo($item['score']);
                $item['bankScore'] = $this->formatMoneyFromMongo($item['bankScore']);
                $item['rechargeAmount'] = $this->formatMoneyFromMongo($item['rechargeAmount']);
                $item['exchangeAmount'] = $this->formatMoneyFromMongo($item['exchangeAmount']);
                $item['revenue'] = $this->formatMoneyFromMongo($item['revenue']);
                $item['winScore'] = $this->formatMoneyFromMongo($item['winScore']);

                $item['regInfoTime'] = $this->formatDate($item['regInfo']['time']);
                $item['lastLoginTime'] = $this->formatDate($item['lastLogin']['time']);
                $item['regInfoMobileType'] = $item['regInfo']['mobileType'];
                $item['totalOnlineGameTime'] = (int)$item['totalOnlineGameTime'];
                $item['totalOnlineGameTime'] = Sec2Time($item['totalOnlineGameTime']);

                $location = $ipLocation->getlocation($item['regInfo']['ip']);
                $item['regInfo']['address'] = $location['country'] . $location['area'];
                $location = $ipLocation->getlocation($item['lastLogin']['ip']);
                $item['lastLogin']['address'] = $location['country'] . $location['area'];
                foreach ($data as $playRecordData) {
                    if ($item['userId'] == $playRecordData['userId']) {
                        $item['todayPtIncome'] = $this->formatMoneyFromMongo($playRecordData['platformWinScore'] + $playRecordData['revenue']);
                        $item['todayAllBet'] = $this->formatMoneyFromMongo($playRecordData['allBet']);
                        $item['todayValidBet'] = $this->formatMoneyFromMongo($playRecordData['validBet']);
                        $item['todayWinScore'] = $this->formatMoneyFromMongo($playRecordData['winScore']);
                        $item['todayPlatformWinScore'] = $this->formatMoneyFromMongo($playRecordData['platformWinScore']);
                        $item['todayRevenue'] = $this->formatMoneyFromMongo($playRecordData['revenue']);
                        $item['todayWinLostScore'] = $this->formatMoneyFromMongo($playRecordData['winLostScore']);
                    }
                }
            }

            return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $list]);
        }
        $assignData = [
            'isPay' => ISPAY,
            'isActive' => ISACTIVE,
            'isNormal' => ISNORMAL,
            'isSys' => GameUser::ACCOUNT_CLASSIFY,
        ];
        return view('club/online/list', $assignData);
    }

    public function onlineRoom(Request $request)
    {
        $roomList = Db::connection('mongodb_club')->collection('user_online')->raw()->aggregate([
            [
                '$match'=>
                    [
                        'roomId'=>['$gt'=>0]
                    ]
            ],
            [
                '$project' =>
                    [
                        'roomId' => 1,
                    ]
            ],
            [
                '$group' =>
                    [
                        '_id' => '$roomId',
                        'count' => ['$sum' => 1]
                    ]
            ],
            [
                '$sort' => ['_id' => 1]
            ],
            [
                '$project' =>
                    [
                        'roomId' => '$_id',
                        'count'=>1
                    ]
            ]
        ])->toArray();
        $gameRoomInfo = getClubGameRoomInfo();
        $roomList = merge_array($roomList, $gameRoomInfo, 'roomId');
        return json(['code' => 0, 'msg' => 'ok', 'count' => 0, 'data' => $roomList]);
    }
}
