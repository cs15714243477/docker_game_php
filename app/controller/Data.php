<?php
namespace app\controller;

use app\model\ClubPlatformDataRecord;
use app\model\ClubStatPromoterDaily;
use app\model\CoinPlatformDataRecord;
use app\model\GameUser;
use app\model\Promoter;
use app\model\RechargeOrder;
use app\model\StatPromoterDaily;
use support\bootstrap\Container;
use support\Db;
use support\Request;

class Data extends Base
{
    public function index(Request $request)
    {
        $act = $request->get('act');
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

    public function chargeBill(Request $request)
    {
        return view('data/chargeBill/list', ['name' => '']);
    }
    public function rechargeRank(Request $request)
    {
        return view('data/rechargeRank/list', ['name' => '']);
    }
    public function agentAnalysis(Request $request)
    {
        return view('data/agentAnalysis/list', ['name' => '']);
    }
    public function userAnalysis(Request $request)
    {
        $assignData = [
            'startDate'=>'',
            'endDate'=>'',
            'promoterId'=>''
        ];
        return view('data/userAnalysis/list', $assignData);
    }

    public function overView(Request $request)
    {
        $assignData = [
            'gameList' => getGameInfoGameRoomInfo(),
        ];
        return view('data/overView/list', $assignData);
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
        $sro_user_list = Db::connection('mongodb_main')->collection('stat_room_online')->raw()->aggregate([
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
            $count_array = Db::connection('mongodb_main')->collection('stat_room_online')->raw()->aggregate([
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
            $stat_room_online_list = Db::connection('mongodb_main')->collection('stat_room_online')->raw()->aggregate([
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
        return view('data/onlineView/list', $assignData);
    }

    public function regTime(Request $request)
    {
        if ($request->isAjax()) {
            $where = [];
            $getData = $request->get();
            extract($getData);
            if (empty($startDate)) $startDate = date("Y-m-d");
            if (empty($endDate)) $endDate = date("Y-m-d");
            $startTime = strtotime($startDate);
            $endTime = strtotime("$endDate +1 day");
            if (!empty($promoterId)) $where['promoterId'] = (int)$promoterId;
            $where['regInfo.time'] = ['$gte' => $this->formatTimestampToMongo($startTime), '$lt' => $this->formatTimestampToMongo($endTime)];
            $count_array = Db::connection('mongodb_main')->collection('game_user')->raw()->aggregate([
                [
                    '$match' => $where
                ],
                [
                    '$match' => [
                        'userId' => [
                            '$gte' => 10000000,
                        ]
                    ]
                ],
                [
                    '$project' =>
                        [
                            'promoterId' => 1,
                            'regInfo' => 1,
                            'Date' => ['$substr' => [['$add' => ['$regInfo.time', 28800000]], 0, 10]]
                        ]
                ],
                [
                    '$group' =>
                        [
                            '_id' => ['promoterId' => '$promoterId', 'Date' => '$Date'],
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
            $reg_user_list = Db::connection('mongodb_main')->collection('game_user')->raw()->aggregate([
                [
                    '$match' => $where
                ],
                [
                    '$match' => [
                        'userId' => [
                            '$gte' => 10000000,
                        ]
                    ]
                ],
                [
                    '$project' =>
                        [
                            'promoterId' => 1,
                            'Date' => ['$substr' => [['$add' => ['$regInfo.time', 28800000]], 0, 10]],
//                    'DayHour' => ['$substr' => ['$regInfo.time', 0, 13]],
                            'Hour' => ['$hour' => ['$add' =>['$regInfo.time', 28800000]]]
                        ]
                ],
                [
                    '$group' =>
                        [
                            '_id' => ['promoterId' => '$promoterId', 'Date' => '$Date'],
                            'H00' => ['$sum' => ['$cond' => ['if' => ['$eq' => ['$Hour', 0]], 'then' => 1, 'else' => 0]]],
                            'H01' => ['$sum' => ['$cond' => ['if' => ['$eq' => ['$Hour', 1]], 'then' => 1, 'else' => 0]]],
                            'H02' => ['$sum' => ['$cond' => ['if' => ['$eq' => ['$Hour', 2]], 'then' => 1, 'else' => 0]]],
                            'H03' => ['$sum' => ['$cond' => ['if' => ['$eq' => ['$Hour', 3]], 'then' => 1, 'else' => 0]]],
                            'H04' => ['$sum' => ['$cond' => ['if' => ['$eq' => ['$Hour', 4]], 'then' => 1, 'else' => 0]]],
                            'H05' => ['$sum' => ['$cond' => ['if' => ['$eq' => ['$Hour', 5]], 'then' => 1, 'else' => 0]]],
                            'H06' => ['$sum' => ['$cond' => ['if' => ['$eq' => ['$Hour', 6]], 'then' => 1, 'else' => 0]]],
                            'H07' => ['$sum' => ['$cond' => ['if' => ['$eq' => ['$Hour', 7]], 'then' => 1, 'else' => 0]]],
                            'H08' => ['$sum' => ['$cond' => ['if' => ['$eq' => ['$Hour', 8]], 'then' => 1, 'else' => 0]]],
                            'H09' => ['$sum' => ['$cond' => ['if' => ['$eq' => ['$Hour', 9]], 'then' => 1, 'else' => 0]]],
                            'H10' => ['$sum' => ['$cond' => ['if' => ['$eq' => ['$Hour', 10]], 'then' => 1, 'else' => 0]]],
                            'H11' => ['$sum' => ['$cond' => ['if' => ['$eq' => ['$Hour', 11]], 'then' => 1, 'else' => 0]]],
                            'H12' => ['$sum' => ['$cond' => ['if' => ['$eq' => ['$Hour', 12]], 'then' => 1, 'else' => 0]]],
                            'H13' => ['$sum' => ['$cond' => ['if' => ['$eq' => ['$Hour', 13]], 'then' => 1, 'else' => 0]]],
                            'H14' => ['$sum' => ['$cond' => ['if' => ['$eq' => ['$Hour', 14]], 'then' => 1, 'else' => 0]]],
                            'H15' => ['$sum' => ['$cond' => ['if' => ['$eq' => ['$Hour', 15]], 'then' => 1, 'else' => 0]]],
                            'H16' => ['$sum' => ['$cond' => ['if' => ['$eq' => ['$Hour', 16]], 'then' => 1, 'else' => 0]]],
                            'H17' => ['$sum' => ['$cond' => ['if' => ['$eq' => ['$Hour', 17]], 'then' => 1, 'else' => 0]]],
                            'H18' => ['$sum' => ['$cond' => ['if' => ['$eq' => ['$Hour', 18]], 'then' => 1, 'else' => 0]]],
                            'H19' => ['$sum' => ['$cond' => ['if' => ['$eq' => ['$Hour', 19]], 'then' => 1, 'else' => 0]]],
                            'H20' => ['$sum' => ['$cond' => ['if' => ['$eq' => ['$Hour', 20]], 'then' => 1, 'else' => 0]]],
                            'H21' => ['$sum' => ['$cond' => ['if' => ['$eq' => ['$Hour', 21]], 'then' => 1, 'else' => 0]]],
                            'H22' => ['$sum' => ['$cond' => ['if' => ['$eq' => ['$Hour', 22]], 'then' => 1, 'else' => 0]]],
                            'H23' => ['$sum' => ['$cond' => ['if' => ['$eq' => ['$Hour', 23]], 'then' => 1, 'else' => 0]]],
                            'regTotal' => ['$sum' => 1]
                        ]
                ],
                [
                    '$sort' => ['_id.Date' => -1, 'regTotal' => -1, '_id.promoterId' => 1]
                ],
                [
                    '$skip' => $request->skip
                ],
                [
                    '$limit' => $request->limit
                ],
                [
                    '$lookup' =>
                        [
                            'from' => 'promoter',
                            'localField' => '_id.promoterId',
                            'foreignField' => 'promoterId',
                            'as' => 'promoters'
                        ]
                ],
//        [
//            '$unwind' => '$promoters'
//        ],
                [
                    '$project' =>
                        [
                            'promoterId' => '$_id.promoterId',
                            'Date' => '$_id.Date',
//                    'DayHour'=>'$_id.DayHour',
//                    'promoterName' => '$promoters.promoterName',
                            'H00' => 1,
                            'H01' => 1,
                            'H02' => 1,
                            'H03' => 1,
                            'H04' => 1,
                            'H05' => 1,
                            'H06' => 1,
                            'H07' => 1,
                            'H08' => 1,
                            'H09' => 1,
                            'H10' => 1,
                            'H11' => 1,
                            'H12' => 1,
                            'H13' => 1,
                            'H14' => 1,
                            'H15' => 1,
                            'H16' => 1,
                            'H17' => 1,
                            'H18' => 1,
                            'H19' => 1,
                            'H20' => 1,
                            'H21' => 1,
                            'H22' => 1,
                            'H23' => 1,
                            'regTotal' => 1
                        ]
                ],
            ])->toArray();
            $promoters = Promoter::all(['promoterId','promoterName'])->toArray();
            $reg_user_list = merge_array($reg_user_list, $promoters, 'promoterId');

            return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $reg_user_list]);
        }

        $assignData = [];
        return view('data/regTime/list', $assignData);
    }

    public function rechargeTime(Request $request)
    {
        if ($request->isAjax()) {
            $where = [];
            $getData = $request->get();
            extract($getData);
            if (empty($startDate)) $startDate = date("Y-m-d");
            if (empty($endDate)) $endDate = date("Y-m-d");
            $startTime = strtotime($startDate);
            $endTime = strtotime("$endDate +1 day");
            $where['status'] = RechargeOrder::ORDER_STATUS_FINISH;
            $where['userId'] = ['$gte' => GameUser::COMMON_ACCOUNT_START_ID];
            $where['applyTime'] = ['$gte' => $this->formatTimestampToMongo($startTime), '$lt' => $this->formatTimestampToMongo($endTime)];
            $count_array = Db::connection('mongodb_main')->collection('recharge_order')->raw()->aggregate([
                [
                    '$match' => $where
                ],
                [
                    '$project' =>
                        [
                            //'Date' => ['$substr' => ['$applyTime', 0, 10]],
                            'Date' => ['$substr' => [['$add' => ['$applyTime', 28800000]], 0, 10]],
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
            $recharge_order_list = Db::connection('mongodb_main')->collection('recharge_order')->raw()->aggregate([
                [
                    '$match' => $where
                ],
                [
                    '$project' =>
                        [
                            'Date' => ['$substr' => [['$add' => ['$applyTime', 28800000]], 0, 10]],
                            //'Date' => ['$substr' => ['$applyTime', 0, 10]],
                            'Hour' => ['$hour' => ['$add' =>['$applyTime', 28800000]]],
                            //'Hour' => ['$hour' => '$applyTime'],
                            'rechargeMoney'=>1
                        ]
                ],
                [
                    '$group' =>
                        [
                            '_id' => '$Date',
                            'H00' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 0]], 'then'=>'$rechargeMoney', 'else'=>0]]],
                            'H01' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 1]], 'then'=>'$rechargeMoney', 'else'=>0]]],
                            'H02' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 2]], 'then'=>'$rechargeMoney', 'else'=>0]]],
                            'H03' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 3]], 'then'=>'$rechargeMoney', 'else'=>0]]],
                            'H04' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 4]], 'then'=>'$rechargeMoney', 'else'=>0]]],
                            'H05' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 5]], 'then'=>'$rechargeMoney', 'else'=>0]]],
                            'H06' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 6]], 'then'=>'$rechargeMoney', 'else'=>0]]],
                            'H07' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 7]], 'then'=>'$rechargeMoney', 'else'=>0]]],
                            'H08' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 8]], 'then'=>'$rechargeMoney', 'else'=>0]]],
                            'H09' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 9]], 'then'=>'$rechargeMoney', 'else'=>0]]],
                            'H10' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 10]], 'then'=>'$rechargeMoney', 'else'=>0]]],
                            'H11' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 11]], 'then'=>'$rechargeMoney', 'else'=>0]]],
                            'H12' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 12]], 'then'=>'$rechargeMoney', 'else'=>0]]],
                            'H13' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 13]], 'then'=>'$rechargeMoney', 'else'=>0]]],
                            'H14' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 14]], 'then'=>'$rechargeMoney', 'else'=>0]]],
                            'H15' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 15]], 'then'=>'$rechargeMoney', 'else'=>0]]],
                            'H16' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 16]], 'then'=>'$rechargeMoney', 'else'=>0]]],
                            'H17' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 17]], 'then'=>'$rechargeMoney', 'else'=>0]]],
                            'H18' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 18]], 'then'=>'$rechargeMoney', 'else'=>0]]],
                            'H19' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 19]], 'then'=>'$rechargeMoney', 'else'=>0]]],
                            'H20' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 20]], 'then'=>'$rechargeMoney', 'else'=>0]]],
                            'H21' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 21]], 'then'=>'$rechargeMoney', 'else'=>0]]],
                            'H22' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 22]], 'then'=>'$rechargeMoney', 'else'=>0]]],
                            'H23' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$Hour', 23]], 'then'=>'$rechargeMoney', 'else'=>0]]],
                            'totalRechargeAmount' => ['$sum' => '$rechargeMoney'],
                            'totalNum' => ['$sum'=>1]
                        ]
                ],
                [
                    '$project' =>
                        [
                            'Date'=>'$_id',
                            'H00'=>1,
                            'H01'=>1,
                            'H02'=>1,
                            'H03'=>1,
                            'H04'=>1,
                            'H05'=>1,
                            'H06'=>1,
                            'H07'=>1,
                            'H08'=>1,
                            'H09'=>1,
                            'H10'=>1,
                            'H11'=>1,
                            'H12'=>1,
                            'H13'=>1,
                            'H14'=>1,
                            'H15'=>1,
                            'H16'=>1,
                            'H17'=>1,
                            'H18'=>1,
                            'H19'=>1,
                            'H20'=>1,
                            'H21'=>1,
                            'H22'=>1,
                            'H23'=>1,
                            'totalRechargeAmount' => 1,
                            'totalNum'=>1
                        ]
                ],
                [
                    '$sort' => ['Date'=>-1]
                ],
                [
                    '$skip' => $request->skip
                ],
                [
                    '$limit' => $request->limit
                ]
            ])->toArray();
            foreach ($recharge_order_list AS $key => $val) {
                $recharge_order_list[$key]['H00'] = round($recharge_order_list[$key]['H00']*0.01, 2);
                $recharge_order_list[$key]['H01'] = round($recharge_order_list[$key]['H01']*0.01, 2);
                $recharge_order_list[$key]['H02'] = round($recharge_order_list[$key]['H02']*0.01, 2);
                $recharge_order_list[$key]['H03'] = round($recharge_order_list[$key]['H03']*0.01, 2);
                $recharge_order_list[$key]['H04'] = round($recharge_order_list[$key]['H04']*0.01, 2);
                $recharge_order_list[$key]['H05'] = round($recharge_order_list[$key]['H05']*0.01, 2);
                $recharge_order_list[$key]['H06'] = round($recharge_order_list[$key]['H06']*0.01, 2);
                $recharge_order_list[$key]['H07'] = round($recharge_order_list[$key]['H07']*0.01, 2);
                $recharge_order_list[$key]['H08'] = round($recharge_order_list[$key]['H08']*0.01, 2);
                $recharge_order_list[$key]['H09'] = round($recharge_order_list[$key]['H09']*0.01, 2);
                $recharge_order_list[$key]['H10'] = round($recharge_order_list[$key]['H10']*0.01, 2);
                $recharge_order_list[$key]['H11'] = round($recharge_order_list[$key]['H11']*0.01, 2);
                $recharge_order_list[$key]['H12'] = round($recharge_order_list[$key]['H12']*0.01, 2);
                $recharge_order_list[$key]['H13'] = round($recharge_order_list[$key]['H13']*0.01, 2);
                $recharge_order_list[$key]['H14'] = round($recharge_order_list[$key]['H14']*0.01, 2);
                $recharge_order_list[$key]['H15'] = round($recharge_order_list[$key]['H15']*0.01, 2);
                $recharge_order_list[$key]['H16'] = round($recharge_order_list[$key]['H16']*0.01, 2);
                $recharge_order_list[$key]['H17'] = round($recharge_order_list[$key]['H17']*0.01, 2);
                $recharge_order_list[$key]['H18'] = round($recharge_order_list[$key]['H18']*0.01, 2);
                $recharge_order_list[$key]['H19'] = round($recharge_order_list[$key]['H19']*0.01, 2);
                $recharge_order_list[$key]['H20'] = round($recharge_order_list[$key]['H20']*0.01, 2);
                $recharge_order_list[$key]['H21'] = round($recharge_order_list[$key]['H21']*0.01, 2);
                $recharge_order_list[$key]['H22'] = round($recharge_order_list[$key]['H22']*0.01, 2);
                $recharge_order_list[$key]['H23'] = round($recharge_order_list[$key]['H23']*0.01, 2);
                $recharge_order_list[$key]['totalRechargeAmount'] = round($recharge_order_list[$key]['totalRechargeAmount']*0.01, 2);
            }

            return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $recharge_order_list]);
        }

        $assignData = [];
        return view('data/rechargeTime/list', $assignData);
    }

    public function operateData(Request $request){
        if ($request->isAjax()) {
            $getData = $request->get();
            extract($getData);
            if (empty($startDate)) $startDate = date("Y-m-d", strtotime('-7 day'));
            if (empty($endDate)) $endDate = date("Y-m-d");
            if ((strtotime($endDate) - strtotime($startDate)) > 7*24*3600) return json(['code' => -1, 'msg' => '一次最多只能查询7天数据']);


            $date = $this->getDateFromRange($startDate, $endDate);
            /*$promoterDailyData = $this->promoterDailyData($startDate, $endDate);
            $platformDailyData = $this->platformDailyData($startDate, $endDate);
            $clubPlatformDailyData = $this->clubPlatformDailyData($startDate, $endDate);
            $clubPromoterDailyData = $this->clubPromoterDailyData($startDate, $endDate);*/

            //金币场 1000代理统计一天一条数据
            $promoterDailyData = StatPromoterDaily::getDataByDate($startDate, $endDate);
            $platformDailyData = CoinPlatformDataRecord::getDataByDate($startDate, $endDate);
            //俱乐部 -1000
            $clubPromoterDailyData = ClubStatPromoterDaily::getDataByDate($startDate, $endDate);
            $clubPlatformDailyData = ClubPlatformDataRecord::getDataByDate($startDate, $endDate);

            $data = [];
            foreach ($date as $day) {
                $tempArr = [];
                $tempArr['date'] = $day;
                if (!isset($promoterDailyData[$day]) || !isset($platformDailyData) || !isset($clubPromoterDailyData) || !isset($clubPlatformDailyData)) {
                    $tempArr['teamRegPeople'] = $tempArr['teamRegBindPeople'] = $tempArr['teamRegValidNewBetPeople'] = $tempArr['todayRechargeAmount'] = $tempArr['todayExchangeAmount'] = $tempArr['todayRechargeTimes'] = $tempArr['todayExchangeTimes'] = 0;
                    $tempArr['platformWinScore'] = $tempArr['todayAllBetScore'] = 0;
                    $tempArr['todayValidBetScore'] = $tempArr['teamProfit'] = $tempArr['revenue'] = $tempArr['pureRevenue'] = $tempArr['platformProfit'] = $tempArr['rewardScore'] = 0;
                } else {
                    $tempArr['teamRegPeople'] = $promoterDailyData[$day]['teamRegPeople']??0;
                    $tempArr['teamRegBindPeople'] = $promoterDailyData[$day]['teamRegBindPeople']??0;
                    $tempArr['teamRegValidNewBetPeople'] = $promoterDailyData[$day]['teamRegValidNewBetPeople']??0;
                    $tempArr['todayRechargeAmount'] = $platformDailyData[$day]['todayRechargeAmount']??0;
                    $tempArr['todayExchangeAmount'] = $platformDailyData[$day]['todayExchangeAmount']??0;
                    $tempArr['todayRechargeTimes'] = $platformDailyData[$day]['todayRechargeTimes']??0;
                    $tempArr['todayExchangeTimes'] = $platformDailyData[$day]['todayExchangeTimes']??0;
                    //官方游戏输赢
                    $tempArr['platformWinScore'] = dataSummary($promoterDailyData[$day], $clubPromoterDailyData[$day], $platformDailyData[$day], $clubPlatformDailyData[$day], 'gameWinScore');
                    //用户下注流水
                    $tempArr['todayAllBetScore'] = dataSummary($promoterDailyData[$day], $clubPromoterDailyData[$day], $platformDailyData[$day], $clubPlatformDailyData[$day], 'allBet');
                    //用户有效下注流水
                    $tempArr['todayValidBetScore'] = dataSummary($promoterDailyData[$day], $clubPromoterDailyData[$day], $platformDailyData[$day], $clubPlatformDailyData[$day], 'vaildBet');
                    //代理提成
                    $tempArr['teamProfit'] = dataSummary($promoterDailyData[$day], $clubPromoterDailyData[$day], $platformDailyData[$day], $clubPlatformDailyData[$day], 'promoterScore');
                    //系统税收
                    $tempArr['revenue'] = dataSummary($promoterDailyData[$day], $clubPromoterDailyData[$day], $platformDailyData[$day], $clubPlatformDailyData[$day], 'revenue');
                    //税收纯利润
                    $tempArr['pureRevenue'] = dataSummary($promoterDailyData[$day], $clubPromoterDailyData[$day], $platformDailyData[$day], $clubPlatformDailyData[$day], 'taxProfit');
                    //官方盈亏
                    $tempArr['platformProfit'] = dataSummary($promoterDailyData[$day], $clubPromoterDailyData[$day], $platformDailyData[$day], $clubPlatformDailyData[$day], 'profitOrLoss');
                    //奖励金额
                    $tempArr['rewardScore'] = dataSummary($promoterDailyData[$day], $clubPromoterDailyData[$day], $platformDailyData[$day], $clubPlatformDailyData[$day], 'rewardScore');
                }
                $data[] = $tempArr;
            }
            return json(['code' => 0, 'msg' => 'ok', 'count' => 0,'data' => $data]);
        }

        $assignData = [];
        return view('data/operateData/list', $assignData);
    }

    private function promoterDailyData($startDate,$endDate)
    {
        $startTime = strtotime(trim($startDate));
        $endTime = strtotime(trim($endDate)) + 86400;
        $startTimeMongo = $this->formatTimestampToMongo($startTime);
        $endTimeMongo = $this->formatTimestampToMongo($endTime);

        $where = [];
        $where['promoterId'] = 1000;
        $where['date'] = ['$gte' => $startTimeMongo, '$lt' => $endTimeMongo];

        $data_array = Db::connection('mongodb_main')->collection('stat_promoter_daily')->raw()->aggregate([
            [
                '$match' => $where
            ],
            [
                '$project' =>
                    [
                        '_id' => 0,
                        //'Date' => ['$substr' => [ '$date', 0, 10]],
                        'Date' => ['$substr' => [['$add' => ['$date',28800000]], 0, 10]],
                        'teamRevenue' => 1,
                        'teamProfit' => 1,
                        'teamExchangeAmount' =>1,
                        'teamRechargeAmount'=>1,
                        'teamWinScore'=>1,
                        'teamGameWinScore'=>1,
                        'teamRegPeople'=>1,
                        //'teamRegBindPeople'=>1,
                        'teamActivePeople'=>1,
                        'totalTeamPlayerCount'=>1,
                        'regActivePeople'=>1,
                        'teamExchangePeople'=>1,//团队今天总兑换人数
                        'teamExchangeNum'=>1,//团队今天总兑换笔数
                        'teamRegBindPeople'=>1,//团队今天注册并绑定人数
                        'teamRegValidNewBetPeople'=>1,//团队今天新增有下注会员
                        'teamRegPromoterNum'=>1,//团队今天注册代理数
                        'teamActiveRegPromoterNum'=>1,//团队今天注册有效代理数
                        'teamRewardAmount'//奖励支出
                    ],
            ],
            [
                '$sort' => ['Date'=>1]
            ]
        ])->toArray();

        $result_array = [];
        foreach ($data_array as $item){
            /*$item['teamGameWinScore'] = $this->formatMoneyFromMongo($item['teamGameWinScore']);
            $item['teamRechargeAmount'] = $this->formatMoneyFromMongo($item['teamRechargeAmount']);
            $item['teamRevenue'] = $this->formatMoneyFromMongo($item['teamRevenue']);
            $item['teamWinScore'] = $this->formatMoneyFromMongo($item['teamWinScore']);
            $item['teamExchangeAmount'] = $this->formatMoneyFromMongo($item['teamExchangeAmount']);*/
            $result_array[$item['Date']] = $item;
        }
        return $result_array;
    }
    private function platformDailyData($startDate,$endDate){
        $startTime = strtotime(trim($startDate));
        $endTime = strtotime(trim($endDate)) + 86400;
        $startTimeMongo = $this->formatTimestampToMongo($startTime);
        $endTimeMongo = $this->formatTimestampToMongo($endTime);
        $where = [];

        $where['date'] = ['$gte' => $startTimeMongo, '$lt' => $endTimeMongo];

        $data_array = Db::connection('mongodb_main')->collection('platform_data_record')->raw()->aggregate([
            [
                '$match' => $where
            ],
            [
                '$project' =>
                    [
                        '_id' => 0,
                        'Date' => ['$substr' => [['$add' => ['$date',28800000]], 0, 10]],
                        'totalLoginCount' => 1,
                        'totalGameCount' => 1,
                        'totalOnlineLoginTime' =>1,
                        'totalOnlineGameTime'=>1,
                        'totalRechargeAmount'=>1,
                        'totalRechargeTimes'=>1,
                        'totalExchangeAmount'=>1,
                        'totalExchangeTimes'=>1,
                        'totalAllBetScore'=>1,
                        'totalValidBetScore'=>1,
                        'totalRevenue'=>1,
                        'totalWinScore'=>1,
                        'totalScore'=>1,
                        'totalBankScore'=>1,
                        'todayPromoterScore'=>1,
                        'totalPromoterScore'=>1,
                        'totalPromoterExchange'=>1,
                        'totalPromoterCount'=>1,
                        'totalRoomCard'=>1,
                        'todayRewardScore'=>1,
                        'totalRewardScore'=>1,
                        'todayClubPromoterScore'=>1,
                        'totalClubPromoterScore'=>1,
                        'todayClubRewardScore'=>1,
                        'totalClubRewardScore'=>1,
                        'todayRechargeAmount'=>1,
                        'todayExchangeAmount'=>1,
                        'totalExchangePeople'=>1,
                        'todayRechargeTimes'=>1,
                        'todayExchangeTimes'=>1,
                        'todayAllBetScore'=>1,
                        'todayValidBetScore'=>1,
                    ],
            ],
        ])->toArray();
        $result_array = [];
        if($data_array){
            foreach ($data_array as $item){
                $result_array[$item['Date']] = $item;
            }
        }
        return $result_array;
    }
    private function clubPlatformDailyData($startDate,$endDate){
        $startTime = strtotime(trim($startDate));
        $endTime = strtotime(trim($endDate)) + 86400;
        $startTimeMongo = $this->formatTimestampToMongo($startTime);
        $endTimeMongo = $this->formatTimestampToMongo($endTime);
        $where = [];
        $where['clubId'] = -1000;
        $where['date'] = ['$gte' => $startTimeMongo, '$lt' => $endTimeMongo];

        $data_array = Db::connection('mongodb_club')->collection('platform_data_record')->raw()->aggregate([
            [
                '$match' => $where
            ],
            [
                '$project' =>
                    [
                        '_id' => 0,
                        'Date' => ['$substr' => [['$add' => ['$date',28800000]], 0, 10]],
                        'platformWinScore' => 1,
                        'totalPlatformWinScore' => 1,
                        'allBet' => 1,
                        'totalAllBet' => 1,
                        'validBet' => 1,
                        'totalValidBet' => 1,
                        'revenue' => 1,
                        'agentRevenue' => 1,
                        'totalAgentRevenue' => 1,
                        'totalRevenue' => 1,
                        'platformProfit' =>1,
                        'totalPlatformProfit' =>1,
                        'rewardScore' => 1,
                        'totalRewardScore' => 1,
                        'promoterScore' => 1,
                        'totalPromoterScore' => 1,
                        'gamePlayerCount'=>1,
                    ],
            ],
        ])->toArray();
        $result_array = [];

        if($data_array){
            foreach ($data_array as $item){
               $result_array[$item['Date']] = $item;
            }
        }
        return $result_array;
    }

    private function clubPromoterDailyData($startDate,$endDate){
        $startTime = strtotime(trim($startDate));
        $endTime = strtotime(trim($endDate))+ 86400;
        $startTimeMongo = $this->formatTimestampToMongo($startTime);
        $endTimeMongo = $this->formatTimestampToMongo($endTime);

        $where = [];
        $where['date'] = ['$gte' => $startTimeMongo, '$lt' => $endTimeMongo];
        $where['promoterId'] = -1000;

        $data_array = Db::connection('mongodb_club')->collection('stat_promoter_daily')->raw()->aggregate([
            [
                '$match' => $where
            ],
            [
                '$project' =>
                    [
                        '_id' => 0,
                        'Date' => ['$substr' => [['$add' => ['$date',28800000]], 0, 10]],
                        'teamGameWinScore'=>1,//游戏输赢
                        'totalTeamGameWinScore'=>1, //游戏输赢
                        'teamFlowAmount'=>1,//用户下注流水
                        'totalTeamFlowAmount'=>1,//用户下注流水
                        'teamProfit' => 1,//代理提成
                        'totalTeamProfit' => 1,//累计代理提成
                        'myProfit' => 1,
                        'myTeamProfit' => 1,
                        'totalMyProfit' => 1,
                        'totalMyTeamProfit' => 1,
                        'teamRevenue' => 1,//税收
                        'totalTeamRevenue' => 1,//累计
                        'platformProfit' => 1,//官方盈亏
                        'totalPlatformProfit' => 1,//累计
                        'teamRegPromoterNum' => 1,//新增代理
                        'totalTeamRegPromoterNum' => 1,//累计新增代理
                        'teamActiveRegPromoterNum' => 1,//新增有效代理
                        'totalTeamActiveRegPromoterNum' => 1,//累计新增有效代理
                        'transferToScoreAmount' => 1,
                        'totalTransferToScoreAmount' => 1,
                        'teamTransferToScoreAmount'=>1,
                        'totalTeamTransferToScoreAmount'=>1
                    ],
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

    private function getDateFromRange($startdate, $enddate){
        $stimestamp = strtotime($startdate);
        $etimestamp = strtotime($enddate);
        $days = ($etimestamp-$stimestamp)/86400+1;
        $date = [];
        for($i=0; $i<$days; $i++){
            $date[] = date('Y-m-d', $stimestamp+(86400*$i));
        }
        return $date;
    }
}
