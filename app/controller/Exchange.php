<?php
namespace app\controller;

use app\model\Activity;
use app\model\AgentExchangeOrder;
use app\model\PlayRecord;
use app\model\PromoterExchangeOrder;
use app\model\ExchangeOrder;
use app\model\ExchangeRejectReason;
use app\model\GameUser;
use app\model\RechargeOrder;
use app\model\RewardOrder;
use app\model\UserScoreChange;
use MongoDB\BSON\UTCDateTime;
use IpLocation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use support\Db;
use support\Request;

class Exchange extends Base
{
    public function index(Request $request)
    {

    }

    private function _ajaxParam()
    {
        $where = ['cashUp' => 1];
        $request = request();
        $getData = check_type($request->all());//dd($getData);
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

        if(isset($timeType) && $timeType == 2){
            $where[] = ['applyTime', '>=', $this->formatTimestampToMongo($startTime)];
            $where[] = ['applyTime', '<', $this->formatTimestampToMongo($endTime)];
        }else{
            $where[] = ['createTime', '>=', $this->formatTimestampToMongo($startTime)];
            $where[] = ['createTime', '<', $this->formatTimestampToMongo($endTime)];
        }

        //输入框优先于下拉框
        if (!empty($searchText)) {
            $searchTextLen = strlen($searchText);
            if(in_array($searchTextLen, [6, 8])) {
                $where['userId'] = (int)$searchText;
            }elseif (str_contains($searchText, 'TX')) {
                $where['orderId'] = $searchText;
            }else {
                return json(['code' => -1, 'msg' => '会员ID长度是6位或者8位数字，订单号是27位字符']);
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
        if (!empty($withdrawType)) {
            if($withdrawType == 1){
                $where[] = ['withdrawType', 'in', [ExchangeOrder::WITHDRAWTYPE_BANKCARD, ExchangeOrder::WITHDRAWTYPE_EBANK]];
            }else{
                $where['withdrawType'] = $withdrawType;
            }
        }
        if (!empty($trueName)) {
            $where2 = ['trueName' => $trueName];
            $user = GameUser::where($where2)->select('userId')->first();
            if(empty($user)) return json(['code' => -1, 'msg' => '真实姓名会员不存在']);
            $where['userId'] = $user->userId;
        }
        return $where;
    }

    public function exchangeList(Request $request)
    {
       if($request->isAjax()) {
           $where = $this->_ajaxParam();
           if (!is_array($where)) return $where;
           //$rechargeTypeIdNameKV = RechargeType::rechargeTypeIdNameKV();
           $count = ExchangeOrder::where($where)->count();
           $list = ExchangeOrder::where($where)->orderBy('createTime', 'desc')->skip($request->skip)->take($request->limit)->get()->toArray();
           $userIdArr = array_column($list, 'userId'); $userIdArr = array_unique($userIdArr);
           $userList = GameUser::whereIn('userId', $userIdArr)->select('userId','score','bankScore','rechargeAmount','exchangeAmount','regInfo','trueName','nickName')->get()->toArray();
           $list = merge_array($list, $userList, 'userId');
           $ipLocation = new IpLocation();
           foreach ($list as &$item) {
               if(!empty($item['regInfo']['ip'])){
                   $location = $ipLocation->getlocation($item['regInfo']['ip']);
                   $item['address'] = $location['country'] . $location['area'];
               }else{
                   $location = "";
                   $item['address'] = "";
               }

               //$item['rechargeTypeName'] = $item['rechargeTypeName'] ?? $rechargeTypeIdNameKV[$item['rechargeTypeId']];
               $item['rechargeAmount'] = !empty($item['rechargeAmount']) ? $this->formatMoneyFromMongo($item['rechargeAmount']) : 0;
               $item['exchangeAmount'] = !empty($item['exchangeAmount']) ? $this->formatMoneyFromMongo($item['exchangeAmount']) : 0;
               $item['requestMoney'] = !empty($item['requestMoney']) ? $this->formatMoneyFromMongo($item['requestMoney']) : 0;
               $item['payMoney'] = !empty($item['payMoney']) ? $this->formatMoneyFromMongo($item['payMoney']) : 0;
               $item['tax'] = !empty($item['tax']) ? $this->formatMoneyFromMongo($item['tax']) : 0;
               $item['score'] = !empty($item['score']) ? $this->formatMoneyFromMongo($item['score']) : 0;
               $item['bankScore'] = !empty($item['bankScore']) ? $this->formatMoneyFromMongo($item['bankScore']) : 0;
               $item['usdt'] = !empty($item['usdt']) ? $this->formatMoneyFromMongo($item['usdt']) : 0;
               $item['usdtRate'] = !empty($item['usdtRate']) ? $this->formatMoneyFromMongo($item['usdtRate']) : 0;
               if ($item['applyTime'] == '0') {
                   $item['serviceTime'] = $item['applyTime'] = '暂未处理';
               } else {
                   $actTimeResult = $this->diffTime($item['createTime'], $item['applyTime']);
                   $item['serviceTime'] = Sec3Time($actTimeResult);
                   $item['applyTime'] = $this->formatDate($item['applyTime']);
               }
               $item['createTime'] = $this->formatDate($item['createTime']);


               $item['remark'] = $item['remark'] ?? '';

               $item['statusCn'] = ExchangeOrder::STATUS[$item['status']]??'未知状态';
               $item['usdtTypeCn'] = ExchangeOrder::USDTTYPE[$item['usdfType']??0]??'';

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
            $requestMoneySum = ExchangeOrder::where($where)->sum('requestMoney');
            $requestMoneySum = $this->formatMoneyFromMongo($requestMoneySum);
            $requestMoneySum = number_format($requestMoneySum, 2);
            $payMoneySum = ExchangeOrder::where($where)->sum('payMoney');
            $payMoneySum = $this->formatMoneyFromMongo($payMoneySum);
            $payMoneySum = number_format($payMoneySum, 2);

            return json(['code' => 0, 'msg' => 'ok', 'data' => ['requestMoneySum' => $requestMoneySum, 'payMoneySum' => $payMoneySum]]);
        }
    }

    public function exportExchange(Request $request)
    {
        $where = $this->_ajaxParam();
        if (!is_array($where)) return $where;
        //$rechargeTypeIdNameKV = RechargeType::rechargeTypeIdNameKV();
        $cursor = ExchangeOrder::where($where)->orderBy('createTime', 'desc')->cursor();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', '订单号');
        $sheet->setCellValue('B1', '会员ID');
        $sheet->setCellValue('C1', '手机号');
        $sheet->setCellValue('D1', '提现类型');
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
            $user = GameUser::where('userId', $item->userId)->select('userId','score','bankScore','rechargeAmount','exchangeAmount','regInfo','trueName','nickName','mobile')->first();
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
            $sheet->setCellValue("B{$num}", $item->userId);
            if($session->get('userName') == "admin"){
                $sheet->setCellValue("C{$num}", mobile($user->mobile));
            }else{
                $sheet->setCellValue("C{$num}", '');
            }
            $sheet->setCellValue("D{$num}", ExchangeOrder::WITHDRAWTYPE[$item->withdrawType]);
            $sheet->setCellValue("E{$num}", $this->formatMoneyFromMongo($item->requestMoney));
            $sheet->setCellValue("F{$num}", $this->formatMoneyFromMongo($item->payMoney));
            $sheet->setCellValue("G{$num}", $user->trueName??$user->nickName);
            $sheet->setCellValue("H{$num}", ExchangeOrder::STATUS[$item->status]);
            $sheet->setCellValue("I{$num}", $this->formatMoneyFromMongo($user->rechargeAmount));
            $sheet->setCellValue("J{$num}", $this->formatMoneyFromMongo($user->exchangeAmount));
            $sheet->setCellValue("K{$num}", $this->formatDate($item->createTime));
            $sheet->setCellValue("L{$num}", $item->applyTime);
            $sheet->setCellValue("M{$num}", $serviceTime);
            $sheet->setCellValue("N{$num}", $item->reason??'');
            $sheet->setCellValue("O{$num}", $item->remark??'');

            $num++;
        }
        $writer = new Xlsx($spreadsheet);
        $file_path = public_path().'/exportExchange.xlsx';
        // 保存文件到 public 下
        $writer->save($file_path);

        return json(['code' => 0, 'msg' => 'ok', 'file' => $file_path]);
    }

    public function audit(Request $request)
    {
        $_id = $request->post('_id', 0);
        if (empty($_id)) return json(['code' => -1, 'msg' => '参数错误']);
        $order = ExchangeOrder::find($_id);
        if (empty($order)) return json(['code' => -1, 'msg' => '订单不存在']);
        if ($order->status != ExchangeOrder::ORDER_STATUS_AUDIT) return json(['code' => -1, 'msg' => '订单状态有误，请刷新重试']);

        $order->status = ExchangeOrder::ORDER_STATUS_TOBEREMITTED;
        $order->applytime = new \MongoDB\BSON\UTCDateTime;

        $updateResult = $order->save();
        if (!$updateResult) return json(['code' => -1, 'msg' => '操作失败']);
        $this->adminLog(["content"=>"操作提现订单审核【".$order->orderId."】"]);
        return json(['code' => 0, 'msg' => '操作成功']);

    }

    public function allowExchange(Request $request)
    {
        $_id = $request->post('_id', 0);
        if (empty($_id)) return json(['code' => -1, 'msg' => '参数错误']);
        $order = ExchangeOrder::find($_id);
        if (empty($order)) return json(['code' => -1, 'msg' => '订单不存在']);
        if (!in_array($order->status, [ExchangeOrder::ORDER_STATUS_TOBEREMITTED, ExchangeOrder::ORDER_STATUS_EXCHANGE_FAIL])) {
            return json(['code' => -1, 'msg' => '订单状态不正确']);
        }
        $userId = $order->userId;
        $orderId = $order->orderId;
        $requestMoney = $order->requestMoney;

        $session = DB::connection('mongodb_main')->getMongoClient()->startSession();
        $session->startTransaction();
        try {
            $where = ['_id' => $_id];
            $updateData = [
                'status' => ExchangeOrder::ORDER_STATUS_EXCHANGE_SUCCESS,
                'payMoney' => $requestMoney,
                'remark' => '手工处理',
                'applyTime' => new \MongoDB\BSON\UTCDateTime,
                'finishTime' => new \MongoDB\BSON\UTCDateTime
            ];
            $updateResult = ExchangeOrder::where($where)->update($updateData, ['session' => $session]);
            if (!$updateResult) {
                $session->abortTransaction();
                return json(['code' => -1, 'msg' => '提现订单更新失败！']);
            }

            $user = GameUser::where('userId', $userId)->select('userId','vip','score','bankScore','exchangeAmount')->first();
            if (empty($user)) {
                $session->abortTransaction();
                return json(['code' => -1, 'msg' => '该用户不存在！']);
            }
            $updateData = [
                'exchangeAmount' => $user->exchangeAmount + $requestMoney,
                'exchangeTimes' => $user->exchangeTimes + 1,
            ];
            $updateResult = GameUser::where('userId', $userId)->update($updateData, ['session' => $session]);
            if (!$updateResult) {
                $session->abortTransaction();
                return json(['code' => -1, 'msg' => '更新用户信息失败！']);
            }
            $session->commitTransaction();
            $this->adminLog(["content"=>"提现订单汇款操作订单号为【".$orderId."】"]);
            $info = '您的提现已成功。请注意查收，如有问题请联系客服。';
            sendData2(['userId'=>$userId, 'orderId'=>$orderId, 'type'=>1, 'money'=>$requestMoney, 'status'=>ExchangeOrder::ORDER_STATUS_EXCHANGE_SUCCESS, 'info'=>$info]);
            return json(['code' => 0, 'msg' => '操作成功！']);

        } catch (\Exception $e) {
            $session->abortTransaction();
            return json(['code' => -1, 'msg' => '操作失败！']);
        }

    }

    public function drawMoney(Request $request)
    {
        //检查服务商
        $exchangeServiceList = ExchangeServer::getAvailableServiceList(1);
        if (empty($exchangeServiceList)) return json(['code' => -1, 'msg' => '网银代付功能未开启']);
        /*$length = count($exchangeServiceList);
        $seed = mt_rand(0, $length - 1);
        $exchangeService = $exchangeServiceList[$seed];*/
        $exchangeService = '';
        $exchangeServiceId = (int)$request->post('exchangeServiceId', 0);
        foreach ($exchangeServiceList as $es) {
            if ($es['exchangeServiceId'] == $exchangeServiceId) $exchangeService = $es;
        }
        if (!$exchangeService) return json(['code' => -1, 'msg' => '您选择的银行卡渠道代付未开启']);
        //检查订单
        $_id = $request->post('_id', 0);
        if (empty($_id)) return json(['code' => -1, 'msg' => '参数错误(_id)']);
        $where = ['_id' => $_id];
        $order = ExchangeOrder::where($where)->select('userId','requestMoney','payMoney','withdrawType','bankName','bankCardName','bankCardNum','status','orderId')->first();
        if (empty($order)) return json(['code' => -1, 'msg' => '订单不存在']);
        if ($order->status != ExchangeOrder::ORDER_STATUS_TOBEREMITTED) {
            return json(['code' => -1, 'msg' => '订单状态不正确,请刷新重试']);
        }
        $userId = $order->userId;
        $orderId = $order->orderId;
        $requestMoney = $order->requestMoney;

        $session = DB::connection('mongodb_main')->getMongoClient()->startSession();
        $session->startTransaction();
        try{
            $updateData = [
                'status' => ExchangeOrder::ORDER_STATUS_REMITTING,
                'applyTime' => new \MongoDB\BSON\UTCDateTime,
                'finishTime' => new \MongoDB\BSON\UTCDateTime
            ];
            $updateResult = ExchangeOrder::where($where)->update($updateData, ['session' => $session]);
            if (!$updateResult) {
                $session->abortTransaction();
                return json(['code' => -1, 'msg' => '下分失败--提现订单更新失败！']);
            }
            $user = GameUser::where('userId', $userId)->select('userId','vip','score','bankScore','exchangeAmount', 'exchangeTimes')->first();
            if (empty($user)) {
                $session->abortTransaction();
                return json(['code' => -1, 'msg' => '下分失败--该用户不存在！']);
            }
            $updateData = [
                'exchangeAmount' => $user->exchangeAmount + $requestMoney,
                'exchangeTimes' => $user->exchangeTimes + 1,
            ];
            $updateResult = GameUser::where('userId', $userId)->update($updateData, ['session' => $session]);
            if (!$updateResult) {
                $session->abortTransaction();
                return json(['code' => -1, 'msg' => '下分失败--更新用户信息失败！']);
            }
            /*$result = DrawMoney(
                [
                    'orderId' => $orderId,
                    'payMoney' => $this->formatMoneyFromMongo($order->payMoney),
                    'bankName' => $order->bankName??'',
                    'bankCardName' => $order->bankCardName,
                    'bankCardNum' => $order->bankCardNum,
                ], $exchangeService);*/
            $orderInfo = $order->toArray();
            $orderInfo['requestMoney'] = $this->formatMoneyFromMongo($orderInfo['requestMoney']);
            $result = DrawMoney($orderInfo, $exchangeService);
            if (!$result['result']) {
                $session->abortTransaction();
                return json(['code' => -1, 'msg' => "下分失败--{$result['description']}！"]);
            }
            $session->commitTransaction();
            $this->adminLog(["content"=>"提现订单银行卡汇款操作订单号为【".$orderId."】"]);
            return json(['code' => 0, 'msg' => "下分成功--{$result['description']}！"]);
        } catch (\Exception $e) {
            $session->abortTransaction();
            return json(['code' => -1, 'msg' => '下分失败--抛出异常！！']);
        }
    }

    public function drawMoneyAlipay(Request $request)
    {
        //检查服务商
        $exchangeServiceList = ExchangeServer::getAvailableServiceList(2);
        if (empty($exchangeServiceList)) return json(['code' => -1, 'msg' => '支付宝代付功能未开启']);
        $exchangeServiceId = $request->post('exchangeServiceId', 0);
        $exchangeService = '';
        if (!empty($exchangeServiceId)) {
            foreach ($exchangeServiceList as $es) {
                if ($es['exchangeServiceId'] == $exchangeServiceId) $exchangeService = $es;
            }
            if (!$exchangeService) return json(['code' => -1, 'msg' => '您选择的支付宝代付未开启']);
        } else {
            $length = count($exchangeServiceList);
            $seed = mt_rand(0, $length - 1);
            $exchangeService = $exchangeServiceList[$seed];
        }
        $otherAlipay = 0;
        if ($exchangeService['exchangeServiceId'] == 12) $otherAlipay = 1;

        //检查订单
        $_id = $request->post('_id', 0);
        if (empty($_id)) return json(['code' => -1, 'msg' => '参数错误']);
        $where = ['_id' => $_id];
        $order = ExchangeOrder::where($where)->select('userId','requestMoney','payMoney','withdrawType','bankName','bankCardName','bankCardNum','status','alipayAccount','alipayName','orderId')->first();
        if (empty($order)) return json(['code' => -1, 'msg' => '订单不存在']);
        if ($order->status != ExchangeOrder::ORDER_STATUS_TOBEREMITTED) {
            return json(['code' => -1, 'msg' => '订单状态不正确']);
        }
        $userId = $order->userId;
        $orderId = $order->orderId;
        $requestMoney = $order->requestMoney;

        $session = DB::connection('mongodb_main')->getMongoClient()->startSession();
        $session->startTransaction();
        try{
            if ($otherAlipay) {
                $updateData = [
                    'status' => ExchangeOrder::ORDER_STATUS_REMITTING,
                    'applyTime' => new \MongoDB\BSON\UTCDateTime,
                    'finishTime' => new \MongoDB\BSON\UTCDateTime
                ];
            } else {
                $updateData = [
                    'status' => ExchangeOrder::ORDER_STATUS_EXCHANGE_SUCCESS,
                    'remark' => $exchangeService['controllerName'],
                    'payMoney' => $requestMoney,
                    'applyTime' => new \MongoDB\BSON\UTCDateTime,
                    'finishTime' => new \MongoDB\BSON\UTCDateTime
                ];
            }
            $updateResult = ExchangeOrder::where($where)->update($updateData, ['session' => $session]);
            if (!$updateResult) {
                $session->abortTransaction();
                return json(['code' => -1, 'msg' => '下分失败--提现订单更新失败！']);
            }
            $user = GameUser::where('userId', $userId)->select('userId','vip','score','bankScore','exchangeAmount', 'exchangeTimes')->first();
            if (empty($user)) {
                $session->abortTransaction();
                return json(['code' => -1, 'msg' => '下分失败--该用户不存在！']);
            }
            $updateData = [
                'exchangeAmount' => $user->exchangeAmount + $requestMoney,
                'exchangeTimes' => $user->exchangeTimes + 1,
            ];
            $updateResult = GameUser::where('userId', $userId)->update($updateData, ['session' => $session]);
            if (!$updateResult) {
                $session->abortTransaction();
                return json(['code' => -1, 'msg' => '下分失败--更新用户信息失败！']);
            }

            $result = DrawMoney_Ali2(
                [
                    'orderId' => $orderId,
                    'payMoney' => $this->formatMoneyFromMongo($requestMoney),
                    'alipayAccount' => $order->alipayAccount??'',
                    'alipayName' => $order->alipayName,
                ], $exchangeService);
            if (!$result['result']) {
                $session->abortTransaction();
                return json(['code' => -1, 'msg' => "下分失败--{$result['description']}！"]);
            } elseif (isset($result['agentpayOrderId']) && !empty($result['agentpayOrderId'])) {
                $updateData = [
                    'agentpayOrderId' => $result['agentpayOrderId']
                ];
                $updateResult = ExchangeOrder::where($where)->update($updateData, ['session' => $session]);
                if (!$updateResult) {
                    $session->abortTransaction();
                    return json(['code' => -1, 'msg' => '下分失败--提现订单代付单号更新失败！']);
                }
            }
            $session->commitTransaction();
            $this->adminLog(["content"=>"提现订单支付宝汇款操作订单号为【".$orderId."】"]);
            return json(['code' => 0, 'msg' => "下分成功--{$result['description']}！"]);
        } catch (\Exception $e) {
            $session->abortTransaction();
            return json(['code' => -1, 'msg' => '下分失败--抛出异常！！']);
        }
    }

    public function availableBank(Request $request)
    {
        $exchangeServiceList = ExchangeServer::getAvailableServiceList(1);
        return json(['code' => 0, 'msg' => 'ok', 'count' => 0, 'data' => $exchangeServiceList]);
    }

    public function availableAli(Request $request)
    {
        $exchangeServiceList = ExchangeServer::getAvailableServiceList(2);
        return json(['code' => 0, 'msg' => 'ok', 'count' => 0, 'data' => $exchangeServiceList]);
    }

    public function reject(Request $request)
    {
        if ($request->method() == 'GET') {
            $reasons = ExchangeRejectReason::all();
            $assignData = ['reasons' => $reasons];
            return view('order/exchange/reject', $assignData);
        }
        if ($request->method() == 'POST') {
            $postData = check_type($request->post());
            extract($postData);
            if (!$userId || !$orderId || !$requestMoney || !$reason) {
                return json(['code' => -1, 'msg' => '信息有误']);
            }
            if (!setMutex("EXCHANGE", $orderId)) return json(['code' => -1, 'msg' => '订单正在处理中，请刷新重试']);
            if (empty($_id)) return json(['code' => -1, 'msg' => '参数错误']);
            $where = ['_id' => $_id];
            $order = ExchangeOrder::find($_id);
            if (empty($order)) {
                return json(['code' => -1, 'msg' => '订单不存在']);
            }
            if (($order->userId != $userId) || ($order->orderId != $orderId)) {
                return json(['code' => -1, 'msg' => '订单不存在2']);
            }
            if (!in_array($order->status, [ExchangeOrder::ORDER_STATUS_AUDIT, ExchangeOrder::ORDER_STATUS_TOBEREMITTED, ExchangeOrder::ORDER_STATUS_EXCHANGE_FAIL, ExchangeOrder::ORDER_STATUS_REMITTING])) {
                return json(['code' => -1, 'msg' => '订单状态有误，请刷新重试']);
            }
            $requestMoney = (int)$requestMoney;
            $session = DB::connection('mongodb_main')->getMongoClient()->startSession();
            $session->startTransaction();
            try {

                $updateData = [
                    'status' => ExchangeOrder::ORDER_STATUS_REJECT,
                    'reason' => $reason,
                    'applyTime' => new \MongoDB\BSON\UTCDateTime
                ];
                $updateResult = ExchangeOrder::where($where)->update($updateData, ['session' => $session]);
                if (!$updateResult) {
                    $session->abortTransaction();
                    return json(['code' => -1, 'msg' => '订单已操作，或者操作失败！']);
                }

                $user = GameUser::where('userId', $userId)->select('userId','vip','score','bankScore','rechargeAmount', 'exchangeAmount', 'exchangeTimes')->first();
                if (empty($user)) {
                    $session->abortTransaction();
                    return json(['code' => -1, 'msg' => '该用户不存在！']);
                }
                $updateData = [
                    'score' => $user->score + $requestMoney,
                ];
                if ($order->status == ExchangeOrder::ORDER_STATUS_REMITTING) {
                    $updateData['exchangeAmount'] = $user->exchangeAmount - $requestMoney;
                    $updateData['exchangeTimes'] = $user->exchangeTimes - 1;
                }
                $updateResult = GameUser::where('userId', $userId)->update($updateData, ['session' => $session]);
                if (!$updateResult) {
                    $session->abortTransaction();
                    return json(['code' => -1, 'msg' => '更新用户金币失败！']);
                }

                $insertData = [
                    'userId' => $userId,
                    'beforeScore' => $user->score,
                    'beforeBankScore' => $user->bankScore,
                    'addScore' => $requestMoney,
                    'addBankScore' => 0,
                    'afterScore' => $user->score + $requestMoney,
                    'afterBankScore' => $user->bankScore,
                    'changeType' => ExchangeOrder::ORDER_STATUS_REJECT,
                    'roomId' => 0,
                    'createTime' => new \MongoDB\BSON\UTCDateTime
                ];
                $insertResult = UserScoreChange::raw()->insertOne($insertData, ['session' => $session]);
                if (!$insertResult) {
                    $session->abortTransaction();
                    return json(['code' => -1, 'msg' => '添加分值改变记录失败']);
                }
                $session->commitTransaction();

                $this->adminLog(["content"=>"驳回提现订单操作订单号为【".$orderId."】"]);
                $info = '您的提现已驳回。请注意查收，如有问题请联系客服。';
                sendData2(['userId'=>$userId, 'orderId'=>$orderId, 'type'=>1, 'money'=>$requestMoney, 'status'=>ExchangeOrder::ORDER_STATUS_REJECT, 'info'=>$info]);
                return json(['code' => 0, 'msg' => '操作成功！']);
            } catch (\Exception $e) {
                $session->abortTransaction();
                return json(['code' => -1, 'msg' => '操作失败！']);
            }

        }

    }

    public function rejectReasonAdd(Request $request)
    {
        if ($request->isAjax()) {
            $reason = $request->post('reason', '');
            if (empty($reason)) {
                return json(['code' => -1, 'msg' => '输入不能为空']);
            }
            $rejectId = ExchangeRejectReason::max('rejectId');
            $insertData = [
                'rejectId' => intval($rejectId+1),
                'title' => $reason,
                'reason' => $reason,
            ];
            $insertResult = ExchangeRejectReason::insert($insertData);
            if (!$insertResult) json(['code' => -1, 'msg' => '操作失败']);
            return json(['code' => 0, 'msg' => '操作成功']);
        }
    }

    public function rejectReasonDel(Request $request)
    {
        if ($request->isAjax()) {
            $rejectId = $request->post('rejectId', 0);
            if (empty($rejectId)) {
                return json(['code' => -1, 'msg' => '输入不能为空']);
            }
            $deleteResult = ExchangeRejectReason::where('rejectId', $rejectId)->delete();
            if (!$deleteResult) json(['code' => -1, 'msg' => '操作失败']);
            return json(['code' => 0, 'msg' => '操作成功']);
        }
    }

    public function statisticalInfo(Request $request)
    {
        if ($request->method() == 'GET') {
            $userId = (int)$request->get('userId', 0);
            return view('order/exchange/statisticalInfo', ['userId'=>$userId]);
        }

    }

    public function statisticalData(Request $request)
    {
        $userId = (int)$request->get('userId', 0);
        $where = [
            'userId' => $userId,
            'status' => RechargeOrder::ORDER_STATUS_FINISH
        ];
        $list = RechargeOrder::where($where)->orderBy('createTime', 'desc')->skip($request->skip)->take(3)->get()->toArray();
        $rechargeTypeIdNameKV = RechargeType::rechargeTypeIdNameKV();
        foreach ($list as $key => &$item) {
            $item['rechargeTypeName'] = $item['rechargeTypeName'] ?? $rechargeTypeIdNameKV[$item['rechargeTypeId']];
            $item['rechargeMoney'] = $this->formatMoneyFromMongo($item['rechargeMoney']);

            $item['createTime'] = $this->formatDate($item['createTime']);
            $startTime = $item['createTime'];
            if(!empty($list[$key-1]['createTime'])){
                $endTime = $list[$key-1]['createTime'];
            }else{
                $endTime = date("Y-m-d H:i:s");
            }
            $item['startDate'] = $startTime;
            $item['endDate'] = $endTime;
            $validBet = $this->getVaildBet($startTime,$endTime,$userId);//金币场
            $validBetClub = $this->getClubVaildBet($startTime,$endTime,$userId);//俱乐部
            $item['validBet'] = sprintf("%.2f",substr(sprintf("%.3f", $validBet* 0.01), 0, -1)); //保留两位不进行四舍五入
            $item['validBetClub'] = sprintf("%.2f",substr(sprintf("%.3f", $validBetClub* 0.01), 0, -1)); //保留两位不进行四舍五入
        }
        return json(['code' => 0, 'msg' => 'ok', 'count' => 0, 'data' => $list]);
    }
    public function statisticalData2(Request $request)
    {
        $userId = (int)$request->get('userId', 0);
        $where = [
            'userId' => $userId,
        ];
        $list = RewardOrder::where($where)->whereIn('rewardType', array_keys(RewardOrder::REWARD_TYPE_FOR_EXCHANGE_STATISTICAL))->orderBy('createTime', 'desc')->skip($request->skip)->take(3)->get()->toArray();
        $activityList = Activity::all('activityId','title')->toArray();
        $list = merge_array($list, $activityList, 'activityId');
        foreach ($list as $key => &$item) {
            $item['reason'] = $item['reason'];
            $item['rewardTypeName'] = resultChangeType($item['rewardType']);
            $item['rechargeMoney'] = $this->formatMoneyFromMongo($item['rechargeMoney']);
            $item['orderId'] = $item['orderId']??'无';
            $item['createTime'] = $this->formatDate($item['createTime']);
            $startTime = $item['createTime'];
            if(!empty($list[$key-1]['createTime'])){
                $endTime = $list[$key-1]['createTime'];
            }else{
                $endTime = date("Y-m-d H:i:s");
            }
            $validBet = $this->getVaildBet($startTime,$endTime,$userId);
            $validBetClub = $this->getClubVaildBet($startTime,$endTime,$userId);//俱乐部
            $item['validBet'] = sprintf("%.2f",substr(sprintf("%.3f", $validBet* 0.01), 0, -1)); //保留两位不进行四舍五入
            $item['validBetClub'] = sprintf("%.2f",substr(sprintf("%.3f", $validBetClub* 0.01), 0, -1)); //保留两位不进行四舍五入
        }
        return json(['code' => 0, 'msg' => 'ok', 'count' => 0, 'data' => $list]);
    }

    public function statisticalData3(Request $request)
    {
        $userId = (int)$request->get('userId', 0);
        $where = [
            'promoterId' => $userId,
        ];
        $count = AgentExchangeOrder::where($where)->count();
        $list = AgentExchangeOrder::where($where)->orderBy('createTime', 'desc')->skip($request->skip)->take(5)->get()->toArray();
        $userList = GameUser::where('userId', $userId)->select('userId')->get()->toArray();
        $list = merge_array($list, $userList, 'promoterId', 'userId');
        foreach ($list as &$item) {
            $item['requestMoney'] = $this->formatMoneyFromMongo($item['requestMoney']);
            $item['createTime'] = $this->formatDate($item['createTime']);
        }
        return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $list]);
    }

