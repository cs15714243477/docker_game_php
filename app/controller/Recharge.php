<?php
namespace app\controller;

use app\model\GameUser;
use app\model\RechargeOrder;
use app\model\RewardOrder;
use app\model\ClubRewardOrder;
use app\model\SystemConfig;
use app\model\UserActivityRecord;
use app\model\UserScoreChange;
use app\model\ClubUserScoreChange;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use support\Db;
use support\Request;

class Recharge extends Base
{
    private function _ajaxParam()
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
        }
        $startTime = strtotime($startDate);
        $endTime = strtotime("$endDate +1 day");
        if ($startTime >= $endTime) return json(['code' => -1, 'msg' => '请核对开始时间结束时间']);
        if(isset($timeType) && $timeType == 1){
            $where[] = ['createTime', '>=', $this->formatTimestampToMongo($startTime)];
            $where[] = ['createTime', '<', $this->formatTimestampToMongo($endTime)];
        }else{
            $where[] = ['applyTime', '>=', $this->formatTimestampToMongo($startTime)];
            $where[] = ['applyTime', '<', $this->formatTimestampToMongo($endTime)];
        }
        //输入框优先于下拉框
        if (!empty($searchText)) {
            $searchTextLen = strlen($searchText);
            if(in_array($searchTextLen, [6, 8])) {
                $where['userId'] = (int)$searchText;
            }elseif (in_array($searchTextLen, [27, 25])) {
                $where['orderId'] = $searchText;
            }else {
                return json(['code' => -1, 'msg' => '会员ID长度是6位或者8位数字，订单号是25或27位字符']);
            }
        } elseif (!empty($isSys)) {
            if ($isSys == GameUser::COMMON_ACCOUNT) {
                $where[] = ['userId', '>=', GameUser::COMMON_ACCOUNT_START_ID];
            } elseif ($isSys == GameUser::SYSTEM_ACCOUNT) {
                $where[] = ['userId', '<', GameUser::COMMON_ACCOUNT_START_ID];
            }
        } else {
            $where[] = ['userId', '>=', GameUser::COMMON_ACCOUNT_START_ID];
        }
        if (!empty($status)) {
            $where['status'] = (int)$status;
        }
        if (!empty($sp)) {
            if($sp == 1 && $offline){
                $where['rechargeTypeId'] = (int)$offline;
            }elseif($sp == 2 && $online){
                $where['rechargeTypeId'] = (int)$online;
            }
            $where['sp'] = (int)$sp;
        }
        return $where;
    }

    public function rechargeList(Request $request)
    {
       if($request->isAjax()) {
           $where = $this->_ajaxParam();
           if (!is_array($where)) return $where;
           $rechargeTypeIdNameKV = RechargeType::rechargeTypeIdNameKV();
           $count = RechargeOrder::where($where)->count();
           $list = RechargeOrder::where($where)->orderBy('createTime', 'desc')->skip($request->skip)->take($request->limit)->get()->toArray();
           $userIdArr = array_column($list, 'userId'); $userIdArr = array_unique($userIdArr);
           $userList = GameUser::whereIn('userId', $userIdArr)->select('userId','rechargeAmount','exchangeAmount')->get()->toArray();
           $list = merge_array($list, $userList, 'userId');
           foreach ($list as &$item) {
               $item['rechargeTypeName'] = $item['rechargeTypeName'] ?? $rechargeTypeIdNameKV[$item['rechargeTypeId']];
               $item['rechargeAmount'] = !empty($item['rechargeAmount']) ? $this->formatMoneyFromMongo($item['rechargeAmount']) : 0;
               $item['exchangeAmount'] = !empty($item['exchangeAmount']) ? $this->formatMoneyFromMongo($item['exchangeAmount']) : 0;
               $item['requestMoney'] = !empty($item['requestMoney']) ? $this->formatMoneyFromMongo($item['requestMoney']) : 0;
               $item['rechargeMoney'] = !empty($item['rechargeMoney']) ? $this->formatMoneyFromMongo($item['rechargeMoney']) : 0;

               $actTimeResult = $this->diffTime($item['createTime'], $item['applyTime']);
               $item['actTime'] = Sec3Time($actTimeResult);
               $item['createTime'] = $this->formatDate($item['createTime']);
               $item['applyTime'] = $this->formatDate($item['applyTime']);

               $item['usdtRate'] = !empty($item['usdtRate']) ? $this->formatMoneyFromMongo($item['usdtRate']) : 0;
               $item['usdt'] = !empty($item['usdt']) ? $this->formatMoneyFromMongo($item['usdt']) : 0;
               if (!empty($item['account'])) {
                   $AccountArr = json_decode($item['account'], true);
                   //usdtType:number; // USDT 类型1 为ETC20  2 TRC20
                   if (isset($item['usdtType']) && in_array($item['usdtType'], [1, 2])) {
                       $item['accountNo'] = $AccountArr[$item['usdtType']]??$AccountArr['no'];
                       $item['accountName'] = '';
                   } else {
                       $item['accountNo'] = $AccountArr['no'];
                       $item['accountName'] = $AccountArr['name'];
                   }
               }

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

            $requestMoneySum = number_format($requestMoneySum, 2);
            $rechargeMoneySum = RechargeOrder::where($where)->sum('rechargeMoney');

            $rechargeMoneySum = $this->formatMoneyFromMongo($rechargeMoneySum);
            $rechargeMoneySum = number_format($rechargeMoneySum, 2);

            return json(['code' => 0, 'msg' => 'ok', 'data' => ['requestMoneySum' => $requestMoneySum, 'rechargeMoneySum' => $rechargeMoneySum]]);
        }
    }

    public function exportRecharge(Request $request)
    {
        $where = $this->_ajaxParam();
        if (!is_array($where)) return $where;
        //$rechargeTypeIdNameKV = RechargeType::rechargeTypeIdNameKV();
        $cursor = RechargeOrder::where($where)->orderBy('createTime', 'desc')->cursor();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', '订单号');
        $sheet->setCellValue('B1', '会员ID');
        $sheet->setCellValue('C1', '手机号');
        $sheet->setCellValue('D1', '支付类型');
        $sheet->setCellValue('E1', '提交金额');
        $sheet->setCellValue('F1', '实际付款');
        $sheet->setCellValue('G1', '收款信息');
        $sheet->setCellValue('H1', '订单状态');
        $sheet->setCellValue('I1', '总充');
        $sheet->setCellValue('J1', '总提');
        $sheet->setCellValue('K1', '提交时间');
        $sheet->setCellValue('L1', '处理时间');
        $sheet->setCellValue('M1', '耗时');
        $sheet->setCellValue('N1', '操作记录');
        $sheet->setCellValue('O1', '备注');

        $num = 2;
        $session = $request->session();
        foreach ($cursor as $item) {
            $user = GameUser::where('userId', $item->userId)->select('userId','rechargeAmount','exchangeAmount','mobile')->first();
            $sheet->setCellValue("A{$num}", $item->orderId);
            $sheet->setCellValue("B{$num}", $item->userId);
            if($session->get('userName') == "admin"){
                $sheet->setCellValue("C{$num}", mobile($user->mobile));
            }else{
                $sheet->setCellValue("C{$num}", '');
            }
            $sheet->setCellValue("D{$num}", $item->rechargeTypeName);
            $sheet->setCellValue("E{$num}", $this->formatMoneyFromMongo($item->requestMoney));
            $sheet->setCellValue("F{$num}", $this->formatMoneyFromMongo($item->rechargeMoney));
            $sheet->setCellValue("G{$num}", $item->account??'');
            $sheet->setCellValue("H{$num}", RechargeOrder::STATUS[$item->status]);
            $sheet->setCellValue("I{$num}", $this->formatMoneyFromMongo($user->rechargeAmount));
            $sheet->setCellValue("J{$num}", $this->formatMoneyFromMongo($user->exchangeAmount));
            $sheet->setCellValue("K{$num}", $this->formatDate($item->createTime));
            $sheet->setCellValue("L{$num}", $this->formatDate($item->applyTime));
            $sheet->setCellValue("M{$num}", Sec3Time($this->diffTime($item->createTime, $item->applyTime)));
            $sheet->setCellValue("N{$num}", $item->reason??'');
            $sheet->setCellValue("O{$num}", $item->remark??'');

            $num++;
        }
        $writer = new Xlsx($spreadsheet);
        $file_path = public_path().'/recharge.xlsx';
        // 保存文件到 public 下
        $writer->save($file_path);

        return json(['code' => 0, 'msg' => 'ok', 'file' => $file_path]);
    }

    public function confirm(Request $request)
    {
        $_id = $request->post('_id', 0);
        if (empty($_id)) return json(['code' => -1, 'msg' => '参数错误']);
        $session = DB::connection('mongodb_main')->getMongoClient()->startSession();
        $session->startTransaction();
        try {
            $where = ['_id' => $_id];
            $order = RechargeOrder::where($where)->select('userId','requestMoney','rechargeTypeId','usdtAmount','status')->first();
            if (empty($order)) {
                $session->abortTransaction();
                return json(['code' => -1, 'msg' => '订单不存在']);
            }
            if ($order->status != RechargeOrder::ORDER_STATUS_PAID) {
                $session->abortTransaction();
                return json(['code' => -1, 'msg' => '订单状态有误']);
            }

            $userId = $order->userId;
            $rechargeMoney = $order->requestMoney;
            //修改个人信息

            $user = GameUser::where('userId', $userId)->select('userId','vip','score','bankScore','rechargeAmount','rechargeTimes')->first();
            if (empty($user)) {
                $session->abortTransaction();
                return json(['code' => -1, 'msg' => '修改分数失败,该用户不存在！']);
            }
            $beforeScore = $user->score;
            $updateData = [
                'rechargeAmount' => $user->rechargeAmount + $rechargeMoney,
                'score' => $user->score + $rechargeMoney,
                'rechargeTimes' => $user->rechargeTimes + 1
            ];
            $updateResult = GameUser::where('userId', $userId)->update($updateData, ['session' => $session]);
            if (!$updateResult) {
                $session->abortTransaction();
                return json(['code' => -1, 'msg' => '修改分数失败,该用户不存在！']);
            }

            $systemConfig = SystemConfig::select('vips')->first();
            $vips = $systemConfig['vips'];
            $newVip = $user->vip;
            foreach ($vips as $val) {
                if(($user->rechargeAmount + $rechargeMoney) < $val['value']){
                    $newVip = $val['level'] - 1;
                    break;
                }
                if(($user->rechargeAmount + $rechargeMoney) >= $val['value']){
                    $newVip = $val['level'];
                }
            }
            if($newVip > $user->vip) {
                $updateData = [
                    'vip' => $newVip
                ];
                $updateResult = GameUser::where('userId', $userId)->update($updateData, ['session' => $session]);
                if (!$updateResult) {
                    $session->abortTransaction();
                    return json(['code' => -1, 'msg' => '修改vip失败！']);
                }
            }

            $insertData = [
                'userId' => $userId,
                'beforeScore' => $beforeScore,
                'beforeBankScore' => $user->bankScore,
                'addScore' => $rechargeMoney,
                'addBankScore' => 0,
                'afterScore' => $user->score + $rechargeMoney,
                'afterBankScore' => $user->bankScore,
                'changeType' => 3,
                'roomId' => 0,
                'createTime' => new \MongoDB\BSON\UTCDateTime
            ];
            $insertResult = UserScoreChange::raw()->insertOne($insertData, ['session' => $session]);
            if (!$insertResult) {
                $session->abortTransaction();
                return json(['code' => -1, 'msg' => '添加分值改变记录失败']);
            }

            //修改订单状态 补发信息
            $updateData = [
                'status' => RechargeOrder::ORDER_STATUS_FINISH,
                'rechargeMoney' => $rechargeMoney,
                'applyTime' => new \MongoDB\BSON\UTCDateTime,
                'reason' => "【" . session('userName') . "】确认【".$userId."】线下充值金币:".round($rechargeMoney*0.01, 2),
            ];
            $updateResult = RechargeOrder::where($where)->update($updateData, ['session' => $session]);
            if (!$updateResult) {
                $session->abortTransaction();
                return json(['code' => -1, 'msg' => '订单状态更新失败！']);
            }
            $session->commitTransaction();
            $this->adminLog(["content" => "【" . session('userName') . "】确认【".$userId."】线下充值金币:".round($rechargeMoney*0.01, 2)]);
            $info = "充值到账" . round($rechargeMoney*0.01,2) . "金币";
            sendData2(['userId'=>$userId, 'orderId'=>$order->orderId, 'type'=>0, 'money'=>$rechargeMoney, 'status'=>1, 'info'=>$info]);
            return json(['code' => 0, 'msg' => '充值成功', 'info' => $rechargeMoney]);

        } catch(\Exception $e) {
            $session->abortTransaction();
            json(['code' => -1, 'msg' => '订单状态更新失败！']);
        }

    }

    public function cancle(Request $request)
    {
        $_id = $request->post('_id', 0);
        if (empty($_id)) return json(['code' => -1, 'msg' => '参数错误']);
        $where = ['_id' => $_id];
        $order = RechargeOrder::where($where)->select('userId','rechargeMoney','rechargeTypeId','usdtAmount','status')->first();
        if (empty($order)) {
            return json(['code' => -1, 'msg' => '订单不存在']);
        }
        $userId = $order->userId;
        $rechargeMoney = $order->rechargeMoney;

        $updateData = [
            'status' => RechargeOrder::ORDER_STATUS_CANCLE,
            'applyTime' => new \MongoDB\BSON\UTCDateTime,
            'reason' => "取消【".$userId."】线下充值金币:".round($rechargeMoney*0.01, 2),
        ];
        $updateResult = RechargeOrder::where($where)->update($updateData);
        if (!$updateResult) {
            return json(['code' => -1, 'msg' => '订单状态更新失败！']);
        }
        $this->adminLog(["content"=>"取消【".$userId."】线下充值金币:".round($rechargeMoney*0.01, 2)]);
        return json(['code' => 0, 'msg' => '取消成功']);
    }

    public function incscore(Request $request)
    {
        $postData = check_type($request->post());
        extract($postData);
        if (empty($userId) || empty($rechargeMoney)) return json(['code' => -1, 'msg' => '传入参数错误']);
        $rechargeMoney = intval($rechargeMoney * 100);
        $where = [
            'userId' => $userId,
            'status' => GameUser::USER_STATUS_ON,
        ];
        $user = Player::getPlayer($where, ['userId','score','bankScore','promoterId','rechargeAmount','rechargeTimes','rewardScore','clubRewardScore']);
        if (empty($user)) return json(['code' => -1, 'msg' => '会员不存在']);
        $orderId = '';
        //订单入库（充值订单/奖励订单）--修改会员信息/VIP--分值变化--发通知
        $session = DB::connection('mongodb_main')->getMongoClient()->startSession();
        $session->startTransaction();
        try {
            if ($rechargeChannelId == GIVE) {
                $changeType = 8;
            } elseif ($rechargeChannelId == ACTIVITY_REWARD) {
                if (empty($rewardType)) return json(['code' => -1, 'msg' => '请选择活动奖励']);
                if (($rewardType == 100 || $rewardType == 134) && Recharge::checkUserReward($userId, $rewardType)) return json(['code' => -1, 'msg' => RewardOrder::REWARD_TYPE_FOR_GIVE_SCORE[$rewardType] . '已经送过了']);
                $changeType = $rewardType;
                $timeStr = date("YmdHis",time());
                $randStr = (string)rand(1000,9999);
                $orderId = "HDJL".$timeStr."-".(string)$userId."-".$randStr;
                $rewardOrderData = [
                    'orderId' => $orderId,
                    'userId' => $userId,
                    'promoterId' => $user->promoterId,
                    'rewardType' => $rewardType,
                    'userTaskId' => 0,
                    'dirPlayUserId' => 0,
                    'taskId' => 0,
                    'activityId' => 0,
                    'activityType' => $rewardType,
                    'requestMoney' => $rechargeMoney,
                    'rechargeMoney' => $rechargeMoney,
                    'status' => RewardOrder::ORDER_STATUS_FINISH,
                    'createTime' => new \MongoDB\BSON\UTCDateTime,
                    'reason' => $remark,
                    'operator' => session('userName')
                ];

                $insertResult = RewardOrder::raw()->insertOne($rewardOrderData, ['session' => $session]);
                if (!$insertResult) {
                    $session->abortTransaction();
                    return json(['code' => -1, 'msg' => '添加奖励订单记录失败']);
                }
                if ($rewardType == '101') {
                    $insertData = [
                        'userId' => $userId,
                        'activityId' => $rewardType,
                        'allBet' => 0,
                        'validBet' => 0,
                        'status' => 1,
                        'createTime' => new \MongoDB\BSON\UTCDateTime,
                    ];
                    $insertResult = UserActivityRecord::raw()->insertOne($insertData, ['session' => $session]);
                    if (!$insertResult) {
                        $session->abortTransaction();
                        return json(['code' => -1, 'msg' => '添加奖励订单记录失败2']);
                    }
                }

            } elseif ($rechargeChannelId == CLUB_ACTIVITY_REWARD) {
                if (empty($clubRewardType)) return json(['code' => -1, 'msg' => '请选择奖励类型']);
                $changeType = $clubRewardType;
                $timeStr = date("YmdHis",time());
                $randStr = (string)rand(1000,9999);
                $orderId = "CLUBJL".$timeStr."-".(string)$userId."-".$randStr;
                $clubRewardOrderData = [
                    'orderId' => $orderId,
                    'userId' => $userId,
                    'promoterId' => $user->promoterId,
                    'rewardType' => 27,
                    'userTaskId' => 0,
                    'dirPlayUserId' => 0,
                    'taskId' => 0,
                    'activityId' => $clubRewardType,
                    'activityType' => $clubRewardType,
                    'requestMoney' => $rechargeMoney,
                    'rechargeMoney' => $rechargeMoney,
                    'status' => ClubRewardOrder::ORDER_STATUS_FINISH,
                    'createTime' => new \MongoDB\BSON\UTCDateTime,
                    'reason' => "俱乐部活动奖励".$remark,
                    'operator' => session('userName')
                ];

                $insertResult = ClubRewardOrder::raw()->insertOne($clubRewardOrderData, ['session' => $session]);
                if (!$insertResult) {
                    $session->abortTransaction();
                    return json(['code' => -1, 'msg' => '添加俱乐部奖励订单记录失败']);
                }
            }else {
                $changeType = 6;
                $sp = RechargeOrder::CLASSIFY_SYSTEM_REISSUE;
                $rechargeTypeId = 0;
                $rechargeTypeName = '官方补发';
                if ($rechargeChannelId == 99999999) {
                    $changeType = 7;
                    $sp = RechargeOrder::CLASSIFY_PAYMENT_REISSUE;
                    $rechargeTypeId = 99999999;
                    $rechargeTypeName = '支付补发';
                }
                $orderId = strtoupper("XX" . orderNumber() . $userId);
                $ip = GetIP();
                $rechargeOrderData = [
                    'orderId' => $orderId,
                    'userId' => $userId,
                    'rechargeServiceId' => 0,  //充值服务id
                    'rechargeTypeId' => $rechargeTypeId,     //充值类型id
                    'rechargeTypeName' => $rechargeTypeName,
                    'rechargeProviderId' => 0, //充值银商Id
                    'requestMoney' => $rechargeMoney,
                    'rechargeMoney' => $rechargeMoney,
                    'isBuFa' => true,
                    'BFAccount' => session('userName'),
                    'promoterId' => $user->promoterId, //注册渠道id
                    'sp' => $sp,  //第三方支付合作商ID
                    'tid' => '', //第三方订单ID
                    'ip' => $ip,
                    'status' => 4, //`status` tinyint(4) NOT NULL COMMENT '状态 1.未支付 2.已支付 3.已退款',
                    'createTime' => new \MongoDB\BSON\UTCDateTime,
                    'applyTime' => new \MongoDB\BSON\UTCDateTime,
                    'reason' => "【".session('userName')."】充值【".$userId."】金币:". $this->formatMoneyFromMongo($rechargeMoney),
                    'remark' => $remark,
                ];
                $insertResult = RechargeOrder::raw()->insertOne($rechargeOrderData, ['session' => $session]);
                if (!$insertResult) {
                    $session->abortTransaction();
                    return json(['code' => -1, 'msg' => '添加充值订单记录失败']);
                }
            }


            if($rechargeChannelId == ACTIVITY_REWARD || $rechargeChannelId == CLUB_ACTIVITY_REWARD){
                $updateData = [
                    'score' => $user->score + $rechargeMoney,
                ];
                if($rechargeChannelId == ACTIVITY_REWARD){
                    $updateData['rewardScore'] = $user->rewardScore + $rechargeMoney;
                }
                if($rechargeChannelId == CLUB_ACTIVITY_REWARD){
                    $updateData['clubRewardScore'] = $user->clubRewardScore + $rechargeMoney;
                }
            }else {
                $updateData = [
                    'score' => $user->score + $rechargeMoney,
                    'rechargeAmount' => $user->rechargeAmount + $rechargeMoney,
                    'rechargeTimes' => $user->rechargeTimes + 1,
                ];
                $newVip = Player::getPlayerNewVip($updateData['rechargeAmount']);
                if ($newVip > $user->vip) $updateData['vip'] = $newVip;
            }

            $updateResult = GameUser::where($where)->update($updateData, ['session' => $session]);
            if (!$updateResult) {
                $session->abortTransaction();
                return json(['code' => -1, 'msg' => '修改用户数据记录失败']);
            }


            if($rechargeChannelId == CLUB_ACTIVITY_REWARD){
                $insertData = [
                    'userId' => $userId,
                    'beforeScore' => $user->score,
                    'beforeBankScore' => $user->bankScore,
                    'addScore' => $rechargeMoney,
                    'addBankScore' => 0,
                    'afterScore' => $user->score + $rechargeMoney,
                    'afterBankScore' => $user->bankScore,
                    'changeType' => $changeType,
                    'roomId' => 0,
                    'clubId' => 0,
                    'createTime' => new \MongoDB\BSON\UTCDateTime,
                    //'refId' => '',
                    'remark' => $remark,
                ];
                $insertResult = ClubUserScoreChange::raw()->insertOne($insertData, ['session' => $session]);
            }else{
                $insertData = [
                    'userId' => $userId,
                    'beforeScore' => $user->score,
                    'beforeBankScore' => $user->bankScore,
                    'addScore' => $rechargeMoney,
                    'addBankScore' => 0,
                    'afterScore' => $user->score + $rechargeMoney,
                    'afterBankScore' => $user->bankScore,
                    'changeType' => $changeType,
                    'roomId' => 0,
                    'createTime' => new \MongoDB\BSON\UTCDateTime,
                    //'refId' => '',
                    'remark' => $remark,
                ];
                $insertResult = UserScoreChange::raw()->insertOne($insertData, ['session' => $session]);
            }

            if (!$insertResult) {
                $session->abortTransaction();
                return json(['code' => -1, 'msg' => '添加分值改变记录失败']);
            }

            $session->commitTransaction();
            $this->adminLog(["content"=>session("userName")."充值【".$userId."】金币:".$this->formatMoneyFromMongo($rechargeMoney)]);
            $rewardTypeForGiveScore = RewardOrder::REWARD_TYPE_FOR_GIVE_SCORE;
            $clubRewardTypeForGiveScore = ClubRewardOrder::CLUB_REWARD_TYPE_FOR_GIVE_SCORE;
            $info = '';
            if ($changeType == 8) {
                $info = "赠送" . $this->formatMoneyFromMongo($rechargeMoney) . "金币已到账";
            }elseif (in_array($changeType, [6, 7])) {
                $info = "充值到账" . $this->formatMoneyFromMongo($rechargeMoney) . "金币";
            }elseif (isset($rewardTypeForGiveScore[$changeType])) {
                $info = "到账" . $this->formatMoneyFromMongo($rechargeMoney) . "金币";
            }elseif (isset($clubRewardTypeForGiveScore[$changeType])) {
                $info = "到账" . $this->formatMoneyFromMongo($rechargeMoney) . "金币";
            }
            sendData2(['userId'=>$userId, 'orderId'=>$orderId, 'type'=>0, 'money'=>$rechargeMoney, 'status'=>1, 'info'=>$info]);
            return json(['code' => 0, 'msg' => '充值成功']);
        } catch (\Exception $e) {
            $session->abortTransaction();
            json(['code' => -1, 'msg' => '充值失败！']);
        }


    }

    //检测会员是否参与过某个活动奖励(60天内)
    public static function checkUserReward($userId, $rewardType) {
        $where = ['userId' => $userId, 'rewardType' => $rewardType];
        $startTime = strtotime(date("Y-m-d")) - 60*24*3600;
        $where['createTime'] = ['$gte' => formatTimestampToMongo($startTime)];
        $rs = RewardOrder::where($where)->first();
        if (empty($rs)) return false;
        return true;
    }

}
