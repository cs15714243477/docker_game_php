<?php
namespace app\controller;

use support\Request;
use app\model\Promoter;
use app\model\GameUser;
use support\Db;

class userAnalysisCon extends Base
{
    private function _ajaxParam()
    {
        $where = [];
        $request = request();
        $getData = $request->all();
        extract($getData);
        $userId = !empty($userId) ? trim($userId) : false;
        $promoterId = !empty($promoterId) ? (int)$promoterId : false;
        $startTime = !empty($startDate) ? trim($startDate) : date("Y-m-d");
        $endTime = !empty($endDate) ? trim($endDate) : date("Y-m-d");
        $startTime = strtotime($startTime);
        $endTime = strtotime("$endTime +1 day");
        $startTimeMongo = $this->formatTimestampToMongo($startTime);
        $endTimeMongo = $this->formatTimestampToMongo($endTime);
        $where[1]['userId'] =  ['$gte' => GameUser::COMMON_ACCOUNT_START_ID];
        if($userId){
            if ($userId && in_array(strlen($userId), [6, 8])) {
                $where[1]['userId'] = (int)$userId;
            }else{
                //$where[1]['regInfo.ip'] = new MongoDB\BSON\Regex($userId, 'i');
                $where[1]['regInfo.ip'] = $userId;
            }
        }
        if($promoterId){
            $where[1]['promoterId'] = (int)$promoterId;
        }
        $where[1]['regInfo.time'] = ['$gte' => $startTimeMongo, '$lt' => $endTimeMongo];
        $where[2]['createTime'] = ['$gte' => $startTimeMongo, '$lt' => $endTimeMongo];
        return $where;
    }
    
