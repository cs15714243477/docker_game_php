<?php
namespace app\controller;

use app\model\GameUser;
use app\model\RewardOrder;
use app\model\ClubRewardOrder;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use support\Request;

class Reward extends Base
{
    private function _ajaxParam()
    {
        $where = [];
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
        $where[] = ['createTime', '>=', $this->formatTimestampToMongo($startTime)];
        $where[] = ['createTime', '<', $this->formatTimestampToMongo($endTime)];

        //输入框优先于下拉框
        if (!empty($searchText)) {
            $searchTextLen = strlen($searchText);
            if(in_array($searchTextLen, [SYSTEM_USER_ID_LENGTH, COMMON_USER_ID_LENGTH])) {
                $where['userId'] = (int)$searchText;
            }elseif ($searchTextLen == REWARD_ORDER_ID_LENGTH) {
                $where['orderId'] = $searchText;
            }else {
                return json(['code' => -1, 'msg' => '会员ID长度是'.SYSTEM_USER_ID_LENGTH.'位或者'.COMMON_USER_ID_LENGTH.'位数字，订单号是'.REWARD_ORDER_ID_LENGTH.'位字符']);
            }
        } elseif (!empty($isSys)) {
            if ($isSys == GameUser::COMMON_ACCOUNT) {
                $where[] = ['userId', '>=', GameUser::COMMON_ACCOUNT_START_ID];
            } elseif ($isSys == GameUser::SYSTEM_ACCOUNT) {
                $where[] = ['userId', '<', GameUser::COMMON_ACCOUNT_START_ID];
            }
        }
        if (!empty($reason)) {
            $where[] = ['reason', 'regex', new \MongoDB\BSON\Regex($reason, 'i')];
        }
        if (!empty($rewardType)) {
            $where['rewardType'] = $rewardType;
        }
        if (!empty($activityType)) {
            $where['activityType'] = $activityType;
        }
        return $where;
    }