    public function statisticalData4(Request $request)
    {
        $userId = (int)$request->get('userId', 0);
        $where = [
            'promoterId' => $userId,
        ];
        $count = PromoterExchangeOrder::where($where)->count();
        $list = PromoterExchangeOrder::where($where)->orderBy('createTime', 'desc')->skip($request->skip)->take(5)->get()->toArray();
        $userList = GameUser::where('userId', $userId)->select('userId')->get()->toArray();
        $list = merge_array($list, $userList, 'promoterId', 'userId');
        foreach ($list as &$item) {
            $item['requestMoney'] = $this->formatMoneyFromMongo($item['requestMoney']);
            $item['createTime'] = $this->formatDate($item['createTime']);
        }
        return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $list]);
    }

    public function statisticalData5(Request $request)
    {
        $userId = (int)$request->get('userId', 0);
        $where = [
            'userId' => $userId,
        ];
        $count = PlayRecord::where($where)->count();
        $list = PlayRecord::where($where)->orderBy('startTime', 'desc')->skip($request->skip)->take(3)->get()->toArray();

        $gameRoomInfo = getGameRoomInfo();

        $list = merge_array($list, $gameRoomInfo, 'roomId');

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
            }elseif ($item['gameId'] == 910){
                $xiandui = $item['cellScore'][0];
                $zhuangdui = $item['cellScore'][1];
                $xian = $item['cellScore'][2];
                $zhuang = $item['cellScore'][3];
                $he = $item['cellScore'][4];
                $item['xiandui'] = $this->formatMoneyFromMongo($xiandui);
                $item['zhuangdui'] = $this->formatMoneyFromMongo($zhuangdui);
                $item['xian'] = $this->formatMoneyFromMongo($xian);
                $item['zhuang'] = $this->formatMoneyFromMongo($zhuang);
                $item['he'] = $this->formatMoneyFromMongo($he);
            }

            $item['beforeScore'] = $this->formatMoneyFromMongo($item['beforeScore']);
            $item['score'] = $this->formatMoneyFromMongo($item['score']);
            $item['allBet'] = $this->formatMoneyFromMongo($item['allBet']);
            $item['validBet'] = $this->formatMoneyFromMongo($item['validBet']);
            $item['winScore'] = $this->formatMoneyFromMongo($item['winScore']);
            $item['platformWinScore'] = $this->formatMoneyFromMongo($item['platformWinScore']??0);
            $item['revenue'] = $this->formatMoneyFromMongo($item['revenue']);
            if(isset($item['winLostScore'])) {
                $item['earnScore'] = $this->formatMoneyFromMongo($item['winLostScore']);
            } else {
                $item['earnScore'] = $item['winScore'] + $item['revenue'];
            }
            $item['ptIncome'] = $this->formatMoneyFromMongo($item['platformWinScore']*100 + $item['revenue']*100);

            $item['playTime'] = $this->diffTime($item['startTime'], $item['endTime'], '%I:%S');
            $item['startTime'] = $this->formatDate($item['startTime']);
            $item['endTime'] = $this->formatDate($item['endTime']);
            $item['isBanker'] = $item['isBanker']??0;
        }
        return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $list]);
    }

    private function getVaildBet($startDate,$endDate,$userId)
    {
        $startTime = $startDate ?? date("Y-m-d H:i:s");
        $endTime = $endDate ?? date("Y-m-d H:i:s");
        $startTime = strtotime(trim($startTime));
        $endTime = strtotime(trim($endTime));
        $result = Db::connection('mongodb_main')->collection('play_record')->raw()->aggregate([
            [
                '$match' => [
                    'userId'=>['$eq'=>$userId]
                ]
            ],
            [
                '$match' => [
                    'endTime' => [
                        '$gte' => new UTCDateTime($startTime*1000),
                        '$lt' => new UTCDateTime($endTime*1000)
                    ]
                ]
            ],
            [
                '$project' =>
                    [
                        //'Date' => ['$substr' => [['$add' => ['$endTime',28800000]], 0, 10]],
                        'validBet' => 1,
                        'userId' => 1
                    ],
            ],
            [
                '$group' => [
                    '_id'=>[
                        'userId'=>'$userId',
                    ],
                    'validBet' => ['$sum' => '$validBet'],
                ]
            ],
        ])->toArray();

        if(isset($result[0]['validBet']) && $result[0]['validBet']){
            $validBet = $result[0]['validBet'];
        }else{
            $validBet = 0;
        }
        return $validBet;
    }

    private function getClubVaildBet($startDate,$endDate,$userId)
    {
        $startTime = $startDate ?? date("Y-m-d H:i:s");
        $endTime = $endDate ?? date("Y-m-d H:i:s");
        $startTime = strtotime(trim($startTime));
        $endTime = strtotime(trim($endTime));
        $result = Db::connection('mongodb_club')->collection('play_record')->raw()->aggregate([
            [
                '$match' => [
                    'userId'=>['$eq'=>$userId]
                ]
            ],
            [
                '$match' => [
                    'endTime' => [
                        '$gte' => new UTCDateTime($startTime*1000),
                        '$lt' => new UTCDateTime($endTime*1000)
                    ]
                ]
            ],
            [
                '$project' =>
                    [
                        //'Date' => ['$substr' => [['$add' => ['$endTime',28800000]], 0, 10]],
                        'validBet' => 1,
                        'userId' => 1
                    ],
            ],
            [
                '$group' => [
                    '_id'=>[
                        'userId'=>'$userId',
                    ],
                    'validBet' => ['$sum' => '$validBet'],
                ]
            ],
        ])->toArray();

        if(isset($result[0]['validBet']) && $result[0]['validBet']){
            $validBet = $result[0]['validBet'];
        }else{
            $validBet = 0;
        }
        return $validBet;
    }

    public function createExchangeOrder(Request $request){
        if($request->isAjax()) {
            $postData = check_type($request->post());
            extract($postData);
            /*userId: 38578416
            trueName: 测试
            promoterId: 1000
            nowScore: 10868.66
            bankScore: 0.00
            rechargeMoney: 5000
            withdrawType: 3
            remark: 带人提现*/
            if (!isset($withdrawType)) return json(['code' => -1, 'msg' => '提现类型不能为空']);
            if (empty($userId) || empty($rechargeMoney) || empty($withdrawType)) return json(['code' => -1, 'msg' => '传入参数错误']);
            $rechargeMoney = intval($rechargeMoney * 100);
            $where = [
                'userId' => $userId,
                'status' => GameUser::USER_STATUS_ON,
            ];
            $user = Player::getPlayer($where);
            if (empty($user)) return json(['code' => -1, 'msg' => '会员不存在']);
            if ($user->score < $rechargeMoney) return json(['code' => -1, 'msg' => '会员余额不足']);

            $orderId = strtoupper("TX" . orderNumber() . $userId);
            $session = DB::connection('mongodb_main')->getMongoClient()->startSession();
            $session->startTransaction();
            try {
                    $ip = GetIP();
                    $exchangeOrderData = [
                        'orderId' => $orderId,
                        'userId' => $userId,
                        'promoterId' => $user->promoterId, //注册渠道id
                        'exchangeServiceId' => 1,  //充值服务id
                        'exchangeProviderId' => 1, //充值银商Id
                        'requestMoney' => $rechargeMoney,
                        'payMoney' => 0,
                        'usdt' => 0,
                        'usdtRate' => 0,
                        'tax' => 0,
                        'beforeScore' => $user->score,
                        'afterScore' => $user->score - $rechargeMoney,
                        'withdrawType' => $withdrawType,
                        'bankCardNum' => '',
                        'bankCardName' => '',
                        'bankName' => '',
                        'alipayAccount' => '',
                        'alipayName' => '',
                        'usdtAddress' => '',
                        'usdtType' => 0,
                        'auditId' => '',
                        'auditName' => '',
                        'auditAccount' => '',

                        'sp' => 0,
                        'spOrderId' => '',
                        'spRemark' => '',
                        'ip' => $ip,
                        'callInfo' => '',
                        'cashUp' => 1,
                        'reason' => $remark,

                        'createTime' => new \MongoDB\BSON\UTCDateTime,
                        'applyTime' => 0,
                        'finishTime' => 0,
                        'status' => 8,
                        'remark' => '',
                    ];
                    if ($withdrawType == 2) {
                        $exchangeOrderData['alipayAccount'] = $user->alipay['alipayAccount'];
                        $exchangeOrderData['alipayName'] = $user->alipay['alipayName'];
                    } elseif ($withdrawType == 3) {
                        $exchangeOrderData['bankCardNum'] = $user->bank['bankCardNum'];
                        $exchangeOrderData['bankCardName'] = $user->bank['bankCardName'];
                        $exchangeOrderData['bankName'] = $user->bank['bankName'];
                    } elseif ($withdrawType == 4) {
                        $rate = getRate();
                        $usdt = floor(($rechargeMoney / $rate) * 100);
                        $exchangeOrderData['usdtType'] = $user->usdt['type'];
                        $exchangeOrderData['usdt'] = $usdt;
                        $exchangeOrderData['usdtRate'] = $rate;
                        $exchangeOrderData['usdtAddress'] = $user->usdt['address'];
                    }
                    $insertResult = ExchangeOrder::raw()->insertOne($exchangeOrderData, ['session' => $session]);
                    if (!$insertResult) {
                        $session->abortTransaction();
                        return json(['code' => -1, 'msg' => '添加提现订单记录失败']);
                    }

                $updateData = [
                    'score' => $user->score - $rechargeMoney,
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
                    'addScore' => -$rechargeMoney,
                    'addBankScore' => 0,
                    'afterScore' => $user->score - $rechargeMoney,
                    'afterBankScore' => $user->bankScore,
                    'changeType' => 4,
                    'roomId' => 0,
                    'createTime' => new \MongoDB\BSON\UTCDateTime,
                    //'refId' => '',
                    'remark' => $remark,
                ];
                $insertResult = UserScoreChange::raw()->insertOne($insertData, ['session' => $session]);
                if (!$insertResult) {
                    $session->abortTransaction();
                    return json(['code' => -1, 'msg' => '添加分值改变记录失败']);
                }


                $session->commitTransaction();
                $this->adminLog(["content"=>session("userName")."创建提现订单【".$userId."】金币:".$this->formatMoneyFromMongo($rechargeMoney)]);


                return json(['code' => 0, 'msg' => '提现订单创建成功']);
            } catch (\Exception $e) {
                $session->abortTransaction();
                json(['code' => -1, 'msg' => '提现订单创建失败！']);
            }
        }
    }
}
