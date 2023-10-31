<?php
namespace app\controller;

use app\model\FriendsDetailPlayRecord;
use app\model\FriendsGameKind;
use app\model\FriendsMainPlayRecord;
use app\model\FriendsUserGameKind;
use app\model\GameUser;
use app\model\Promoter;
use app\model\RechargeCardNumList;
use app\model\RechargeRoomCard;
use app\model\RoomCardChange;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use support\bootstrap\Container;
use support\Db;
use support\Request;

class FriendsRoom extends Base
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

    public static function gameKind($where)
    {
        return FriendsGameKind::where($where)->orderBy('sort', 'asc')->get();
    }
    public static function gameKindIdNameList()
    {
        $return = [];
        $where = ['status' => FriendsGameKind::STATUS_OPEN];
        $list = static::gameKind($where);
        foreach ($list as $item) {
            $return[] = ["gameId" => $item['gameId'], 'gameName' => $item['gameName']];
        }
        return $return;
    }
    public static function gameKindIdNameKV()
    {
        $return = [];
        $where = ['status' => FriendsGameKind::STATUS_OPEN];
        $list = static::gameKind($where);
        foreach ($list as $item) {
            $return[$item['gameId']] = $item['gameName'];
        }
        return $return;
    }

    //开房记录
    public function playerRoomRecord(Request $request)
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
            }
            $startTime = strtotime($startDate);
            $endTime = strtotime($endDate) + 24*3600;
            if ($startTime >= $endTime) return json(['code' => -1, 'msg' => '请核对开始时间结束时间']);
            $where[] = ['createTime', '>=', $this->formatTimestampToMongo($startTime)];
            $where[] = ['createTime', '<', $this->formatTimestampToMongo($endTime)];
            if (!empty($userId)) $where['userId'] = $userId;
            if (!empty($roomId)) $where['roomId'] = $roomId;
            if (!empty($gameId)) $where['gameId'] = $gameId;

            $count = FriendsUserGameKind::where($where)->count();
            $list = FriendsUserGameKind::where($where)->orderBy('createTime', 'desc')->skip($request->skip)->take($request->limit)->get()->toArray();
            $gameKind = static::gameKindIdNameList();
            $list = merge_array($list, $gameKind, 'gameId', );
            $idsArr = array_column($list, '_id');
            $idsArr = array_map('stringToObjectId', $idsArr);
            $mainList = FriendsMainPlayRecord::whereIn('userGameKindId', $idsArr)->get()->toArray();

            $mainidsArr = array_column($list, 'mainId');
            $mainidsArr = array_map('stringToObjectId', $mainidsArr);
            $detailList = FriendsDetailPlayRecord::whereIn('mainId', $mainidsArr)->get(['mainId', 'userCount'])->toArray();

            foreach ($list as &$item) {
                $item['winScore'] = 0;
                $item['mainId'] = 0;
                $item['endTime'] = '';
                foreach ($mainList as $v) {
                    if ($item['_id'] == $v['userGameKindId'] && $item['userId'] == $v['userId']) {
                        $item['winScore'] = $v['winScore']??0;
                        $item['endTime'] = $v['endTime'];
                        $item['mainId'] = $v['_id'];
                    }
                }
                $item['createTime'] = $this->formatDate($item['createTime']);
                $item['endTime'] = $this->formatDate($item['endTime']??0);

                $item['userCount'] = 0;
                foreach ($detailList as $dv) {
                    if ($item['mainId'] == $dv['mainId']) {
                        $item['userCount'] == $dv['userCount'];
                    }
                }
                $item['winScore'] = $this->formatMoneyFromMongo($item['winScore']);
                $item['playDuration'] = Sec2Time($item['playDuration']);
                $item['needRoomCard'] = $this->formatMoneyFromMongo($item['needRoomCard']);
            }
            return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $list]);

        }
        $assignData = [
            'gameList' => static::gameKindIdNameList(),
        ];
        return view('friendsRoom/playerRoomRecord/list', $assignData);
    }

    public function playerCreateRoomRecord(Request $request)
    {
        $assignData = [
            'gameList' => static::gameKindIdNameList(),
        ];
        return view('friendsRoom/playerCreateRoomRecord/list', $assignData);
    }

    private function _ajaxParam()
    {
        $where = $where1 = $where2 =[];
        $request = request();
        $getData = check_type($request->all());
        extract($getData);
        if (empty($startDate)) {
            $startDate = date("Y-m-d");
        }
        if (empty($endDate)) {
            $endDate = date("Y-m-d");
        }
        $startTime = strtotime($startDate);
        $endTime = strtotime($endDate) + 24*3600;
        if ($startTime >= $endTime) return json(['code' => -1, 'msg' => '请核对开始时间结束时间']);
        $startTimeMongo = $this->formatTimestampToMongo($startTime);
        $endTimeMongo = $this->formatTimestampToMongo($endTime);
        $where['date'] = ['$gte' => $startTimeMongo, '$lt' => $endTimeMongo];
        $where1['createTime'] = ['$gte' => $startTimeMongo, '$lt' => $endTimeMongo];
        $where2[] = ['date', '>=', $startTimeMongo];
        $where2[] = ['date', '<', $endTimeMongo];

        if (empty($searchText)) $searchText = 1000;
        if (!empty($searchText)) {
            $searchTextLen = strlen($searchText);
            if(in_array($searchTextLen, PROMOTER_ID_LENGTH)) {
                $where['promoterId'] = (int)$searchText;
            }elseif($searchText == SYSTEM_PROMOTER_ID){
                $where['promoterId'] = ['$gt' => SYSTEM_PROMOTER_ID];
            }else {
                return json(['code' => -1, 'msg' => '代理ID长度不正确']);
            }
        }
        $where2['promoterId'] = $searchText;
        //$where['channelId'] = 1;
        return ['where' => $where, 'where1' => $where1, 'where2' => $where2];
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
            $where['date'] = ['$gte' => $this->formatTimestampToMongo($startTime), '$lt' => $this->formatTimestampToMongo($endTime)];
            $userOnline = Db::connection('mongodb_friend')->collection('stat_game_user')->raw()->aggregate([
                [
                    '$match' => $where
                ],
                [
                    '$project' =>
                        [
                            'statValue' => 1,
                            //'day' => ['$dateToString' => ['format' => "%Y-%m-%d", 'date' => '$optTime']],
                            'Date' => ['$substr' => [['$add' => ['$date', 28800000]], 0, 10]],
                            'gameId' => 1,
                        ]
                ]
            ])->toArray();
            while($startTime < $endTime){
                $dateArr[] = date('Y-m-d',$startTime);
                $startTime =$startTime + 3600*24;
            }
            if (!empty($userOnline)) {
                $userOnlineArr = array_column($userOnline, 'statValue', 'Date');
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
                    'series_name' => '人数',
                    'title' => "{$startDate}~{$endDate}时间段",
                    'ytitle' => '人数',
                ]
            ];
            return json($return);
        }

        $assignData = [
            'gameList' => static::gameKindIdNameKV(),
        ];
        return view('friendsRoom/playerCount/list', $assignData);
    }

    public function playerGameDetail(Request $request)
    {
        if ($request->isAjax()) {
            $where = [];
            $getData = check_type($request->all());
            extract($getData);
            if (!empty($roomId)) {
                $where['roomId'] = $roomId;
            } else {
                if (empty($startDate)) {
                    $startDate = date("Y-m-d");
                }
                if (empty($endDate)) {
                    $endDate = date("Y-m-d");
                }
                $startTime = strtotime($startDate);
                $endTime = strtotime($endDate) + 24*3600;
                if ($startTime >= $endTime) return json(['code' => -1, 'msg' => '请核对开始时间结束时间']);
                $where[] = ['startTime', '>=', $this->formatTimestampToMongo($startTime)];
                $where[] = ['startTime', '<', $this->formatTimestampToMongo($endTime)];
            }
            if (!empty($userId)) $where['userId'] = $userId;
            if (!empty($gameInfoId)) $where['gameInfoId'] = $gameInfoId;
            if (!empty($gameId)) $where['gameId'] = $gameId;

            $count = FriendsDetailPlayRecord::where($where)->count();
            $list = FriendsDetailPlayRecord::where($where)->orderBy('startTime', 'desc')->skip($request->skip)->take($request->limit)->get()->toArray();

            foreach ($list as &$item) {
                $item['beforeScore'] = $this->formatMoneyFromMongo($item['beforeScore']);
                $item['score'] = $this->formatMoneyFromMongo($item['score']);
                $item['allBet'] = $this->formatMoneyFromMongo($item['allBet']);
                $item['winScore'] = $this->formatMoneyFromMongo($item['winScore']);
                $item['platformWinScore'] = $this->formatMoneyFromMongo($item['platformWinScore']);
                $item['winLostScore'] = $this->formatMoneyFromMongo($item['winLostScore']);//得分

                $item['startTime'] = $this->formatDate($item['startTime']);
                $item['endTime'] = $this->formatDate($item['endTime']??0);
                $item['playTime'] = Sec2Time($item['playTime']);

            }
            return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $list]);

        }
        $assignData = [
            'gameList' => static::gameKindIdNameList(),
            'roomId' => $request->get('roomId', 0),
        ];
        return view('friendsRoom/playerGameDetail/list', $assignData);
    }

    public function playerGameStat(Request $request)
    {
        if ($request->isAjax()) {
            $where = [];
            $groupId = [];
            $getData = check_type($request->all());
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
            $where['endTime'] = ['$gte' => $this->formatTimestampToMongo($startTime), '$lt' => $this->formatTimestampToMongo($endTime)];
            if (!empty($userId)) $where['userId'] = $userId;
            $groupId = ['userId' => '$userId'];
            if (!empty($gameId)) {
                $where['gameId'] = $gameId;
                $groupId = ['userId' => '$userId', 'gameId' => '$gameId'];
            }
            $list = Db::connection('mongodb_friend')->collection('detail_play_record')->raw()->aggregate([
                [
                    '$match' => $where
                ],
                [
                    '$project' =>
                        [
                            'userId'=>1,
                            'gameId'=>1,
                            //'roomId'=>1,
                            'allBet'=>1,//压分
                            'winLostScore'=>1,//得分
                            'winScore'=>1,//输赢
                            'platformWinScore' => 1,//平台输赢
                            'revenue' => 1,
                            'playTime'=>1
                        ]
                ],
                [
                    '$group' =>
                        [
                            '_id' => $groupId,
                            'gameRound' => ['$sum' => 1],
                            'allBet' => ['$sum' => '$allBet'],
                            'winLostScore' => ['$sum' => '$winLostScore'],
                            'winScore' => ['$sum' => '$winScore'],
                            'platformWinScore' => ['$sum' => '$platformWinScore'],
                            'revenue' => ['$sum' => '$revenue'],
                            'playTime' => ['$sum' => '$playTime']
                        ]
                ],
                [
                    '$skip' => $request->skip
                ],
                [
                    '$limit' => $request->limit
                ],
                [
                    '$project' =>
                        [
                            'userId'=>'$_id.userId',
                            'gameId'=>'$_id.gameId',
                            //'roomId'=>'$_id.roomId',
                            'allBet'=>1,
                            'winLostScore'=>1,
                            'gameRound'=>1,
                            'winScore'=>1,
                            'platformWinScore' => 1,
                            'revenue' => 1,
                            'playTime'=>1
                        ]
                ]
            ])->toArray();
            $listCount = Db::connection('mongodb_friend')->collection('detail_play_record')->raw()->aggregate([
                [
                    '$match' => $where
                ],
                [
                    '$project' =>
                        [
                            'userId'=>1,
                            'gameId'=>1,
                            //'roomId'=>1,
                            'allBet'=>1,
                            'winLostScore'=>1,
                            'winScore'=>1,
                            'platformWinScore' => 1,
                            'revenue' => 1,
                            'playTime'=>1
                        ]
                ],
                [
                    '$group' =>
                        [
                            '_id' => $groupId,
                            'gameRound' => ['$sum' => 1],
                            'allBet' => ['$sum' => '$allBet'],
                            'winLostScore' => ['$sum' => '$winLostScore'],
                            'winScore' => ['$sum' => '$winScore'],
                            'platformWinScore' => ['$sum' => '$platformWinScore'],
                            'revenue' => ['$sum' => '$revenue'],
                            'playTime' => ['$sum' => '$playTime']
                        ]
                ],
                [
                    '$skip' => 0
                ],
                [
                    '$project' =>
                        [
                            'userId'=>'$_id.userId',
                            'gameId'=>'$_id.gameId',
                            //'roomId'=>'$_id.roomId',
                            'allBet'=>1,
                            'winLostScore'=>1,
                            'gameRound'=>1,
                            'winScore'=>1,
                            'platformWinScore' => 1,
                            'revenue' => 1,
                            'playTime'=>1
                        ]
                ]
            ])->toArray();
            $count = count($listCount);
            $games = static::gameKindIdNameKV();
            foreach ($list as &$item) {
                $item['winLostScore'] = $this->formatMoneyFromMongo($item['winLostScore']);
                //$item['ptIncome'] = $this->formatMoneyFromMongo($item['platformWinScore'] + $item['revenue']);

                $item['allBet'] = $this->formatMoneyFromMongo($item['allBet']);
                $item['winScore'] = $this->formatMoneyFromMongo($item['winScore']);
                $item['platformWinScore'] = $this->formatMoneyFromMongo($item['platformWinScore']);
                $item['revenue'] = $this->formatMoneyFromMongo($item['revenue']);
                $item['playTime'] = Sec2Time($item['playTime']);
                if (isset($item['gameId'])) {
                    $item['gameName'] = $games[$item['gameId']];
                } else {
                    $item['gameName'] = '';
                }

            }
            return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $list]);

        }
        $assignData = [
            'gameList' => static::gameKindIdNameList(),
        ];
        return view('friendsRoom/playerGameStat/list', $assignData);
    }

    public function survey(Request $request)
    {
        $assignData = [];
        return view('friendsRoom/survey/list', $assignData);
    }
    public function surveyYestodayAndToday(Request $request)
    {
        if ($request->method() == 'GET') {
            $date_t = date("Y-m-d");
            $date_y = date("Y-m-d", strtotime('-1 day'));
            $platform_data_record = $this->get_day_data($date_t);
            foreach ($platform_data_record as &$item) {
                $item['mallSalesScore'] = $this->formatMoneyFromMongo($item['mallSalesScore']??0);
                $item['promoterSalesScore'] = $this->formatMoneyFromMongo($item['promoterSalesScore']??0);
                $item['playerLeftCardNum'] = $this->formatMoneyFromMongo($item['playerLeftCardNum']??0);
                $item['rewardCardNum'] = $this->formatMoneyFromMongo($item['rewardCardNum']??0);
                $item['firstRechargeAvgScore'] = $this->formatMoneyFromMongo($item['firstRechargeAvgScore']??0);
                $item['totalSalesScore'] = $this->formatMoneyFromMongo($item['totalSalesScore']??0);
                $item['secondRechargeRatio'] = $this->formatPercentFromMongo($item['secondRechargeRatio']??0);
                $item['roomDuration'] = Sec2Time($item['roomDuration']);
            }
            //dd($platform_data_record);
            $list = [
                ['name' => '新增用户', 'today' => $platform_data_record[$date_t]['newPlayerCount']??0, 'yestoday' => $platform_data_record[$date_y]['newPlayerCount']??0],
                ['name' => '游戏人数', 'today' => $platform_data_record[$date_t]['gamePlayerCount']??0, 'yestoday' => $platform_data_record[$date_y]['gamePlayerCount']??0],
                //['name' => '最高同时在线', 'today' => $platform_data_record[$date_t]['newPlayerCount']??0, 'yestoday' => $platform_data_record[$date_y]['newPlayerCount']??0],
                //['name' => '累计玩牌用户', 'today' => $platform_data_record[$date_t]['cumulatelGamePlayer']??0, 'yestoday' => $platform_data_record[$date_y]['cumulatelGamePlayer']??0],
                ['name' => '开房时长', 'today' => $platform_data_record[$date_t]['roomDuration']??0, 'yestoday' => $platform_data_record[$date_y]['roomDuration']??0],
                ['name' => '开房局数', 'today' => $platform_data_record[$date_t]['roomRoundNum']??0, 'yestoday' => $platform_data_record[$date_y]['roomRoundNum']??0],
                ['name' => '赠送房卡数量', 'today' => $platform_data_record[$date_t]['rewardCardNum']??0, 'yestoday' => $platform_data_record[$date_y]['rewardCardNum']??0],
                ['name' => '会员剩余房卡', 'today' => $platform_data_record[$date_t]['playerLeftCardNum']??0, 'yestoday' => $platform_data_record[$date_y]['playerLeftCardNum']??0],
                ['name' => '房卡付费用户', 'today' => $platform_data_record[$date_t]['rechargeUser']??0, 'yestoday' => $platform_data_record[$date_y]['rechargeUser']??0],
                ['name' => '二次充值比例(%)', 'today' => $platform_data_record[$date_t]['secondRechargeRatio']??0, 'yestoday' => $platform_data_record[$date_y]['secondRechargeRatio']??0],
                ['name' => '房卡购买次数', 'today' => $platform_data_record[$date_t]['rechargeTimes']??0, 'yestoday' => $platform_data_record[$date_y]['rechargeTimes']??0],
                ['name' => '房卡首充用户', 'today' => $platform_data_record[$date_t]['firstRechargeUser']??0, 'yestoday' => $platform_data_record[$date_y]['firstRechargeUser']??0],
                ['name' => '房卡首充平均金额', 'today' => $platform_data_record[$date_t]['firstRechargeAvgScore']??0, 'yestoday' => $platform_data_record[$date_y]['firstRechargeAvgScore']??0],
                ['name' => '房卡总充值', 'today' => $platform_data_record[$date_t]['totalSalesScore']??0, 'yestoday' => $platform_data_record[$date_y]['totalSalesScore']??0],
                ['name' => '代理销售额', 'today' => $platform_data_record[$date_t]['promoterSalesScore']??0, 'yestoday' => $platform_data_record[$date_y]['promoterSalesScore']??0],
                ['name' => '商城销售额', 'today' => $platform_data_record[$date_t]['mallSalesScore']??0, 'yestoday' => $platform_data_record[$date_y]['mallSalesScore']??0],
            ];
            return json(['code' => 0, 'msg' => 'ok', 'count' => 0, 'data' => $list]);
        }
        if ($request->method() == 'POST') {
            $assignData = [];
            return view('friendsRoom/survey/surveyYestodayAndToday/list', $assignData);
        }
    }

    private function get_day_data($endDate, $startDate = '')
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

        $data_array = Db::connection('mongodb_friend')->collection('platform_data_record')->raw()->aggregate([
            [
                '$match' => $where
            ],
            [
                '$project' =>
                    [
                        '_id' => 0,
                        'Date' => ['$substr' => [['$add' => ['$date',28800000]], 0, 10]],
                        'newPlayerCount' => 1,
                        'gamePlayerCount' => 1,
                        'cumulatelGamePlayer' =>1,
                        'roomDuration'=>1,
                        'roomRoundNum'=>1,
                        'rewardCardNum'=>1,
                        'playerLeftCardNum'=>1,
                        'rechargeUser'=>1,
                        'secondRechargeRatio'=>1,
                        'rechargeTimes'=>1,
                        'firstRechargeUser'=>1,
                        'firstRechargeAvgScore'=>1,
                        'totalSalesScore'=>1,
                        'promoterSalesScore'=>1,
                        'mallSalesScore'=>1,
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


        return Db::connection('mongodb_friend')->collection('platform_data_record')->raw()->aggregate([
            [
                '$match' => $where
            ],
            [
                '$project' =>
                    [
                        '_id' => 0,
                        'newPlayerCount' => 1,
                        'gamePlayerCount' => 1,
                        'cumulatelGamePlayer' =>1,
                        'playerLeftCardNum'=>1,
                        'roomDuration'=>1,
                        'roomRoundNum'=>1,
                        'firstRechargeUser'=>1,
                        'secondRechargeRatio'=>1,
                        'rechargeTimes'=>1,
                        'rechargeUser'=>1,
                        'rewardCardNum'=>1,
                        'firstRechargeAvgScore'=>1,
                        'totalSalesScore'=>1,
                        'promoterSalesScore'=>1,
                        'mallSalesScore'=>1,
                    ],
            ],
            [
                '$group' =>
                    [
                        '_id' => null,
                        'newPlayerCount' => ['$sum' => '$newPlayerCount'],
                        'gamePlayerCount' => ['$sum' => '$gamePlayerCount'],
                        'cumulatelGamePlayer' => ['$sum' => '$cumulatelGamePlayer'],
                        'playerLeftCardNum' => ['$sum' => '$playerLeftCardNum'],
                        'roomDuration' => ['$sum' => '$roomDuration'],
                        'roomRoundNum' => ['$sum' => '$roomRoundNum'],
                        'firstRechargeAvgScore' => ['$sum' => '$firstRechargeAvgScore'],
                        'firstRechargeUser' => ['$sum' => '$firstRechargeUser'],
                        'secondRechargeRatio' => ['$sum' => '$secondRechargeRatio'],
                        'mallSalesScore' => ['$sum' => '$mallSalesScore'],
                        'promoterSalesScore' => ['$sum' => '$promoterSalesScore'],
                        'rechargeTimes' => ['$sum' => '$rechargeTimes'],
                        'rechargeUser' => ['$sum' => '$rechargeUser'],
                        'rewardCardNum' => ['$sum' => '$rewardCardNum'],
                        'totalSalesScore' => ['$sum' => '$totalSalesScore'],
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
                $item['mallSalesScore'] = $this->formatMoneyFromMongo($item['mallSalesScore']);
                $item['promoterSalesScore'] = $this->formatMoneyFromMongo($item['promoterSalesScore']);
                $item['playerLeftCardNum'] = $this->formatMoneyFromMongo($item['playerLeftCardNum']);
                $item['rewardCardNum'] = $this->formatMoneyFromMongo($item['rewardCardNum']);
                $item['firstRechargeAvgScore'] = $this->formatMoneyFromMongo($item['firstRechargeAvgScore']);
                $item['totalSalesScore'] = $this->formatMoneyFromMongo($item['totalSalesScore']);
                $item['secondRechargeRatio'] = $this->formatPercentFromMongo($item['secondRechargeRatio']);
                $item['secondRechargeRatio'] = $this->formatPercentFromMongo($item['secondRechargeRatio']);
                $item['roomDuration'] = Sec2Time($item['roomDuration']);
            }
            $lastMonthData = $this->get_month_data($date_y_m_s,$date_y_m_e);
            foreach ($lastMonthData as &$item) {
                $item['mallSalesScore'] = $this->formatMoneyFromMongo($item['mallSalesScore']);
                $item['promoterSalesScore'] = $this->formatMoneyFromMongo($item['promoterSalesScore']);
                $item['playerLeftCardNum'] = $this->formatMoneyFromMongo($item['playerLeftCardNum']);
                $item['rewardCardNum'] = $this->formatMoneyFromMongo($item['rewardCardNum']);
                $item['firstRechargeAvgScore'] = $this->formatMoneyFromMongo($item['firstRechargeAvgScore']);
                $item['totalSalesScore'] = $this->formatMoneyFromMongo($item['totalSalesScore']);
                $item['secondRechargeRatio'] = $this->formatPercentFromMongo($item['secondRechargeRatio']);
                $item['secondRechargeRatio'] = $this->formatPercentFromMongo($item['secondRechargeRatio']);
                $item['roomDuration'] = Sec2Time($item['roomDuration']);
            }

            $list = [
                ['name' => '新增用户', 'today' => $thisMonthData[0]['newPlayerCount']??0, 'yestoday' => $lastMonthData[0]['newPlayerCount']??0],
                ['name' => '游戏人数', 'today' => $thisMonthData[0]['gamePlayerCount']??0, 'yestoday' => $lastMonthData[0]['gamePlayerCount']??0],
               // ['name' => '最高同时在线', 'today' => $thisMonthData[0]['newPlayerCount']??0, 'yestoday' => $lastMonthData[0]['newPlayerCount']??0],
                //['name' => '累计玩牌用户', 'today' => $thisMonthData[0]['cumulatelGamePlayer']??0, 'yestoday' => $lastMonthData[0]['cumulatelGamePlayer']??0],
                ['name' => '开房时长', 'today' => $thisMonthData[0]['roomDuration']??0, 'yestoday' => $lastMonthData[0]['roomDuration']??0],
                ['name' => '开房局数', 'today' => $thisMonthData[0]['roomRoundNum']??0, 'yestoday' => $lastMonthData[0]['roomRoundNum']??0],
                ['name' => '赠送房卡数量', 'today' => $thisMonthData[0]['rewardCardNum']??0, 'yestoday' => $lastMonthData[0]['rewardCardNum']??0],
                ['name' => '会员剩余房卡', 'today' => $thisMonthData[0]['playerLeftCardNum']??0, 'yestoday' => $lastMonthData[0]['playerLeftCardNum']??0],
                ['name' => '房卡付费用户', 'today' => $thisMonthData[0]['rechargeUser']??0, 'yestoday' => $lastMonthData[0]['rechargeUser']??0],
                ['name' => '二次充值比例(%)', 'today' => $thisMonthData[0]['secondRechargeRatio']??0, 'yestoday' => $lastMonthData[0]['secondRechargeRatio']??0],
                ['name' => '房卡购买次数', 'today' => $thisMonthData[0]['rechargeTimes']??0, 'yestoday' => $lastMonthData[0]['rechargeTimes']??0],
                ['name' => '房卡首充用户', 'today' => $thisMonthData[0]['firstRechargeUser']??0, 'yestoday' => $lastMonthData[0]['firstRechargeUser']??0],
                ['name' => '房卡首充平均金额', 'today' => $thisMonthData[0]['firstRechargeAvgScore']??0, 'yestoday' => $lastMonthData[0]['firstRechargeAvgScore']??0],
                ['name' => '房卡总充值', 'today' => $thisMonthData[0]['totalSalesScore']??0, 'yestoday' => $lastMonthData[0]['totalSalesScore']??0],
                ['name' => '代理销售额', 'today' => $thisMonthData[0]['promoterSalesScore']??0, 'yestoday' => $lastMonthData[0]['promoterSalesScore']??0],
                ['name' => '商城销售额', 'today' => $thisMonthData[0]['mallSalesScore']??0, 'yestoday' => $lastMonthData[0]['mallSalesScore']??0],
            ];
            return json(['code' => 0, 'msg' => 'ok', 'count' => 0, 'data' => $list]);
        }
        if ($request->method() == 'POST') {
            $assignData = [];
            return view('friendsRoom/survey/surveyLastMonthAndThisMonth/list', $assignData);
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
                    $ytitle = '时长(分钟)';
                    foreach ($date_range as $item_date){
                        $new_data[$item_date] = round(($platform_data_record[$item_date]['roomDuration']??0)/60, 2);
                    }
                    break;
                case 101:
                    $ytitle = '局数';
                    foreach ($date_range as $item_date){
                        $new_data[$item_date] = $platform_data_record[$item_date]['roomRoundNum']??0;
                    }
                    break;
                case 102:
                    $ytitle = '数量';
                    foreach ($date_range as $item_date){
                        $new_data[$item_date] = $this->formatMoneyFromMongo($platform_data_record[$item_date]['rewardCardNum']??0);
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
            return view('friendsRoom/survey/surveyPlayerScale/list', $assignData);
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
            return view('friendsRoom/survey/surveyRevenueScale/list', $assignData);
        }
    }

    private function getYestodayData()
    {

    }

    public function showCard(Request $request)
    {
        $oid = $request->get('oid', '');
        $rs = FriendsDetailPlayRecord::find($oid, ['cardValue', 'gameId'])->toArray();
        $cardValue = $rs['cardValue'];
        $gameId = $rs['gameId'];
        if ($gameId == 11) {
            $gameId = 220;
        } elseif ($gameId == 12) {
            $gameId = 100;
        } elseif ($gameId == 13) {
            $gameId = 300;
        } elseif ($gameId == 14) {
            $gameId = 890;
        } elseif ($gameId == 15) {
            $gameId = 870;
        }
        $card = [];
        if (!empty($cardValue)) {
            if ($gameId == 900) {
                $card[] = substr($cardValue, 0, 2);
                $card[] = substr($cardValue, 2, 2);
            }
            elseif ($gameId == 920) {
                $card = decomposeCardValue($cardValue, 7);
                $len = count($card);
                for($i = 0; $i < $len; $i++) {
                    $card[$i] = substr($card[$i], 1);
                    if ($i < 5) {
                        $card[$i] = decomposeCardValue($card[$i], 2);
                    } else {
                        $temp = [];
                        $card[$i] = hexdec($card[$i]);
                        $temp[] = !($card[$i] & 15);
                        $temp[] = $card[$i] & 1;
                        $temp[] = $card[$i] & 2;
                        $temp[] = $card[$i] & 4;
                        $temp[] = $card[$i] & 8;
                        $card[$i] = $temp;
                    }
                }
            }
            elseif ($gameId == 930) {
                $card = decomposeCardValue($cardValue, 11);
                $len = count($card);
                for($i = 0; $i < $len; $i++) {
                    $card[$i] = substr($card[$i], 1);
                    if ($i < 5) {
                        $card[$i] = decomposeCardValue($card[$i], 2);
                    } else {
                        $temp = [];
                        $card[$i] = hexdec($card[$i]);
                        $temp[] = !($card[$i] & 15);
                        $temp[] = $card[$i] & 1;
                        $temp[] = $card[$i] & 2;
                        $temp[] = $card[$i] & 4;
                        $temp[] = $card[$i] & 8;
                        $card[$i] = $temp;
                    }
                }
            }
            elseif ($gameId == 720) {
                $card = decomposeCardValue($cardValue, 5);
                $len = count($card);
                for($i = 0; $i < $len; $i++) {
                    $card[$i] = substr($card[$i], 1);
                    if ($i < 4) {
                        $card[$i] = decomposeCardValue($card[$i], 2);
                        foreach ($card[$i] as $t) {
                            $card[$i]['value'][] = hexdec($t);
                        }
                        $card[$i]['valueSum'] = array_sum($card[$i]['value']) % 100;
                        $card[$i]['point1'] = floor($card[$i]['valueSum'] / 10);
                        $card[$i]['point2'] = $card[$i]['valueSum'] % 10;
                    } else {
                        $card[$i] = hexdec($card[$i]);
                        $temp = [];
                        $temp[] = 0;
                        $temp[] = $card[$i] & 1;
                        $temp[] = $card[$i] & 2;
                        $temp[] = $card[$i] & 4;
                        $card[$i] = $temp;
                    }
                }
            }
            elseif (in_array($gameId, [830])) {
                $card = decomposeCardValue($cardValue, 10);
                $len = count($card);
                for($i = 0; $i < $len; $i++) {
                    if ($i < 4) {
                        $card[$i] = decomposeCardValue($card[$i], 2);
                    } else {
                        $temp = [];
                        $temp[] = substr($card[$i], 0, 1);
                        $temp[] = hexdec(substr($card[$i], 1, 2));
                        $temp[] = hexdec(substr($card[$i], 3, 2));
                        $temp[] = hexdec(substr($card[$i], 5, 2));
                        $temp[] = hexdec(substr($card[$i], 7, 2));
                        $card['999'] = $card[$i] = $temp;
                    }
                }
            }
            elseif (in_array($gameId, [860])) {
                $card = decomposeCardValue($cardValue, 6);
                $len = count($card);
                for($i = 0; $i < $len; $i++) {
                    if ($i < 5) {
                        $card[$i] = decomposeCardValue($card[$i], 2);
                    } else {
                        $temp = [];
                        $temp[] = substr($card[$i], 0, 1);
                        $temp[] = hexdec(substr($card[$i], 1, 2));
                        /* $temp[] = hexdec(substr($card[$i], 3, 2));
                         $temp[] = hexdec(substr($card[$i], 5, 2));
                         $temp[] = hexdec(substr($card[$i], 7, 2));*/
                        $card['999'] = $card[$i] = $temp;
                    }
                }
            }
            elseif (in_array($gameId, [810, 870, 890])) {
                $card = decomposeCardValue($cardValue, 10);
                $len = count($card);
                for($i = 0; $i < $len; $i++) {
                    if ($i < 4) {
                        $card[$i] = decomposeCardValue($card[$i], 2);
                    } else {
                        $temp = [];
                        $temp[] = substr($card[$i], 0, 1);
                        $temp[] = hexdec(substr($card[$i], 1, 2));
                        /* $temp[] = hexdec(substr($card[$i], 3, 2));
                         $temp[] = hexdec(substr($card[$i], 5, 2));
                         $temp[] = hexdec(substr($card[$i], 7, 2));*/
                        $card['999'] = $card[$i] = $temp;
                    }
                }
            }
            elseif ($gameId == 210) {
                $card = decomposeCardValue($cardValue, 6);
                $len = count($card);
                for($i = 0; $i < $len; $i++) {
                    $card[$i] = decomposeCardValue($card[$i], 2);
                }
            }
            elseif ($gameId == 220) {
                $card2 = decomposeCardValue($cardValue, 8);
                $temp = array_pop($card2);
                $len = count($card2);
                $card = [];
                for($i = 0; $i < $len; $i++) {
                    $index = substr($card2[$i], 0, 2);
                    $v = substr($card2[$i], 2);
                    $card[$index] = decomposeCardValue($v, 2);
                }
                $card['999'] = $temp;
            }
            elseif (in_array($gameId, [850, 880, 820])) {
                $card = decomposeCardValue($cardValue, 6);
                $len = count($card);
                for($i = 0; $i < $len; $i++) {
                    if ($i < 4) {
                        $card[$i] = decomposeCardValue($card[$i], 2);
                    } else {
                        $temp = [];
                        $temp[] = substr($card[$i], 0, 1);
                        $temp[] = hexdec(substr($card[$i], 1, 2));
                        /* $temp[] = hexdec(substr($card[$i], 3, 2));
                         $temp[] = hexdec(substr($card[$i], 5, 2));
                         $temp[] = hexdec(substr($card[$i], 7, 2));*/
                        $card['999'] = $card[$i] = $temp;
                    }
                }
            }
            elseif ($gameId == 300) {
                $cardArray = explode("|", $cardValue);
                $black3 = array_shift($cardArray);
                $card = [
                    '0' => array_slice($cardArray, 0, 7),
                    '1' => array_slice($cardArray, 7, 7),
                    '2' => array_slice($cardArray, 14, 7),
                ];
                foreach ($card as &$item) {
                    $item[0] = explode(",", $item[0]);
                    foreach ($item[0] as &$item2) {
                        $item2 = ys($item2);
                    }
                    $item[1] = explode(",", $item[1]);
                    foreach ($item[1] as &$item3) {
                        $item3 = ys($item3);
                    }
                }
                $card['999'] = $black3;
                //echo "<pre>";print_r($card);exit;

            }
            elseif ($gameId == 100) {
                $cardArray = explode("|", $cardValue);
                $dizhuChairId = array_shift($cardArray);
                $dizhuDiPai = array_shift($cardArray);
                $card = [
                    '0' => array_slice($cardArray, 0, 3),
                    '1' => array_slice($cardArray, 3, 3),
                    '2' => array_slice($cardArray, 6, 3),
                ];
                foreach ($card as &$item) {
                    $item[0] = explode(",", $item[0]);
                    foreach ($item[0] as &$item2) {
                        $item2 = ys($item2);
                    }
                    $item[1] = explode(",", $item[1]);
                    foreach ($item[1] as &$item3) {
                        $item3 = ys($item3);
                    }
                }
                $card['999'] = $dizhuChairId;
                $card['9999'] = explode(",", $dizhuDiPai);
                foreach ($card['9999'] as &$item4) {
                    $item4 = ys($item4);
                }
                dd($card);

            }
            elseif ($gameId == 550) {
                $cardArray = explode(";", $cardValue);
                $card = [];
                foreach ($cardArray as $c) {
                    $temp = '';
                    $temp = explode(",", $c);
                    $card[$temp['3']] = [decomposeCardValue($temp['0'], 2), decomposeCardValue($temp['1'], 2), decomposeCardValue($temp['2'], 2)];
                }
                /*foreach ($card as &$item) {
                    foreach ($item as &$item2) {
                        $item2 = array_slice($item2, 0, count($item2) - 1);
                    }
                }*/
            }

            //1先按","分割会员
            //2再按"|"分割选择数据 按照 座位号,操作码(0无1买保险2双倍),牌型
            //3 若有001B17,10370B-007283A,200B341B,501C1D带"-",则按-分割,第一个是 座位 ,操作码,牌型 第二个被"-"分割的数据为 操作码,牌型
            elseif ($gameId == 600) {
                $cardArrayUsers = explode(",", $cardValue);//根据","拆分会员
                $card = [];
                foreach ($cardArrayUsers as $val_u) {
                    $moreSeats = strpos($val_u,"|");//该会员是否压住了多个位置
                    if($moreSeats){//有多个位置
                        $seat_array = explode("|", $val_u);//根据"|"拆分座位号
                        foreach ($seat_array as $val_seat){
                            $split_cards = strpos($val_seat,"-");
                            if($split_cards){//有多个位置并且包含分牌
                                $split_cards_array = explode("-", $val_seat);//根据"-"拆分分牌//003215  //6003-1619507770-124419-0-39
                                foreach ($split_cards_array as $key_split => $val_split){//000631_1|121A363A_3|202218_4|30160405_234-00631_1|400532_4,5003251A_1
                                    $val_split_arr = explode("_", $val_split);//$val_split_arr[0]牌型$val_split_arr[1]点数
                                    if($key_split == 0){
                                        $seat_num = substr($val_split_arr[0],0,1);
                                        $op = substr($val_split_arr[0],1,1);
                                        $card_str = substr($val_split_arr[0],2);
                                        $card[$seat_num][$key_split]['op'] = $op;
                                        $card[$seat_num][$key_split]['card_str'] = decomposeCardValue($card_str, 2);
                                    }else{
                                        $seat_num = substr($split_cards_array[0],0,1);
                                        $op = substr($val_split_arr[0],0,1);
                                        $card_str = substr($val_split_arr[0],1);
                                        $card[$seat_num][$key_split]['op'] = $op;
                                        $card[$seat_num][$key_split]['card_str'] = decomposeCardValue($card_str, 2);
                                    }
                                    //点数解析
                                    if(isset($val_split_arr[1])){
                                        $points = substr($val_split_arr[1],0,1);
                                        if($points == 1){
                                            $card[$seat_num][$key_split]['points'] = "爆牌";
                                        }elseif ($points == 2){
                                            $points = substr($val_split_arr[1],1);
                                            $card[$seat_num][$key_split]['points'] = $points;
                                        }elseif ($points == 3){
                                            $card[$seat_num][$key_split]['points'] = "五小龙";
                                        }else{
                                            $card[$seat_num][$key_split]['points'] = "黑杰克";
                                        }
                                    }else{
                                        $card[$seat_num][$key_split]['points'] = "";
                                    }
                                }
                            }else{//有多个位置但不包含分牌 303118291B|000412060B|102A1C11|2002132829|4026063407,501312190D
                                $val_seat_arr = explode("_", $val_seat);//$val_seat_arr[0]牌型$val_seat_arr[1]点数
                                $seat_num = substr($val_seat_arr[0],0,1);
                                $op = substr($val_seat_arr[0],1,1);
                                $card_str = substr($val_seat_arr[0],2);
                                $card[$seat_num]['op'] = $op;
                                $card[$seat_num]['card_str'] = decomposeCardValue($card_str, 2);
                                //点数解析
                                if(isset($val_seat_arr[1])){
                                    $points = substr($val_seat_arr[1],0,1);
                                    if($points == 1){
                                        $card[$seat_num]['points'] = "爆牌";
                                    }elseif ($points == 2){
                                        $points = substr($val_seat_arr[1],1);
                                        $card[$seat_num]['points'] = $points;
                                    }elseif ($points == 3){
                                        $card[$seat_num]['points'] = "五小龙";
                                    }else{
                                        $card[$seat_num]['points'] = "黑杰克";
                                    }
                                }else{
                                    $card[$seat_num]['points'] = "";
                                }
                            }
                        }
                    }else{//如果没有多个位置
                        $split_cards = strpos($val_u,"-");//是否分牌
                        if($split_cards){ //没有多个位置,但是有分牌
                            $split_cards_array = explode("-", $val_u);//根据"-"拆分分牌
                            foreach ($split_cards_array as $key_split => $val_split){
                                $val_split_arr = explode("_", $val_split);//$val_split_arr[0]牌型$val_split_arr[1]点数
                                if($key_split == 0){//分牌的情况下第一条数据头2个位置分别为 座位号,操作码(0,1,2),剩下的为牌型
                                    $seat_num = substr($val_split_arr[0],0,1);
                                    $op = substr($val_split_arr[0],1,1);
                                    $card_str = substr($val_split_arr[0],2);
                                    $card[$seat_num][$key_split]['op'] = $op;
                                    $card[$seat_num][$key_split]['card_str'] = decomposeCardValue($card_str, 2);
                                }else{//分牌的情况下第二天数据开始,第一个位置代表操作码(0,1,2),其余为牌型
                                    $seat_num = substr($split_cards_array[0],0,1);
                                    $op = substr($val_split_arr[0],0,1);
                                    $card_str = substr($val_split_arr[0],1);
                                    $card[$seat_num][$key_split]['op'] = $op;
                                    $card[$seat_num][$key_split]['card_str'] = decomposeCardValue($card_str, 2);
                                }
                                //点数解析
                                if(isset($val_split_arr[1])){
                                    $points = substr($val_split_arr[1],0,1);
                                    if($points == 1){
                                        $card[$seat_num][$key_split]['points'] = "爆牌";
                                    }elseif ($points == 2){
                                        $points = substr($val_split_arr[1],1);
                                        $card[$seat_num][$key_split]['points'] = $points;
                                    }elseif ($points == 3){
                                        $card[$seat_num][$key_split]['points'] = "五小龙";
                                    }else{
                                        $card[$seat_num][$key_split]['points'] = "黑杰克";
                                    }
                                }else{
                                    $card[$seat_num][$key_split]['points'] = "";
                                }
                            }
                        }else{ //没有多位置和分牌直接展示
                            $val_u_arr = explode("_", $val_u);//$val_u_arr[0]牌型$val_u_arr[1]点数

                            $seat_num = substr($val_u_arr[0],0,1);
                            $op = substr($val_u_arr[0],1,1);
                            $card_str = substr($val_u_arr[0],2);
                            $card[$seat_num]['op'] = $op;
                            $card[$seat_num]['card_str'] = decomposeCardValue($card_str, 2);

                            //点数解析
                            if(isset($val_u_arr[1])){
                                $points = substr($val_u_arr[1],0,1);
                                if($points == 1){
                                    $card[$seat_num]['points'] = "爆牌";
                                }elseif ($points == 2){
                                    $points = substr($val_u_arr[1],1);
                                    $card[$seat_num]['points'] = $points;
                                }elseif ($points == 3){
                                    $card[$seat_num]['points'] = "五小龙";
                                }else{
                                    $card[$seat_num]['points'] = "黑杰克";
                                }
                            }else{
                                $card[$seat_num]['points'] = "";
                            }
                        }
                    }
                }

            }
            elseif ($gameId == 950) {
                //$smarty->assign("cardValue", $cardValue);
                $card['1'] = 'ok';
            }
        }
        $assignData = [
            'gameId' => $gameId,
            'card' => $card,
            'cardValue' => $cardValue,
        ];
        //dd($card);
        return view('player/showCard/list', $assignData);
    }

    public function rechargeRoomCard(Request $request)
    {

        $assignData = [
            'giveScoreType' => GIVE_ROOMCARD_TYPE,
        ];
        return view('friendsRoom/rechargeRoomCard/add',$assignData);
    }

    public function incRoomCard(Request $request)
    {
        $postData = check_type($request->post());
        extract($postData);
        if (empty($userId) || empty($rechargeRoomCard)) return json(['code' => -1, 'msg' => '传入参数错误']);
        $rechargeRoomCard = $this->formatMoneytoMongo($rechargeRoomCard);
        $where = [
            'userId' => $userId,
            'status' => GameUser::USER_STATUS_ON,
        ];
        $user = Player::getPlayer($where, ['channelId','userId','roomCard','score','bankScore','promoterId']);
        if (empty($user)) return json(['code' => -1, 'msg' => '会员不存在']);
        //代理
        $wherePro = [
            'promoterId' => $user->userId,
        ];

        $promoter = $this->getPromoter($wherePro, ['level']);
        
        $level = 0;
        if (!empty($promoter)) $level = $promoter->level;

         //return json(['code' => -1, 'msg' => '代理不存在']);
        $orderId = '';
        //订单入库（充值订单/奖励订单）--修改会员信息/VIP--分值变化--发通知
        $session = DB::connection('mongodb_friend')->getMongoClient()->startSession();
        $session->startTransaction();
        $newRemark = $remark."操作人:".session("userName");
        try {
            $orderId = strtoupper("FK" . orderNumber() . $userId);
            $rechargeOrderData = [
                'userId' => $userId,
                'promoterId' => $user->promoterId,
                'channelId' => $user->channelId,
                'level' => $level,

                'cardNum' => 0,
                'rewardNum' => $rechargeRoomCard,
                'wantScore' => $user->score,

                'beforeScore' => $user->score,
                'beforeBankScore' => $user->bankScore,
                'beforeRoomCard' => $user->roomCard,

                'afterScore' => $user->score,
                'afterBankScore' => $user->bankScore,
                'afterRoomCard' => $user->roomCard+$rechargeRoomCard,

                'type' => 3,
                'createTime' => new \MongoDB\BSON\UTCDateTime,
                'remark' => $remark,
                'orderId' => $orderId
            ];
            $insertResult = RechargeRoomCard::raw()->insertOne($rechargeOrderData, ['session' => $session]);
            if (!$insertResult) {
                $session->abortTransaction();
                return json(['code' => -1, 'msg' => '添加充值订单记录失败']);
            }

            $updateData = [
                'roomCard' => $user->roomCard + $rechargeRoomCard
            ];

            $updateResult = GameUser::where($where)->update($updateData, ['session' => $session]);
            if (!$updateResult) {
                $session->abortTransaction();
                return json(['code' => -1, 'msg' => '修改用户数据记录失败']);
            }

            $insertData = [
                'userId' => $userId,
                'beforeScore' => $user->score,
                'beforeBankScore' => $user->bankScore,
                'beforeRoomCard' => $user->roomCard,

                'addScore' => 0,
                'addBankScore' => 0,
                'addRoomCard' => $rechargeRoomCard,

                'afterScore' => $user->bankScore,
                'afterBankScore' => $user->bankScore,
                'afterRoomCard' =>  $user->roomCard + $rechargeRoomCard,

                'changeType' => 17,
                'refId' => '',
                'roomId' => 0,
                'createTime' => new \MongoDB\BSON\UTCDateTime,
                'remark' => $newRemark,
            ];
            $insertResult = RoomCardChange::raw()->insertOne($insertData, ['session' => $session]);
            if (!$insertResult) {
                $session->abortTransaction();
                return json(['code' => -1, 'msg' => '添加分值改变记录失败']);
            }

            $session->commitTransaction();
            $this->adminLog(["content"=>session("userName")."充值【".$userId."】房卡:".$this->formatMoneyFromMongo($rechargeRoomCard)]);
            $info = "赠送" . $this->formatMoneyFromMongo($rechargeRoomCard) . "张房卡成功到账";
            sendData2(['userId'=>$userId, 'orderId'=>$orderId, 'type'=>2, 'money'=>$rechargeRoomCard, 'status'=>1, 'info'=>$info]);
            return json(['code' => 0, 'msg' => '充值成功']);
        } catch (\Exception $e) {
            $session->abortTransaction();
            json(['code' => -1, 'msg' => '充值房卡失败！']);
        }
    }

    public function roomCardList(Request $request)
    {
        if($request->isAjax()) {
            $where = [];
            $request = request();
            $getData = check_type($request->all());
            extract($getData);
            $where['type'] = 3;
            if (!is_array($where)) return $where;
            $count = RechargeRoomCard::where($where)->count();
            $list = RechargeRoomCard::where($where)->orderBy('createTime', 'desc')->skip($request->skip)->take($request->limit)->get()->toArray();
            foreach ($list as &$item) {
                $item['beforeRoomCard'] = $this->formatMoneyFromMongo($item['beforeRoomCard']);
                $item['rewardNum'] = $this->formatMoneyFromMongo($item['rewardNum']);
                $item['afterRoomCard'] = $this->formatMoneyFromMongo($item['afterRoomCard']);
                $item['createTime'] = $this->formatDate($item['createTime']);
            }
            return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $list]);
        }
    }

    private function _accountDetailsAjaxParam()
    {
        $where = [];
        $request = request();
        $getData = check_type($request->all());
        extract($getData);
        $startDate = isset($startDate) ? $startDate : date('Y-m-d');
        $endDate = isset($endDate) ? $endDate : date('Y-m-d');
        $startTime = strtotime($startDate);
        $endTime = strtotime("$endDate +1 day");
        $where[] = ['createTime', '>=', $this->formatTimestampToMongo($startTime)];
        $where[] = ['createTime', '<', $this->formatTimestampToMongo($endTime)];
        //输入框优先于下拉框
        if (!empty($searchValue)) {
            $where['userId'] = (int)$searchValue;
        }
        if (!empty($isSys) && empty($searchValue)) {
            if ($isSys == GameUser::COMMON_ACCOUNT) {
                $where[] = ['userId', '>=', GameUser::COMMON_ACCOUNT_START_ID];
            } elseif ($isSys == GameUser::SYSTEM_ACCOUNT) {
                $where[] = ['userId', '<', GameUser::COMMON_ACCOUNT_START_ID];
            }
        }

        if (!empty($type)) {
            $where['type'] = $type;
        }
        return $where;
    }

    public function accountDetails(Request $request)
    {
        if ($request->isAjax()) {
            $where = $this->_accountDetailsAjaxParam();
            $count = RechargeRoomCard::where($where)->count();
            $list = RechargeRoomCard::where($where)->orderBy('createTime', 'desc')->skip($request->skip)->take($request->limit)->get()->toArray();
            $typeArr = RechargeRoomCard::RECHARGE_RC_TYPE;
            foreach ($list as &$item) {
                $item['typeName'] = $typeArr[$item['type']];
                $item['cardNum'] = $this->formatMoneyFromMongo($item['cardNum']);
                if($item['type'] == 2){
                    $item['rewardNum'] = $item['rewardNum'];
                }else{
                    $item['rewardNum'] = $this->formatMoneyFromMongo($item['rewardNum']);
                }

                $item['afterRoomCard'] = $this->formatMoneyFromMongo($item['afterRoomCard']);
                $item['createTime'] = $this->formatDate($item['createTime']);
            }
            return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $list]);
        }
        $assignData = [
            'isSys' => GameUser::ACCOUNT_CLASSIFY,
            'type' => RechargeRoomCard::RECHARGE_RC_TYPE,
        ];
        return view('friendsRoom/accountDetails/list', $assignData);
    }

    public function exportAccountDetails(Request $request)
    {
        $where = $this->_accountDetailsAjaxParam();
        if (!is_array($where)) return $where;
        $cursor = RechargeRoomCard::where($where)->orderBy('createTime', 'desc')->cursor();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', '会员ID');
        $sheet->setCellValue('B1', '订单号');
        $sheet->setCellValue('C1', '类型');
        $sheet->setCellValue('D1', '房卡数量');
        $sheet->setCellValue('E1', '赠送房卡数量/折扣');
        $sheet->setCellValue('F1', '剩余房卡');
        $sheet->setCellValue('G1', '时间');
        $sheet->setCellValue('H1', '备注');
        $num = 2;
        $typeArr = RechargeRoomCard::RECHARGE_RC_TYPE;
        foreach ($cursor as $item) {
            $sheet->setCellValue("A{$num}", $item->userId);
            $sheet->setCellValue("B{$num}", $item->orderId);
            $sheet->setCellValue("C{$num}", $typeArr[$item->type]);
            $sheet->setCellValue("D{$num}", $this->formatMoneyFromMongo($item->cardNum));

            if($item->type == 2){
                if($item->rewardNum == 0 || $item->rewardNum == 100){
                    $rewardNum = "不打折";
                }else{
                    $rewardNum = $item->rewardNum."折";
                }
            }else{
                $rewardNum = $this->formatMoneyFromMongo($item->rewardNum);
            }
            $sheet->setCellValue("E{$num}", $rewardNum);

            $sheet->setCellValue("F{$num}", $this->formatMoneyFromMongo($item->afterRoomCard));
            $sheet->setCellValue("G{$num}", $this->formatDate($item->createTime));
            $sheet->setCellValue("H{$num}", $item->remark);
            $num++;
        }
        $writer = new Xlsx($spreadsheet);
        $file_path = public_path().'/accountDetails.xlsx';
        // 保存文件到 public 下
        $writer->save($file_path);
        return json(['code' => 0, 'msg' => 'ok', 'file' => $file_path]);
    }

    public function agentPrice(Request $request)
    {
        if ($request->isAjax()) {
            $where[] = ['level', '>=', 5];
            $count = RechargeCardNumList::where($where)->count();
            $list = RechargeCardNumList::where($where)->orderBy('level', 'asc')->get()->toArray();
            $levelName = RechargeCardNumList::LEVEL_NAME;
            $result = [];
            foreach ($list as $k => &$item) {
                $result[$k]['level'] = $item['level'];
                $result[$k]['levelName'] = $levelName[$item['level']];
                foreach ($item['cardNumList'] as &$itemNum) {
                    $cardNum = $this->formatMoneyFromMongo($itemNum['cardNum']);
                    $result[$k]['score_'.$cardNum] = $this->formatMoneyFromMongo($itemNum['score']);
                }
            }
            var_dump($result);
            return json(['code' => 0, 'msg' => 'ok','data' => $result]);
        }
        return view('friendsRoom/roomCardAgentPrice/list');
    }

    public function retailPrice(Request $request)
    {
        if ($request->isAjax()) {
            $where['level'] = 0;
            $count = RechargeCardNumList::where($where)->count();
            $list = RechargeCardNumList::where($where)->orderBy('level', 'asc')->get()->toArray();
            $result = [];
            foreach ($list as &$item) {
                foreach ($item['cardNumList'] as $k => &$itemNum) {
                    $result[$k]['cardNum'] = $this->formatMoneyFromMongo($itemNum['cardNum']);
                    $result[$k]['score'] = $this->formatMoneyFromMongo($itemNum['score']);
                    $result[$k]['rewardNum'] = $this->formatMoneyFromMongo($itemNum['rewardNum']);
                    $result[$k]['salesNum'] = $result[$k]['cardNum'] + $result[$k]['rewardNum'];
                }
            }
            return json(['code' => 0, 'msg' => 'ok','data' => $result]);
        }
        return view('friendsRoom/retailPrice/list');
    }

    public static function getPromoter($where, $column = ['*'])
    {
        return Promoter::where($where)->first($column);
    }


}
