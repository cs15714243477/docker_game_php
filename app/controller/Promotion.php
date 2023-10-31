<?php
namespace app\controller;


use app\model\GameUser;
use app\model\PromotionRecord;
use app\model\RewardOrder;
use app\model\UserScoreChange;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use support\bootstrap\Container;
use support\Db;
use support\Request;
use app\model\PromotionPlan;
use app\model\PromotionPlanDaily;

class Promotion extends Base
{
    public function index(Request $request)
    {
        $act = $request->get('act');
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

    public function notice(Request $request)
    {
        return view('notice/list', ['name' => '']);
    }

    public function promoterLevel(Request $request)
    {
        return view('agent/promoterLevel/list', ['data' => '']);
    }

    public function promotionPlanAdd(Request $request)
    {
        if ($request->isAjax()) {
            $data = check_type($request->post());
            extract($data);
            if (empty($planTitle) || empty($planExplain)) return json(['code' => -1, 'msg' => '方案名称,说明为必填项']);

            $session = DB::connection('mongodb_main')->getMongoClient()->startSession();
            $session->startTransaction();

            $planId = PromotionPlan::max('planId');
            $newPlanId = intval($planId+1);
            $planData = [
                'planId' => $newPlanId,
                'planTitle' => (string)$planTitle,
                'planExplain' => (string)$planExplain,
                'createTime' => new \MongoDB\BSON\UTCDateTime,
            ];

            $insertResult = PromotionPlan::insert($planData);
            if (!$insertResult) {
                $session->abortTransaction();
                return json(['code' => -1, 'msg' => '添加方案失败']);
            }
            //在方案列表里再增加一条数据
            $statPlanData = [
                'planId' => $newPlanId
            ];
            $insertResult = PromotionPlanDaily::insert($statPlanData);
            if (!$insertResult) {
                $session->abortTransaction();
                return json(['code' => -1, 'msg' => '添加失败']);
            }
            $session->commitTransaction();
            return json(['code' => 0, 'msg' => '添加成功']);
        }
        return view('promotion/promotionPlanAdd/add');
    }

    public function promotionRecordAdd(Request $request)
    {
        //方案列表
        $planList = PromotionPlan::select('planId','planTitle')->get()->toArray();
        $planArr = [];
        foreach ($planList as $planItem){
            $planArr[$planItem['planId']] = $planItem['planTitle'];
        }
        if ($request->isAjax()) {
            $data = check_type($request->post());
            extract($data);
            if (empty($numbering) || empty($rechargeMoney)) return json(['code' => -1, 'msg' => '传入参数错误']);
            $rechargeMoney = intval($rechargeMoney * 100);
            if($numberType == 1){
                $where = [
                    'userId' => intval($numbering)
                ];
            }else{
                //先检测手机号
                $isMobile = preg_match_all('/(13\d|14[579]|15[^4\D]|17[^49\D]|18\d)\d{8}/', $numbering, $matches_mobile);
                if($isMobile == 0){
                    return json(['code' => -1, 'msg' => '手机格式错误']);
                }
                $mobileEncry = mobile_en($numbering);
                $where = [
                    'mobile' => $mobileEncry
                ];
            }

//            $promotionRecord = Promotion::getPromotionRecord($where, ['recordId','userId']);
//            if ($promotionRecord) return json(['code' => -1, 'msg' => $numbering.'已经赠送过金额']);

            $where['status'] = GameUser::USER_STATUS_ON;

            $user = Player::getPlayer($where, ['userId','score','mobile','bankScore','promoterId','rechargeAmount','rechargeTimes','rewardScore','clubRewardScore']);
            if (empty($user)) return json(['code' => -1, 'msg' => '会员不存在']);

            $session = DB::connection('mongodb_main')->getMongoClient()->startSession();
            $session->startTransaction();
            try {
                $rewardType = 135;
                //增加奖励订单记录
                $timeStr = date("YmdHis",time());
                $randStr = (string)rand(1000,9999);
                $orderId = "TGJL".$timeStr."-".(string)$user->userId."-".$randStr;
                $rewardOrderData = [
                    'orderId' => $orderId,
                    'userId' => $user->userId,
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
                    'reason' => $planArr[$planId].'(方案ID为'.$planId.')',
                    'operator' => session('userName')
                ];
                $insertResult = RewardOrder::raw()->insertOne($rewardOrderData, ['session' => $session]);
                if (!$insertResult) {
                    $session->abortTransaction();
                    return json(['code' => -1, 'msg' => '添加奖励订单记录失败']);
                }

                //更新金额,推广方式加钱忽略vip升级
                $updateData = [
                    'score' => $user->score + $rechargeMoney,
                    //'rechargeAmount' => $user->rechargeAmount + $rechargeMoney,
                    //'rechargeTimes' => $user->rechargeTimes + 1,
                ];
                $updateResult = GameUser::where($where)->update($updateData, ['session' => $session]);
                if (!$updateResult) {
                    $session->abortTransaction();
                    return json(['code' => -1, 'msg' => '充值失败']);
                }

                //增加金币变化
                $insertData = [
                    'userId' => $user->userId,
                    'beforeScore' => $user->score,
                    'beforeBankScore' => $user->bankScore,
                    'addScore' => $rechargeMoney,
                    'addBankScore' => 0,
                    'afterScore' => $user->score + $rechargeMoney,
                    'afterBankScore' => $user->bankScore,
                    'changeType' => $rewardType,
                    'roomId' => 0,
                    'createTime' => new \MongoDB\BSON\UTCDateTime,
                    'remark' => "推广方案充值",
                ];
                $insertResult = UserScoreChange::raw()->insertOne($insertData, ['session' => $session]);
                if (!$insertResult) {
                    $session->abortTransaction();
                    return json(['code' => -1, 'msg' => '添加分值改变失败']);
                }

                //充值成功后,录入记录
                $recordId = PromotionRecord::max('recordId');
                $insertProRecord = [
                    'recordId' => intval($recordId+1),
                    'planId' => intval($planId),
                    'userId' => $user->userId,
                    'mobile' => $user->mobile,
                    'rechargeMoney' => $rechargeMoney,
                    'createTime' => new \MongoDB\BSON\UTCDateTime,
                ];
                $insertResult = PromotionRecord::raw()->insertOne($insertProRecord, ['session' => $session]);
                if (!$insertResult) {
                    $session->abortTransaction();
                    return json(['code' => -1, 'msg' => '添加记录失败']);
                }

                $session->commitTransaction();
                $this->adminLog(["content"=>session("userName")."单独录入【".$numbering."】金币:".$this->formatMoneyFromMongo($rechargeMoney).",方案:".$planArr[$planId].'(方案ID为'.$planId.')']);
                //$info = "赠送" . $this->formatMoneyFromMongo($rechargeMoney) . "金币已到账";
                //sendData2(['userId'=>$user->userId, 'orderId'=>$orderId, 'type'=>0, 'money'=>$rechargeMoney, 'status'=>1, 'info'=>$info]);
                return json(['code' => 0, 'msg' => '充值成功']);
            } catch (\Exception $e) {
                $session->abortTransaction();
                return json(['code' => -1, 'msg' => $e->getMessage()]);
            }
        }

        $assignData = [
            'planList' => $planList
        ];
        return view('promotion/promotionRecordAdd/edit',$assignData);
    }


    public function batchRecordAdd(Request $request)
    {
        if ($request->isAjax()) {
            $request = request();
            $data = $request->all();
            extract($data);
            $inputFileType = IOFactory::identify($pathName);
            $reader = IOFactory::createReader($inputFileType);
            $spreadsheet = $reader->load($pathName);
            $sheet = $spreadsheet->getSheet(0);
            $highestRow = $sheet->getHighestRow();
            $rewardType = 135;
            $rechargeMoney = intval($rechargeMoney);
            $rechargeMoney = intval($rechargeMoney * 100);
            $unUpload = "";


            //方案列表
            $planList = PromotionPlan::select('planId','planTitle')->get()->toArray();
            $planArr = [];
            foreach ($planList as $planItem){
                $planArr[$planItem['planId']] = $planItem['planTitle'];
            }

            if (empty($rechargeMoney)) return json(['code' => -1, 'msg' => '未输入金额!!!']);
            if($highestRow >= 1){
                for($i = 1;$i <= $highestRow;$i ++) {
                    //批量循环录入开始...
                    $mobile = $spreadsheet->getActiveSheet()->getCell('A' . $i)->getValue();
                    $mobile = intval($mobile);

                    if (empty($mobile) || empty($rechargeMoney)){
                        $unUpload .= $i.",";
                        continue;
                    }
                    //先检测手机号 
                    $isMobile = preg_match_all('/(13\d|14[579]|15[^4\D]|17[^49\D]|18\d)\d{8}/', $mobile, $matches_mobile);
                    if($isMobile == 0){
                        $unUpload .= $i.",";
                        continue;
                    }
                    //检测该会员是否添加过记录
                    $mobileEncry = mobile_en($mobile);
//                    $where = [
//                        'mobile' => $mobileEncry
//                    ];
//                    $promotionRecord = Promotion::getPromotionRecord($where, ['recordId','userId']);
//                    if ($promotionRecord){
//                        $unUpload .= $i.",";
//                        continue;
//                    }

                    //检测会员是否存在
                    $where = [
                        'mobile' => $mobileEncry,
                        'status' => GameUser::USER_STATUS_ON,
                    ];
                    $user = Player::getPlayer($where, ['userId','score','bankScore','promoterId','rechargeAmount','rechargeTimes','rewardScore','clubRewardScore']);
                    if (empty($user)){
                        $unUpload .= $i.",";
                        continue;
                    }

                    //录入开始
                    $session = DB::connection('mongodb_main')->getMongoClient()->startSession();
                    $session->startTransaction();


                    //增加奖励订单记录
                    $timeStr = date("YmdHis",time());
                    $randStr = (string)rand(1000,9999);
                    $orderId = "TGJL".$timeStr."-".(string)$user->userId."-".$randStr;
                    $rewardOrderData = [
                        'orderId' => $orderId,
                        'userId' => $user->userId,
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
                        'reason' => $planArr[$planId].'(方案ID为'.$planId.')',
                        'operator' => session('userName')
                    ];
                    $insertResult = RewardOrder::raw()->insertOne($rewardOrderData, ['session' => $session]);
                    if (!$insertResult) {
                        $session->abortTransaction();
                        $unUpload .= $i.",";
                        continue;
                    }

                    //更新金额,推广方式加钱忽略vip升级
                    $updateData = [
                        'score' => $user->score + $rechargeMoney,
                        //'rechargeAmount' => $user->rechargeAmount + $rechargeMoney,
                        //'rechargeTimes' => $user->rechargeTimes + 1,
                    ];
                    $updateResult = GameUser::where($where)->update($updateData, ['session' => $session]);
                    if (!$updateResult) {
                        $session->abortTransaction();
                        $unUpload .= $i.",";
                        continue;
                    }

                    //增加金币变化
                    $insertData = [
                        'userId' => $user->userId,
                        'beforeScore' => $user->score,
                        'beforeBankScore' => $user->bankScore,
                        'addScore' => $rechargeMoney,
                        'addBankScore' => 0,
                        'afterScore' => $user->score + $rechargeMoney,
                        'afterBankScore' => $user->bankScore,
                        'changeType' => $rewardType,
                        'roomId' => 0,
                        'createTime' => new \MongoDB\BSON\UTCDateTime,
                        'remark' => "推广方案充值",
                    ];
                    $insertResult = UserScoreChange::raw()->insertOne($insertData, ['session' => $session]);
                    if (!$insertResult) {
                        $session->abortTransaction();
                        $unUpload .= $i.",";
                        continue;
                    }

                    //充值成功后,录入记录
                    $recordId = PromotionRecord::max('recordId');
                    $insertProRecord = [
                        'recordId' => intval($recordId+1),
                        'planId' => intval($planId),
                        'userId' => $user->userId,
                        'mobile' => $mobileEncry,
                        'rechargeMoney' => $rechargeMoney,
                        'createTime' => new \MongoDB\BSON\UTCDateTime,
                    ];
                    $insertResult = PromotionRecord::raw()->insertOne($insertProRecord, ['session' => $session]);
                    if (!$insertResult) {
                        $session->abortTransaction();
                        $unUpload .= $i.",";
                        continue;
                    }
                    $session->commitTransaction();
                    //循环录入结束
                }
                unlink($pathName);
                $this->adminLog(["content"=>session("userName")."批量录入【】金币:".$this->formatMoneyFromMongo($rechargeMoney).",方案:".$planArr[$planId].'(方案ID为'.$planId.')']);
                if(!empty($unUpload)){
                    return json(['code' => -2, 'msg' => '录入完毕,其中第'.$unUpload.'行数据未录入成功']);
                }else{
                    return json(['code' => 0, 'msg' => '录入完毕']);
                }
            }else{
                return json(['code' => -1, 'msg' => '文档无数据']);
            }
        }
    }

    public function excelFileUpload(Request $request)
    {
        $file = $request->file('file');
        if ($file && $file->isValid()) {
            $uploadFile = $file->move(UPFILE_PATH.$file->getUploadName());
            $pathName = $uploadFile->getPathname();
            $basename = $uploadFile->getBasename();
            if(!$uploadFile) return json(['code' => -1, 'msg' => '文件上传失败']);
            return json(['code' => 0, 'msg' => '文件上传成功','pathName' => $pathName,'basename' => $basename]);
        }else{
            return json(['code' => -1, 'msg' => '文件验证失败']);
        }
    }

    public static function getPromotionRecord($where, $column = ['*'])
    {
        return PromotionRecord::where($where)->first($column);//get
    }

    public function promotionPlanList(Request $request)
    {
        if ($request->isAjax()) {
            $proReData = Db::connection('mongodb_main')->collection('stat_plan_daily')->raw()->aggregate([
                [
                    '$project' =>
                        [
                            'planId' => 1,
                            'rewardMoney' => 1,//方案送分金额
                            'joinPeople' => 1,//参与人数
                            'goldValidBet' => 1,//金币场有效投注
                            'clubValidBet' => 1,//俱乐部有效投注
                            'goldWinScore' => 1,//金币场玩家输赢(不含税)
                            'clubWinScore' => 1,//俱乐部玩家输赢(不含税)
                            'rechargeAmount' => 1,//充值金额
                            'exchangeAmount' => 1,//提现金额
                            'rechargePeople' => 1,//充值人数
                            'lastActiveTime' => 1,//最后数据时间
                            'monthActivePeople' => 1//本月活跃人数
                        ]
                ],
                [
                    '$sort' => ['planId'=>-1]
                ]
            ])->toArray();
            $count = count($proReData);

            //所有方案
            $planList = PromotionPlan::all(['*']);
            if($planList){
                $planList = $planList->toArray();
            }else{
                return json(['code' => -1, 'msg' => '暂无方案数据']);
            }
            //方案表合并
            $proReData = merge_array($proReData, $planList, 'planId');
            foreach ($proReData as &$item) {
                $item['rewardMoney'] = !empty($item['rewardMoney']) ? $this->formatMoneyFromMongo($item['rewardMoney']) : 0 ;
                $item['joinPeople'] = !empty($item['joinPeople']) ? $item['joinPeople'] : 0 ;
                $item['goldValidBet'] = !empty($item['goldValidBet']) ? $this->formatMoneyFromMongo($item['goldValidBet']) : 0 ;
                $item['clubValidBet'] = !empty($item['clubValidBet']) ? $this->formatMoneyFromMongo($item['clubValidBet']) : 0 ;
                $item['goldWinScore'] = !empty($item['goldWinScore']) ? $this->formatMoneyFromMongo($item['goldWinScore']) : 0 ;
                $item['clubWinScore'] = !empty($item['clubWinScore']) ? $this->formatMoneyFromMongo($item['clubWinScore']) : 0 ;
                $item['rechargeAmount'] = !empty($item['rechargeAmount']) ? $this->formatMoneyFromMongo($item['rechargeAmount']) : 0 ;
                $item['exchangeAmount'] = !empty($item['exchangeAmount']) ? $this->formatMoneyFromMongo($item['exchangeAmount']) : 0 ;
                $item['rechargePeople'] = !empty($item['rechargePeople']) ? $item['rechargePeople'] : 0 ;
                $item['depositWithdraw'] = $this->formatMoneyFromMongoNo($item['rechargeAmount'] - $item['exchangeAmount']);
                $item['lastActiveTime'] = !empty($item['lastActiveTime']) ? $this->formatDate($item['lastActiveTime']) : 0 ;
                $item['monthActivePeople'] = !empty($item['monthActivePeople']) ? $item['monthActivePeople'] : 0 ;
                $item['createTime'] = !empty($item['createTime']) ? $this->formatDate($item['createTime']) : 0 ;
            }

            return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $proReData]);
        }
        return view('promotion/planList/list');
    }

    private function _returnVisitAjaxParam(){
        $request = request();
        $getData = check_type($request->all());
        extract($getData);
        if (empty($startDate)) {
            $startDate = date("Y-m-01");
        }
        $startTime = strtotime($startDate);
        if (empty($endDate)) {
            $endDate = date("Y-m-t");
        }
        if($startDate == $endDate){
            $endTime = strtotime("$endDate +1 day");
        }else{
            $endTime = strtotime($endDate);
        }

        if ($startTime >= $endTime) return json(['code' => -1, 'msg' => '请核对开始时间结束时间']);
        $where['joinPlanTime'] = ['$gte' => $this->formatTimestampToMongo($startTime), '$lt' => $this->formatTimestampToMongo($endTime)];

        if (!empty($userId)) {
            $where['userId'] = (int)$userId;
        }

        if (!empty($planId)) {
            $where['planId'] = (int)$planId;
        }
        return $where;
    }

    public function returnVisit(Request $request)
    {
        //所有方案
        $planList = PromotionPlan::all(['*']);
        if($planList){
            $planList = $planList->toArray();
        }else{
            return json(['code' => -1, 'msg' => '暂无方案数据']);
        }
        if ($request->isAjax()) {
            $where = $this->_returnVisitAjaxParam();
            if (!is_array($where)) return $where;


            $field = $request->get('field');
            $order = $request->get('order');
            if (empty($field)) $field = 'joinPlanTime';
            if (empty($order)) {
                $order = -1;
            }
            if($order == "asc"){
                $sort = 1;
            }else{
                $sort = -1;
            }

            
            $userPlanData = Db::connection('mongodb_main')->collection('stat_user_plan_daily')->raw()->aggregate([
                [
                    '$match' => $where
                ],
                [
                    '$project' =>
                        [
                            'userId' => 1,
                            'planId' => 1,
                            'rewardMoney' => 1,//送分金额
                            //'totalScore' => 1,//账户余额
                            'score' => 1,//玩家余额
                            'betDays' => 1,//投注天数
                            'totalDays' => 1,//累计天数
                            'goldValidBet' => 1,//金币投注量
                            'clubValidBet' => 1,//俱乐部投注量
                            'goldWinScore' => 1,//金币场玩家输赢(税后)
                            'clubWinScore' => 1,//俱乐部玩家输赢(税后)
                            'rechargeNum' => 1,//充值次数
                            'rechargeAmount' => 1,//充值金额
                            'exchangeAmount' => 1,//提现金额
                            'lastGoldBetTime' => 1,//最后金币场下注时间
                            'lastClubBetTime' => 1,//最后俱乐部下注时间
                            'joinPlanTime' => 1,//添加记录时间
                            'rechargeExchangeDiff' => 1
                        ]
                ],
                [
                    '$sort' => [$field=>$sort]
                ]
            ])->toArray();
            $count = count($userPlanData);
            //方案表合并
            $userPlanData = merge_array($userPlanData, $planList, 'planId');
            foreach ($userPlanData as &$item) {
                $item['rewardMoney'] = !empty($item['rewardMoney']) ? $this->formatMoneyFromMongo($item['rewardMoney']) : 0 ;
                $item['score'] = !empty($item['score']) ? $this->formatMoneyFromMongo($item['score']) : 0 ;

                $item['betDays'] = !empty($item['betDays']) ? $item['betDays'] : 0 ;
                $item['totalDays'] = !empty($item['totalDays']) ? $item['totalDays'] : 0 ;

                $item['goldValidBet'] = !empty($item['goldValidBet']) ? $this->formatMoneyFromMongo($item['goldValidBet']) : 0 ;
                $item['clubValidBet'] = !empty($item['clubValidBet']) ? $this->formatMoneyFromMongo($item['clubValidBet']) : 0 ;

                $item['goldWinScore'] = !empty($item['goldWinScore']) ? $this->formatMoneyFromMongo($item['goldWinScore']) : 0 ;
                $item['clubWinScore'] = !empty($item['clubWinScore']) ? $this->formatMoneyFromMongo($item['clubWinScore']) : 0 ;
                $item['rechargeNum'] = !empty($item['rechargeNum']) ? $item['rechargeNum'] : 0 ;

                $item['rechargeAmount'] = !empty($item['rechargeAmount']) ? $this->formatMoneyFromMongo($item['rechargeAmount']) : 0 ;
                $item['exchangeAmount'] = !empty($item['exchangeAmount']) ? $this->formatMoneyFromMongo($item['exchangeAmount']) : 0 ;

                $item['rechargeExchangeDiff'] = !empty($item['rechargeExchangeDiff']) ? $this->formatMoneyFromMongo($item['rechargeExchangeDiff']) : 0 ;
                $item['lastGoldBetTime'] = !empty($item['lastGoldBetTime']) ? $this->formatDate($item['lastGoldBetTime']) : 0 ;
                $item['lastClubBetTime'] = !empty($item['lastClubBetTime']) ? $this->formatDate($item['lastClubBetTime']) : 0 ;
                $item['joinPlanTime'] = !empty($item['joinPlanTime']) ? $this->formatDate($item['joinPlanTime']) : 0 ;
            }

            return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $userPlanData]);

        }
        $getData = check_type($request->all());
        extract($getData);
        if (empty($startDate)) {
            $startDate = date("Y-m-01");
        }
        if (empty($endDate)) {
            $endDate = date("Y-m-t");
        }

        $assignData = [
            'planList' => $planList,
            'startDate'=>$startDate,
            'endDate'=>$endDate
        ];
        return view('promotion/returnVisit/list',$assignData);
    }

    public function editPlan(Request $request)
    {
        if ($request->isAjax()) {
            $postData = check_type($request->post());
            extract($postData);
            if (!$planId) {
                return json(['code' => -1, 'msg' => '信息输入不正确']);
            }
//            if (mb_strlen($planTitle) > PromotionPlan::TITLE_LENGTH) {
//                return json(['code' => -1, 'msg' => '标题超出'. PromotionPlan::TITLE_LENGTH .'个字符']);
//            }
            $updateData = [
                'planTitle' => $planTitle,
                'planExplain' => $planExplain,
            ];
            $updateResult = PromotionPlan::where('planId', $planId)->update($updateData);
            if (!$updateResult) return json(['code' => -1, 'msg' => '修改失败']);
            return json(['code' => 0, 'msg' => '修改成功']);
        }
        $planId = $request->get('planId', 0);
        if(empty($planId)) return json(['code' => -1, 'msg' => '无此方案']);
        $formData = PromotionPlan::where('planId', intval($planId))->first();
//        $resultArr = [];
//        foreach ($formData as $item){
//            $resultArr['planId'] = $formData['planId'];
//            $resultArr['planTitle'] = $formData['planTitle'];
//            $resultArr['planExplain'] = $formData['planExplain'];
//        }
        $assignData = [
            'planId' => $formData['planId'],
            'planTitle'=>$formData['planTitle'],
            'planExplain'=>$formData['planExplain']
        ];
        return view('promotion/promotionPlanAdd/edit',$assignData);
    }

    public function userPlanStatSummary(Request $request)
    {
        if($request->isAjax()) {
            $where = $this->_returnVisitAjaxParam();
            if (!is_array($where)) return $where;
            $where2 = $where;
            $where2['rechargeNum'] = ['$gt' => 0];
            $rechargePeople = Db::connection('mongodb_main')->collection('stat_user_plan_daily')->raw()->aggregate([
                [
                    '$match' => $where2
                ],
                [
                    '$project' =>
                        [
                            'rechargeNum'=>1,
                        ]
                ],
                [
                    '$group' =>
                        [
                            '_id' => null,
                            'rechargeNums' => ['$sum' => 1],
                        ]
                ],
                [
                    '$project' =>
                        [
                            'rechargeNums' => 1,
                        ]
                ]
            ])->toArray();

            if(!empty($rechargePeople)){
                $rechargePeopleNums = $rechargePeople[0]['rechargeNums'];
            }else{
                $rechargePeopleNums = 0;
            }
            $listData = Db::connection('mongodb_main')->collection('stat_user_plan_daily')->raw()->aggregate([
                [
                    '$match' => $where
                ],
                [
                    '$project' =>
                        [
                            'rewardMoney'=>1,
                            'goldValidBet'=>1,
                            'clubValidBet'=>1,
                            'goldWinScore'=>1,
                            'clubWinScore'=>1,
                            'rechargeAmount'=>1,
                            'exchangeAmount'=>1,
                            'score'=>1,
                            'rechargeExchangeDiff'=>1
                        ]
                ],
                [
                    '$group' =>
                        [
                            '_id' => null,
                            'rewardMoney' => ['$sum' => '$rewardMoney'],
                            'goldValidBet' => ['$sum' => '$goldValidBet'],
                            'clubValidBet' => ['$sum' => '$clubValidBet'],
                            'goldWinScore' => ['$sum' => '$goldWinScore'],
                            'clubWinScore' => ['$sum' => '$clubWinScore'],
                            'rechargeAmount' => ['$sum' => '$rechargeAmount'],
                            'exchangeAmount' => ['$sum' => '$exchangeAmount'],
                            'score' => ['$sum' => '$score'],
                            'rechargeExchangeDiff' => ['$sum' => '$rechargeExchangeDiff'],
                            'giveScoreNums' => ['$sum' => 1],

                        ]
                ],
                [
                    '$project' =>
                        [
                            'rewardMoney'=>1,
                            'goldValidBet'=>1,
                            'clubValidBet' => 1,
                            'goldWinScore' => 1,
                            'clubWinScore' => 1,
                            'rechargeAmount' => 1,
                            'exchangeAmount' => 1,
                            'rechargeExchangeDiff' =>1,
                            'score' => 1,
                            'giveScoreNums' => 1,
                        ]
                ]
            ])->toArray();

            if(!empty($listData)){
                $rewardMoneySum = $this->formatMoneyFromMongo($listData[0]['rewardMoney']);
                $goldValidBetSum = $this->formatMoneyFromMongo($listData[0]['goldValidBet']);
                $clubValidBetSum = $this->formatMoneyFromMongo($listData[0]['clubValidBet']);
                $goldWinScoreSum = $this->formatMoneyFromMongo($listData[0]['goldWinScore']);
                $clubWinScoreSum = $this->formatMoneyFromMongo($listData[0]['clubWinScore']);
                $rechargeAmountSum = $this->formatMoneyFromMongo($listData[0]['rechargeAmount']);
                $exchangeAmountSum = $this->formatMoneyFromMongo($listData[0]['exchangeAmount']);
                $depositWithdrawSum = $this->formatMoneyFromMongo($listData[0]['rechargeExchangeDiff']);
                $totalScoreSum = $this->formatMoneyFromMongo($listData[0]['score']);
                $giveScoreNums = $listData[0]['giveScoreNums'];
            }else{
                $rewardMoneySum = 0;
                $goldValidBetSum = 0;
                $clubValidBetSum = 0;
                $goldWinScoreSum = 0;
                $clubWinScoreSum = 0;
                $rechargeAmountSum = 0;
                $exchangeAmountSum = 0;
                $totalScoreSum = 0;
                $giveScoreNums = 0;
                $depositWithdrawSum = 0;
            }
            $assignData = [
                'rewardMoneySum' => $rewardMoneySum,
                'goldValidBetSum'=>$goldValidBetSum,
                'clubValidBetSum'=>$clubValidBetSum,
                'goldWinScoreSum'=>$goldWinScoreSum,
                'clubWinScoreSum'=>$clubWinScoreSum,
                'rechargeAmountSum'=>$rechargeAmountSum,
                'exchangeAmountSum'=>$exchangeAmountSum,
                'totalScoreSum'=>$totalScoreSum,
                'giveScoreNums'=>$giveScoreNums,
                'depositWithdrawSum'=>$depositWithdrawSum,
                'rechargePeopleNums'=>$rechargePeopleNums,
            ];
           return json(['code' => 0, 'msg' => 'ok', 'data' =>$assignData]);
        }
    }

    public function exportUserPlanStat(Request $request)
    {

        //所有方案
        $planList = PromotionPlan::all(['*']);
        if($planList){
            $planList = $planList->toArray();
        }else{
            return json(['code' => -1, 'msg' => '暂无方案数据']);
        }
        $where = $this->_returnVisitAjaxParam();
        if (!is_array($where)) return $where;
        $userPlanData = Db::connection('mongodb_main')->collection('stat_user_plan_daily')->raw()->aggregate([
            [
                '$match' => $where
            ],
            [
                '$project' =>
                    [
                        'userId' => 1,
                        'planId' => 1,
                        'rewardMoney' => 1,//送分金额
                        'score' => 1,//账户余额
                        'betDays' => 1,//投注天数
                        'totalDays' => 1,//累计天数
                        'goldValidBet' => 1,//金币投注量
                        'clubValidBet' => 1,//俱乐部投注量
                        'goldWinScore' => 1,//金币场玩家输赢(含税)
                        'clubWinScore' => 1,//俱乐部玩家输赢(含税)
                        'rechargeNum' => 1,//充值次数
                        'rechargeAmount' => 1,//充值金额
                        'exchangeAmount' => 1,//提现金额
                        'lastGoldBetTime' => 1,//最后金币场下注时间
                        'lastClubBetTime' => 1,//最后俱乐部下注时间
                        'joinPlanTime' => 1,//添加记录时间
                        'rechargeExchangeDiff' =>1
                    ]
            ],
        ])->toArray();

        //方案表合并
        $userPlanData = merge_array($userPlanData, $planList, 'planId');


        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'ID');
        $sheet->setCellValue('B1', '方案名称');
        $sheet->setCellValue('C1', '添加时间');
        $sheet->setCellValue('D1', '送分金额');
        $sheet->setCellValue('E1', '投注天数');
        $sheet->setCellValue('F1', '累计天数');
        $sheet->setCellValue('G1', '(金)投注量');
        $sheet->setCellValue('H1', '(金)玩家输赢');
        $sheet->setCellValue('I1', '(俱)投注量');
        $sheet->setCellValue('J1', '(俱)玩家输赢');
        $sheet->setCellValue('K1', '充值次数');
        $sheet->setCellValue('L1', '账户余额');
        $sheet->setCellValue('M1', '充值');
        $sheet->setCellValue('N1', '提现');
        $sheet->setCellValue('O1', '充提亏盈');
        $sheet->setCellValue('P1', '(金)最近下注时间');
        $sheet->setCellValue('Q1', '(俱)最近下注时间');

        $num = 2;
        foreach ($userPlanData as &$item) {
            $item['rewardMoney'] = !empty($item['rewardMoney']) ? $this->formatMoneyFromMongo($item['rewardMoney']) : 0 ;
            $item['score'] = !empty($item['score']) ? $this->formatMoneyFromMongo($item['score']) : 0 ;
            $item['betDays'] = !empty($item['betDays']) ? $item['betDays'] : 0 ;
            $item['totalDays'] = !empty($item['totalDays']) ? $item['totalDays'] : 0 ;
            $item['goldValidBet'] = !empty($item['goldValidBet']) ? $this->formatMoneyFromMongo($item['goldValidBet']) : 0 ;
            $item['clubValidBet'] = !empty($item['clubValidBet']) ? $this->formatMoneyFromMongo($item['clubValidBet']) : 0 ;
            $item['goldWinScore'] = !empty($item['goldWinScore']) ? $this->formatMoneyFromMongo($item['goldWinScore']) : 0 ;
            $item['clubWinScore'] = !empty($item['clubWinScore']) ? $this->formatMoneyFromMongo($item['clubWinScore']) : 0 ;
            $item['rechargeNum'] = !empty($item['rechargeNum']) ? $item['rechargeNum'] : 0 ;
            $item['rechargeAmount'] = !empty($item['rechargeAmount']) ? $this->formatMoneyFromMongo($item['rechargeAmount']) : 0 ;
            $item['exchangeAmount'] = !empty($item['exchangeAmount']) ? $this->formatMoneyFromMongo($item['exchangeAmount']) : 0 ;
            $item['rechargeExchangeDiff'] = !empty($item['rechargeExchangeDiff']) ? $this->formatMoneyFromMongo($item['rechargeExchangeDiff']) : 0 ;
            $item['lastGoldBetTime'] = !empty($item['lastGoldBetTime']) ? $this->formatDate($item['lastGoldBetTime']) : 0 ;
            $item['lastClubBetTime'] = !empty($item['lastClubBetTime']) ? $this->formatDate($item['lastClubBetTime']) : 0 ;
            $item['joinPlanTime'] = !empty($item['joinPlanTime']) ? $this->formatDate($item['joinPlanTime']) : 0 ;

            $sheet->setCellValue("A{$num}", $item['userId']);
            $sheet->setCellValue("B{$num}", $item['planTitle']);
            $sheet->setCellValue("C{$num}", $item['joinPlanTime']);
            $sheet->setCellValue("D{$num}", $item['rewardMoney']);
            $sheet->setCellValue("E{$num}", $item['betDays']);
            $sheet->setCellValue("F{$num}", $item['totalDays']);
            $sheet->setCellValue("G{$num}", $item['goldValidBet']);
            $sheet->setCellValue("H{$num}", $item['goldWinScore']);
            $sheet->setCellValue("I{$num}", $item['clubValidBet']);
            $sheet->setCellValue("J{$num}", $item['clubWinScore']);
            $sheet->setCellValue("K{$num}", $item['rechargeNum']);
            $sheet->setCellValue("L{$num}", $item['score']);
            $sheet->setCellValue("M{$num}", $item['rechargeAmount']);
            $sheet->setCellValue("N{$num}", $item['exchangeAmount']);
            $sheet->setCellValue("O{$num}", $item['rechargeExchangeDiff']);
            $sheet->setCellValue("P{$num}", $item['lastGoldBetTime']);
            $sheet->setCellValue("Q{$num}", $item['lastClubBetTime']);
            $num++;
        }


        $writer = new Xlsx($spreadsheet);
        $file_path = public_path().'/exportUserPlanStat.xlsx';
        // 保存文件到 public 下
        $writer->save($file_path);
        return json(['code' => 0, 'msg' => 'ok', 'file' => $file_path]);
    }
}
