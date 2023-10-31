<?php
namespace app\controller;

use app\model\ClubMo;
use app\model\GameKind;
use app\model\GameUser;
use app\model\PlayRecord;
use app\model\RechargeType;
use app\model\StatRechargeHome;
use app\model\UserOnline;
use support\bootstrap\Container;
use support\Db;
use support\Request;

class Home extends Base
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

    public function globalData(Request $request)
    {
        $date_t = date("Y-m-d");
        $online_user = $this->_getOnline(2);
        $promoter_daily = $this->_getPromoterDaily();
        if(isset($promoter_daily[$date_t]['teamExchangeAmount'])){
            $promoter_daily[$date_t]['teamExchangeAmount'] = $promoter_daily[$date_t]['teamExchangeAmount']/100;
        }else{
            $promoter_daily[$date_t]['teamExchangeAmount'] = 0;
        }
        $data = [
            'simulatorValue' => $online_user[0]['simulatorValue'] ?? 0,
            'iosValue' => $online_user[0]['iosValue'] ?? 0,
            'androidValue' => $online_user[0]['androidValue'] ?? 0,
            'teamRegPeople' => $promoter_daily[$date_t]['teamRegPeople'] ?? 0,
            'teamRegPromoterNum' => $promoter_daily[$date_t]['teamRegPromoterNum'] ?? 0,

            'teamExchangePeople' => $promoter_daily[$date_t]['teamExchangePeople'] ?? 0,
            'teamExchangeNum' => $promoter_daily[$date_t]['teamExchangeNum'] ?? 0,
            'teamExchangeAmount' => $promoter_daily[$date_t]['teamExchangeAmount'] ?? 0,
            'teamRegBindPeople' => $promoter_daily[$date_t]['teamRegBindPeople'] ?? 0,
            'teamActiveRegPromoterNum' => $promoter_daily[$date_t]['teamActiveRegPromoterNum'] ?? 0,

            'friendRoomValue' => $this->_getOnline(1, FRIEND_ROOM),
            'clubValue' => $this->_getOnline(1, CLUB),

            'totalPlayerCount' => $this->get_platform_data(),
            'totalPlayerCountFriend' => $this->get_platform_data(FRIEND_ROOM),
            'totalPlayerCountClub' => $this->get_platform_data(CLUB),
        ];

        return view('home/global', ['data' => $data]);
    }

    private function _getOnline($type=1, $classify = GOLD_COIN)
    {
        //当前在线数据
        $startTimeFmt = date("Y-m-d");
        $startTime = strtotime(trim($startTimeFmt));
        $endTime = strtotime(trim($startTimeFmt)) + 86400;
        $filter = array();
        $filter += ['statTime' => ['$gte' => $this->formatTimestampToMongo($startTime), '$lt' => $this->formatTimestampToMongo($endTime)]];
        $filter += ['roomId' => 999];
        $tempDb = match ($classify) {
            CLUB => 'mongodb_club',
            FRIEND_ROOM => 'mongodb_friend',
            default => 'mongodb_main',
        };
        $data_array = Db::connection($tempDb)->collection('stat_room_online')->raw()->aggregate([
            [
                '$match' => $filter
            ],
            [
                '$project' =>
                    [
                        '_id' => 0,
                        'statValue' => 1,
                        'iosValue' => 1,
                        'androidValue' => 1,
                        'simulatorValue' => 1,
                    ],
            ]
        ])->toArray();
        //1:总计在线2:返回安卓,苹果
        if($type == 1){
            $number = $data_array[0]['statValue'] ?? 0;
        }else{
            $number = $data_array;
        }
        return $number;
    }

    private function get_platform_data($classify = GOLD_COIN)
    {
        if ($classify == CLUB) {
            $realMemberNumSum = ClubMo::where('clubId', '>', 1000)->sum('realMemberNum');
            return $realMemberNumSum??0;
        }
        $endTime = date("Y-m-d");
        $endTime = strtotime(trim($endTime))+ 86400;
        $endTimeMongo = $this->formatTimestampToMongo($endTime);

        $where = [];
        $where['date'] = ['$lt' => $endTimeMongo];
        $limit = 1;

        $tempDb = match ($classify) {
            CLUB => 'mongodb_club',
            FRIEND_ROOM => 'mongodb_friend',
            default => 'mongodb_main',
        };
        if ($classify == CLUB) {
            $where['clubId'] = ['$ne'=>1000];
            $where1['clubId'] = ['$ne'=>-1000];
            $data_array = Db::connection($tempDb)->collection('platform_data_record')->raw()->aggregate([
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
                            'totalPlayerCount' => 1,
                            'gamePlayerCount' => 1,
                        ],
                ],
                [
                    '$sort' => ['Date'=>-1]
                ],
                [
                    '$limit' => $limit
                ]
            ])->toArray();
        }else{
            $data_array = Db::connection($tempDb)->collection('platform_data_record')->raw()->aggregate([
                [
                    '$match' => $where
                ],
                [
                    '$project' =>
                        [
                            '_id' => 0,
                            'Date' => ['$substr' => [['$add' => ['$date',28800000]], 0, 10]],
                            'totalPlayerCount' => 1,
                            'gamePlayerCount' => 1,
                        ],
                ],
                [
                    '$sort' => ['Date'=>-1]
                ],
                [
                    '$limit' => $limit
                ]
            ])->toArray();
        }
        $todayData = [];
        if($data_array){
            foreach ($data_array as $item){
                $todayData = $item;
            }
        }
        return $todayData['totalPlayerCount']??0;
    }

    private function _getPromoterDaily()
    {
        $startTime = date("Y-m-d");
        $endTime = date("Y-m-d");
        $startTime = strtotime(trim($startTime));
        $endTime = strtotime(trim($endTime))+ 86400;

        $filter = array();
        $filter += ['date' => ['$gte' => $this->formatTimestampToMongo($startTime), '$lt' => $this->formatTimestampToMongo($endTime)]];
        $filter += ['promoterId' => 1000];
        $data_array = Db::connection('mongodb_main')->collection('stat_promoter_daily')->raw()->aggregate([
            [
                '$match' => $filter
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
            $result_array[$item['Date']] = $item;//$item[$field];
        }
        return $result_array;

    }

    public function rechargeData(Request $request)
    {
        $tdate = date("Y-m-d");
        $todayTime = strtotime($tdate);
        $where = ['createDate' => $this->formatTimestampToMongo($todayTime)];
        $todayStatRechargeHome = StatRechargeHome::where($where)->orderBy('sortId', 'asc')->select('rechargeTypeId','rechargeAmount','rechargeNum','rechargePeople')->get()->toArray();

        $recharge_type_list = RechargeType::all('rechargeTypeId', 'rechargeTypeName')->toArray();
        $recharge_type_list[] = ['rechargeTypeId'=>-5, 'rechargeTypeName'=>'首充总充值'];
        $recharge_type_list[] = ['rechargeTypeId'=>-4, 'rechargeTypeName'=>'多次充值'];
        $recharge_type_list[] = ['rechargeTypeId'=>-3, 'rechargeTypeName'=>'官方总充值'];
        $recharge_type_list[] = ['rechargeTypeId'=>-2, 'rechargeTypeName'=>'新用户充值'];
        $recharge_type_list[] = ['rechargeTypeId'=>-1, 'rechargeTypeName'=>'老用户充值'];
        $recharge_type_list[] = ['rechargeTypeId'=>0, 'rechargeTypeName'=>'后台补发'];
        $recharge_type_list[] = ['rechargeTypeId'=>99999999, 'rechargeTypeName'=>'支付补发'];

        $todayStatRechargeHome = merge_array($todayStatRechargeHome, $recharge_type_list, 'rechargeTypeId');
        $endData = [];
        foreach ($todayStatRechargeHome as $key => &$val) {
            $todayStatRechargeHome[$key]['rechargeAmount'] = $this->formatMoneyFromMongo($todayStatRechargeHome[$key]['rechargeAmount']);
            if ($val['rechargeTypeId'] == -3) {
                $endData = $val;
                unset($todayStatRechargeHome[$key]);
            }
        }
        if (!empty($endData)) array_push($todayStatRechargeHome, $endData);
        return json(['code' => 0, 'msg' => 'ok', 'count' => '', 'data' => $todayStatRechargeHome]);
    }

    public function gameData(Request $request)
    {
        $userOnline = Db::connection('mongodb_main')->collection('user_online')->raw()->aggregate([
            [
                '$group' =>
                    [
                        '_id' => '$roomId',
                        'online_count' => ['$sum' => 1]
                    ]
            ],
            [
                '$project' =>
                    [
                        'roomId' => '$_id',
                        'online_count' => 1
                    ]
            ]
        ])->toArray();

        $startTime = date("Y-m-d");
        //$startTime = "2021-05-22";
        $startTime = strtotime($startTime);
        $endTime = strtotime(date('Y-m-d 23:59:59.999', $startTime));

        /*$playRecord = Db::connection('mongodb_main')->collection('play_record')->raw(function ($collection) use ($startTime, $endTime) {
            dd($collection);dd($endTime);
        });*/
        $playGameRoomInfoList = Db::connection('mongodb_main')->collection('play_record')->raw()->aggregate([
            [
                '$match' => ['isAndroid' => false, 'endTime' => ['$gte' => new \MongoDB\BSON\UTCDateTime($startTime*1000), '$lte' => new \MongoDB\BSON\UTCDateTime($endTime*1000)],'userId' => ['$gte' => GameUser::COMMON_ACCOUNT_START_ID]]
            ],
            [
                '$project' =>
                    [
                        'gameId' => 1,
                        'roomId' => 1,
                        'userId' => 1,
                        'winScore' => 1,
                        'platformWinScore'=>1,
                        'revenue' => 1,
                    ]
            ],
            [
                '$group' =>
                    [
                        '_id' => ['gameId'=>'$gameId', 'roomId'=>'$roomId', 'userId'=>'$userId'],
                        'winScore' => ['$sum' => '$winScore'],
                        'platformWinScore' => ['$sum' => '$platformWinScore'],
                        'revenue' => ['$sum' => '$revenue'],
                    ]
            ],
            [
                '$group' =>
                    [
                        '_id' => ['gameId'=>'$_id.gameId', 'roomId'=>'$_id.roomId'],
                        'userCount'=>['$sum'=>1],
                        'winScore' => ['$sum' => '$winScore'],
                        'platformWinScore' => ['$sum' => '$platformWinScore'],
                        'revenue' => ['$sum' => '$revenue']
                    ]
            ],
            [
                '$project' =>
                    [
                        'gameId'=>'$_id.gameId',
                        'roomId'=>'$_id.roomId',
                        'userCount'=>1,
                        'winScore'=>1,
                        'platformWinScore'=>1,
                        'revenue' => 1
                    ]
            ]
        ])->toArray();
        //$gameRoomInfo = getGameRoomInfo();
        $gameRoomInfo = GameKind::gameAllRoomInfo();
        $playGameRoomInfoList = merge_array($playGameRoomInfoList, $gameRoomInfo, 'roomId');

        $allRevenue = 0;
        $allAvg = 0;

        if ($playGameRoomInfoList) {
            foreach ($playGameRoomInfoList as $key1 => $value1) {
                $playGameRoomInfoList[$key1]['online_count'] = 0;
                foreach ($userOnline as $key2 => $value2) {
                    if ($playGameRoomInfoList[$key1]['roomId'] == $userOnline[$key2]['roomId']) {
                        $playGameRoomInfoList[$key1]['online_count'] = $userOnline[$key2]['online_count'] ? $userOnline[$key2]['online_count'] : 0;
                    }
                }
                $playGameRoomInfoList[$key1]["winScore"] = -$playGameRoomInfoList[$key1]["winScore"];
                $playGameRoomInfoList[$key1]["taxAvg"] = $playGameRoomInfoList[$key1]["revenue"] / $playGameRoomInfoList[$key1]["userCount"];
                $playGameRoomInfoList[$key1]["avg"] = $playGameRoomInfoList[$key1]["winScore"] / $playGameRoomInfoList[$key1]["userCount"];

                $allRevenue += $playGameRoomInfoList[$key1]["revenue"];
                $allAvg += $playGameRoomInfoList[$key1]["avg"];
            }
            foreach ($playGameRoomInfoList as $key => $value) {
                if($allRevenue > 0){
                    $playGameRoomInfoList[$key]["taxRate"] = $playGameRoomInfoList[$key]["revenue"] / $allRevenue;
                }else{
                    $playGameRoomInfoList[$key]["taxRate"] = 0;
                }
                if($allAvg > 0){
                    $playGameRoomInfoList[$key]["avgRate"] = $playGameRoomInfoList[$key]["avg"] / $allAvg;
                }else{
                    $playGameRoomInfoList[$key]["avgRate"] = 0;
                }


                $playGameRoomInfoList[$key]["allWinScore"] = round(($playGameRoomInfoList[$key]["revenue"]+$playGameRoomInfoList[$key]["platformWinScore"])*0.01, 2);
                //$play_game_room_info_list[$key]["allWinScore"] = round(($play_game_room_info_list[$key]["revenue"]+$play_game_room_info_list[$key]["winScore"]), 2);
                $playGameRoomInfoList[$key]["winScore"] = round($playGameRoomInfoList[$key]["winScore"]*0.01, 2);
                $playGameRoomInfoList[$key]["platformWinScore"] = round($playGameRoomInfoList[$key]["platformWinScore"]*0.01, 2);
                $playGameRoomInfoList[$key]["revenue"] = round($playGameRoomInfoList[$key]["revenue"]*0.01, 2);

                $playGameRoomInfoList[$key]["taxAvg"] = round($playGameRoomInfoList[$key]["taxAvg"]*0.01, 2);
                $playGameRoomInfoList[$key]["taxRate"] = round($playGameRoomInfoList[$key]["taxRate"], 2);

                $playGameRoomInfoList[$key]["avg"] = round($playGameRoomInfoList[$key]["avg"]*0.01, 2);
                $playGameRoomInfoList[$key]["avgRate"] = round($playGameRoomInfoList[$key]["avgRate"], 2);
            }
        }

        //dd($playGameRoomInfoList);
        return json(['code' => 0, 'msg' => 'ok', 'count' => '', 'data' => $playGameRoomInfoList]);
    }
}