    public function userAnalysis(Request $request)
    {
        if ($request->isAjax()) {
            $where = $this->_ajaxParam();
            if (!is_array($where)) return $where;
            $count = GameUser::where($where[1])->count();

            $user_arrays = GameUser::where($where[1])->orderBy('regInfo.time','desc')->skip($request->skip)->take($request->limit)->get()->toArray();
            $uid = [];
            $promoterId = [];
            if ($user_arrays) {
                foreach ($user_arrays as $ukey => $uvalue) {
                    $uid[] = $uvalue['userId'];
                    $promoterId[] = $uvalue['promoterId'];
                }
                array_unique($uid);
                array_unique($promoterId);
            }

            $promoters = Promoter::whereIn('promoterId',$promoterId)->select('promoterId','promoterName')->get()->toArray();
            $user_arrays = merge_array($user_arrays, $promoters, 'promoterId');

            $where2 = [];
            $where2['userId']=  ['$in'=>$uid];
            $where2['createTime'] = $where[2]['createTime'];
//            $game_logs = Db::connection('mongodb_main')->collection('game_log')->raw()->aggregate([
//                [
//                    '$match' => $where2
//                ],
//                [
//                    '$project' =>
//                        [
//                            'userId' => 1,
//                            'gameInfoId' => 1,
//                        ],
//                ],
//                [
//                    '$group' =>
//                        [
//                            '_id' => '$gameInfoId',
//                            'userId'=> ['$first'=>'$userId']
//                        ]
//                ],
//                [
//                    '$group' =>
//                        [
//                            '_id' => '$userId',
//                            'gameCount' =>['$sum' => 1],
//                            'userId'=> ['$first'=>'$userId']
//                        ]
//                ],
//            ])->toArray();
            //$user_arrays = merge_array($user_arrays, $game_logs, 'userId');
            //根据uid[]查询相应的游戏最后的时间,最后再聚合
//            $game_logs_time = Db::connection('mongodb_main')->collection('game_log')->raw()->aggregate([
//                [
//                    '$match' => $where2
//                ],
//                [
//                    '$project' =>
//                        [
//                            'userId' => 1,
//                            'createTime' => 1,
//                        ],
//                ],
//                [
//                    '$sort' => ['createTime' => -1]
//                ],
//                [
//                    '$group' =>
//                        [
//                            '_id' => '$userId',
//                            'userId'=> ['$first'=>'$userId'],
//                            'createTime'=> ['$first'=>'$createTime']
//                        ]
//                ],
//            ])->toArray();

            //$user_arrays = merge_array($user_arrays, $game_logs_time, 'userId');
            if ($user_arrays) {
                foreach ($user_arrays as $key => $value) {
                    //$user_arrays[$key]['gameCount'] =  $user_arrays[$key]['gameCount']??0;
                    $user_arrays[$key]['regPromoterName'] =  $user_arrays[$key]['promoterName'];
                    $user_arrays[$key]['promoterId'] = $user_arrays[$key]['promoterId'];
                    $user_arrays[$key]['trueName'] = $user_arrays[$key]['trueName']??'';
                    $user_arrays[$key]['score'] = $this->formatMoneyFromMongo($user_arrays[$key]['score']);
                    $user_arrays[$key]['bankScore'] = $this->formatMoneyFromMongo($user_arrays[$key]['bankScore']);
                    $user_arrays[$key]['rechargeAmount_number'] = $this->formatMoneyFromMongo($user_arrays[$key]['rechargeAmount']);
                    $user_arrays[$key]['rechargeAmount'] = number_format($user_arrays[$key]['rechargeAmount_number'],2,".",",");
                    $user_arrays[$key]['exchangeAmount'] = $this->formatMoneyFromMongo($user_arrays[$key]['exchangeAmount']);
                    $user_arrays[$key]['allBet'] = $this->formatMoneyFromMongo($user_arrays[$key]['allBet']);

                    $user_arrays[$key]['regInfoTime'] = $this->formatDate($user_arrays[$key]['regInfo']['time']);
                    if(isset($user_arrays[$key]['lastGameTime'])){
                        $user_arrays[$key]['lastGameTime'] = $this->formatDate($user_arrays[$key]['lastGameTime']);
                    }else{
                        $user_arrays[$key]['lastGameTime'] = "无最后游戏时间";
                    }

                    $user_arrays[$key]['regInfoTimeFom'] = $this->formatDate($user_arrays[$key]['regInfo']['time'],'Y-m-d');
                    if(isset($user_arrays[$key]['lastLogin']['time']) && $user_arrays[$key]['lastLogin']['time'] && $user_arrays[$key]['lastLogin']['time'] !="0"){
                        $user_arrays[$key]['lastLoginTime'] = $this->formatDate($user_arrays[$key]['lastLogin']['time']);
                    }else{
                        $user_arrays[$key]['lastLoginTime'] = "/";
                    }
                    $user_arrays[$key]['regInfoMobileType'] = $user_arrays[$key]['regInfo']['mobileType'];
                    $user_arrays[$key]['ip'] = $user_arrays[$key]['regInfo']['ip']??'';
                }
            }

            return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $user_arrays]);
        }else{
            $getData = check_type($request->all());
            extract($getData);
            $startDate = !empty($startDate) ? trim($startDate) : "";
            $endDate = !empty($endDate) ? trim($endDate) : "";
            $promoterId = !empty($promoterId) ? (int)$promoterId : '';
            $assignData = [
                'startDate'=>$startDate,
                'endDate'=>$endDate,
                'promoterId'=>$promoterId
            ];
            return view('data/userAnalysis/list', $assignData);
        }
    }

    public function summary(Request $request)
    {
        if($request->isAjax()) {
            $where = $this->_ajaxParam();
            if (!is_array($where)) return $where;
            $rechargeAmountSum = GameUser::where($where[1])->sum('rechargeAmount');
            $rechargeAmountSum = $this->formatMoneyFromMongo($rechargeAmountSum);

            $exchangeAmountSum = GameUser::where($where[1])->sum('exchangeAmount');
            $exchangeAmountSum = $this->formatMoneyFromMongo($exchangeAmountSum);

            $allBetSum = GameUser::where($where[1])->sum('allBet');
            $allBetSum = $this->formatMoneyFromMongo($allBetSum);



            return json(['code' => 0, 'msg' => 'ok', 'data' => ['rechargeAmountSum' => $rechargeAmountSum, 'exchangeAmountSum' => $exchangeAmountSum, 'allBetSum' => $allBetSum]]);
        }
    }

    public function playRecords(Request $request)
    {
        $getData = $request->all();
        extract($getData);
        $startDate = !empty($startDate) ? trim($startDate) : "";
        $endDate = !empty($endDate) ? trim($endDate) : "";
        $userId = !empty($userId) ? (int)$userId : '';
        $assignData = [
            'startDate'=>$startDate,
            'endDate'=>$endDate,
            'userId'=>$userId
        ];

        return view('data/userAnalysis/playerRecord', $assignData);
    }
}