    public function rewardList(Request $request)
    {
       if($request->isAjax()) {
           $where = $this->_ajaxParam();
           if (!is_array($where)) return $where;
           //$rechargeTypeIdNameKV = RechargeType::rechargeTypeIdNameKV();
           $count = RewardOrder::where($where)->count();
           $list = RewardOrder::where($where)->orderBy('createTime', 'desc')->skip($request->skip)->take($request->limit)->get()->toArray();
           $userIdArr = array_column($list, 'userId'); $userIdArr = array_unique($userIdArr);
           $userList = GameUser::whereIn('userId', $userIdArr)->select('userId','mobile')->get()->toArray();
           $list = merge_array($list, $userList, 'userId');
           $taskConfigList = TaskConfig::taskConfigIdTitleList();
           $list = merge_array($list, $taskConfigList, 'taskId', 'Id');
           $activityList = Activity::activityIdTitleList();
           $list = merge_array($list, $activityList, 'activityId');
           $rewardArr = RewardOrder::REWARD_TYPE_FOR_GIVE_SCORE;
           $rewardArr = array_keys($rewardArr);
           foreach ($list as &$item) {
               $item['rewardTypeName'] = resultChangeType($item['rewardType']);
               $item['rechargeMoney'] = $this->formatMoneyFromMongo($item['rechargeMoney']??0);
               $item['requestMoney'] = $this->formatMoneyFromMongo($item['requestMoney']??0);
               $item['createTime'] = $this->formatDate($item['createTime']);
               if(!empty($item['mobile'])){
                   $item['mobile'] = mobileShow(mobile($item['mobile']));
               }else{
                   $item['mobile'] = '';
               }
               /*if(in_array($item['rewardType'],$rewardArr)){
                   $item['reason'] = $item['reason'];
               }else{
                   $item['reason'] = "";
               }*/
           }
           //dd($list);
           return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $list]);
       }
    }

    public function clubRewardList(Request $request)
    {
        if($request->isAjax()) {
            $where = $this->_ajaxParam();
            if (!is_array($where)) return $where;
            //$rechargeTypeIdNameKV = RechargeType::rechargeTypeIdNameKV();
            $count = ClubRewardOrder::where($where)->count();
            $list = ClubRewardOrder::where($where)->orderBy('createTime', 'desc')->skip($request->skip)->take($request->limit)->get()->toArray();
            $userIdArr = array_column($list, 'userId'); $userIdArr = array_unique($userIdArr);
            $userList = GameUser::whereIn('userId', $userIdArr)->select('userId','mobile')->get()->toArray();
            $list = merge_array($list, $userList, 'userId');
            //$taskConfigList = TaskConfig::taskConfigIdTitleList();
            //$list = merge_array($list, $taskConfigList, 'taskId', 'Id');
            //$activityList = Activity::activityIdTitleList();
            //$list = merge_array($list, $activityList, 'activityId');
            foreach ($list as &$item) {
                $item['rewardTypeName'] = resultChangeType($item['activityType']);
                $item['rechargeMoney'] = $this->formatMoneyFromMongo($item['rechargeMoney']??0);
                $item['requestMoney'] = $this->formatMoneyFromMongo($item['requestMoney']??0);
                $item['createTime'] = $this->formatDate($item['createTime']);
                $onLineUser = session("userName");
                if($onLineUser == "admin"){
                    $item['mobile'] = mobile($item['mobile']);
                }else{
                    $item['mobile'] = mobileShow(mobile($item['mobile']));
                }

            }
            return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $list]);
        }
    }

    public function summary(Request $request)
    {
        if($request->isAjax()) {
            $where = $this->_ajaxParam();
            if (!is_array($where)) return $where;
            $requestMoneySum = RewardOrder::where($where)->sum('requestMoney');
            $requestMoneySum = $this->formatMoneyFromMongo($requestMoneySum);
            $rechargeMoneySum = RewardOrder::where($where)->sum('rechargeMoney');
            $rechargeMoneySum = $this->formatMoneyFromMongo($rechargeMoneySum);

            return json(['code' => 0, 'msg' => 'ok', 'data' => ['requestMoneySum' => $requestMoneySum, 'rechargeMoneySum' => $rechargeMoneySum]]);
        }
    }

    public function clubSummary(Request $request)
    {
        if($request->isAjax()) {
            $where = $this->_ajaxParam();
            if (!is_array($where)) return $where;
            $requestMoneySum = ClubRewardOrder::where($where)->sum('requestMoney');
            $requestMoneySum = $this->formatMoneyFromMongo($requestMoneySum);
            $rechargeMoneySum = ClubRewardOrder::where($where)->sum('rechargeMoney');
            $rechargeMoneySum = $this->formatMoneyFromMongo($rechargeMoneySum);

            return json(['code' => 0, 'msg' => 'ok', 'data' => ['requestMoneySum' => $requestMoneySum, 'rechargeMoneySum' => $rechargeMoneySum]]);
        }
    }

    public function exportReward(Request $request)
    {
        $where = $this->_ajaxParam();
        if (!is_array($where)) return $where;
        //$rechargeTypeIdNameKV = RechargeType::rechargeTypeIdNameKV();
        $cursor = RewardOrder::where($where)->orderBy('createTime', 'desc')->cursor();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', '订单号');
        $sheet->setCellValue('B1', '会员ID');
        $sheet->setCellValue('C1', '奖励类型');
        $sheet->setCellValue('D1', '提交金额');
        $sheet->setCellValue('E1', '实际付款');
        $sheet->setCellValue('F1', '');
        $sheet->setCellValue('G1', '订单状态');
        $sheet->setCellValue('H1', '手机号码');
        $sheet->setCellValue('J1', '发放时间');
        $num = 2;
        $session = $request->session();
        foreach ($cursor as $item) {
            $user = GameUser::where('userId', $item->userId)->select('userId','score','bankScore','rechargeAmount','exchangeAmount','regInfo','trueName','nickName','mobile')->first();
            if (empty($user)) continue;
            $sheet->setCellValue("A{$num}", $item->orderId);
            $sheet->setCellValue("B{$num}", $item->userId);
            $sheet->setCellValue("C{$num}", resultChangeType($item->rewardType));
            $sheet->setCellValue("D{$num}", $this->formatMoneyFromMongo($item->requestMoney));
            $sheet->setCellValue("E{$num}", $this->formatMoneyFromMongo($item->rechargeMoney));
            $sheet->setCellValue("F{$num}", '');
            $sheet->setCellValue("G{$num}", '已完成');
            if($session->get('userName') == "admin"){
                $sheet->setCellValue("H{$num}", mobile($user->mobile));
            }else{
                $sheet->setCellValue("H{$num}", mobileShow(mobile($user->mobile)));
            }
            $sheet->setCellValue("J{$num}", $this->formatDate($item->createTime));
            $num++;
        }
        $writer = new Xlsx($spreadsheet);
        $file_path = public_path().'/exportReward.xlsx';
        // 保存文件到 public 下
        $writer->save($file_path);

        return json(['code' => 0, 'msg' => 'ok', 'file' => $file_path]);
    }

    public function clubExportReward(Request $request)
    {
        $where = $this->_ajaxParam();
        if (!is_array($where)) return $where;
        //$rechargeTypeIdNameKV = RechargeType::rechargeTypeIdNameKV();
        $cursor = ClubRewardOrder::where($where)->orderBy('createTime', 'desc')->cursor();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', '订单号');
        $sheet->setCellValue('B1', '会员ID');
        $sheet->setCellValue('C1', '奖励类型');
        $sheet->setCellValue('D1', '提交金额');
        $sheet->setCellValue('E1', '实际付款');
        $sheet->setCellValue('F1', '');
        $sheet->setCellValue('G1', '订单状态');
        $sheet->setCellValue('J1', '发放时间');
        $num = 2;
        foreach ($cursor as $item) {
            $user = GameUser::where('userId', $item->userId)->select('userId','score','bankScore','rechargeAmount','exchangeAmount','regInfo','trueName','nickName')->first();
            if (empty($user)) continue;
            $sheet->setCellValue("A{$num}", $item->orderId);
            $sheet->setCellValue("B{$num}", $item->userId);
            $sheet->setCellValue("C{$num}", resultChangeType($item->rewardType));
            $sheet->setCellValue("D{$num}", $this->formatMoneyFromMongo($item->requestMoney));
            $sheet->setCellValue("E{$num}", $this->formatMoneyFromMongo($item->rechargeMoney));
            $sheet->setCellValue("F{$num}", '');
            $sheet->setCellValue("G{$num}", '已完成');
            $sheet->setCellValue("J{$num}", $this->formatDate($item->createTime));

            $num++;
        }
        $writer = new Xlsx($spreadsheet);
        $file_path = public_path().'/exportReward.xlsx';
        // 保存文件到 public 下
        $writer->save($file_path);

        return json(['code' => 0, 'msg' => 'ok', 'file' => $file_path]);
    }

}
