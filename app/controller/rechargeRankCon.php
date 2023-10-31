<?php
namespace app\controller;

use support\Request;
use app\model\GameUser;
use support\Db;

class rechargeRankCon extends Base
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
        $searchType = !empty($searchType) ? (int)$searchType : 1;

        if($searchType == 2){
            $where['userId'] = ['$gte' => GameUser::COMMON_ACCOUNT_START_ID];
        }elseif($searchType == 3) {
            $where['userId'] = ['$lt' => GameUser::COMMON_ACCOUNT_START_ID];
        }

        if($searchText)
            $where['promoterId'] = $searchText;
        $where['status'] = 4;
        $where['applyTime'] = ['$gte' => $startTimeMongo, '$lt' => $endTimeMongo];

        return $where;
    }
    
    public function rechargeRank(Request $request)
    {
        if ($request->isAjax()) {
            $where = $this->_ajaxParam();
            if (!is_array($where)) return $where;
            $exchangeWhere = [];
            $exchangeWhere['applyTime'] = $where['applyTime'];
            $exchangeWhere['status'] = 18;

            $field = $request->get('field');
            $order = $request->get('order');

            $sort = -1;
            if (empty($field)) $field = 'rechargeTotal';
            if (empty($order)) $order = 'desc';
            if ($field == 'roomCard') $field = 'roomCard';
            if ($field == 'score') $field = 'score';
            if ($field == 'bankScore') $field = 'bankScore';
            if ($field == 'todayRechargeTotal') $field = 'todayRechargeTotal';
            if ($field == 'totalRechargeyNum') $field = 'totalRechargeyNum';
            if ($field == 'minRecharge') $field = 'minRecharge';
            if ($field == 'maxRecharge') $field = 'maxRecharge';
            if ($field == 'avgRecharge') $field = 'avgRecharge';
            if ($field == 'rechargeTotal') $field = 'rechargeTotal';
            if ($field == 'exchangeTotal') $field = 'exchangeTotal';
            if ($field == 'rechargeTotal') $field = 'rechargeTotal';

            if ($order == 'desc'){
                $sort = -1;
            }else{
                $sort = 1;
            }

            $order = [$field => $sort];


            $count_array = Db::connection('mongodb_main')->collection('recharge_order')->raw()->aggregate([
                [
                    '$match' => $where
                ],
                [
                    '$project' =>
                        [
                            'userId' => 1
                        ]
                ],
                [
                    '$group' =>
                        [
                            '_id' => '$userId',
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
            if($count_array && sizeof($count_array) > 0)
                $count = $count_array[0]['count'];


            $rechargeOrderList = Db::connection('mongodb_main')->collection('recharge_order')->raw()->aggregate([
                [
                    '$match' => $where
                ],
                [
                    '$project' =>
                        [
                            'userId' => 1,
                            'promoterId' => 1,
                            'rechargeMoney' => 1
                        ]
                ],
                [
                    '$group' =>
                        [
                            '_id' => ['userId' => '$userId', 'promoterId' => '$promoterId'],
                            'todayRechargeTotal' => ['$sum' => '$rechargeMoney'],
                            'totalRechargeyNum' => ['$sum' => 1],
                            'minRecharge' => ['$min' => '$rechargeMoney'],
                            'maxRecharge' => ['$max' => '$rechargeMoney'],
                            'avgRecharge' => ['$avg' => '$rechargeMoney']
                        ]
                ],
                [
                    '$sort' => ['todayRechargeTotal'=>-1]
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
                            'promoterId'=>'$_id.promoterId',
                            'todayRechargeTotal'=> 1,
                            'totalRechargeyNum'=> 1,
                            'minRecharge'=> 1,
                            'maxRecharge'=> 1,
                            'avgRecharge'=> 1
                        ]
                ],
                [
                    '$lookup' =>
                        [
                            'from' => 'exchange_order',
                            'let' => [
                                'rechargeOrderUserId' => '$userId',
                            ],
                            'as' => 'exchangeOrders',
                            'pipeline' => [
                                ['$match' =>[
                                    'status' => 18,
                                    'applyTime' => $exchangeWhere['applyTime'],
                                    '$expr' => [
                                        '$and' => [
                                            ['$eq' => ['$userId', '$$rechargeOrderUserId']]
                                        ]
                                    ]
                                ]
                                ],
                                ['$project' => [
                                    'payMoney' => 1
                                ]
                                ],
                                ['$group' => [
                                    '_id' => null,
                                    'payMoney' => ['$sum' => '$payMoney']
                                ]
                                ]
                            ],
                        ]
                ],
                [
                    '$lookup' =>
                        [
                            'from' => 'promoter',
                            'localField' => 'promoterId',
                            'foreignField' => 'promoterId',
                            'as' => 'promoters'
                        ]
                ],
                [
                    '$lookup' =>
                        [
                            'from' => 'game_user',
                            'localField' => 'userId',
                            'foreignField' => 'userId',
                            'as' => 'users'
                        ]
                ],
                /*[
                    '$lookup' =>
                        [
                            'from' => 'exchange_order',
                            'localField' => 'userId',
                            'foreignField' => 'userId',
                            'as' => 'exchangeOrders'
                        ]
                ],*/
                [
                    '$unwind' => '$users'
                ],
                [
                    '$project' =>
                        [
                            'promoterId' => 1,
                            'userId' => 1,
                            'score' => '$users.score',
                            'bankScore' => '$users.bankScore',
                            'roomCard' => '$users.roomCard',
                            'todayRechargeTotal' => 1,
                            'totalRechargeyNum' => 1,
                            'minRecharge' => 1,
                            'maxRecharge' => 1,
                            'avgRecharge' => 1,
                            'rechargeTotal' => '$users.rechargeAmount',
                            'exchangeTotal' => '$users.exchangeAmount',
                            'regDateTime' => '$users.regInfo.time',
                            'lastDateTime' => '$users.lastLogin.time',
                            'promoters' => '$promoters',
                            'exchangeOrders' => '$exchangeOrders'
                        ]
                ],
                [
                    '$sort' => $order//['rechargeTotal'=>-1]
                ]
            ])->toArray();//dd($rechargeOrderList);//dd($where);dd($exchangeWhere);
            foreach ($rechargeOrderList AS $key => $val) {
                $rechargeOrderList[$key]['score'] = $this->formatMoneyFromMongo($rechargeOrderList[$key]['score']);
                $rechargeOrderList[$key]['bankScore'] = $this->formatMoneyFromMongo($rechargeOrderList[$key]['bankScore']);
                if(!empty($rechargeOrderList[$key]['roomCard'])){
                    $rechargeOrderList[$key]['roomCard'] = $this->formatMoneyFromMongo($rechargeOrderList[$key]['roomCard']);
                }else{
                    $rechargeOrderList[$key]['roomCard'] = 0;
                }


                $rechargeOrderList[$key]['todayRechargeTotal'] = $this->formatMoneyFromMongo($rechargeOrderList[$key]['todayRechargeTotal']);
                $rechargeOrderList[$key]['minRecharge'] = $this->formatMoneyFromMongo($rechargeOrderList[$key]['minRecharge']);
                $rechargeOrderList[$key]['maxRecharge'] = $this->formatMoneyFromMongo($rechargeOrderList[$key]['maxRecharge']);
                $rechargeOrderList[$key]['avgRecharge'] = $this->formatMoneyFromMongo($rechargeOrderList[$key]['avgRecharge']);

                $rechargeOrderList[$key]['rechargeTotal'] = $this->formatMoneyFromMongo($rechargeOrderList[$key]['rechargeTotal']);
                $rechargeOrderList[$key]['exchangeTotal'] = $this->formatMoneyFromMongo($rechargeOrderList[$key]['exchangeTotal']);

                $rechargeOrderList[$key]['regDateTime'] = $this->formatDate($rechargeOrderList[$key]['regDateTime']);
                $rechargeOrderList[$key]['lastDateTime'] = $this->formatDate($rechargeOrderList[$key]['lastDateTime']);

                $promoters = $rechargeOrderList[$key]['promoters'][0]??[];
                if($promoters)
                    $rechargeOrderList[$key]['promoterName'] = $promoters['promoterName'];
                else
                    $rechargeOrderList[$key]['promoterName'] = '';

                $rechargeOrderList[$key]['todayExchangeTotal'] = 0;
                if (!empty($rechargeOrderList[$key]['exchangeOrders'])) {
                    foreach($rechargeOrderList[$key]['exchangeOrders'] as $item) {
                        $rechargeOrderList[$key]['todayExchangeTotal'] += $item->payMoney;
                    }
                }
                $rechargeOrderList[$key]['todayExchangeTotal'] = $this->formatMoneyFromMongo($rechargeOrderList[$key]['todayExchangeTotal']);
            }
            return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $rechargeOrderList]);
        }
    }
}
