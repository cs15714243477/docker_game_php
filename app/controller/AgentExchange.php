<?php
namespace app\controller;

use app\model\Activity;
use app\model\AgentExchangeOrder;
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

class AgentExchange extends Base
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
            if(in_array($searchTextLen, [6, 8])) {
                $where['promoterId'] = (int)$searchText;
            }elseif ($searchTextLen == 35) {
                $where['orderId'] = $searchText;
            }else {
                return json(['code' => -1, 'msg' => '会员ID长度是6位或者8位数字，订单号是27位字符']);
            }
        }
        return $where;
    }

    public function exchangeList(Request $request)
    {
       if($request->isAjax()) {
           $where = $this->_ajaxParam();
           if (!is_array($where)) return $where;
           //$rechargeTypeIdNameKV = RechargeType::rechargeTypeIdNameKV();
           $count = AgentExchangeOrder::where($where)->count();
           $list = AgentExchangeOrder::where($where)->orderBy('createTime', 'desc')->skip($request->skip)->take($request->limit)->get()->toArray();
           $userIdArr = array_column($list, 'promoterId'); $userIdArr = array_unique($userIdArr);
           $userList = GameUser::whereIn('userId', $userIdArr)->select('userId','score','bankScore','rechargeAmount','exchangeAmount','regInfo','trueName','nickName')->get()->toArray();
           $list = merge_array($list, $userList, 'promoterId', 'userId');
           $ipLocation = new IpLocation();
           foreach ($list as &$item) {
               $location = $ipLocation->getlocation($item['regInfo']['ip']??'');
               $item['address'] = $location['country'] . $location['area'];
               //$item['rechargeTypeName'] = $item['rechargeTypeName'] ?? $rechargeTypeIdNameKV[$item['rechargeTypeId']];
               $item['rechargeAmount'] = $this->formatMoneyFromMongo($item['rechargeAmount']??0);
               $item['exchangeAmount'] = $this->formatMoneyFromMongo($item['exchangeAmount']??0);
               $item['requestMoney'] = $this->formatMoneyFromMongo($item['requestMoney']);
               //$item['payMoney'] = $this->formatMoneyFromMongo($item['payMoney']);
               //$item['tax'] = $this->formatMoneyFromMongo($item['tax']);
               $item['score'] = $this->formatMoneyFromMongo($item['score']??0);
               $item['bankScore'] = $this->formatMoneyFromMongo($item['bankScore']??0);
               $item['usdt'] = $this->formatMoneyFromMongo($item['usdt']??0);
               $item['usdtRate'] = $this->formatMoneyFromMongo($item['usdtRate']??0);

               $actTimeResult = $this->diffTime($item['createTime'], $item['applyTime']);
               $item['serviceTime'] = Sec3Time($actTimeResult);
               $item['createTime'] = $this->formatDate($item['createTime']);
               $item['applyTime'] = $this->formatDate($item['applyTime']);

               $item['remark'] = $item['remark'] ?? '';

               $item['statusCn'] = AgentExchangeOrder::STATUS[$item['status']]??'未知状态';


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
            $requestMoneySum = AgentExchangeOrder::where($where)->sum('requestMoney');
            $requestMoneySum = $this->formatMoneyFromMongo($requestMoneySum);

            return json(['code' => 0, 'msg' => 'ok', 'data' => ['requestMoneySum' => $requestMoneySum, 'payMoneySum' => $requestMoneySum]]);
        }
    }

    public function exportExchange(Request $request)
    {
        $where = $this->_ajaxParam();
        if (!is_array($where)) return $where;
        //$rechargeTypeIdNameKV = RechargeType::rechargeTypeIdNameKV();
        $cursor = AgentExchangeOrder::where($where)->orderBy('createTime', 'desc')->cursor();
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
            $sheet->setCellValue("G{$num}", AgentExchangeOrder::STATUS[$item->status]);
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
        $file_path = public_path().'/recharge.xlsx';
        // 保存文件到 public 下
        $writer->save($file_path);

        return json(['code' => 0, 'msg' => 'ok', 'file' => $file_path]);
    }
}
