<?php
namespace app\controller;

use app\model\GameUser;
use app\model\Promoter;
use app\model\PromoterLevel;
use app\model\PromoterPackage;
use app\model\StatPromoterDaily;
use app\model\UserScoreChange;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use support\bootstrap\Container;
use support\Db;
use support\Request;

class Agent extends Base
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

    private function _agentListAjaxParam()
    {
        $where = $filter = [];
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
        $where[] = ['createTime', '>=', $this->formatTimestampToMongo($startTime)];
        $where[] = ['createTime', '<', $this->formatTimestampToMongo($endTime)];
        if (!empty($channelId)) $where['channelId'] = $channelId;
        if (!empty($status)) {
            if ($status == 2) {
                $where['withdrawLocked'] = 1;
            } else {
                $where['status'] = $status;
            }
        }
        if (!empty($searchType) && !empty($searchValue)) {
            if ($searchType == 1) {
                $where['promoterId'] = (int)$searchValue;
            }
        }
        $orderDirect = 'asc';
        if (!empty($level) && $level == -1) $orderDirect = 'desc';

        //$filter['date']= ['$gte' => $this->formatTimestampToMongo($startTime), '$lt' => $this->formatTimestampToMongo($endTime)];

        return ['where' => $where, 'orderBy' => $orderDirect];
    }

    public function exportAgent(Request $request)
    {
        $where = $this->_agentListAjaxParam();
        if (!is_array($where)) return $where;
        $cursor = Promoter::where($where['where'])->orderBy('level', $where['orderBy'])->orderBy('createTime', 'asc')->cursor();
        $promoterLevel = PromoterLevel::all(['Id','title'])->toArray();
        $directTeamData = Db::connection('mongodb_main')->collection('stat_promoter_daily')->raw()->aggregate([
//            [
//                '$match' => $where['filter']
//            ],
            [
                '$project' =>
                    [
                        'promoterId' => 1,
                        'rechargeAmount' => 1,//直属今天总充值
                        'exchangeAmount' => 1,//直属今天总提现
                        'totalDirectRechargeAmount' => 1,//直属总充值
                        'totalDirectExchangeAmount' => 1,//直属总提现
                        'totalDirectFlowAmount' => 1,//直属会员总流水
                        'totalDirectValidFlowAmount' => 1,//直属会员有效总流水
                        //'totalDirectFlowAmount' => 1,//直属中奖
                        'profit' => 1,//直属今天收益
                        'totalDirectPlayerCount' => 1,//直属人数
                        'totalTeamPlayerCount' => 1,//团队人数
                        'teamRechargeAmount' => 1,//团队今天总充值
                        'teamExchangeAmount' => 1,//团队今天总提现
                        'totalTeamFlowAmount' => 1,//团队业绩(团队总流水)
                        'totalTeamValidFlowAmount' => 1,//团队有效业绩(团队有效总流水)
                        'totalTeamRechargeAmount' => 1,//团队总充值
                        'totalTeamExchangeAmount' => 1,//团队总提现
                        //'teamFlowAmount' => 1,//团队中奖
                        'teamProfit' => 1,//团队今天收益
                        'date' =>1
                    ]
            ],
            [
                '$sort' => ['date'=>-1]
            ],
            [
                '$group' =>
                    [
                        '_id' => '$promoterId',
                        'promoterId'=> ['$first'=>'$promoterId'],
                        //'rechargeAmount' => ['$sum' => '$rechargeAmount'],//直属总充值
                        //'exchangeAmount' => ['$sum' => '$exchangeAmount'],//直属总提现
                        'rechargeAmount'=> ['$first'=>'$totalDirectRechargeAmount'],//直属总充值
                        'exchangeAmount'=> ['$first'=>'$totalDirectExchangeAmount'],//直属总提现
                        'totalDirectFlowAmount' => ['$first' => '$totalDirectFlowAmount'],//直属总业绩
                        'totalDirectValidFlowAmount' => ['$first' => '$totalDirectValidFlowAmount'],//直属有效总业绩
                        'profit' => ['$sum' => '$profit'],//直属总收益
                        'totalDirectPlayerCount'=> ['$first'=>'$totalDirectPlayerCount'],//直属人数
                        'totalTeamPlayerCount'=> ['$first'=>'$totalTeamPlayerCount'],//团队人数
                        //'teamRechargeAmount'=> ['$sum'=>'$teamRechargeAmount'],//团队总充值
                        //'teamExchangeAmount'=> ['$sum'=>'$teamExchangeAmount'],//团队总提现
                        'teamRechargeAmount'=> ['$first'=>'$totalTeamRechargeAmount'],//团队总充值
                        'teamExchangeAmount'=> ['$first'=>'$totalTeamExchangeAmount'],//团队总提现
                        'totalTeamFlowAmount'=> ['$first'=>'$totalTeamFlowAmount'],//团队业绩
                        'totalTeamValidFlowAmount'=> ['$first'=>'$totalTeamValidFlowAmount'],//团队有效业绩
                        //'teamFlowAmount'=> ['$sum'=>'$teamFlowAmount'],//团队中奖
                        'teamProfit'=> ['$sum'=>'$teamProfit'],//团队收益
                    ]
            ],
        ])->toArray();
        $session = $request->session();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', '注册渠道');
        $sheet->setCellValue('B1', '代理ID');
        $sheet->setCellValue('C1', '代理名称');
        $sheet->setCellValue('D1', '代理等级');
        $sheet->setCellValue('E1', '等级名称');
        $sheet->setCellValue('F1', '推广链接');
        $sheet->setCellValue('G1', '可提');
        $sheet->setCellValue('H1', '已提');
        $sheet->setCellValue('I1', '提成');
        $sheet->setCellValue('J1', '直接成员');
        $sheet->setCellValue('K1', '直属人数');
        $sheet->setCellValue('L1', '团队人数');
        $sheet->setCellValue('M1', '直属总充值');
        $sheet->setCellValue('N1', '直属总提现');
        $sheet->setCellValue('O1', '直属业绩');
        $sheet->setCellValue('P1', '直属收益');
        $sheet->setCellValue('Q1', '团队总充值');
        $sheet->setCellValue('R1', '团队总提现');
        $sheet->setCellValue('S1', '团队业绩');
        $sheet->setCellValue('T1', '团队收益');
        $sheet->setCellValue('U1', '创建时间');
        $sheet->setCellValue('V1', '上级代理');

        if($session->get('userName') == "admin"){
            $sheet->setCellValue('W1', '手机号码');
        }
        $num = 2;

        foreach ($cursor as $item) {
            $title = '';
            foreach ($promoterLevel as $pl) {
                if ($pl['Id'] == $item->level) {
                    $title = $pl['title'];
                    break;
                }
            }
            $addData = [];
            $addData['totalDirectPlayerCount_team'] = 0;//直属人数
            $addData['totalTeamPlayerCount_team'] = 0;//团队人数
            $addData['rechargeAmount_direct'] = 0;
            $addData['exchangeAmount_direct'] = 0;
            $addData['totalDirectValidFlowAmount_direct'] = 0;
            $addData['profit_direct'] = 0;
            $addData['teamRechargeAmount_team'] = 0;
            $addData['teamExchangeAmount_team'] = 0;
            $addData['totalTeamValidFlowAmount_team'] = 0;
            $addData['teamProfit_team'] = 0;
            foreach ($directTeamData as $dt_item){
                if($item->promoterId == $dt_item['promoterId']){
                    $addData['totalDirectPlayerCount_team'] = $dt_item['totalDirectPlayerCount']??0;//直属人数
                    $addData['totalTeamPlayerCount_team'] = $dt_item['totalTeamPlayerCount']??0;//团队人数
                    $addData['rechargeAmount_direct'] = $dt_item['rechargeAmount']??0;//直属总充值
                    $addData['rechargeAmount_direct'] = $this->formatMoneyFromMongo($addData['rechargeAmount_direct']);
                    $addData['exchangeAmount_direct'] = $dt_item['exchangeAmount']??0;//直属总提现
                    $addData['exchangeAmount_direct'] = $this->formatMoneyFromMongo($addData['exchangeAmount_direct']);
                    $addData['totalDirectValidFlowAmount_direct'] = $dt_item['totalDirectValidFlowAmount']??0;//直属有效业绩
                    $addData['totalDirectValidFlowAmount_direct'] = $this->formatMoneyFromMongo($addData['totalDirectValidFlowAmount_direct']);
                    $addData['profit_direct'] = $dt_item['profit']??0;//直属总收益
                    $addData['profit_direct'] = $this->formatMoneyFromMongo($addData['profit_direct']);
                    $addData['teamRechargeAmount_team'] = $dt_item['teamRechargeAmount']??0;//团队总充值
                    $addData['teamRechargeAmount_team'] = $this->formatMoneyFromMongo($addData['teamRechargeAmount_team']);
                    $addData['teamExchangeAmount_team'] = $dt_item['teamExchangeAmount']??0;//团队总提现
                    $addData['teamExchangeAmount_team'] = $this->formatMoneyFromMongo($addData['teamExchangeAmount_team']);
                    $addData['totalTeamValidFlowAmount_team'] = $dt_item['totalTeamValidFlowAmount']??0;//团队有效业绩(团队有效总流水)
                    $addData['totalTeamValidFlowAmount_team'] = $this->formatMoneyFromMongo($addData['totalTeamValidFlowAmount_team']);
                    $addData['teamProfit_team'] = $dt_item['teamProfit']??0;//团队收益
                    $addData['teamProfit_team'] = $this->formatMoneyFromMongo($addData['teamProfit_team']);
                }
            }


            $sheet->setCellValue("A{$num}", $item->channelId);
            $sheet->setCellValue("B{$num}", $item->promoterId);
            $sheet->setCellValue("C{$num}", $item->promoterName);
            $sheet->setCellValue("D{$num}", $item->level);
            $sheet->setCellValue("E{$num}", $title);
            $sheet->setCellValue("F{$num}", $item->URL);
            $sheet->setCellValue("G{$num}", $this->formatMoneyFromMongo($item->score));
            $sheet->setCellValue("H{$num}", $this->formatMoneyFromMongo($item->totalExchange));
            if($item->setRate > 0 ){
                $sheet->setCellValue("I{$num}", $item->setRate);
            }else{
                $sheet->setCellValue("I{$num}", $item->curRate);
            }

            $sheet->setCellValue("J{$num}", $item->myPlayerTotalCount);
            $sheet->setCellValue("K{$num}", $addData['totalDirectPlayerCount_team']);
            $sheet->setCellValue("L{$num}", $addData['totalTeamPlayerCount_team']);
            $sheet->setCellValue("M{$num}", $addData['rechargeAmount_direct']);
            $sheet->setCellValue("N{$num}", $addData['exchangeAmount_direct']);
            $sheet->setCellValue("O{$num}", $addData['totalDirectValidFlowAmount_direct']);
            $sheet->setCellValue("P{$num}", $addData['profit_direct']);
            $sheet->setCellValue("Q{$num}", $addData['teamRechargeAmount_team']);
            $sheet->setCellValue("R{$num}", $addData['teamExchangeAmount_team']);
            $sheet->setCellValue("S{$num}", $addData['totalTeamValidFlowAmount_team']);
            $sheet->setCellValue("T{$num}", $addData['teamProfit_team']);
            $sheet->setCellValue("U{$num}", $this->formatDate($item->createTime));
            $sheet->setCellValue("V{$num}", $item->pid);
            if($session->get('userName') == "admin"){
                $whereUser = ['userId' => $item->promoterId];
                $user = GameUser::where($whereUser)->first(['mobile']);
                if(!empty($user)){
                    $user = $user->toArray();
                    $sheet->setCellValue("W{$num}", mobile($user['mobile']));
                }
            }

            $num++;
        }
        $writer = new Xlsx($spreadsheet);
        $fileName = 'agent'. date("ymdHis") . '.xlsx';
        $filePath = public_path().'/'.$fileName;
        $writer->save($filePath);
        $env = check_env($request->header());
        if ($env == "prod") {
            $downIp = DOWN_IP;
        } else {
            $downIp = $request->header('origin');
        }
        return json(['code' => 0, 'msg' => 'ok', 'file' => $downIp . '/' . $fileName]);
    }

    public function agentList(Request $request)
    {
        if ($request->isAjax()) {
            /*$where = [];
            $getData = check_type($request->all());dd($getData);
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
//            $where[] = ['createTime', '>=', $this->formatTimestampToMongo($startTime)];
//            $where[] = ['createTime', '<', $this->formatTimestampToMongo($endTime)];
            if (!empty($channelId)) $where['channelId'] = $channelId;
            if (!empty($status)) {
                if ($status == 2) {
                    $where['withdrawLocked'] = 1;
                } else {
                    $where['status'] = $status;
                }
            }
            if (!empty($searchType) && !empty($searchValue)) {
                if ($searchType == 1) {
                    $where['promoterId'] = (int)$searchValue;
                }
            }
            $orderDirect = 'asc';
            if (!empty($level) && $level == -1) $orderDirect = 'desc';*/
            $where = $this->_agentListAjaxParam();
            if (!is_array($where)) return $where;
            $count = Promoter::where($where['where'])->count();
            $list = Promoter::where($where['where'])->orderBy('level', $where['orderBy'])->orderBy('createTime', 'asc')->skip($request->skip)->take($request->limit)->get()->toArray();
            $promoterLevel = PromoterLevel::all(['Id','title'])->toArray();
            $list = merge_array($list, $promoterLevel, 'level', 'Id');
//            $statData = StatPromoterDaily::where('date', date('Y-m-d H:i:s'))->get()->toArray();
//            $list = merge_array($list, $statData, 'promoterId');


            //$filter = [];
            //$filter ['date']= ['$gte' => $this->formatTimestampToMongo($startTime), '$lt' => $this->formatTimestampToMongo($endTime)];
            $directTeamData = Db::connection('mongodb_main')->collection('stat_promoter_daily')->raw()->aggregate([
//                [
//                    '$match' => $where['filter']
//                ],
                [
                    '$project' =>
                        [
                            'promoterId' => 1,
                            'rechargeAmount' => 1,//直属今天总充值
                            'exchangeAmount' => 1,//直属今天总提现
                            'totalDirectRechargeAmount' => 1,//直属总充值
                            'totalDirectExchangeAmount' => 1,//直属总提现
                            'totalDirectFlowAmount' => 1,//直属会员总流水
                            'totalDirectValidFlowAmount' => 1,//直属会员有效总流水
                            //'totalDirectFlowAmount' => 1,//直属中奖
                            'profit' => 1,//直属今天收益
                            'totalDirectPlayerCount' => 1,//直属人数
                            'totalTeamPlayerCount' => 1,//团队人数
                            'teamRechargeAmount' => 1,//团队今天总充值
                            'teamExchangeAmount' => 1,//团队今天总提现
                            'totalTeamFlowAmount' => 1,//团队业绩(团队总流水)
                            'totalTeamValidFlowAmount' => 1,//团队有效业绩(团队有效总流水)
                            'totalTeamRechargeAmount' => 1,//团队总充值
                            'totalTeamExchangeAmount' => 1,//团队总提现
                            //'teamFlowAmount' => 1,//团队中奖
                            'teamProfit' => 1,//团队今天收益
                            'date' =>1
                        ]
                ],
                [
                    '$sort' => ['date'=>-1]
                ],
                [
                    '$group' =>
                        [
                            '_id' => '$promoterId',
                            'promoterId'=> ['$first'=>'$promoterId'],
                            //'rechargeAmount' => ['$sum' => '$rechargeAmount'],//直属总充值
                            //'exchangeAmount' => ['$sum' => '$exchangeAmount'],//直属总提现
                            'rechargeAmount'=> ['$first'=>'$totalDirectRechargeAmount'],//直属总充值
                            'exchangeAmount'=> ['$first'=>'$totalDirectExchangeAmount'],//直属总提现
                            'totalDirectFlowAmount' => ['$first' => '$totalDirectFlowAmount'],//直属总业绩
                            'totalDirectValidFlowAmount' => ['$first' => '$totalDirectValidFlowAmount'],//直属有效总业绩
                            'profit' => ['$sum' => '$profit'],//直属总收益
                            'totalDirectPlayerCount'=> ['$first'=>'$totalDirectPlayerCount'],//直属人数
                            'totalTeamPlayerCount'=> ['$first'=>'$totalTeamPlayerCount'],//团队人数
                            //'teamRechargeAmount'=> ['$sum'=>'$teamRechargeAmount'],//团队总充值
                            //'teamExchangeAmount'=> ['$sum'=>'$teamExchangeAmount'],//团队总提现
                            'teamRechargeAmount'=> ['$first'=>'$totalTeamRechargeAmount'],//团队总充值
                            'teamExchangeAmount'=> ['$first'=>'$totalTeamExchangeAmount'],//团队总提现
                            'totalTeamFlowAmount'=> ['$first'=>'$totalTeamFlowAmount'],//团队业绩
                            'totalTeamValidFlowAmount'=> ['$first'=>'$totalTeamValidFlowAmount'],//团队有效业绩
                            //'teamFlowAmount'=> ['$sum'=>'$teamFlowAmount'],//团队中奖
                            'teamProfit'=> ['$sum'=>'$teamProfit'],//团队收益
                        ]
                ],
            ])->toArray();

            foreach ($list as &$item) {
                $item['createTime'] = $this->formatDate($item['createTime']);

                $item['score'] = $this->formatMoneyFromMongo($item['score']);
                $item['totalExchange'] = $this->formatMoneyFromMongo($item['totalExchange']);
                $item['myPlayerCount_direct'] = $item['totalDirectPlayerCount']??0;//直属人数
                //团队直属默认为0
                $item['rechargeAmount_direct'] = 0;
                $item['exchangeAmount_direct'] = 0;
                $item['totalDirectFlowAmount_direct'] = 0;
                $item['totalDirectValidFlowAmount_direct'] = 0;
                $item['profit_direct'] = 0;
                $item['teamRechargeAmount_team'] = 0;
                $item['teamExchangeAmount_team'] = 0;
                $item['totalTeamFlowAmount_team'] = 0;
                $item['totalTeamValidFlowAmount_team'] = 0;
                $item['teamProfit_team'] = 0;
                $item['totalTeamPlayerCount_team'] = 0;//团队人数
                $item['totalDirectPlayerCount_team'] = 0;//直属人数
                foreach ($directTeamData as $dt_item){
                    if($item['promoterId'] == $dt_item['promoterId']){
                        $item['rechargeAmount_direct'] = $dt_item['rechargeAmount']??0;//直属总充值
                        $item['rechargeAmount_direct'] = $this->formatMoneyFromMongo($item['rechargeAmount_direct']);
                        $item['exchangeAmount_direct'] = $dt_item['exchangeAmount']??0;//直属总提现
                        $item['exchangeAmount_direct'] = $this->formatMoneyFromMongo($item['exchangeAmount_direct']);
                        $item['totalDirectFlowAmount_direct'] = $dt_item['totalDirectFlowAmount']??0;//直属业绩
                        $item['totalDirectFlowAmount_direct'] = $this->formatMoneyFromMongo($item['totalDirectFlowAmount_direct']);
                        $item['totalDirectValidFlowAmount_direct'] = $dt_item['totalDirectValidFlowAmount']??0;//直属有效业绩
                        $item['totalDirectValidFlowAmount_direct'] = $this->formatMoneyFromMongo($item['totalDirectValidFlowAmount_direct']);
                        $item['profit_direct'] = $dt_item['profit']??0;//直属总收益
                        $item['profit_direct'] = $this->formatMoneyFromMongo($item['profit_direct']);
                        $item['totalDirectPlayerCount_team'] = $dt_item['totalDirectPlayerCount']??0;//直属人数
                        $item['totalTeamPlayerCount_team'] = $dt_item['totalTeamPlayerCount']??0;//团队人数
                        $item['teamRechargeAmount_team'] = $dt_item['teamRechargeAmount']??0;//团队总充值


                        $item['teamRechargeAmount_team'] = $this->formatMoneyFromMongo($item['teamRechargeAmount_team']);
                        $item['teamExchangeAmount_team'] = $dt_item['teamExchangeAmount']??0;//团队总提现
                        $item['teamExchangeAmount_team'] = $this->formatMoneyFromMongo($item['teamExchangeAmount_team']);
                        $item['totalTeamFlowAmount_team'] = $dt_item['totalTeamFlowAmount']??0;//团队业绩(团队总流水)
                        $item['totalTeamFlowAmount_team'] = $this->formatMoneyFromMongo($item['totalTeamFlowAmount_team']);
                        $item['totalTeamValidFlowAmount_team'] = $dt_item['totalTeamValidFlowAmount']??0;//团队有效业绩(团队有效总流水)
                        $item['totalTeamValidFlowAmount_team'] = $this->formatMoneyFromMongo($item['totalTeamValidFlowAmount_team']);

                        $item['teamProfit_team'] = $dt_item['teamProfit']??0;//团队收益
                        $item['teamProfit_team'] = $this->formatMoneyFromMongo($item['teamProfit_team']);
                    }
                }
            }
            return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $list]);

        }
        $getData = check_type($request->all());
        extract($getData);
        $startDate = !empty($startDate) ? trim($startDate) : "";
        $endDate = !empty($endDate) ? trim($endDate) : "";
        $searchValue = !empty($searchValue) ? (int)$searchValue : '';
        $searchType = !empty($searchType) ? (int)$searchType : '';


        $assignData = [
            'startDate'=>$startDate,
            'endDate'=>$endDate,
            'searchValue'=>$searchValue,
            'searchType'=>$searchType
        ];
        return view('agent/agentList/list', $assignData);
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
        $where['date'] = ['$gte' => $this->formatTimestampToMongo($startTime), '$lt' => $this->formatTimestampToMongo($endTime)];
        $where1['createTime'] = ['$gte' => $this->formatTimestampToMongo($startTime), '$lt' => $this->formatTimestampToMongo($endTime)];
        $where2[] = ['date', '>=', $this->formatTimestampToMongo($startTime)];
        $where2[] = ['date', '<', $this->formatTimestampToMongo($endTime)];

        if (empty($searchText)) $searchText = 1000;
        if (!empty($searchText)) {
            $searchText = (int)$searchText;
            $searchTextLen = strlen($searchText);
            if(in_array($searchTextLen, PROMOTER_ID_LENGTH)) {
                $where['promoterId'] = (int)$searchText;
            }elseif($searchText == SYSTEM_PROMOTER_ID){
                $where['promoterId'] = ['$eq' => SYSTEM_PROMOTER_ID];
            }else {
                return json(['code' => -1, 'msg' => '代理ID长度不正确']);
            }
        }
        $where2['promoterId'] = (int)$searchText;
        //$where['channelId'] = 1;

        return ['where' => $where, 'where1' => $where1, 'where2' => $where2];
    }

    public function daily(Request $request)
    {
        if ($request->isAjax()) {
            $where = $this->_ajaxParam();

            if (!is_array($where)) return $where;

            $statData = Db::connection('mongodb_main')->collection('stat_promoter_daily')->raw()->aggregate([
                [
                    '$match' => $where['where']
                ],
                [
                    '$project' =>
                        [
                            '_id' => 1,
                            'date' => 1,
                            'Date' => ['$substr' => [['$add' => ['$date',28800000]], 0, 10]],
                            'teamRegPeople' => 1,
                            'teamFlowAmount' => 1,
                            'teamValidFlowAmount' => 1,
                            'teamProfit' => 1,
                            'teamTransferToScoreAmount' => 1,
                            'transferToScoreAmount' => 1,
                        ]
                ],
                [
                    '$group' =>
                        [
                            '_id' => '$Date',
                            'teamRegPeople' => ['$sum' => '$teamRegPeople'],
                            'teamFlowAmount' => ['$sum' => '$teamFlowAmount'],
                            'teamValidFlowAmount' => ['$sum' => '$teamValidFlowAmount'],
                            'teamProfit' => ['$sum' => '$teamProfit'],
                            'teamTransferToScoreAmount' => ['$sum' => '$teamTransferToScoreAmount'],
                            'transferToScoreAmount' => ['$sum' => '$transferToScoreAmount'],
                        ]
                ],
                [
                    '$sort' => ['_id' => -1]
                ],
                [
                    '$skip' => $request->skip
                ],
                [
                    '$limit' => $request->limit
                ]
            ])->toArray();//dd($statData);dd($where);
            $statDataAll = Db::connection('mongodb_main')->collection('stat_promoter_daily')->raw()->aggregate([
                [
                    '$match' => $where['where']
                ],
                [
                    '$project' =>
                        [
                            '_id' => 1,
                            'date' => 1,
                            'Date' => ['$substr' => [['$add' => ['$date',28800000]], 0, 10]],
                            'teamRegPeople' => 1,
                            'teamFlowAmount' => 1,
                            'teamValidFlowAmount' => 1,
                            'teamProfit' => 1,
                            'teamTransferToScoreAmount' => 1,
                            'transferToScoreAmount' => 1,
                        ]
                ],
                [
                    '$group' =>
                        [
                            '_id' => '$Date',
                            'teamRegPeople' => ['$sum' => '$teamRegPeople'],
                            'teamFlowAmount' => ['$sum' => '$teamFlowAmount'],
                            'teamValidFlowAmount' => ['$sum' => '$teamValidFlowAmount'],
                            'teamProfit' => ['$sum' => '$teamProfit'],
                            'teamTransferToScoreAmount' => ['$sum' => '$teamTransferToScoreAmount'],
                            'transferToScoreAmount' => ['$sum' => '$transferToScoreAmount'],
                        ]
                ]
//                [
//                    '$group' =>
//                        [
//                            '_id' => null,
//                            'count' => ['$sum' => 1]
//                        ]
//                ]
            ])->toArray();
            $count = count($statDataAll);

//            $count = 0;
//            if($statDataAll && sizeof($statDataAll) > 0)
//                $count = $statDataAll[0]['count'];

            $promoterData = Db::connection('mongodb_main')->collection('promoter')->raw()->aggregate([
                [
                    '$match' => $where['where1']
                ],
                [
                    '$project' =>
                        [
                            '_id' => 1,
                            'createTime' => 1,
                            'mobile' => 1,
                            //'Date' => ['$substr' => ['$createTime', 0, 10]],
                            'Date' => ['$substr' => [['$add' => ['$createTime',28800000]], 0, 10]],
                        ]
                ],
                [
                    '$group' =>
                        [
                            '_id' => '$Date',
                            'newAgent' => ['$sum' => 1],
                            'newAgentMobile' => ['$sum'=>['$cond'=>['if'=>['$eq'=>['$mobile', '']], 'then'=>0, 'else'=>1]]],
                        ]
                ],
                [
                    '$sort' => ['_id' => -1]
                ]
            ])->toArray();
            $statData = merge_array_id($statData, $promoterData, '_id');//dd($statData);

            $statPromoterDailyData = StatPromoterDaily::where($where['where2'])->get(['date','promoterId','teamFlowAmount','teamValidFlowAmount','totalTeamFlowAmount','totalDirectFlowAmount','teamRegPromoterNum','teamActiveRegPromoterNum','teamTransferToScoreAmount'])->toArray();
            foreach ($statPromoterDailyData as $key => $value) {
                $statPromoterDailyData[$key]['date'] = $this->formatDate($value['date'], 'Y-m-d');
            }
            $statData = merge_array($statData, $statPromoterDailyData, '_id','date');
            foreach ($statData as &$item) {
                $item['newAgent'] = $item['newAgent']??0;
                $item['teamFlowAmount'] = $this->formatMoneyFromMongo($item['teamFlowAmount']??0);//sprintf("%.2f",substr(sprintf("%.3f", $item['teamFlowAmount']*100), 0, -1));
                $item['teamFlowAmount'] = sprintf("%.2f",substr(sprintf("%.3f", $item['teamFlowAmount']), 0, -1));

                $item['teamValidFlowAmount'] = $this->formatMoneyFromMongo($item['teamValidFlowAmount']);
                $item['teamValidFlowAmount'] = sprintf("%.2f",substr(sprintf("%.3f", $item['teamValidFlowAmount']), 0, -1));

                $item['teamProfit'] = $this->formatMoneyFromMongo($item['teamProfit']);
                $item['teamProfit'] = sprintf("%.2f",substr(sprintf("%.3f", $item['teamProfit']), 0, -1));

                $item['teamTransferToScoreAmount'] = $this->formatMoneyFromMongo($item['teamTransferToScoreAmount']);
                $item['transferToScoreAmount'] = $this->formatMoneyFromMongo($item['transferToScoreAmount']);

                $item['totalDirectFlowAmount'] = $item['totalDirectFlowAmount']??0;
                $item['totalTeamFlowAmount'] = $item['totalTeamFlowAmount']??0;
                $item['totalTeamFlowAmount'] -= $item['totalDirectFlowAmount'];
                $item['totalTeamFlowAmount'] = $this->formatMoneyFromMongo($item['totalTeamFlowAmount'])*0.025;
                $item['totalTeamFlowAmount'] = sprintf("%.2f",substr(sprintf("%.3f", $item['totalTeamFlowAmount']), 0, -1));
                //$new_data[$item_date] = sprintf("%.2f",substr(sprintf("%.3f", $result_data['vals_t']*100), 0, -1)); //保留两位不进行四舍五入
            }
            //dd($statData);
            return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $statData]);
        }
        $assignData = [
            'channel' => []
        ];
        return view('agent/daily/list', $assignData);
    }

    public function levelList(Request $request)
    {
        $count = PromoterLevel::count();
        $list = PromoterLevel::orderBy('Id', 'asc')->get()->toArray();
        foreach ($list as &$item) {
            $item['teamAmount'] = $this->formatMoneyFromMongo($item['teamAmount']);
        }
        return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $list]);
    }

    public function addAgent(Request $request)
    {
        if ($request->isAjax()) {
            $postData = check_type($request->post());
            extract($postData);
            if(strlen($startId) != 5 || strlen($endId) != 5 || $startId > $endId) return json(['code' => -1, 'msg' => '开始ID 结束ID不正确']);
            $promoter = Promoter::where('promoterId', $promoterId)->first(['promoterId']);
            if (empty($promoter)) return json(['code' => -1, 'msg' => '上级代理不存在']);
            $where[] = ['promoterId', '>=', $startId];
            $where[] = ['promoterId', '<=', $endId];
            $promoter = Promoter::where($where)->first(['promoterId']);
            if (!empty($promoter)) return json(['code' => -1, 'msg' => '已经存在开始ID 结束ID之间的代理']);
            for($i = $startId; $i <= $endId; $i++) {
                $url = '';
                $insertData = [
                    'promoterId' => (int)$i,
                    'promoterName' => (string)$i,
                    'pid' => $promoterId,
                    'platformId' => 1,
                    'channelId' => 2,
                    'isAdmin' => 1,
                    'status' => 1,
                    'score' => 0,
                    'level' => 1,
                    'mobile' => '',
                    'withdrawLocked' => 1,
                    'totalExchange' => 0,
                    'myPlayerCount' => 0,
                    'teamPlayerCount' => 0,
                    'totalTeamIOSValue' => 0,
                    'totalTeamAndroidValue' => 0,
                    'totalTeamSimulatorValue' => 0,
                    'transferScore' => 0,
                    'totalTransferScore' => 0,
                    'transferWechat' => '',
                    'BalanceLimitDay' => 1,
                    'account' => '',
                    'password' => '',
                    'safe_password' => '',
                    'salt' => '',
                    'setRate' => -1,
                    'curRate' => 50,
                    'enableMail' => 1,
                    'alipayAccount' => '',
                    'alipayName' => '',
                    'bankCardNum' => '',
                    'bankCardName' => '',
                    'initScore' => 300,
                    'bindScore' => 300,
                    'iosReg' => 1,
                    'iosLogin' => 1,
                    'iosGiveScore' => 300,
                    'iosBindScore' => 300,
                    'androidReg' => 1,
                    'androidLogin' => 1,
                    'androidGiveScore' => 300,
                    'androidBindScore' => 300,
                    'simulatorReg' => 0,
                    'simulatorLogin' => 0,
                    'simulatorGiveScore' => 0,
                    'simulatorBindScore' => 0,
                    'showMyPlayerExchange' => 1,
                    'URL' => $url,
                    'isPackage' => 1,
                    'lastPackageDate' => new \MongoDB\BSON\UTCDateTime,
                    'allowPromoter' => 1,
                    'type' => 1,
                    'note' => '',
                    'createTime' => new \MongoDB\BSON\UTCDateTime,
                ];
                Promoter::insert($insertData);
                sendDataToQueue(['promoterId' => $i, 'channelId' => 2]);
            }

            return json(['code' => 0, 'msg' => '生成完毕']);

        }
        $where = [];
        $where[] = ['promoterId', '<', 99999];
        $promoter = Promoter::where($where)->orderBy('promoterId', 'desc')->first(['promoterId']);
        $assignData = [
            'startId' => $promoter->promoterId+1
        ];
        if ($assignData['startId'] < 10000) {
            $assignData['startId'] = 10000;
        }
        $assignData['queueLen'] = \support\bootstrap\Redis::lLen("HallServerToWebQueue");
        return view('agent/addAgent/add', $assignData);
    }

    public function batchBuildApk(Request $request)
    {
        if ($request->isAjax()) {
            $postData = check_type($request->post());
            extract($postData);
            if (!isset($apkshorturl)) return json(['code' => -1, 'msg' => '请选择打包、短链接']);
            if($startNO > $endNO) return json(['code' => -1, 'msg' => '开始NO 结束NO不正确']);

            if (isset($apkshorturl['apk']) && $apkshorturl['apk']) {
                $data = ['type'=>0, 'data'=>['NO'=>0]];
                for($i = $startNO; $i <= $endNO; $i++) {
                    $data['data']['NO'] = $i;
                    \support\bootstrap\Redis::lPush("HallServerToWebQueue", json_encode($data));
                }
            }
            if (isset($apkshorturl['shorturl']) && $apkshorturl['shorturl']) {
                $where = [];
                $where['Id'] = ['$gte'=>(int)$startNO, '$lte'=>(int)$endNO];
                $promoters = PromoterPackage::where($where)->get();
                if (empty($promoters)) return json(['code' => -1, 'msg' => '代理不存在']);
                $data = ['type'=>2, 'data'=>['NO'=>0, 'promoterId' => 0, 'channelId' => 0]];
                foreach ($promoters as $promoter) {
                    $data['data']['NO'] = $promoter->Id;
                    $data['data']['promoterId'] = $promoter->promoterId;
                    $data['data']['channelId'] = $promoter->channelId;
                    \support\bootstrap\Redis::lPush("HallServerToWebQueue", json_encode($data));
                }
            }

            return json(['code' => 0, 'msg' => '开始批量更新...']);
        }
    }

    public function batchRefreshShortUrl(Request $request)
    {
        if ($request->isAjax()) {
            $postData = check_type($request->post());
            extract($postData);
            if($startNO1 > $endNO1) return json(['code' => -1, 'msg' => '开始NO 结束NO不正确']);

            $where = [];
            $where['Id'] = ['$gte'=>(int)$startNO1, '$lte'=>(int)$endNO1];
            $promoters = PromoterPackage::where($where)->get();
            if (empty($promoters)) return json(['code' => -1, 'msg' => '代理不存在']);
            $data = ['type'=>2, 'data'=>['NO'=>0, 'promoterId' => 0, 'channelId' => 0]];
            foreach ($promoters as $promoter) {
                $data['data']['NO'] = $promoter->Id;
                $data['data']['promoterId'] = $promoter->promoterId;
                $data['data']['channelId'] = $promoter->channelId;
                \support\bootstrap\Redis::lPush("HallServerToWebQueue", json_encode($data));
            }
            return json(['code' => 0, 'msg' => '开始更新...']);
        }
    }

    public function refreshShortUrl(Request $request) {
        if ($request->isAjax()) {
            $postData = check_type($request->post());
            extract($postData);
            if(empty($promoterId2)) return json(['code' => -1, 'msg' => '代理ID不能为空']);
            $promoter = PromoterPackage::where('promoterId', (int)$promoterId2)->first();
            if (empty($promoter)) return json(['code' => -1, 'msg' => '代理不存在']);

            $data = ['type'=>2, 'data'=>['NO'=>$promoter->Id, 'promoterId' => $promoter->promoterId, 'channelId' => $promoter->channelId]];
            \support\bootstrap\Redis::lPush("HallServerToWebQueue", json_encode($data));
            return json(['code' => 0, 'msg' => '开始生成推广短链接...']);
        }
    }

    public function refreshShortUrlAndBatchBuildApk(Request $request) {
        if ($request->isAjax()) {
            $postData = check_type($request->post());
            extract($postData);
            if(empty($promoterId3)) return json(['code' => -1, 'msg' => '代理ID不能为空']);
            $promoter = PromoterPackage::where('promoterId', (int)$promoterId3)->first();
            if (empty($promoter)) return json(['code' => -1, 'msg' => '代理不存在']);

            $data = ['type'=>1, 'data'=>['NO'=>$promoter->Id, 'promoterId' => $promoter->promoterId, 'channelId' => $promoter->channelId]];
            \support\bootstrap\Redis::lPush("HallServerToWebQueue", json_encode($data));
            return json(['code' => 0, 'msg' => '开始生成推广短链接并打包...']);
        }
    }

    public function editAgent(Request $request)
    {
        if ($request->isAjax()) {
            //print_r($request->post());
            $postData = check_type($request->post());
            extract($postData);
//            if ($pid != 1000) {
//                return json(['code' => -1, 'msg' => '信息输入不正确']);
//            }
            $session = DB::connection('mongodb_main')->getMongoClient()->startSession();
            $session->startTransaction();
            try{
                $updateData = [
                    //'setRate' => $this->formatMoneytoMongo($setRate),
                    //'pid' => $pid,
                    'setRate' => (int)$setRate,
                    'allowTransferScore' => (int)$allowTransferScore,
                ];
                $updateResult = Promoter::where('_id', $_id)->update($updateData);
                if (!$updateResult) {
                    $session->abortTransaction();
                    return json(['code' => -1, 'msg' => '修改失败1']);
                }
                //$gmaeUser = GameUser::where('userId', $promoterId)->first();
//                if ($gmaeUser->promoterId != 1000) {
//                    $updateResult = GameUser::where('userId', $promoterId)->update(['promoterId' => $pid]);
//                    if (!$updateResult) {
//                        $session->abortTransaction();
//                        return json(['code' => -1, 'msg' => '修改失败2']);
//                    }
//                }
                $this->adminLog(["content"=>"设置代理".$promoterId."分成比例【".$setRate."】"]);
                return json(['code' => 0, 'msg' => '修改成功']);
            } catch(\Exception $e) {
                $session->abortTransaction();
                json(['code' => -1, 'msg' => '修改失败！']);
            }
        }
        $promoterId = (int)$request->get('promoterId', 0);
        $promoterData = Promoter::where('promoterId', $promoterId)->first();
        //$promoterData->setRate = $this->formatMoneyFromMongo($promoterData->setRate);
        return view('agent/addAgent/edit', ['formData' => json_encode($promoterData)]);
    }

    public function switchAgent(Request $request)
    {
        $postData = check_type($request->post());
        extract($postData);
        $updateData = check_type([$field => $value]);
        $updateResult = Promoter::where('_id', $_id)->update($updateData);
        if (!$updateResult) return json(['code' => -1, 'msg' => '修改失败']);
        return json(['code' => 0, 'msg' => '修改成功']);
    }

    public function agentDetail(Request $request)
    {
        $getData = check_type($request->get());
        extract($getData);
        if (empty($promoterId)) $promoterId = 1000;
        if (empty($pid)) $pid = 0;
        $tree = [];
        $promoterList = ['pid' => 0, 'zhishu' => 0, 'team' => 0];
        $temp = [];$temp2 = [];
        $promoters = Promoter::all(['promoterId', 'pid']);
        foreach($promoters as $promoter) {
            if (isset($promoter->pid)) {
                $tree[$promoter->pid][] = $promoter->promoterId;
            }
        }
        if (isset($tree[$promoterId])) {
            foreach ($tree[$promoterId] as $t) {
                $temp[] = ['pid'=>$promoterId, 'zhishu'=>$t];
            }
            if ($temp) {
                foreach ($temp as $tt) {
                    if (isset($tree[$tt['zhishu']])) {
                        foreach ($tree[$tt['zhishu']] as $ttt) {
                            $temp2[] = ['pid'=>$tt['pid'], 'zhishu'=>$tt['zhishu'], 'userId'=>$ttt];
                        }
                    } else {
                        $temp2[] = ['pid'=>$tt['pid'], 'zhishu'=>$tt['zhishu'], 'userId'=>0];
                    }
                }
            }
        }
        foreach ($temp2 as $tttt) {
            if ($tttt['userId']) {
                $tData = gTree($tree, $tttt['userId']);
                if ($tData) {
                    foreach ($tData as $tD) {
                        $temp2[] = $tD;
                    }
                }
            }
        }
        $users = GameUser::where('promoterId', $promoterId)->get(['userId']);
        foreach ($users as $user) {
            if (isset($user->userId) && !isset($tree[$promoterId])) {
                $temp2[] = ['pid'=>0, 'zhishu'=>$user->userId, 'userId'=>0];
            } elseif (isset($user->userId) && isset($tree[$promoterId]) && !in_array($user->userId, $tree[$promoterId])) {
                $temp2[] = ['pid'=>0, 'zhishu'=>$user->userId, 'userId'=>0];
            }
        }
        $zhishu = array_unique(array_column($temp2, 'zhishu'));$zhishu = array_values($zhishu);
        $team = array_column($temp2, 'userId');
        $users = GameUser::whereIn('promoterId', $zhishu)->get(['userId', 'promoterId']);
        foreach ($users as $user) {
            if (isset($user->userId) && !in_array($user->userId, $team)) {
                $temp2[] = ['pid'=>0, 'zhishu'=>$user->promoterId, 'userId'=>$user->userId];
            }
        }

        $users = GameUser::whereIn('promoterId', $team)->get(['userId', 'promoterId']);
        foreach ($users as $user) {
            if (isset($user->userId) && !in_array($user->userId, $team)) {
                $temp2[] = ['pid'=>0, 'zhishu'=>0, 'userId'=>$user->userId];
            }
        }





        $assignData = [
            'pid' => $pid,
            'data' => $temp2,
        ];
        return view('agent/agentDetail/list', $assignData);
    }

    public static function getPromoter($where, $column = ['*'])
    {
        return Promoter::where($where)->first($column);//get
    }

    public static function getPromoters($where, $column = ['*'])
    {
        return Promoter::where($where)->get($column)->toArray();
    }

    public function userStatus(Request $request)
    {
        $_ids = $request->post('_ids');
        $_idsArr = explode(",", $_ids);
        if (empty($_idsArr)) return json(['code' => -1, 'msg' => '参数错误']);
        foreach ($_idsArr as &$val) {
            $val = (int)$val;
        }
        $value = (int)$request->post('value');
        $updateResult = Promoter::whereIn('promoterId', $_idsArr)->update(['withdrawLocked' => $value]);
        if ($updateResult) {
            return json(['code' => 0, 'msg' => '操作成功']);
        }
        return json(['code' => -1, 'msg' => '操作失败']);
    }

    private function _agencyTransferAjaxParam()
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
        $endTime = strtotime($endDate) + 24*3600;
        if ($startTime > $endTime) return json(['code' => -1, 'msg' => '请核对开始时间结束时间']);
        $where[] = ['createTime', '>=', $this->formatTimestampToMongo($startTime)];
        $where[] = ['createTime', '<', $this->formatTimestampToMongo($endTime)];

        if (!empty($searchType) && !empty($searchValue)) {
            if ($searchType == 1) {
                $where['userId'] = (int)$searchValue;
            }
        }
        $where[] = ['changeType', '>=', 27];
        $where[] = ['changeType', '<=', 28];
        return $where;
    }

    public function agencyTransfer(Request $request)
    {
        if ($request->isAjax()) {
            $where = $this->_agencyTransferAjaxParam();
            if (!is_array($where)) return $where;
            $count = UserScoreChange::where($where)->count();
            $list = UserScoreChange::where($where)->orderBy('createTime', 'desc')->skip($request->skip)->take($request->limit)->get();
            if (!empty($list)) {
                foreach ($list as &$item) {
                    $item['createTime'] = $this->formatDate($item['createTime']);

                    $item['beforeScore'] = $this->formatMoneyFromMongo($item['beforeScore']);
                    $item['beforeBankScore'] = $this->formatMoneyFromMongo($item['beforeBankScore']);
                    $item['addScore'] = $this->formatMoneyFromMongo($item['addScore']);
                    $item['addBankScore'] = $this->formatMoneyFromMongo($item['addBankScore']);
                    $item['afterScore'] = $this->formatMoneyFromMongo($item['afterScore']);
                    $item['afterBankScore'] = $this->formatMoneyFromMongo($item['afterBankScore']);

                    $item['changeTypeName'] = resultChangeType($item['changeType']);
                    $item['remark'] = $item['remark']??'';
                }
            }
            return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $list]);
        }

        return view('agent/agencyTransfer/list', ['name' => '']);
    }

    private function _agentListAjaxParam2()
    {
        $where = $filter = [];
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
        /*$where[] = ['createTime', '>=', $this->formatTimestampToMongo($startTime)];
        $where[] = ['createTime', '<', $this->formatTimestampToMongo($endTime)];*/
        if (!empty($channelId)) $where['channelId'] = $channelId;
        if (!empty($status)) {
            if ($status == 2) {
                $where['withdrawLocked'] = 1;
            } else {
                $where['status'] = $status;
            }
        }
        if (!empty($searchType) && !empty($searchValue)) {
            if ($searchType == 1) {
                $where['promoterId'] = (int)$searchValue;
            }
        }
        $orderDirect = 'asc';
        if (!empty($level) && $level == -1) $orderDirect = 'desc';

        $filter['date']= ['$gte' => $this->formatTimestampToMongo($startTime), '$lt' => $this->formatTimestampToMongo($endTime)];

        return ['where' => $where, 'filter' => $filter, 'orderBy' => $orderDirect];
    }

    public function agentList2(Request $request)
    {
        if ($request->isAjax()) {
            /*$where = [];
            $getData = check_type($request->all());dd($getData);
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
//            $where[] = ['createTime', '>=', $this->formatTimestampToMongo($startTime)];
//            $where[] = ['createTime', '<', $this->formatTimestampToMongo($endTime)];
            if (!empty($channelId)) $where['channelId'] = $channelId;
            if (!empty($status)) {
                if ($status == 2) {
                    $where['withdrawLocked'] = 1;
                } else {
                    $where['status'] = $status;
                }
            }
            if (!empty($searchType) && !empty($searchValue)) {
                if ($searchType == 1) {
                    $where['promoterId'] = (int)$searchValue;
                }
            }
            $orderDirect = 'asc';
            if (!empty($level) && $level == -1) $orderDirect = 'desc';*/
            $where = $this->_agentListAjaxParam2();
            if (!is_array($where)) return $where;
            $count = Promoter::where($where['where'])->count();
            $list = Promoter::where($where['where'])->orderBy('level', $where['orderBy'])->orderBy('createTime', 'asc')->skip($request->skip)->take($request->limit)->get()->toArray();
            $promoterLevel = PromoterLevel::all(['Id','title'])->toArray();
            $list = merge_array($list, $promoterLevel, 'level', 'Id');
//            $statData = StatPromoterDaily::where('date', date('Y-m-d H:i:s'))->get()->toArray();
//            $list = merge_array($list, $statData, 'promoterId');


            //$filter = [];
            //$filter ['date']= ['$gte' => $this->formatTimestampToMongo($startTime), '$lt' => $this->formatTimestampToMongo($endTime)];
            $directTeamData = Db::connection('mongodb_main')->collection('stat_promoter_daily')->raw()->aggregate([
                [
                    '$match' => $where['filter']
                ],
                [
                    '$project' =>
                        [
                            'promoterId' => 1,
                            'rechargeAmount' => 1,//直属今天总充值
                            'exchangeAmount' => 1,//直属今天总提现
                            'directRechargeAmount' => 1,//直属今天总充值
                            'directExchangeAmount' => 1,//直属今天总提现
                            'totalDirectRechargeAmount' => 1,//直属总充值
                            'totalDirectExchangeAmount' => 1,//直属总提现
                            'totalDirectFlowAmount' => 1,//直属会员总流水
                            'totalDirectValidFlowAmount' => 1,//直属会员有效总流水
                            'validFlowAmount' => 1, //直属今天有效业绩
                            //'totalDirectFlowAmount' => 1,//直属中奖
                            'profit' => 1,//直属今天收益
                            'regPeople' => 1, //直接今天成员
                            'totalRegPeople' => 1, //直接人数
                            'regMemberPeople' => 1, //直属今天成员
                            'totalRegMemberPeople' => 1, //直属今天会员
                            'teamRegPeople' => 1, //团队今天人数
                            'totalDirectPlayerCount' => 1,//直属人数
                            'totalTeamPlayerCount' => 1,//团队人数
                            'totalTeamRegPeople' => 1,//团队人数
                            'teamRechargeAmount' => 1,//团队今天总充值
                            'teamExchangeAmount' => 1,//团队今天总提现
                            'totalTeamFlowAmount' => 1,//团队业绩(团队总流水)
                            'totalTeamValidFlowAmount' => 1,//团队有效业绩(团队有效总流水)
                            'totalTeamRechargeAmount' => 1,//团队总充值
                            'totalTeamExchangeAmount' => 1,//团队总提现
                            'teamFlowAmount' => 1,//团队今天流水
                            'teamValidFlowAmount' => 1,//团队今天有效流水
                            'teamProfit' => 1,//团队今天收益
                            'date' =>1

                        ]
                ],
                [
                    '$sort' => ['date'=>-1]
                ],
                [
                    '$group' =>
                        [
                            '_id' => '$promoterId',
                            'promoterId'=> ['$first'=>'$promoterId'],
                            'rechargeAmount' => ['$sum' => '$directRechargeAmount'],//直属总充值
                            'exchangeAmount' => ['$sum' => '$directExchangeAmount'],//直属总提现
                            //'rechargeAmount'=> ['$first'=>'$totalDirectRechargeAmount'],//直属总充值
                            //'exchangeAmount'=> ['$first'=>'$totalDirectExchangeAmount'],//直属总提现
                            //'totalDirectFlowAmount' => ['$first' => '$totalDirectFlowAmount'],//直属总业绩
                            //'totalDirectValidFlowAmount' => ['$first' => '$totalDirectValidFlowAmount'],//直属有效总业绩
                            'totalDirectValidFlowAmount' => ['$sum' => '$validFlowAmount'], //直属今天有效业绩
                            'profit' => ['$sum' => '$profit'],//直属总收益
                            'teamRechargeAmount'=> ['$sum'=>'$teamRechargeAmount'],//团队总充值
                            'teamExchangeAmount'=> ['$sum'=>'$teamExchangeAmount'],//团队总提现
                            //'teamRechargeAmount'=> ['$first'=>'$totalTeamRechargeAmount'],//团队总充值
                            //'teamExchangeAmount'=> ['$first'=>'$totalTeamExchangeAmount'],//团队总提现
                            //'totalTeamFlowAmount'=> ['$first'=>'$totalTeamFlowAmount'],//团队业绩
                            //'totalTeamValidFlowAmount'=> ['$first'=>'$totalTeamValidFlowAmount'],//团队有效业绩
                            'teamFlowAmount'=> ['$sum'=>'$teamFlowAmount'],//团队今天流水
                            'totalTeamValidFlowAmount'=> ['$sum'=>'$teamValidFlowAmount'],//团队今天有效流水
                            'teamProfit'=> ['$sum'=>'$teamProfit'],//团队收益
                            //'myPlayerTotalCount2'=> ['$sum'=>'$regPeople'],//直接人数
                            'myPlayerTotalCount2'=> ['$first'=>'$totalRegPeople'],//直接人数

                            //'totalDirectPlayerCount'=> ['$first'=>'$totalDirectPlayerCount'],//直属人数
                            //'totalDirectPlayerCount'=> ['$sum'=>'$regMemberPeople'],//直属人数
                            'totalDirectPlayerCount'=> ['$first'=>'$totalRegMemberPeople'],//直属会员
                            'totalTeamPlayerCount'=> ['$first'=>'$totalTeamPlayerCount'],//团队人数
                            //'totalTeamPlayerCount'=> ['$sum'=>'$teamRegPeople'],//团队人数

                        ]
                ],
            ])->toArray();

            foreach ($list as &$item) {
                $item['createTime'] = $this->formatDate($item['createTime']);

                $item['score'] = $this->formatMoneyFromMongo($item['score']);
                $item['totalExchange'] = $this->formatMoneyFromMongo($item['totalExchange']);
                $item['myPlayerCount_direct'] = $item['totalDirectPlayerCount']??0;//直属人数
                //团队直属默认为0
                $item['rechargeAmount_direct'] = 0;
                $item['exchangeAmount_direct'] = 0;
                $item['totalDirectFlowAmount_direct'] = 0;
                $item['totalDirectValidFlowAmount_direct'] = 0;
                $item['profit_direct'] = 0;
                $item['teamRechargeAmount_team'] = 0;
                $item['teamExchangeAmount_team'] = 0;
                $item['totalTeamFlowAmount_team'] = 0;
                $item['totalTeamValidFlowAmount_team'] = 0;
                $item['teamProfit_team'] = 0;
                $item['totalTeamPlayerCount_team'] = 0;//团队人数
                $item['totalDirectPlayerCount_team'] = 1;//直属人数
                $item['myPlayerTotalCount2'] = 1;//直接人数
                foreach ($directTeamData as $dt_item){
                    if($item['promoterId'] == $dt_item['promoterId']){
                        $item['rechargeAmount_direct'] = $dt_item['rechargeAmount']??0;//直属总充值
                        $item['rechargeAmount_direct'] = $this->formatMoneyFromMongo($item['rechargeAmount_direct']);
                        $item['exchangeAmount_direct'] = $dt_item['exchangeAmount']??0;//直属总提现
                        $item['exchangeAmount_direct'] = $this->formatMoneyFromMongo($item['exchangeAmount_direct']);
                        $item['totalDirectFlowAmount_direct'] = $dt_item['totalDirectFlowAmount']??0;//直属业绩
                        $item['totalDirectFlowAmount_direct'] = $this->formatMoneyFromMongo($item['totalDirectFlowAmount_direct']);
                        $item['totalDirectValidFlowAmount_direct'] = $dt_item['totalDirectValidFlowAmount']??0;//直属有效业绩
                        $item['totalDirectValidFlowAmount_direct'] = $this->formatMoneyFromMongo($item['totalDirectValidFlowAmount_direct']);
                        $item['profit_direct'] = $dt_item['profit']??0;//直属总收益
                        $item['profit_direct'] = $this->formatMoneyFromMongo($item['profit_direct']);
                        $item['totalDirectPlayerCount_team'] = $dt_item['totalDirectPlayerCount']??0;//直属人数
                        $item['totalDirectPlayerCount_team']++;
                        $item['totalTeamPlayerCount_team'] = $dt_item['totalTeamPlayerCount']??0;//团队人数
                        $item['teamRechargeAmount_team'] = $dt_item['teamRechargeAmount']??0;//团队总充值
                        $item['myPlayerTotalCount2'] = $dt_item['myPlayerTotalCount2']??0;//直接人数
                        $item['myPlayerTotalCount2']++;


                        $item['teamRechargeAmount_team'] = $this->formatMoneyFromMongo($item['teamRechargeAmount_team']);
                        $item['teamExchangeAmount_team'] = $dt_item['teamExchangeAmount']??0;//团队总提现
                        $item['teamExchangeAmount_team'] = $this->formatMoneyFromMongo($item['teamExchangeAmount_team']);
                        $item['totalTeamFlowAmount_team'] = $dt_item['totalTeamFlowAmount']??0;//团队业绩(团队总流水)
                        $item['totalTeamFlowAmount_team'] = $this->formatMoneyFromMongo($item['totalTeamFlowAmount_team']);
                        $item['totalTeamValidFlowAmount_team'] = $dt_item['totalTeamValidFlowAmount']??0;//团队有效业绩(团队有效总流水)
                        $item['totalTeamValidFlowAmount_team'] = $this->formatMoneyFromMongo($item['totalTeamValidFlowAmount_team']);

                        $item['teamProfit_team'] = $dt_item['teamProfit']??0;//团队收益
                        $item['teamProfit_team'] = $this->formatMoneyFromMongo($item['teamProfit_team']);
                    }
                }
            }
            return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $list]);

        }
        $getData = check_type($request->all());
        extract($getData);
        $startDate = !empty($startDate) ? trim($startDate) : "2021-11-17";
        $endDate = !empty($endDate) ? trim($endDate) : date("Y-m-d");
        $searchValue = !empty($searchValue) ? (int)$searchValue : '';
        $searchType = !empty($searchType) ? (int)$searchType : '';


        $assignData = [
            'startDate'=>$startDate,
            'endDate'=>$endDate,
            'searchValue'=>$searchValue,
            'searchType'=>$searchType
        ];
        return view('agent/agentList/list2', $assignData);
    }

}

