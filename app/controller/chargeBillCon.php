<?php
namespace app\controller;

use support\bootstrap\Container;
use support\Request;
use app\model\GameUser;
use app\model\RechargeType;
use support\Db;

class ChargeBillCon extends Base
{
    public function rechargeBill(Request $request)
    {
        if ($request->isAjax()) {
            $data = $request->all();
            extract($data);
            $startTime = !empty($startDate) ? trim($startDate) : date("Y-m-d");
            $endTime = !empty($endDate) ? trim($endDate) : date("Y-m-d");
            $startTime = strtotime($startTime);
            $endTime = strtotime("$endTime +1 day");
            $startTimeMongo = $this->formatTimestampToMongo($startTime);
            $endTimeMongo = $this->formatTimestampToMongo($endTime);

            $where = [];
            $where['status'] = 4;
            $where['userId'] = ['$gte' => GameUser::COMMON_ACCOUNT_START_ID];
            $where['applyTime'] = ['$gte' => $startTimeMongo, '$lt' => $endTimeMongo];

            $whereDeduct = [];
            $whereDeduct['rechargeTypeId'] = 0;
            $whereDeduct['status'] = 4;
            $whereDeduct['userId'] = ['$gte' => GameUser::COMMON_ACCOUNT_START_ID];
            $whereDeduct['applyTime'] = ['$gte' => $startTimeMongo, '$lt' => $endTimeMongo];
            $whereDeduct['rechargeMoney'] = ['$lt' => 0];

            $count_array = Db::connection('mongodb_main')->collection('recharge_order')->raw()->aggregate([
                [
                    '$match' => $where
                ],
                [
                    '$project' =>
                        [
                            'rechargeServiceId' => 1,
                            'rechargeProviderId' => 1,
                            'rechargeTypeId' => 1
                        ]
                ],
                [
                    '$group' =>
                        [
                            '_id' =>
                                [
                                    'rechargeTypeId' => '$rechargeTypeId'
                                ]
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

            $recharge_order_list = Db::connection('mongodb_main')->collection('recharge_order')->raw()->aggregate([
                [
                    '$match' => $where
                ],
                [
                    '$project' =>
                        [
                            'rechargeTypeId' => 1,
                            'rechargeMoney' => 1
                        ]
                ],
                [
                    '$group' =>
                        [
                            '_id' =>
                                [
                                    'rechargeTypeId' => '$rechargeTypeId'
                                ],
                            'rechargeNum' => ['$sum' => 1],
                            'rechargeTotal' => ['$sum' => '$rechargeMoney']
                        ]
                ],
                [
                    '$project' =>
                        [
                            'rechargeTypeId'=>'$_id.rechargeTypeId',
                            'rechargeNum' => 1,
                            'rechargeTotal'=> 1
                        ]
                ],
            ])->toArray();

            $rechargeDeductList = Db::connection('mongodb_main')->collection('recharge_order')->raw()->aggregate([
                [
                    '$match' => $whereDeduct
                ],
                [
                    '$project' =>
                        [
                            'rechargeTypeId' => 1,
                            'rechargeMoney' => 1
                        ]
                ],
                [
                    '$group' =>
                        [
                            '_id' =>
                                [
                                    'rechargeTypeId' => '$rechargeTypeId'
                                ],
                            'rechargeNum' => ['$sum' => 1],
                            'rechargeTotal' => ['$sum' => '$rechargeMoney']
                        ]
                ],
                [
                    '$project' =>
                        [
                            'rechargeTypeId'=>'$_id.rechargeTypeId',
                            'rechargeNum' => 1,
                            'rechargeTotal'=> 1
                        ]
                ],
            ])->toArray();

            $rechargeDeductList[0]['rechargeNum'] = $rechargeDeductList[0]['rechargeNum'] ?? 0;
            $rechargeDeductList[0]['rechargeTotal'] = $rechargeDeductList[0]['rechargeTotal'] ?? 0;

            $recharge_type_list = RechargeType::select('rechargeTypeId','rechargeTypeName')->get()->toArray();
            $recharge_type_list = array_merge($recharge_type_list, [['rechargeTypeId'=>0, 'rechargeTypeName'=>'官方补发'], ['rechargeTypeId'=>99999999, 'rechargeTypeName'=>'支付补发']]);
            $recharge_order_list = merge_array($recharge_order_list, $recharge_type_list, 'rechargeTypeId');
            foreach ($recharge_order_list AS $key => $val) {
                if($recharge_order_list[$key]['rechargeTypeId'] == 0){
                    $recharge_order_list[$key]['rechargeNum'] = $recharge_order_list[$key]['rechargeNum']."(".$rechargeDeductList[0]['rechargeNum'].")";
                    $recharge_order_list[$key]['rechargeTotal'] = $this->formatMoneyFromMongo($recharge_order_list[$key]['rechargeTotal'])."(".$this->formatMoneyFromMongo($rechargeDeductList[0]['rechargeTotal']).")";
                    $recharge_order_list[$key]['rechargeTypeName'] = $recharge_order_list[$key]['rechargeTypeName']??'未知补发';
                }else{
                    $recharge_order_list[$key]['rechargeTotal'] = $this->formatMoneyFromMongo($recharge_order_list[$key]['rechargeTotal']);
                    $recharge_order_list[$key]['rechargeTypeName'] = $recharge_order_list[$key]['rechargeTypeName']??'未知补发';
                }
            }
            return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $recharge_order_list]);
        }
    }

    public function exchangeBill(Request $request)
    {
        if ($request->isAjax()) {
            $data = $request->all();
            extract($data);
            $startTime = !empty($startDate) ? trim($startDate) : date("Y-m-d");
            $endTime = !empty($endDate) ? trim($endDate) : date("Y-m-d");
            $startTime = strtotime($startTime);
            $endTime = strtotime("$endTime +1 day");
            $startTimeMongo = $this->formatTimestampToMongo($startTime);
            $endTimeMongo = $this->formatTimestampToMongo($endTime);

            $where = [];
            $where['status'] = 18;
            $where['userId'] = ['$gte' => GameUser::COMMON_ACCOUNT_START_ID];
            $where['applyTime'] = ['$gte' => $startTimeMongo, '$lt' => $endTimeMongo];

            $count_array = Db::connection('mongodb_main')->collection('exchange_order')->raw()->aggregate([
                [
                    '$match' => $where
                ],
                [
                    '$project' =>
                        [
                            'withdrawType' => 1
                        ]
                ],
                [
                    '$group' =>
                        [
                            '_id' =>
                                [
                                    'withdrawType' => '$withdrawType'
                                ]
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


            $exchange_order_list = Db::connection('mongodb_main')->collection('exchange_order')->raw()->aggregate([
                [
                    '$match' => $where
                ],
                [
                    '$project' =>
                        [
                            'withdrawType' => 1,
                            'requestMoney' => 1,
                        ]
                ],
                [
                    '$group' =>
                        [
                            '_id' =>
                                [
                                    'withdrawType' => '$withdrawType'
                                ],
                            'exchangeNum' => ['$sum' => 1],
                            'exchangeTotal' => ['$sum' => '$requestMoney']
                        ]
                ],
                [
                    '$project' =>
                        [
                            'withdrawType'=>'$_id.withdrawType',
                            'exchangeNum' => 1,
                            'exchangeTotal'=> 1
                        ]
                ]
            ])->toArray();

            foreach ($exchange_order_list as $key => $value) {
                if($exchange_order_list[$key]["withdrawType"] == 2)
                    $exchange_order_list[$key]["withdrawTypeName"] = '支付宝';
                else if($exchange_order_list[$key]["withdrawType"] == 3)
                    $exchange_order_list[$key]["withdrawTypeName"] = '银行卡';
                else if($exchange_order_list[$key]["withdrawType"] == 4)
                    $exchange_order_list[$key]["withdrawTypeName"] = 'USDT';
                else if($exchange_order_list[$key]["withdrawType"] == 5)
                    $exchange_order_list[$key]["withdrawTypeName"] = '转余额';
                else
                    $exchange_order_list[$key]["withdrawTypeName"] = '未知支付方式';
                $exchange_order_list[$key]["exchangeTotal"] = $this->formatMoneyFromMongo($exchange_order_list[$key]["exchangeTotal"]);
            }
            return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $exchange_order_list]);
        }
    }

    public function daiLiToYuE (Request $request)
    {
        if ($request->isAjax()) {
            $data = $request->all();
            extract($data);
            $startTime = !empty($startDate) ? trim($startDate) : date("Y-m-d");
            $endTime = !empty($endDate) ? trim($endDate) : date("Y-m-d");
            $startTime = strtotime($startTime);
            $endTime = strtotime("$endTime +1 day");
            $startTimeMongo = $this->formatTimestampToMongo($startTime);
            $endTimeMongo = $this->formatTimestampToMongo($endTime);

            $where = [];
            $where['status'] = 18;
            $where['promoterId'] = ['$gte' => 1000];
            $where['applyTime'] = ['$gte' => $startTimeMongo, '$lt' => $endTimeMongo];

            $count_array = Db::connection('mongodb_main')->collection('promoter_exchange_order')->raw()->aggregate([
                [
                    '$match' => $where
                ],
                [
                    '$project' =>
                        [
                            'withdrawType' => 1
                        ]
                ],
                [
                    '$group' =>
                        [
                            '_id' =>
                                [
                                    'withdrawType' => '$withdrawType'
                                ]
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


            $exchange_order_list = Db::connection('mongodb_main')->collection('promoter_exchange_order')->raw()->aggregate([
                [
                    '$match' => $where
                ],
                [
                    '$project' =>
                        [
                            'withdrawType' => 1,
                            'requestMoney' => 1,
                        ]
                ],
                [
                    '$group' =>
                        [
                            '_id' =>
                                [
                                    'withdrawType' => '$withdrawType'
                                ],
                            'exchangeNum' => ['$sum' => 1],
                            'exchangeTotal' => ['$sum' => '$requestMoney']
                        ]
                ],
                [
                    '$project' =>
                        [
                            'withdrawType'=>'$_id.withdrawType',
                            'exchangeNum' => 1,
                            'exchangeTotal'=> 1
                        ]
                ]
            ])->toArray();

            foreach ($exchange_order_list as $key => $value) {
                $exchange_order_list[$key]["withdrawTypeName"] = '代理转余额';
                $exchange_order_list[$key]["exchangeTotal"] = $this->formatMoneyFromMongo($exchange_order_list[$key]["exchangeTotal"]);
            }
            return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $exchange_order_list]);
        }
    }
}
