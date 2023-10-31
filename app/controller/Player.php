<?php
namespace app\controller;

use app\model\FriendsDetailPlayRecord;
use app\model\FriendsGameKind;
use app\model\RoomCardChange;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use app\model\ExchangeOrder;
use app\model\GameKind;
use app\model\GameUser;
use app\model\ControlUser;
use app\model\AndroidUser;
use app\model\PlayRecord;
use app\model\ClubPlayRecord;
use app\model\Promoter;
use app\model\RechargeOrder;
use app\model\RewardOrder;
use app\model\ClubRewardOrder;
use app\model\SmsSend;
use app\model\UserOnline;
use app\model\UserScoreChange;
use app\model\ClubUserScoreChange;
use IpLocation;
use support\bootstrap\Container;
use support\Db;
use support\Request;

class Player extends Base
{
    public function index(Request $request)
    {
        $act = $request->get('act')??$request->post('act');
        $controller = Container::get($request->controller);
        //dd($controller);
        if (method_exists($controller, $act)) {
            $before_response = call_user_func([$controller, $act], $request);
            return $before_response;
            if ($before_response instanceof Response) {
                dd('yes');
                return $before_response;
            }
        }
        //return response('hello webman' .$act);
        //return view('index/index', ['name' => 'webman1']);
    }

    public function addPlayer(Request $request)
    {
        if ($request->isAjax()) {
            $postData = check_type($request->post());
            extract($postData);

            if (!$mobile||!$promoterId || !$password) {
                return json(['code' => -1, 'msg' => '手机号码,代理ID,密码为必填项']);
            }

            $isMobile = preg_match_all('/(13\d|14[579]|15[^4\D]|17[^49\D]|18\d)\d{8}/', $mobile, $matches_mobile);
            if($isMobile == 0){
                return json(['code' => -1, 'msg' => '手机格式错误']);
            }

            if($alipayAccount){
                //邮箱检测
                $isEmail = preg_match_all('/\w[-\w.+]*@([A-Za-z0-9][-A-Za-z0-9]+\.)+[A-Za-z]{2,14}/', $alipayAccount, $matches_email);
                if($isEmail == 0){
                    $isMobile = preg_match_all('/(13\d|14[579]|15[^4\D]|17[^49\D]|18\d)\d{8}/', $alipayAccount, $matches_mobile);
                    if($isMobile == 0){
                        return json(['code' => -1, 'msg' => '支付宝账号格式错误']);
                    }
                }
            }

            if($promoterId > 0){
                $where = ['promoterId' => $promoterId];
                $promoter = Agent::getPromoter($where);
                if (empty($promoter)) {
                    return json(['code' => -1, 'msg' => '代理用户不存在']);
                }
            }

            if (strlen($mobile) != 11)  return json(['code' => -1, 'msg' => '手机号码格式错误']);
            $mobile = mobile_en($mobile);
            $user = static::getPlayer(['mobile' => $mobile]);
            if (!empty($user)) return json(['code' => -1, 'msg' => '该手机号已被使用']);

            //userId
            do {
                if($accountType == 0){
                    $userId = rand(10000000,99999999);
                }else{
                    $userId = rand(100000,999999);
                }
                $user = static::getPlayer(['userId' => $userId]);
                $androidUser = static::getAndroidPlayer(['userId' => $userId]);
            } while ($user || $androidUser);
            //salt && pwd
            $salt = (string)rand(100000,999999);

            //$password = strToHex($password);

            $password = md5($password);
            $password =strtoupper($password);
            $password = $password.$salt;
            $password = md5($password);
            //$password = dechex($password);
            $password =strtoupper($password);
            //echo $password;exit;

            $nickName = (string)$userId;
            $aliPay['alipayAccount'] = $alipayAccount ?? "";
            $aliPay['alipayName'] = $alipayName ?? "";
            $bank['bankName'] = $bankName ?? "";
            $bank['bankCardNum'] = $bankCardNum ?? "";
            $bank['bankCardName'] = $bankCardName ?? "";
            $regInfo['time'] = new \MongoDB\BSON\UTCDateTime;
            $regInfo['ip'] = GetIP();
            $regInfo['mobileSerial'] = "";
            $regInfo['mobileType'] = "pc";
            $regInfo['osType'] = 3;
            $lastLogin['time'] = new \MongoDB\BSON\UTCDateTime(0);
            $lastLogin['ip'] = "";
            $lastLogin['mobileSerial'] = "";
            $lastLogin['mobileType'] = "";
            $lastLogin['osType'] = 0;
            $usdtarr['address'] = $address;
            $usdtarr['type'] = 0;
            $insertData = [
                'userId' => $userId,
                'nickName' => $nickName,
                'trueName' => $trueName,
                'password' => $password,
                'salt' => $salt,
                'mobile' => $mobile,
                'headId' => 0,
                'headboxId' => 0,
                'gender' => 0,
                'vip' => 0,
                'platformId' => 1,
                'channelId' => 1,
                'promoterId' => (int)$promoterId,
                'guideTag' => 0,
                'alipay' => $aliPay,
                'bank' => $bank,
                'usdt' => $usdtarr,
                'regInfo' => $regInfo,
                'lastLogin' => $lastLogin,
                'superAccount' => 0,
                'loginCount' => 0,
                'activeDays' => 0,
                "keepLoginDays" => 0,
                "gameCount" => 0,
                "totalOnlineLoginTime" => 0,
                "totalOnlineGameTime" => 0,
                "rechargeAmount" => 0,
                "rechargeTimes" => 0,
                "exchangeAmount" => 0,
                "exchangeTimes" => 0,
                "allBet" => 0,
                "validBet" => 0,
                "revenue" => 0,
                "winScore" => 0,
                "score" => 0,
                "roomCard" => 0,
                "bankScore" => 0,
                'status' => $status
            ];
            $insertResult = GameUser::insert($insertData);
            if ($insertResult) {
                $this->adminLog(["content" => session('userName') . "添加用户【".$userId."】"]);
                return json(['code' => 0, 'msg' => '添加成功']);
            }
            return json(['code' => -1, 'msg' => '添加失败']);
        }
        return view('player/player/add', []);
    }

    public function addControlPoint(Request $request)
    {
        if ($request->isAjax()) {
            $postData = check_type($request->post());
            extract($postData);

            if (!$userId || !$sort) {
                return json(['code' => -1, 'msg' => 'userId和排序为必填选项']);
            }
            //是否数量超过限制
            $totalCount = ControlUser::count();
            dd($totalCount);
            if($totalCount == 5) return json(['code' => -1, 'msg' => '点控人数最多5个']);
            //sort是否重复
            $sameSortUser = static::getControPlayer(['sort' => (int)$sort]);
            if ($sameSortUser) return json(['code' => -1, 'msg' => '排序值重复,请更换其他排序值']);


            $user = static::getPlayer(['userId' => $userId]);
            if (empty($user)) return json(['code' => -1, 'msg' => '无此用户']);
            $insertData = [
                'sort' => (int)$sort,
                'userId' => (int)$userId,
                'cscore' => $this->formatMoneytoMongo($cscore),
                'createTime' => new \MongoDB\BSON\UTCDateTime,
            ];
            $insertResult = ControlUser::insert($insertData);
            if ($insertResult) {
                $this->adminLog(["content" => session('userName') . "添加点控用户【".$userId."】"]);
                return json(['code' => 0, 'msg' => '添加成功']);
            }
            return json(['code' => -1, 'msg' => '添加失败']);
        }
        return view('player/point/add', []);
    }

    public function removeControlPoint(Request $request)
    {
        if ($request->post('_ids')) {
            $_ids = $request->post('_ids');
            $idsArr = explode(",", $_ids);
            $removeResult = ControlUser::destroy($idsArr);
            if (!$removeResult) return json(['code' => -1, 'msg' => '删除失败']);
            $this->adminLog(["content"=>"删除点控【".$_ids."】"]);
            return json(['code' => 0, 'msg' => '删除成功']);
        }

        $postData = check_type($request->post());
        extract($postData);
        $_id = (int)$_id;
        $removeResult = ControlUser::where('userId', $_id)->delete();
        if (!$removeResult) return json(['code' => -1, 'msg' => '删除失败']);
        $this->adminLog(["content"=>"删除点控【".$_id."】"]);
        return json(['code' => 0, 'msg' => '删除成功']);
    }

    private function _playerListAjaxParam()
    {
        $where = [];
        $request = request();
        $getData = check_type($request->all());
        extract($getData);
        if (!empty($dateValue)) {
            //$startDate = date("Y-m-d");
            $dateValue = explode('~', $dateValue);
            $startDate = trim($dateValue[0]);
            $endDate = trim($dateValue[1]);
            $startTime = strtotime($startDate);
            $endTime = strtotime("$endDate +1 day");
            $where[] = ['regInfo.time', '>=', $this->formatTimestampToMongo($startTime)];
            $where[] = ['regInfo.time', '<', $this->formatTimestampToMongo($endTime)];
        }
        //if ($startTime >= $endTime) return json(['code' => -1, 'msg' => '请核对开始时间结束时间']);

        //输入框优先于下拉框
        if (!empty($searchValue)) {
            switch ($searchType) {
                case 1:
                    $where['userId'] = (int)$searchValue;
                    break;
                case 2:
                    $where['trueName'] = $searchValue;
                    break;
                case 3:
                    $searchValue = mobile_en($searchValue);
                    $where['mobile'] = $searchValue;
                    break;
                case 4:
                    $where['regInfo.mobileType'] = $searchValue;
                    break;
                case 5:
                    $where[] = ['regInfo.ip', 'regex', new \MongoDB\BSON\Regex($searchValue, 'i')];
                    break;
                case 6:
                    $where[] = ['lastLogin.ip', 'regex', new \MongoDB\BSON\Regex($searchValue, 'i')];
                    break;
                case 7:
                    $where[] = ['nickName', 'regex', new \MongoDB\BSON\Regex($searchValue, 'i')];
                    break;
            }
        }
        if (!empty($isSys)) {
            if (empty($searchValue) || $searchType != 1) {
                if ($isSys == GameUser::COMMON_ACCOUNT) {
                    $where[] = ['userId', '>=', GameUser::COMMON_ACCOUNT_START_ID];
                } elseif ($isSys == GameUser::SYSTEM_ACCOUNT) {
                    $where[] = ['userId', '<', GameUser::COMMON_ACCOUNT_START_ID];
                }
            }
        } else {
            $where[] = ['userId', '>=', GameUser::COMMON_ACCOUNT_START_ID];
        }

        if (!empty($isPay)) {
            if($isPay == 1) {
                $where[] = ['rechargeAmount', '>', 0];
            } elseif ($isPay == 2) {
                $where['rechargeAmount'] = 0;
            }
        }

        if(isset($searchType) && $searchType != 3){
            if (!empty($isBindMoble)) {
                if($isBindMoble == 1) {
                    $where['mobile'] = ['$ne'=>""];
                } elseif ($isBindMoble == 2) {
                    $where['mobile'] = ['$eq'=>""];
                }
            }
        }



        if (!empty($isGame)) {
            if($isGame == 1) {
                $where[] = ['validBet', '>', 0];
            } elseif ($isGame == 2) {
                $where['validBet'] = 0;
            }
        }

        if (!empty($isActive)) {
            if($isActive == 1) {
                $where[] = ['totalOnlineGameTime', '>', 0];
            } elseif ($isActive == 2) {
                $where['totalOnlineGameTime'] = 0;
            }
        }
        if (!empty($isNormal)) {
            if($isNormal == 1) {
                $where['status'] = 1;
            } elseif ($isNormal == 2) {
                $where['status'] = 0;
            }
        }
        if (!empty($promoterId)) $where['promoterId'] = $promoterId;
        return $where;
    }

    public function playerFieldsSummary(Request $request)
    {
        if($request->isAjax()) {
            $where = $this->_playerListAjaxParam();
            if (!is_array($where)) return $where;

            $scoreSum = GameUser::where($where)->sum('score');
            $scoreSum = $this->formatMoneyFromMongo($scoreSum);

            $roomCardSum = GameUser::where($where)->sum('roomCard');
            $roomCardSum = $this->formatMoneyFromMongo($roomCardSum);

            $bankScoreSum = GameUser::where($where)->sum('bankScore');
            $bankScoreSum = $this->formatMoneyFromMongo($bankScoreSum);



            $data = [
                'scoreSum' => $scoreSum,
                'roomCardSum' => $roomCardSum,
                'bankScoreSum' => $bankScoreSum
            ];

            return json(['code' => 0, 'msg' => 'ok', 'data' => $data]);
        }
    }

    public function playerList(Request $request)
    {
        if ($request->isAjax()) {
            $where = $this->_playerListAjaxParam();
            $field = $request->get('field');
            $order = $request->get('order');
            if (empty($field)) $field = 'regInfo.time';
            if (empty($order)) $order = 'desc';
            if ($field == 'regInfoTime') $field = 'regInfo.time';
            if ($field == 'roomCard') $field = 'roomCard';
            if ($field == 'score') $field = 'score';
            if ($field == 'winScore') $field = 'winScore';
            if ($field == 'lastLoginTime') $field = 'lastLogin.time';

            $count = GameUser::where($where)->count();
            $list = GameUser::where($where)->orderBy($field, $order)->skip($request->skip)->take($request->limit)->get()->toArray();
            $promoterIdArr = array_column($list, 'promoterId'); $promoterIdArr = array_unique($promoterIdArr);
            $promoterList = Promoter::whereIn('promoterId', $promoterIdArr)->select('promoterId','promoterName')->get()->toArray();
            $list = merge_array($list, $promoterList, 'promoterId');
            $ipLocation = new IpLocation();
            foreach ($list as &$item) {
                if(isset($item['clubWinScore'])){
                    $score = $item['clubWinScore'] + $item['winScore'];
                    $item['clubWinScore'] = $this->formatMoneyFromMongo($item['clubWinScore']);
                    $item['totalScore'] = $this->formatMoneyFromMongo($score);
                }else{
                    $item['clubWinScore'] = 0;
                    $item['totalScore'] = $this->formatMoneyFromMongo($item['winScore']);
                }

                $item['regPromoterName'] = $item['promoterId'] . '|' . ($item['promoterName']??'');
                if (!empty($item['mobile'])) $item['mobile'] = mobileShow(mobile($item['mobile']));
                if(!empty($item['roomCard'])){
                    $item['roomCard'] = $this->formatMoneyFromMongo($item['roomCard']);
                }else{
                    $item['roomCard'] = 0;
                }

                $item['score'] = $this->formatMoneyFromMongo($item['score']);
                $item['bankScore'] = $this->formatMoneyFromMongo($item['bankScore']);
                $item['rechargeAmount'] = $this->formatMoneyFromMongo($item['rechargeAmount']);
                $item['exchangeAmount'] = $this->formatMoneyFromMongo($item['exchangeAmount']);
                $item['revenue'] = $this->formatMoneyFromMongo($item['revenue']);
                $item['winScore'] = $this->formatMoneyFromMongo($item['winScore']);
                $item['totalOnlineGameTime'] = (int)$item['totalOnlineGameTime'];
                $item['totalOnlineGameTime'] = Sec2Time($item['totalOnlineGameTime']);


                $item['regInfoTime'] = $this->formatDate($item['regInfo']['time']);
                if ($item['lastLogin']['time'] != '0') {
                    $item['lastLoginTime'] = $this->formatDate($item['lastLogin']['time']);
                } else {
                    $item['lastLoginTime'] = '/';
                }
                $item['regInfoMobileType'] = $item['regInfo']['mobileType'];

                $location = $ipLocation->getlocation($item['regInfo']['ip']);
                $item['regInfo']['address'] = $location['country'] . $location['area'];
                $location = $ipLocation->getlocation($item['lastLogin']['ip']);
                $item['lastLogin']['address'] = $location['country'] . $location['area'];

            }

            return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $list]);
        }
        $assignData = [
            'isPay' => ISPAY,
            'isActive' => ISACTIVE,
            'isNormal' => ISNORMAL,
            'isSys' => GameUser::ACCOUNT_CLASSIFY,
        ];
        return view('player/player/list', $assignData);
    }

    public function playerPoint(Request $request)
    {
        if ($request->isAjax()) {
            //$where = $this->_playerListAjaxParam();
            $field = $request->get('field');
            $order = $request->get('order');
            if (empty($field)) $field = 'sort';
            if (empty($order)) $order = 'asc';
//            if ($field == 'regInfoTime') $field = 'regInfo.time';
//            if ($field == 'roomCard') $field = 'roomCard';
//            if ($field == 'score') $field = 'score';
//            if ($field == 'winScore') $field = 'winScore';
//            if ($field == 'lastLoginTime') $field = 'lastLogin.time';

            //control_user
            $userIdArr = [];
            $controList = ControlUser::orderBy($field, $order)->select('userId','sort','cscore')->get()->toArray();
            if($controList){
                foreach ($controList as $controlItem){
                    $userIdArr[] = $controlItem['userId'];
                }
            }

            $count = GameUser::where(['userId' => ['$in'=>$userIdArr]])->count();
            $list = GameUser::where(['userId' => ['$in'=>$userIdArr]])->orderBy($field, $order)->get()->toArray();//where($where)->->skip($request->skip)->take($request->limit)
            $promoterIdArr = array_column($list, 'promoterId'); $promoterIdArr = array_unique($promoterIdArr);
            $promoterList = Promoter::whereIn('promoterId', $promoterIdArr)->select('promoterId','promoterName')->get()->toArray();
            $list = merge_array($list, $promoterList, 'promoterId');
            $list = merge_array($list, $controList, 'userId');
            $ipLocation = new IpLocation();

            foreach ($list as &$item) {
                if(isset($item['clubWinScore'])){
                    $score = $item['clubWinScore'] + $item['winScore'];
                    $item['clubWinScore'] = $this->formatMoneyFromMongo($item['clubWinScore']);
                    $item['totalScore'] = $this->formatMoneyFromMongo($score);
                }else{
                    $item['clubWinScore'] = 0;
                    $item['totalScore'] = $this->formatMoneyFromMongo($item['winScore']);
                }

                $item['regPromoterName'] = $item['promoterId'] . '|' . ($item['promoterName']??'');
                if (!empty($item['mobile'])) $item['mobile'] = mobileShow(mobile($item['mobile']));
                if(!empty($item['roomCard'])){
                    $item['roomCard'] = $this->formatMoneyFromMongo($item['roomCard']);
                }else{
                    $item['roomCard'] = 0;
                }

                $item['score'] = $this->formatMoneyFromMongo($item['score']);
                $item['cscore'] = $this->formatMoneyFromMongo($item['cscore']??0);
                $item['bankScore'] = $this->formatMoneyFromMongo($item['bankScore']);
                $item['rechargeAmount'] = $this->formatMoneyFromMongo($item['rechargeAmount']);
                $item['exchangeAmount'] = $this->formatMoneyFromMongo($item['exchangeAmount']);
                $item['revenue'] = $this->formatMoneyFromMongo($item['revenue']);
                $item['winScore'] = $this->formatMoneyFromMongo($item['winScore']);
                $item['totalOnlineGameTime'] = (int)$item['totalOnlineGameTime'];
                $item['totalOnlineGameTime'] = Sec2Time($item['totalOnlineGameTime']);


                $item['regInfoTime'] = $this->formatDate($item['regInfo']['time']);
                if ($item['lastLogin']['time'] != '0') {
                    $item['lastLoginTime'] = $this->formatDate($item['lastLogin']['time']);
                } else {
                    $item['lastLoginTime'] = '/';
                }
                $item['regInfoMobileType'] = $item['regInfo']['mobileType'];

                $location = $ipLocation->getlocation($item['regInfo']['ip']);
                $item['regInfo']['address'] = $location['country'] . $location['area'];
                $location = $ipLocation->getlocation($item['lastLogin']['ip']);
                $item['lastLogin']['address'] = $location['country'] . $location['area'];
            }
            $list = arraySort($list,"sort");
            return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $list]);
        }
        $assignData = [
            'isPay' => ISPAY,
            'isActive' => ISACTIVE,
            'isNormal' => ISNORMAL,
            'isSys' => GameUser::ACCOUNT_CLASSIFY,
        ];
        return view('player/point/list', $assignData);
    }

    public function setPointRedis(Request $request)
    {
        if ($request->isAjax()) {
            $userIdArr = [];
            $userIdArr3 = ["userIds" => []];
            $controList = ControlUser::orderBy("sort", "asc")->select('userId','sort')->get()->toArray();
            if($controList){
                foreach ($controList as $controlItem){
                    $userIdArr[] = $controlItem['userId'];
                }
            }
            $list = GameUser::where(['userId' => ['$in'=>$userIdArr]])->get()->toArray();
            $list = merge_array($list, $controList, 'userId');
            $list = arraySort($list,"sort");
            $key = "ControlUserIds";
            if($list){
                foreach ($list as $item){
                    $userIdArr2[] = $item['userId'];
                }
                $userIdArr3['userIds'] = $userIdArr2;

                $jsonData = json_encode($userIdArr3);

                //delControlPoint($key);
                //setControlPoint(json_encode([]),$key);
                setControlPoint($jsonData,$key);
            }else{
                setControlPoint(json_encode($userIdArr3),$key);
            }
            return json(['code' => 0, 'msg' => '操作成功！']);
        }
    }

    public function getPointRedis(Request $request)
    {
        if ($request->isAjax()) {
             $result = [];
             $key = "ControlUserIds";
             $list = getControlPoint($key);
             $dataArr = json_decode($list);
//             $count = count($dataArr);
             foreach ($dataArr as $item){
                 $result[]["userId"] = $item;
             }
             return json(['code' => 0, 'msg' => 'ok','data' => $result]);
        }
    }

    public function controlPointEdit(Request $request)
    {
        if ($request->isAjax()) {

            $postData = $request->post();

            extract($postData);
            $_id = !empty($_id) ? trim($_id) : false;
            $field = !empty($field) ? trim($field) : false;
            $value = isset($value) ? trim($value) : false;
            $_id = (int)$_id;

            if (!$field || !$_id || $value == "") {
                return json(['code' => -1, 'msg' => '信息输入不正确']);
            }

            if ($field == 'sort')
                $value = (int)$value;


            //sort是否重复
            $sameSortUser = static::getControPlayer(['sort' => $value]);
            if ($sameSortUser) return json(['code' => -1, 'msg' => '排序值重复,请更换其他排序值']);

            $updateData = [
                $field=> $value
            ];
            $controlUserUpdate = ControlUser::where('userId', $_id)->update($updateData);

            if (!$controlUserUpdate) {
                return json(['code' => -1, 'msg' => '修改失败']);
            }else{
                return json(['code' => 0, 'msg' => '修改成功']);
            }
        }
    }

    public function exportPlayer(Request $request)
    {
        $where = $this->_playerListAjaxParam();
        if (!is_array($where)) return $where;
        $cursor = GameUser::where($where)->orderBy('regInfo.time', 'desc')->cursor();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', '注册渠道');
        $sheet->setCellValue('B1', '会员ID');
        $sheet->setCellValue('C1', '会员状态');
        $sheet->setCellValue('D1', '昵称');
        $sheet->setCellValue('E1', '真实姓名');
        $sheet->setCellValue('F1', '手机号');
        $sheet->setCellValue('G1', '金币');
        $sheet->setCellValue('H1', '税收');
        $sheet->setCellValue('I1', '累充');
        $sheet->setCellValue('J1', '累提');
        $sheet->setCellValue('K1', '输赢');
        $sheet->setCellValue('L1', '局数');
        $sheet->setCellValue('M1', '游戏时间');
        $sheet->setCellValue('N1', '注册IP');
        $sheet->setCellValue('O1', '最后登录IP');
        $sheet->setCellValue('P1', '手机型号');
        $sheet->setCellValue('Q1', '注册时间');
        $sheet->setCellValue('R1', '最后登录时间');
        $num = 2;
        $session = $request->session();
        foreach ($cursor as $item) {
            $sheet->setCellValue("A{$num}", $item->promoterId);
            $sheet->setCellValue("B{$num}", $item->userId);
            $sheet->setCellValue("C{$num}", $item->status ? '正常' : '封禁');
            $sheet->setCellValue("D{$num}", $item->nickName);
            $sheet->setCellValue("E{$num}", $item->trueName);
            if($session->get('userName') == "admin"){
                $sheet->setCellValue("F{$num}", mobile($item->mobile));
            }else{
                $sheet->setCellValue("F{$num}", mobileShow(mobile($item->mobile)));
            }
            $sheet->setCellValue("G{$num}", $this->formatMoneyFromMongo($item->score));
            $sheet->setCellValue("H{$num}", $this->formatMoneyFromMongo($item->revenue));
            $sheet->setCellValue("I{$num}", $this->formatMoneyFromMongo($item->rechargeAmount));
            $sheet->setCellValue("J{$num}", $this->formatMoneyFromMongo($item->exchangeAmount));
            $sheet->setCellValue("K{$num}", $this->formatMoneyFromMongo($item->winScore));
            $sheet->setCellValue("L{$num}", $item->gameCount);
            $sheet->setCellValue("M{$num}", Sec2Time($item->totalOnlineGameTime));

            $ipLocation = new IpLocation();
            $location = $ipLocation->getlocation($item['regInfo']['ip']);
            $regInfoaddress = $location['country'] . $location['area'];
            $location = $ipLocation->getlocation($item['lastLogin']['ip']);
            $lastLoginaddress = $location['country'] . $location['area'];
            $sheet->setCellValue("N{$num}", $item['regInfo']['ip'].'|'.$regInfoaddress);
            $sheet->setCellValue("O{$num}", $item['lastLogin']['ip'].'|'.$lastLoginaddress);

            $sheet->setCellValue("P{$num}", $item['regInfo']['mobileType']);
            $sheet->setCellValue("Q{$num}", $this->formatDate($item['regInfo']['time']));
            $sheet->setCellValue("R{$num}", $this->formatDate($item['lastLogin']['time']));

            $num++;
        }
        $writer = new Xlsx($spreadsheet);
        $fileName = 'gameUser.xlsx';
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

    public function onlineList(Request $request)
    {
        if ($request->isAjax()) {
            $where = $where1 = [];
            $getData = check_type($request->all());
            extract($getData);
            if (!empty($roomId)) {
                $where['roomId'] = $roomId;
                $where1['roomId'] = $roomId;
            } else {
                $where['roomId'] = ['$gt' => 0];
                $where1[] = ['roomId', '>', 0];
            }
            $order = ['user.regInfo.time' => 1];
            if (!empty($orderType)) {
                $order = match ($orderType) {
                    '1' => ['user.regInfo.ip' => 1],
                    '2' => ['user.lastLogin.ip' => 1],
                    '3' => ['user.promoterId' => 1],
                };
            }

            $count = UserOnline::where($where1)->count();
            $users = Db::connection('mongodb_main')->collection('user_online')->raw()->aggregate([
                [
                    '$match'=> $where
                ],
                [
                    '$project' =>
                        [
                            'userId' => 1
                        ]
                ],
                [
                    '$lookup' =>
                        [
                            'from' => 'game_user',
                            'localField' => 'userId',
                            'foreignField' => 'userId',
                            'as' => 'user'
                        ]
                ],
                [
                    '$unwind' => '$user'
                ],
                [
                    '$sort' => $order,
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
                            'promoterId' => '$user.promoterId',
                            'userId' => '$user.userId',
                            'nickName' => '$user.nickName',
                            'score' => '$user.score',
                            'bankScore' => '$user.bankScore',
                            'rechargeAmount' => '$user.rechargeAmount',
                            'exchangeAmount' => '$user.exchangeAmount',
                            'revenue' => '$user.revenue',
                            'winScore' => '$user.winScore',
                            'totalOnlineGameTime' => '$user.totalOnlineGameTime',
                            'vip' => '$user.vip',
                            'status' => '$user.status',

                            'regInfo' => '$user.regInfo',
                            'lastLogin' => '$user.lastLogin',
                        ]
                ]
            ])->toArray();
            $promoterIdArr = array_column($users, 'promoterId'); $promoterIdArr = array_unique($promoterIdArr);
            $promoterList = Promoter::whereIn('promoterId', $promoterIdArr)->select('promoterId','promoterName')->get()->toArray();
            $list = merge_array($users, $promoterList, 'promoterId');

            $userIdArr = array_column($users, 'userId'); $userIdArr = array_unique($userIdArr);
            $startDate = date("Y-m-d");
            $startTime = strtotime($startDate);
            $endTime = strtotime("$startDate +1 day");
            $where3 = ["userId" => ['$in' => $userIdArr]];
            $where3['endTime'] = ['$gte' => $this->formatTimestampToMongo($startTime), '$lt' => $this->formatTimestampToMongo($endTime)];
            $data = Db::connection('mongodb_main')->collection('play_record')->raw()->aggregate([
                [
                    '$match' => $where3
                ],
                [
                    '$project' =>
                        [
                            'userId'=>1,
                            'gameId'=>1,
                            'roomId'=>1,
                            'allBet'=>1,
                            'validBet'=>1,
                            'winScore'=>1,
                            'winLostScore'=>1,
                            'platformWinScore' => 1,
                            'revenue' => 1,
                            'playTime'=>1
                        ]
                ],
                [
                    '$group' =>
                        [
                            '_id' => ['userId' => '$userId'],
                            'gameRound' => ['$sum' => 1],
                            'allBet' => ['$sum' => '$allBet'],
                            'validBet' => ['$sum' => '$validBet'],
                            'winScore' => ['$sum' => '$winScore'],
                            'winLostScore' => ['$sum' => '$winLostScore'],
                            'platformWinScore' => ['$sum' => '$platformWinScore'],
                            'revenue' => ['$sum' => '$revenue'],
                            'playTime' => ['$sum' => '$playTime']
                        ]
                ],
                [
                    '$project' =>
                        [
                            'userId'=>'$_id.userId',
                            'gameId'=>'$_id.gameId',
                            'roomId'=>'$_id.roomId',
                            'allBet'=>1,
                            'validBet'=>1,
                            'gameRound'=>1,
                            'winScore'=>1,
                            'winLostScore'=>1,
                            'platformWinScore' => 1,
                            'revenue' => 1,
                            'playTime'=>1
                        ]
                ]
            ])->toArray();

            $ipLocation = new IpLocation();
            foreach ($list as &$item) {
                $item['score'] = $this->formatMoneyFromMongo($item['score']);
                $item['bankScore'] = $this->formatMoneyFromMongo($item['bankScore']);
                $item['rechargeAmount'] = $this->formatMoneyFromMongo($item['rechargeAmount']);
                $item['exchangeAmount'] = $this->formatMoneyFromMongo($item['exchangeAmount']);
                $item['revenue'] = $this->formatMoneyFromMongo($item['revenue']);
                $item['winScore'] = $this->formatMoneyFromMongo($item['winScore']);

                $item['regInfoTime'] = $this->formatDate($item['regInfo']['time']);
                $item['lastLoginTime'] = $this->formatDate($item['lastLogin']['time']);
                $item['regInfoMobileType'] = $item['regInfo']['mobileType'];
                $item['totalOnlineGameTime'] = (int)$item['totalOnlineGameTime'];
                $item['totalOnlineGameTime'] = Sec2Time($item['totalOnlineGameTime']);

                $location = $ipLocation->getlocation($item['regInfo']['ip']);
                $item['regInfo']['address'] = $location['country'] . $location['area'];
                $location = $ipLocation->getlocation($item['lastLogin']['ip']);
                $item['lastLogin']['address'] = $location['country'] . $location['area'];
                foreach ($data as $playRecordData) {
                    if ($item['userId'] == $playRecordData['userId']) {
                        $item['todayPtIncome'] = $this->formatMoneyFromMongo($playRecordData['platformWinScore'] + $playRecordData['revenue']);
                        $item['todayAllBet'] = $this->formatMoneyFromMongo($playRecordData['allBet']);
                        $item['todayValidBet'] = $this->formatMoneyFromMongo($playRecordData['validBet']);
                        $item['todayWinScore'] = $this->formatMoneyFromMongo($playRecordData['winScore']);
                        $item['todayPlatformWinScore'] = $this->formatMoneyFromMongo($playRecordData['platformWinScore']);
                        $item['todayRevenue'] = $this->formatMoneyFromMongo($playRecordData['revenue']);
                        $item['todayWinLostScore'] = $this->formatMoneyFromMongo($playRecordData['winLostScore']);
                    }
                }
            }

            return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $list]);
        }
        $assignData = [
            'isPay' => ISPAY,
            'isActive' => ISACTIVE,
            'isNormal' => ISNORMAL,
            'isSys' => GameUser::ACCOUNT_CLASSIFY,
        ];
        return view('player/online/list', $assignData);
    }

    public function onlineRoom(Request $request)
    {
        $roomList = Db::connection('mongodb_main')->collection('user_online')->raw()->aggregate([
            [
                '$match'=>
                    [
                        'roomId'=>['$gt'=>0]
                    ]
            ],
            [
                '$project' =>
                    [
                        'roomId' => 1,
                    ]
            ],
            [
                '$group' =>
                    [
                        '_id' => '$roomId',
                        'count' => ['$sum' => 1]
                    ]
            ],
            [
                '$sort' => ['_id' => 1]
            ],
            [
                '$project' =>
                    [
                        'roomId' => '$_id',
                        'count'=>1
                    ]
            ]
        ])->toArray();
        $gameRoomInfo = getGameRoomInfo();
        $roomList = merge_array($roomList, $gameRoomInfo, 'roomId');
        return json(['code' => 0, 'msg' => 'ok', 'count' => 0, 'data' => $roomList]);
    }

    private function _userGameDetailAjaxParam()
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
            $endTime = strtotime("$endDate +1 day");
        }else{
            $endTime = strtotime($endDate);
        }
        $startTime = strtotime($startDate);

        if ($startTime >= $endTime) return json(['code' => -1, 'msg' => '请核对开始时间结束时间']);
        $where[] = ['endTime', '>=', $this->formatTimestampToMongo($startTime)];
        $where[] = ['endTime', '<', $this->formatTimestampToMongo($endTime)];

        if (!empty($userId)) {
            $where['userId'] = $userId;
        } else {
            if (!empty($isSys)) {
                if ($isSys == GameUser::COMMON_ACCOUNT) {
                    $where[] = ['userId', '>=', GameUser::COMMON_ACCOUNT_START_ID];
                } elseif ($isSys == GameUser::SYSTEM_ACCOUNT) {
                    $where[] = ['userId', '<', GameUser::COMMON_ACCOUNT_START_ID];
                }
            } else {
                $where[] = ['userId', '>=', GameUser::COMMON_ACCOUNT_START_ID];
            }
            //$where[] = ['userId', '>=', GameUser::COMMON_ACCOUNT_START_ID];
        }



        if (!empty($gameInfoId)) {
            $where['gameInfoId'] = $gameInfoId;
        }
        if (!empty($gameId)) {
            $where['gameId'] = $gameId;
        }
        if (!empty($roomId)) {
            $where['roomId'] = $roomId;
        }
        return $where;
    }
    public function userGameDetail(Request $request)
    {
        if ($request->isAjax()) {
            $where = $this->_userGameDetailAjaxParam();
            if (!is_array($where)) return $where;
            $orderType = $request->get('orderType');
            $order = 'desc';
            if(empty($orderType)) $orderType = 'leaveTime';
            $field = match ($orderType) {
                'leaveTime' => 'endTime',
                'earnScore' => 'winScore',
                'revenue' => 'revenue',
            };
            $count = PlayRecord::where($where)->count();
            $list = PlayRecord::where($where)->orderBy($field, $order)->skip($request->skip)->take($request->limit)->get()->toArray();
            $gameRoomInfo = getGameRoomInfoAll();

            $list = merge_array($list, $gameRoomInfo, 'roomId');
            $ipLocation = new IpLocation();
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

        $getData = check_type($request->all());
        extract($getData);
        $startDate = !empty($startDate) ? trim($startDate) : "";
        $endDate = !empty($endDate) ? trim($endDate) : "";
        $userId = !empty($userId) ? (int)$userId : '';
        $assignData = [
            'orderType' => ['leaveTime' => '结算时间', 'earnScore' => '平台营收', 'revenue' => '平台税收'],
            'gameList' => GameKind::where(['status' => 1, 'rooms.status' => 1])->orderBy('sort', 'asc')->get()->toArray(),
            'startDate'=>$startDate,
            'endDate'=>$endDate,
            'userId'=>$userId,
            'isSys' => GameUser::ACCOUNT_CLASSIFY,
        ];
        //dd($assignData['gameList']);
        return view('player/gameDetail/list', $assignData);
    }
    public function userGameDetailSummary(Request $request)
    {
        if($request->isAjax()) {
            $where = $this->_userGameDetailAjaxParam();
            if (!is_array($where)) return $where;
            $where[] = ['userId', '>=', GameUser::COMMON_ACCOUNT_START_ID];
            $ptIncomeSum = 0;
            $winScoreSum = PlayRecord::where($where)->sum('winScore');
            $winScoreSum = $this->formatMoneyFromMongo($winScoreSum);

            $platformWinScoreSum = PlayRecord::where($where)->sum('platformWinScore');
            $ptIncomeSum += $platformWinScoreSum;
            $platformWinScoreSum = $this->formatMoneyFromMongo($platformWinScoreSum);

            $revenueSum = PlayRecord::where($where)->sum('revenue');
            $ptIncomeSum += $revenueSum;
            $revenueSum = $this->formatMoneyFromMongo($revenueSum);

            $validBetSum = PlayRecord::where($where)->sum('validBet');
            $validBetSum = $this->formatMoneyFromMongo($validBetSum);

            $allBetSum = PlayRecord::where($where)->sum('allBet');
            $allBetSum = $this->formatMoneyFromMongo($allBetSum);

            $ptIncomeSum = $this->formatMoneyFromMongo($ptIncomeSum);

            $data = [
                'platformWinScoreSum' => $platformWinScoreSum,
                'revenueSum' => $revenueSum,
                'ptIncomeSum' => $ptIncomeSum,
                'validBetSum' => $validBetSum,
                'allBetSum' => $allBetSum,
                'winScoreSum' => $winScoreSum
            ];

            return json(['code' => 0, 'msg' => 'ok', 'data' => $data]);
        }
    }

    private function _userGameStatAjaxParam(){
        $where = ['isAndroid' => false];
        $request = request();
        $getData = check_type($request->all());
        extract($getData);
        $state = 0;
        if((isset($userId) && $userId > 0) || !empty($roomId) || !empty($type) || (!empty($isSys) && $isSys != 2) || !empty($gameId)){
            $state = 1;
        }

        if (empty($startDate)) {
            $startDate = date("Y-m-d");
        }
        if (empty($endDate)) {
            $endDate = date("Y-m-d");
            $endTime = strtotime("$endDate +1 day");
        }else{
            $endTime = strtotime($endDate);
        }
        $startTime = strtotime($startDate);

        if ($startTime >= $endTime) return json(['code' => -1, 'msg' => '请核对开始时间结束时间']);
        $where['endTime'] = ['$gte' => $this->formatTimestampToMongo($startTime), '$lt' => $this->formatTimestampToMongo($endTime)];
        if (empty($isSys)) $isSys = GameUser::COMMON_ACCOUNT;
        if ($isSys == GameUser::COMMON_ACCOUNT) {
            $where['userId'] = ['$gte' => GameUser::COMMON_ACCOUNT_START_ID];
        } elseif ($isSys == GameUser::SYSTEM_ACCOUNT) {
            $where['userId'] = ['$lt' => GameUser::COMMON_ACCOUNT_START_ID];
        }


        if (!empty($promoterId)) {
            $where['promoterId'] = (int)$promoterId;
        }

        if(!empty($type)){
            $promoterIds = getPromoterId();
            if($type == 1){
                if(isset($where['userId'])){
                    $where['userId'] += ['$in' => $promoterIds];
                }else{
                    $where['userId'] = ['$in' => $promoterIds];
                }
            }else{
                if(isset($where['userId'])){
                    $where['userId'] += ['$nin' => $promoterIds];
                }else{
                    $where['userId'] = ['$nin' => $promoterIds];
                }
            }
        }
        if (!empty($userId)) {
            if(isset($where['userId'])){
                $where['userId'] += ['$eq' =>$userId];
            }else{
                $where['userId'] = $userId;
            }
        }

        $groupId = ['userId' => '$userId'];
        if (!empty($roomId)) {
            $where['roomId'] = $roomId;
            $groupId = ['userId' => '$userId', 'gameId' => '$gameId', 'roomId' => '$roomId'];
        } elseif (!empty($gameId)) {
            $where['gameId'] = $gameId;
            $groupId = ['userId' => '$userId', 'gameId' => '$gameId'];
        }
        if (empty($orderType)) $orderType = 'allBet';
        if ($orderType == "winScore") {
            $options = ['$sort' => ['winScore' => -1]];
        } elseif ($orderType == "revenue") {
            $options = ['$sort' => ['revenue' => -1]];
        } elseif ($orderType == "allBet") {
            $options = ['$sort' => ['allBet' => -1]];
        }

        return [
            'where'   => $where,
            'group'   => $groupId,
            'options' => $options,
            'userId'  => isset($userId) ? $userId : 0,
            'state'  => $state
        ];
    }

    public function userGameStat(Request $request){
        if ($request->isAjax()) {
            $where = $this->_userGameStatAjaxParam();
            if (!is_array($where)) return $where;
            $promoterIds = getPromoterId();
            $list = PlayRecord::playerProfit($where,'list',$request->skip,$request->limit);
            $listCount = PlayRecord::playerProfit($where,'count');
            $num = $total = 0;
            $promoterId = $where['where']['promoterId'] ?? 0;
            if($promoterId){
                $user = PlayRecord::playerProfit($where,'user');
                if($user && $where['userId'] == 0 || $where['userId'] == $where['where']['promoterId']){
                    $list = array_merge($user,$list);
                    $num = 1;
                }
            }

            if (isset($where['where']['roomId'])) {
                $gameRoomInfo = getGameRoomInfo();
                $list = merge_array($list, $gameRoomInfo, 'roomId');
            } elseif (isset($where['where']['gameId'])) {
                $gameInfo = getGameInfo($where['where']['gameId']);
                $list = merge_array($list, $gameInfo, 'gameId');
            }

            $promoterMember = $promoterMember1 = $promoterMember2 = [];
            if($promoterId){
                $userIds = GameUser::where('promoterId',$promoterId) -> select('userId') -> get() -> toArray();
                $promoterMember = array_merge([['userId' =>$promoterId]],$userIds);
                if($promoterMember){
                    foreach ($promoterMember as &$val){
                        $val['earnScore'] = $val['ptIncome'] = $val['allBet'] = $val['validBet'] = $val['winScore'] = $val['platformWinScore'] = $val['revenue'] = $val['playTime'] = $val['agentRevenue'] = 0;
                        $val['type'] = in_array($val['userId'],$promoterIds) ? '代理' : '会员';
                        if(in_array($val['userId'],$promoterIds)) {
                            $promoterMember1[] = $val;
                        }else{
                            $promoterMember2[] = $val;
                        }
                    }
                }
            }

            $nowUid = [];
            foreach ($list as &$item) {
                if (isset($item['winLostScore'])) {
                    $item['earnScore'] = $this->formatMoneyFromMongo($item['winLostScore']);
                } else {
                    $item['earnScore'] = $this->formatMoneyFromMongo($item['winScore'] + $item['revenue']);
                }
                $item['type'] = in_array($item['userId'],$promoterIds) ? '代理' : '会员';
                $item['ptIncome'] = $this->formatMoneyFromMongo($item['platformWinScore'] + $item['revenue']);
                $item['allBet'] = $this->formatMoneyFromMongo($item['allBet']);
                $item['validBet'] = $this->formatMoneyFromMongo($item['validBet']);
                $item['winScore'] = $this->formatMoneyFromMongo($item['winScore']);
                $item['platformWinScore'] = $this->formatMoneyFromMongo($item['platformWinScore']);
                $item['revenue'] = $this->formatMoneyFromMongo($item['revenue']);
                $item['agentRevenue'] = $this->formatMoneyFromMongo($item['agentRevenue']);
                $item['playTime'] = Sec2Time($item['playTime']);

                $nowUid[] = $item['userId'];
            }

            if($promoterMember){
                $promoterNum = 0;
                $tempType = $request->get('type', 0);
                if ($tempType == 1) {
                    foreach ($promoterMember1 as $k => $v){
                        if(!in_array($v['userId'],$nowUid)){
                            $list[] = $v;
                            $promoterNum++;
                        }
                    }
                }elseif ($tempType == 2) {
                    foreach ($promoterMember2 as $k => $v){
                        if(!in_array($v['userId'],$nowUid)){
                            $list[] = $v;
                            $promoterNum++;
                        }
                    }
                }else{
                    foreach ($promoterMember as $k => $v){
                        if(!in_array($v['userId'],$nowUid)){
                            $list[] = $v;
                            $promoterNum++;
                        }
                    }
                }
                $total = count($listCount) + $promoterNum;
            }else{
                $total = count($listCount) + $num;
            }
            if (!empty($list)) {
                $userIdArr =  array_column($list, 'userId');
                $users = GameUser::whereIn('userId', $userIdArr)->get(['userId', 'rechargeAmount', 'exchangeAmount', 'clubRewardScore','score','bankScore'])->toArray();
                foreach ($users as &$user) {
                    unset($user['_id']);
                    $user['rechargeAmount'] = !empty($user['rechargeAmount']) ? $this->formatMoneyFromMongo($user['rechargeAmount']) : 0;
                    $user['exchangeAmount'] = !empty($user['exchangeAmount']) ? $this->formatMoneyFromMongo($user['exchangeAmount']) : 0;
                    $user['clubRewardScore'] = !empty($user['clubRewardScore']) ? $this->formatMoneyFromMongo($user['clubRewardScore']): 0;
                    $user['score'] = $this->formatMoneyFromMongo($user['score']);
                    $user['bankScore'] = $this->formatMoneyFromMongo($user['bankScore']);
                }
                $list = merge_array($list, $users, 'userId');
            }
            foreach ($list as &$item) {
                if (!isset($item['rechargeAmount'])) $item['rechargeAmount'] = 0;
                if (!isset($item['exchangeAmount'])) $item['exchangeAmount'] = 0;
                if (!isset($item['clubRewardScore'])) $item['clubRewardScore'] = 0;
                if (!isset($item['score'])) $item['score'] = 0;
                if (!isset($item['bankScore'])) $item['bankScore'] = 0;
            }

            return json(['code' => 0, 'msg' => 'ok', 'count' => $total, 'data' => $list]);
        }
        $assignData = [
            'orderType' => ['allBet' => '会员压分', 'winScore' => '会员输赢', 'revenue' => '游戏税收'],
            'isSys' => GameUser::ACCOUNT_CLASSIFY,
            'gameList' => GameKind::where(['status' => 1, 'rooms.status' => 1])->orderBy('sort', 'asc')->get()->toArray(),
            'promoterId' => $request -> get("promoterId",''),
        ];
        return view('player/gameStat/list', $assignData);
    }

    public function userGameStatSummary(Request $request){
        if($request->isAjax()) {
            $where = $this->_userGameStatAjaxParam();
            if (!is_array($where)) return $where;
            $listCount = PlayRecord::playerProfit($where,'statistics');
            if(isset($where['where']['promoterId'])){
                $user = PlayRecord::playerProfit($where,'user');
                if($user && $where['userId'] == 0 || $where['userId'] == $where['where']['promoterId']){
                    if($listCount){
                        $listCount[0]['revenue'] += $user[0]['revenue'];
                        $listCount[0]['allBet'] += $user[0]['allBet'];
                        $listCount[0]['winScore'] += $user[0]['winScore'];
                        $listCount[0]['platformWinScore'] += $user[0]['platformWinScore'];
                    }else{
                        $listCount[0]['revenue'] = $user[0]['revenue'];
                        $listCount[0]['allBet'] = $user[0]['allBet'];
                        $listCount[0]['winScore'] = $user[0]['winScore'];
                        $listCount[0]['platformWinScore'] = $user[0]['platformWinScore'];
                    }
                }
            }

            $revenueSum = $this->formatMoneyFromMongo($listCount[0]['revenue']);
            $allBetSum = $this->formatMoneyFromMongo($listCount[0]['allBet']);
            $winScoreSum = $this->formatMoneyFromMongo($listCount[0]['winScore']);
            $platformWinScoreSum = $this->formatMoneyFromMongo($listCount[0]['platformWinScore']);
            $earnScoreSum = $this->formatMoneyFromMongo($listCount[0]['winScore'] + $listCount[0]['revenue']);
            $ptIncomeSum = $this->formatMoneyFromMongo($listCount[0]['platformWinScore'] + $listCount[0]['revenue']);

            $data = [
                'earnScoreSum' => $earnScoreSum,
                'ptIncomeSum'  => $ptIncomeSum,
                'revenueSum'   => $revenueSum,
                'allBetSum'    => $allBetSum,
                'winScoreSum'  => $winScoreSum,
                'platformWinScoreSum' => $platformWinScoreSum
            ];

            return json(['code' => 0, 'msg' => 'ok', 'data' => $data]);
        }
    }

    public function exportGameStat(Request $request)
    {
        $where = $this->_userGameStatAjaxParam();
        if (!is_array($where)) return $where;
        $list = Db::connection('mongodb_main')->collection('play_record')->raw()->aggregate([
            [
                '$match' => $where['where']
            ],
            [
                '$project' =>
                    [
                        'userId'=>1,
                        'gameId'=>1,
                        'roomId'=>1,
                        'allBet'=>1,
                        'winScore'=>1,
                        'platformWinScore'=>1,
                        'revenue'=>1,
                        'playTime'=>1
                    ]
            ],
            [
                '$group' =>
                    [
                        '_id' => $where['group'],
                        'gameRound' => ['$sum' => 1],
                        'allBet' => ['$sum' => '$allBet'],
                        'winScore' => ['$sum' => '$winScore'],
                        'platformWinScore' => ['$sum' => '$platformWinScore'],
                        'revenue' => ['$sum' => '$revenue'],
                        'playTime' => ['$sum' => '$playTime']
                    ]
            ],
            $where['options']
            ,
            [
                '$skip' => 0
            ],
            [
                '$project' =>
                    [
                        'userId'=>'$_id.userId',
                        'gameId'=>'$_id.gameId',
                        'roomId'=>'$_id.roomId',
                        'allBet'=>1,
                        'gameRound'=>1,
                        'winScore'=>1,
                        'platformWinScore' => 1,
                        'revenue' => 1,
                        'playTime'=>1
                    ]
            ]
        ])->toArray();
        $count = count($list);
        if (isset($where['where']['roomId'])) {
            $gameRoomInfo = getGameRoomInfo();
            $list = merge_array($list, $gameRoomInfo, 'roomId');
        } elseif (isset($where['where']['gameId'])) {
            $gameInfo = getGameInfo($where['where']['gameId']);
            $list = merge_array($list, $gameInfo, 'gameId');
        }
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', '会员ID');
        $sheet->setCellValue('B1', '游戏名称');
        $sheet->setCellValue('C1', '会员压分');
        $sheet->setCellValue('D1', '会员得分');
        $sheet->setCellValue('E1', '会员输赢');
        $sheet->setCellValue('F1', '游戏输赢');
        $sheet->setCellValue('G1', '平台税收');
        $sheet->setCellValue('H1', '平台营收');
        $sheet->setCellValue('I1', '会员时长');
        $sheet->setCellValue('J1', '会员局数');
        $num = 2;
        foreach ($list as $item) {
            if (isset($item['winLostScore'])) {
                $item['earnScore'] = $this->formatMoneyFromMongo($item['winLostScore']);
            } else {
                $item['earnScore'] = $this->formatMoneyFromMongo($item['winScore'] + $item['revenue']);
            }
            $item['ptIncome'] = $this->formatMoneyFromMongo($item['platformWinScore'] + $item['revenue']);
            $item['allBet'] = $this->formatMoneyFromMongo($item['allBet']);
            $item['winScore'] = $this->formatMoneyFromMongo($item['winScore']);
            $item['platformWinScore'] = $this->formatMoneyFromMongo($item['platformWinScore']);
            $item['revenue'] = $this->formatMoneyFromMongo($item['revenue']);
            $item['playTime'] = Sec2Time($item['playTime']);
            $sheet->setCellValue("A{$num}", $item['userId']);
            $sheet->setCellValue("B{$num}", $item['roomName']??$item['gameName']??'-');
            $sheet->setCellValue("C{$num}", $item['allBet']);
            $sheet->setCellValue("D{$num}", $item['earnScore']);
            $sheet->setCellValue("E{$num}", $item['winScore']);
            $sheet->setCellValue("F{$num}", $item['platformWinScore']);
            $sheet->setCellValue("G{$num}", $item['revenue']);
            $sheet->setCellValue("H{$num}", $item['ptIncome']);
            $sheet->setCellValue("I{$num}", $item['playTime']);
            $sheet->setCellValue("J{$num}", $item['gameRound']);

            $num++;
        }
        $writer = new Xlsx($spreadsheet);
        $file_path = public_path().'/exportGameStat.xlsx';
        // 保存文件到 public 下
        $writer->save($file_path);

        return json(['code' => 0, 'msg' => 'ok', 'file' => $file_path]);
    }

    private function _userGameStat2AjaxParam(){
        $where = ['isAndroid' => false];
        $request = request();
        $getData = check_type($request->all());
        extract($getData);
        $state = 0;
        /*if((isset($userId) && $userId > 0) || !empty($roomId) || !empty($type) || (!empty($isSys) && $isSys != 2) || !empty($gameId)){
            $state = 1;
        }*/

        if (empty($startDate)) {
            $startDate = date("Y-m-d");
        }
        if (empty($endDate)) {
            $endDate = date("Y-m-d");
            $endTime = strtotime("$endDate +1 day");
        }else{
            $endTime = strtotime($endDate);
        }
        $startTime = strtotime($startDate);

        if ($startTime >= $endTime) return json(['code' => -1, 'msg' => '请核对开始时间结束时间']);
        $where['endTime'] = ['$gte' => $this->formatTimestampToMongo($startTime), '$lt' => $this->formatTimestampToMongo($endTime)];
        /*if (empty($isSys)) $isSys = GameUser::COMMON_ACCOUNT;
        if ($isSys == GameUser::COMMON_ACCOUNT) {
            $where['userId'] = ['$gte' => GameUser::COMMON_ACCOUNT_START_ID];
        } elseif ($isSys == GameUser::SYSTEM_ACCOUNT) {
            $where['userId'] = ['$lt' => GameUser::COMMON_ACCOUNT_START_ID];
        }*/

        /*if (!empty($promoterId)) {
            $where['promoterId'] = (int)$promoterId;
        }*/

        /*if(!empty($type)){
            $promoterIds = getPromoterId();
            if($type == 1){
                if(isset($where['userId'])){
                    $where['userId'] += ['$in' => $promoterIds];
                }else{
                    $where['userId'] = ['$in' => $promoterIds];
                }
            }else{
                if(isset($where['userId'])){
                    $where['userId'] += ['$nin' => $promoterIds];
                }else{
                    $where['userId'] = ['$nin' => $promoterIds];
                }
            }
        }*/
        $idArr = teamPeople((int)$promoterId);
        //$idArr = teamPeople(1000);
        array_unshift($idArr, (int)$promoterId);
        //dd($idArr);
        $useIdArr = array_slice($idArr, $request->skip, $request->limit);
        $where['userId'] = ['$in' => $useIdArr];
        if (!empty($userId)) {
            $where['userId'] = $userId;
        }

        $groupId = ['userId' => '$userId'];
        /*if (!empty($roomId)) {
            $where['roomId'] = $roomId;
            $groupId = ['userId' => '$userId', 'gameId' => '$gameId', 'roomId' => '$roomId'];
        } elseif (!empty($gameId)) {
            $where['gameId'] = $gameId;
            $groupId = ['userId' => '$userId', 'gameId' => '$gameId'];
        }*/
        /*if (empty($orderType)) $orderType = 'allBet';
        if ($orderType == "winScore") {
            $options = ['$sort' => ['winScore' => -1]];
        } elseif ($orderType == "revenue") {
            $options = ['$sort' => ['revenue' => -1]];
        } elseif ($orderType == "allBet") {
            $options = ['$sort' => ['allBet' => -1]];
        }*/

        return [
            'where'   => $where,
            'group'   => $groupId,
            'count' => count($idArr),
            'useIdArr' => $useIdArr,
        ];
    }

    public function userGameStat2(Request $request){
        if ($request->isAjax()) {
            $where = $this->_userGameStat2AjaxParam();
            if (!is_array($where)) return $where;

            $promoterIds = getPromoterId();
            $list = PlayRecord::playerProfit($where,'list2');
            $userList = GameUser::whereIn('userId', $where['useIdArr'])->select('userId','score','bankScore','rechargeAmount','exchangeAmount','trueName','nickName')->get()->toArray();
            $list = merge_array($userList, $list, 'userId');
            foreach ($list as &$item) {
                $item['score'] = $this->formatMoneyFromMongo($item['score']);
                $item['bankScore'] = $this->formatMoneyFromMongo($item['bankScore']);
                $item['rechargeAmount'] = $this->formatMoneyFromMongo($item['rechargeAmount']);
                $item['exchangeAmount'] = $this->formatMoneyFromMongo($item['exchangeAmount']);
                $item['type'] = in_array($item['userId'],$promoterIds) ? '代理' : '会员';

                if (!isset($item['winScore'])) {
                    $item['winScore'] = $item['earnScore'] = $item['winLostScore'] = $item['ptIncome'] = $item['allBet'] = $item['validBet'] = 0;
                    $item['platformWinScore'] = $item['revenue'] = $item['agentRevenue'] = $item['playTime'] = 0;
                } else {
                    if (isset($item['winLostScore'])) {
                        $item['earnScore'] = $this->formatMoneyFromMongo($item['winLostScore']);
                    } else {
                        $item['earnScore'] = $this->formatMoneyFromMongo($item['winScore'] + $item['revenue']);
                    }
                    $item['ptIncome'] = $this->formatMoneyFromMongo($item['platformWinScore'] + $item['revenue']);
                    $item['allBet'] = $this->formatMoneyFromMongo($item['allBet']);
                    $item['validBet'] = $this->formatMoneyFromMongo($item['validBet']);
                    $item['winScore'] = $this->formatMoneyFromMongo($item['winScore']);
                    $item['platformWinScore'] = $this->formatMoneyFromMongo($item['platformWinScore']);
                    $item['revenue'] = $this->formatMoneyFromMongo($item['revenue']);
                    $item['agentRevenue'] = $this->formatMoneyFromMongo($item['agentRevenue']);
                    $item['playTime'] = Sec2Time($item['playTime']);
                }
            }
            return json(['code' => 0, 'msg' => 'ok', 'count' => $where['count'], 'data' => $list]);
        }
        $assignData = [
            'orderType' => ['allBet' => '会员压分', 'winScore' => '会员输赢', 'revenue' => '游戏税收'],
            'isSys' => GameUser::ACCOUNT_CLASSIFY,
            'gameList' => GameKind::where(['status' => 1, 'rooms.status' => 1])->orderBy('sort', 'asc')->get()->toArray(),
            'promoterId' => $request -> get("promoterId",''),
        ];
        return view('player/gameStat2/list', $assignData);
    }

    public function userGameStat2Summary(Request $request){
        if($request->isAjax()) {
            $where = $this->_userGameStat2AjaxParam();
            if (!is_array($where)) return $where;
            $listCount = PlayRecord::playerProfit($where,'statistics');

            $revenueSum = $this->formatMoneyFromMongo($listCount[0]['revenue']);
            $allBetSum = $this->formatMoneyFromMongo($listCount[0]['allBet']);
            $winScoreSum = $this->formatMoneyFromMongo($listCount[0]['winScore']);
            $platformWinScoreSum = $this->formatMoneyFromMongo($listCount[0]['platformWinScore']);
            $earnScoreSum = $this->formatMoneyFromMongo($listCount[0]['winScore'] + $listCount[0]['revenue']);
            $ptIncomeSum = $this->formatMoneyFromMongo($listCount[0]['platformWinScore'] + $listCount[0]['revenue']);

            $data = [
                'earnScoreSum' => $earnScoreSum,
                'ptIncomeSum'  => $ptIncomeSum,
                'revenueSum'   => $revenueSum,
                'allBetSum'    => $allBetSum,
                'winScoreSum'  => $winScoreSum,
                'platformWinScoreSum' => $platformWinScoreSum
            ];

            return json(['code' => 0, 'msg' => 'ok', 'data' => $data]);
        }
    }

    public function userIncomeStat(Request $request)
    {
        if($request->isAjax()) {
            $getData = check_type($request->get());
            extract($getData);
            if (empty($startDate)) {
                $startDate = date("Y-m-d");
            }
            if (empty($endDate)) {
                $endDate = date("Y-m-d");
            }
            $startDate = strtotime($startDate);
            $endDate = strtotime("$endDate +1 day");
            $filter = [];
            $filter += ['endTime' => ['$gte' => $this->formatTimestampToMongo($startDate), '$lt' => $this->formatTimestampToMongo($endDate)]];

            if(!empty($userId)){
                $filter += ['userId' => $userId];
            }else{
                $filter += ['userId' => ['$gte' => 10000000]];
            }

//            $usersOnline = Db::connection('mongodb_main')->collection('game_user')->raw()->aggregate([
//                [
//                    '$match' =>
//                        [
//                            'onlineLogin' => ['$gt' => 0],
//                            'onlineGame' => ['$gt' => 0]
//                        ]
//                ],
//                [
//                    '$match' => [
//                        'userId' => [
//                            '$gte' => 10000000,
//                        ]
//                    ]
//                ],
//                [
//                    '$project' =>
//                        [
//                            'onlineGame' => 1,
//                        ]
//                ],
//                [
//                    '$group' =>
//                        [
//                            '_id' => '$onlineGame',
//                            'onlineCount' => ['$sum' => 1]
//                        ]
//                ],
//                [
//                    '$project' =>
//                        [
//                            'roomId' => '$_id',
//                            'onlineCount' => 1
//                        ]
//                ]
//            ])->toArray();




            $play_game_room_info_arrays = Db::connection('mongodb_main')->collection('play_record')->raw()->aggregate([
                [
                    '$match' => $filter
                ],
                [
                    '$project' =>
                        [
                            'userId' => 1,
                            'gameId' => 1,
                            'roomId' => 1,
                            'allBet' => 1,
                            'winScore' => 1,
                            'platformWinScore' => 1,
                            'revenue' => 1
//                    'playTime'=>1
                        ]
                ],
                [
                    '$group' =>
                        [
                            '_id' => ['gameId' => '$gameId', 'roomId' => '$roomId', 'userId' => '$userId'],
                            'gameRound' => ['$sum' => 1],
                            'winTimes' =>
                                ['$sum' =>
                                    [
                                        '$cond' =>
                                            [
                                                'if' => ['$gt' => ['$winScore', 0]],
                                                'then' => 1,
                                                'else' => 0,
                                            ]
                                    ]
                                ],
                            'lostTimes' =>
                                ['$sum' =>
                                    [
                                        '$cond' =>
                                            [
                                                'if' => ['$lt' => ['$winScore', 0]],
                                                'then' => 1,
                                                'else' => 0,
                                            ]
                                    ]
                                ],
                            'drawTimes' =>
                                ['$sum' =>
                                    [
                                        '$cond' =>
                                            [
                                                'if' => ['$eq' => ['$winScore', 0]],
                                                'then' => 1,
                                                'else' => 0,
                                            ]
                                    ]
                                ],
                            'allBet' => ['$sum' => '$allBet'],
                            'winScore' => ['$sum' => '$winScore'],
                            'platformWinScore' => ['$sum' => '$platformWinScore'],
                            'revenue' => ['$sum' => '$revenue']
//                    'playTime' => ['$sum' => '$playTime']
                        ]
                ],
                [
                    '$group' =>
                        [
                            '_id' => ['gameId' => '$_id.gameId', 'roomId' => '$_id.roomId'],
                            'userCount' => ['$sum' => 1],
                            'gameRound' => ['$sum' => '$gameRound'],
                            'winTimes' => ['$sum' => '$winTimes'],
                            'lostTimes' => ['$sum' => '$lostTimes'],
                            'drawTimes' => ['$sum' => '$drawTimes'],
                            'allBet' => ['$sum' => '$allBet'],
                            'winScore' => ['$sum' => '$winScore'],
                            'platformWinScore' => ['$sum' => '$platformWinScore'],
                            'revenue' => ['$sum' => '$revenue']
//                    'playTime' => ['$sum' => '$playTime']
                        ]
                ],
                [
                    '$project' =>
                        [
                            'gameId' => '$_id.gameId',
                            'roomId' => '$_id.roomId',
                            'gameRound' => 1,
                            'userCount' => 1,
                            'winTimes' => 1,
                            'lostTimes' => 1,
                            'drawTimes' => 1,
                            'allBet' => 1,
                            'winScore' => 1,
                            'platformWinScore' => 1,
                            'revenue' => 1
//                    'playTime'=>1
                        ]
                ]
            ])->toArray();
            $game_room_info = getGameRoomInfo();
            $play_game_room_info_arrays = merge_array($play_game_room_info_arrays, $game_room_info, 'roomId');

            //=======统计======
            $onlineCountTotal = 0;
            $userCountTotal = 0;
            $gameRoundTotal = 0;

            $winTimesTotal = 0;
            $lostTimesTotal = 0;
            $drawTimesTotal = 0;

            $allBetTotal = 0;
            $winScoreTotal = 0;
            $platformWinScoreTotal = 0;
            $revenueTotal = 0;
//    $playTimeTotal = 0;

            $ptIncomeTotal = 0;
            //=================

            $game_info_list = getGameInfo();
            $num = count($game_info_list);
            foreach ($game_info_list as $key => $value) {
                $game_info_list[$key]["pid"] = 0;
                $game_info_list[$key]["id"] = $value['gameId'];
                $game_info_list[$key]["title"] = $value['gameId'] . '_' . $value['gameName'];

                $game_info_list[$key]["onlineCount"] = 0;
                $game_info_list[$key]["userCount"] = 0;
                $game_info_list[$key]["gameRound"] = 0;

                $game_info_list[$key]["winTimes"] = 0;
                $game_info_list[$key]["lostTimes"] = 0;
                $game_info_list[$key]["drawTimes"] = 0;
                $game_info_list[$key]["lostWinRate"] = 0;

                $game_info_list[$key]["allBet"] = 0;
                $game_info_list[$key]["winScore"] = 0;
                $game_info_list[$key]["platformWinScore"] = 0;
                $game_info_list[$key]["revenue"] = 0;

//        $game_info_list[$key]["playTime"] = 0;

                $game_info_list[$key]["ptIncome"] = 0;
                $game_info_list[$key]["ptIncomeRate"] = 0;
                foreach ($play_game_room_info_arrays as $kk => $vv) {
                    //unset($play_game_room_info_arrays[$kk]['_id']);
                    if ($game_info_list[$key]["gameId"] == $play_game_room_info_arrays[$kk]['gameId']) {
                        if (!isset($play_game_room_info_arrays[$kk]['onlineCount']))
                            $play_game_room_info_arrays[$kk]['onlineCount'] = 0;
                        $game_info_list[$key]["onlineCount"] += $play_game_room_info_arrays[$kk]['onlineCount'];
                        $game_info_list[$key]["userCount"] += $play_game_room_info_arrays[$kk]['userCount'];
                        $game_info_list[$key]["gameRound"] += $play_game_room_info_arrays[$kk]['gameRound'];

                        $game_info_list[$key]["winTimes"] += $play_game_room_info_arrays[$kk]['winTimes'];
                        $game_info_list[$key]["lostTimes"] += $play_game_room_info_arrays[$kk]['lostTimes'];
                        $game_info_list[$key]["drawTimes"] += $play_game_room_info_arrays[$kk]['drawTimes'];
//                if($play_game_room_info_arrays[$kk]['lostTimes'] > 0)
//                    $play_game_room_info_arrays[$kk]["lostWinRate"] = round($play_game_room_info_arrays[$kk]['winTimes'] / $play_game_room_info_arrays[$kk]['lostTimes'], 2);
//                else
//                    $play_game_room_info_arrays[$kk]["lostWinRate"] = 0;

                        $play_game_room_info_arrays[$kk]["allBet"] = round($play_game_room_info_arrays[$kk]["allBet"] * 0.01, 2);
                        $play_game_room_info_arrays[$kk]["winScore"] = round($play_game_room_info_arrays[$kk]["winScore"] * 0.01, 2);
                        $play_game_room_info_arrays[$kk]["platformWinScore"] = round($play_game_room_info_arrays[$kk]["platformWinScore"] * 0.01, 2);
                        $play_game_room_info_arrays[$kk]["revenue"] = round($play_game_room_info_arrays[$kk]["revenue"] * 0.01, 2);

//                $play_game_room_info_arrays[$kk]["earnScore"] = round(($play_game_room_info_arrays[$kk]["revenue"]+$play_game_room_info_arrays[$kk]["winScore"])*0.01, 2);
                        //$play_game_room_info_arrays[$kk]["ptIncome"] = round(($play_game_room_info_arrays[$kk]["revenue"]-$play_game_room_info_arrays[$kk]["winScore"])*0.01, 2);
                        //$play_game_room_info_arrays[$kk]["ptIncome"] = round(($play_game_room_info_arrays[$kk]["revenue"]-$play_game_room_info_arrays[$kk]["winScore"]), 2);
                        $play_game_room_info_arrays[$kk]["ptIncome"] = round(($play_game_room_info_arrays[$kk]["platformWinScore"] + $play_game_room_info_arrays[$kk]["revenue"]), 2);


                        $game_info_list[$key]["allBet"] += $play_game_room_info_arrays[$kk]['allBet'];
                        $game_info_list[$key]["winScore"] += $play_game_room_info_arrays[$kk]['winScore'];
                        $game_info_list[$key]["revenue"] += $play_game_room_info_arrays[$kk]['revenue'];
                        $game_info_list[$key]["platformWinScore"] += $play_game_room_info_arrays[$kk]["platformWinScore"];
//                $game_info_list[$key]["earnScore"] += $play_game_room_info_arrays[$kk]['earnScore'];
                        $game_info_list[$key]["ptIncome"] += $play_game_room_info_arrays[$kk]['ptIncome'];

                        array_push($game_info_list, $play_game_room_info_arrays[$kk]);
                        $game_info_list[$num]["id"] = $play_game_room_info_arrays[$kk]["roomId"];
                        $game_info_list[$num]["pid"] = $game_info_list[$key]["id"];
                        $game_info_list[$num]["title"] = $play_game_room_info_arrays[$kk]["roomId"] . '_' . $play_game_room_info_arrays[$kk]["roomName"];
                        unset($play_game_room_info_arrays[$kk]);
                        $num++;
                    }
                }

                $onlineCountTotal += $game_info_list[$key]["onlineCount"];
                $userCountTotal += $game_info_list[$key]["userCount"];
                $gameRoundTotal += $game_info_list[$key]["gameRound"];

                $winTimesTotal += $game_info_list[$key]["winTimes"];
                $lostTimesTotal += $game_info_list[$key]["lostTimes"];
                $drawTimesTotal += $game_info_list[$key]["drawTimes"];

                $allBetTotal += $game_info_list[$key]["allBet"];
                $winScoreTotal += $game_info_list[$key]["winScore"];
                $platformWinScoreTotal += $game_info_list[$key]["platformWinScore"];
                $revenueTotal += $game_info_list[$key]["revenue"];

//        $playTimeTotal += $game_info_list[$key]["playTime"];

                $ptIncomeTotal += $game_info_list[$key]["ptIncome"];
            }

            foreach ($game_info_list as $key => $value) {
                $game_info_list[$key]["allBet"] = round($game_info_list[$key]["allBet"], 2);
                $game_info_list[$key]["winScore"] = round($game_info_list[$key]["winScore"], 2);
                $game_info_list[$key]["revenue"] = round($game_info_list[$key]["revenue"], 2);
                $game_info_list[$key]["platformWinScore"] = round($game_info_list[$key]["platformWinScore"], 2);
                $game_info_list[$key]["ptIncome"] = round($game_info_list[$key]["ptIncome"], 2);


                if ($game_info_list[$key]["lostTimes"]) {
                    $game_info_list[$key]["lostWinRate"] = round($game_info_list[$key]["winTimes"] / $game_info_list[$key]["lostTimes"], 2);
                } else {
                    $game_info_list[$key]["lostWinRate"] = $game_info_list[$key]["winTimes"];
                }

                if ($ptIncomeTotal) {
                    if ($game_info_list[$key]["ptIncome"] > 0) {
                        $game_info_list[$key]["ptIncomeRate"] = round($game_info_list[$key]["ptIncome"] / abs($ptIncomeTotal), 2);
                    } else {
                        $game_info_list[$key]["ptIncomeRate"] = 0;
                    }
                } else {
                    $game_info_list[$key]["ptIncomeRate"] = 0;
                }
            }

            $tmparr = array(
                "pid" => 0,
                "id" => 999999,
                "title" => "总计",

                "onlineCount" => $onlineCountTotal,
                "userCount" => $userCountTotal,
                "gameRound" => $gameRoundTotal,

                "winTimes" => $winTimesTotal,
                "lostTimes" => $lostTimesTotal,
                "drawTimes" => $drawTimesTotal,
                "lostWinRate" => $lostTimesTotal ? round($winTimesTotal / $lostTimesTotal, 2) : 0,

                "allBet" => round($allBetTotal, 2),
                "winScore" => round($winScoreTotal, 2),//$winScoreTotal,
                "platformWinScore" => round($platformWinScoreTotal, 2),
                "revenue" => round($revenueTotal, 2),

//        "playTime" => $playTimeTotal,

//        "earnScore" => $onlineCountTotal,
                //"ptIncome" => round(($ptIncomeTotal)*0.01, 2),
                "ptIncome" => round($ptIncomeTotal, 2),
                "ptIncomeRate" => '/',
            );

            array_push($game_info_list, $tmparr);
            return json(['code' => 0, 'msg' => 'ok', 'count' => 0, 'data' => $game_info_list]);
        }

        $getData = check_type($request->all());
        extract($getData);
        $startDate = !empty($startDate) ? trim($startDate) : "";
        $endDate = !empty($endDate) ? trim($endDate) : "";
        $userId = !empty($userId) ? (int)$userId : '';

        $assignData = [
            'orderType' => ['leaveTime' => '结算时间', 'earnScore' => '平台营收', 'revenue' => '平台税收'],
            'gameList' => GameKind::where(['status' => 1, 'rooms.status' => 1])->orderBy('sort', 'asc')->get()->toArray(),
            'startDate'=>$startDate,
            'endDate'=>$endDate,
            'userId'=>$userId
        ];
        //dd($assignData['gameList']);
        return view('player/incomeStat/list', $assignData);
    }

    public function smsList(Request $request)
    {
        if ($request->isAjax()) {
            $phone = trim($request->get('phone'));
            $where = [];
            if(!empty($phone)) {
                $where[] = ['phone', 'regex', new \MongoDB\BSON\Regex($phone, 'i')];
            }
            $count = SmsSend::where($where)->count();
            $list = SmsSend::where($where)->orderBy('createTime', 'desc')->skip($request->skip)->take($request->limit)->get();
            foreach ($list as &$item) {
                $item['createTime'] = $this->formatDate($item['createTime']);
            }

            return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $list]);
        }
        return view('player/sms/list', ['name' => '']);
    }

    public function playerInfo(Request $request)
    {
        $userId = $request->get('userId', 0);
        if (empty($userId)) return json(['code' => -1, 'msg' => '会员帐号不存在']);
        $assignData = [
            'userId' => $userId,
            'groupId' => session('groupId'),
        ];

        return view('player/playerInfo/list', $assignData);
    }

    public function playerDetails(Request $request)
    {
        $userId = (int)$request->post('userId', 0);
        if (!empty($userId)){
            $where = ['userId' => $userId];
            $user = static::getPlayer($where);
            if(!empty($user)) $user = $user->toArray();
            if (!empty($user['promoterId'])) {
                $promoter = Agent::getPromoter(['promoterId' => $user['promoterId']], ['promoterId', 'promoterName']);
            }
            $user['promoterName'] = $promoter->promoterName ?? '';
            $user['regInfo']['time'] = $this->formatDate($user['regInfo']['time']);
            $user['lastLogin']['time'] = $this->formatDate($user['lastLogin']['time']??0);
            $user['score'] = $this->formatMoneyFromMongo($user['score']);
            $user['bankScore'] = $this->formatMoneyFromMongo($user['bankScore']);
            $user['rechargeAmount'] = $this->formatMoneyFromMongo($user['rechargeAmount']);
            $user['exchangeAmount'] = $this->formatMoneyFromMongo($user['exchangeAmount']);
            $user['winScore'] = $this->formatMoneyFromMongo($user['winScore']);
            $clubWinScore = !empty($user['clubWinScore']) ? $user['clubWinScore'] : 0;
            $user['clubWinScore'] = $this->formatMoneyFromMongo($clubWinScore);
            $user['revenue'] = $this->formatMoneyFromMongo($user['revenue']);
            //游戏时长
            $totalOnlineGameTime = (int)$user['totalOnlineGameTime'];
            $user['totalOnlineGameTime'] = Sec2Time($totalOnlineGameTime);

            if(!empty($user['roomCard'])){
                $user['roomCard'] = $this->formatMoneyFromMongo($user['roomCard']);
            }else{
                $user['roomCard'] = 0;
            }
            if (!empty($user['alipay']['alipayAccount'])) $user['alipay']['alipayAccount'] = hidestr($user['alipay']['alipayAccount'],3,4);
            if (!empty($user["usdt"]["address"])) $user["usdt"]["address"] = hidestr($user["usdt"]["address"],3,6);
            if (!empty($user["bank"]["bankCardNum"])) $user["bank"]["bankCardNum"] = hidestr($user["bank"]["bankCardNum"],4,8);
            if (!empty($user['mobile'])) {
                if (session('userName') == "admin") {
                    $user['mobile'] = mobile_de($user['mobile']);
                } else {
                    $user['mobile'] = mobileShow(mobile_de($user['mobile']));
                }
            }

            $user['bank']['bankName'] = $user['bank']['bankName']??'';
            $assignData = [
                'formData' => json_encode($user),
            ];

            return view('player/playerDetails/edit', $assignData);
        }
    }

    public function playerDetailsEditSave(Request $request)
    {
        if($request->isAjax()) {
            $postData = check_type($request->post());
            extract($postData);
            if (!$field || !$_id) {
                return json(['code' => -1, 'msg' => '参数错误']);
            }
            $user = static::getPlayer(['_id' => $_id]);
            if (empty($user)) return json(['code' => -1, 'msg' => '会员不存在']);

            //多值操作
            if ($field == 'alipayName_alipayAccount'){
                $field = explode("_", $field);
                $value = explode(",", $value);
                if (count($field) != 2 || count($value) != 2) return json(['code' => -1, 'msg' => '格式错误']);
                $alipay = $user->alipay;
                $alipay['alipayName'] = $value[0];
                $alipay['alipayAccount'] = $value[1];
                $user->alipay = $alipay;
                if ($user->save()) return json(['code' => 0, 'msg' => '操作成功']);
                return json(['code' => -1, 'msg' => '操作失败']);
            } elseif ($field == 'bankName_bankCardName_bankCardNum'){
                $field = explode("_", $field);
                $value = explode(",", $value);
                if (count($field) != 3 || count($value) != 3) return json(['code' => -1, 'msg' => '格式错误']);
                $bank = $user->bank;
                $bank['bankName'] = $value[0];
                $bank['bankCardName'] = $value[1];
                $bank['bankCardNum'] = $value[2];
                $user->bank = $bank;
                if ($user->save()) return json(['code' => 0, 'msg' => '操作成功']);
                return json(['code' => -1, 'msg' => '操作失败']);
            } elseif ($field == 'address') {
                $usdt = $user->usdt;
                $usdt['address'] = $value;
                $user->usdt = $usdt;
                if ($user->save()) return json(['code' => 0, 'msg' => '操作成功']);
                return json(['code' => -1, 'msg' => '操作失败']);
            }

            //单值操作
            if ($field == 'nickName') {
                $userCheck = static::getPlayer(['nickName' => $value], ['userId']);
                if (mb_strlen($value) > 6) return json(['code' => -1, 'msg' => '昵称不能超过6个字符']);
                if (!empty($userCheck)) return json(['code' => -1, 'msg' => '该昵称已被使用']);
                $user->nickName = $value;
            } elseif ($field == 'trueName') {
                $userCheck = static::getPlayer(['trueName' => $value], ['userId']);
                if (!empty($userCheck)) return json(['code' => -1, 'msg' => '该昵称已被使用']);
                $user->trueName = $value;
            } elseif ($field == 'password') {
                $value = strtoupper(md5(strtoupper(md5($value)).$user->salt));
                $user->password = $value;
            } elseif ($field == 'promoterId') {
                $sonArr = [];
                $value = (int)$value;
                //不能和userId相等
                if($user['userId'] == $value) return json(['code' => -1, 'msg' => '用户ID和代理ID不能相等']);
                //查询该用户是否有子用户(不能成环)
                $sonData = $this->getSonData($user['userId']);
                //if(!empty($sonData['code'])) return json(['code' => -1, 'msg' => '该玩家有下级代理,请手动修改']);
                if(in_array($value,$sonData)){
                    return json(['code' => -1, 'msg' => '不可以用子代理ID']);
                }
                //检测是否存在
                $promoter = Agent::getPromoter(['promoterId' => $value], ['promoterId']);
                if (empty($promoter)) return json(['code' => -1, 'msg' => '代理不存在']);

                $user->promoterId = $value;
            } elseif($field == 'status') {
                $value = (int)$value;
                $user->status = $value;
            } elseif($field == 'mobile') {
                if (strlen($value) != 11) return json(['code' => -1, 'msg' => '手机号码格式错误']);
                $value = mobile_en($value);
                if(empty($value)) return json(['code' => -1, 'msg' => '加密错误']);
                $userCheck = static::getPlayer(['mobile' => $value], ['userId']);
                if (!empty($userCheck)) return json(['code' => -1, 'msg' => '该手机号已被使用']);
                $user->mobile = $value;
            }

            if ($user->save()) {
                if ($field == 'status') {
                    $updateResult = Promoter::where(['promoterId' => $user->userId])->update(['status' => $value]);
                    //if (!$updateResult) return json(['code' => -1, 'msg' => '代理状态更新失败']);

                } elseif ($field == 'promoterId') {
                    $startDate = date("Y-m-d");
                    $endDate = date("Y-m-d");
                    $startTime = strtotime($startDate);
                    $endTime = strtotime("$endDate +1 day");
                    if ($startTime >= $endTime) return json(['code' => -1, 'msg' => '请核对开始时间结束时间']);
                    $where1[] = ['createTime', '>=', $this->formatTimestampToMongo($startTime)];
                    $where1[] = ['createTime', '<', $this->formatTimestampToMongo($endTime)];
                    $where1['userId'] = $user->userId;

                    $where2[] = ['startTime', '>=', $this->formatTimestampToMongo($startTime)];
                    $where2[] = ['startTime', '<', $this->formatTimestampToMongo($endTime)];
                    $where2['userId'] = $user->userId;

                    $updateResult = Promoter::where(['promoterId' => $user->userId])->update(['pid' => $value]);
                    $updateResult = RechargeOrder::where($where1)->update(['promoterId' => $value]);
                    $updateResult = ExchangeOrder::where($where1)->update(['promoterId' => $value]);
                    $updateResult = PlayRecord::where($where2)->update(['promoterId' => $value]);
                    $updateResult = RewardOrder::where($where1)->update(['promoterId' => $value]);
                    
//                    $updateResult = Promoter::where(['promoterId' => $user->userId])->update(['pid' => $value]);
//                    $updateResult = RechargeOrder::where(['userId' => $user->userId])->update(['promoterId' => $value]);
//                    $updateResult = ExchangeOrder::where(['userId' => $user->userId])->update(['promoterId' => $value]);
                    $this->adminLog(["content"=>"修改代理".$user->userId."的pid为【".$value."】"]);
                }
                return json(['code' => 0, 'msg' => '操作成功']);
            } else {
                return json(['code' => -1, 'msg' => '操作失败']);
            }
        }

    }

    private function _playerScoreChangeAjaxParam()
    {
        $where = [];
        $request = request();
        $getData = check_type($request->all());
        extract($getData);
        if (empty($startDate)) {
            $startDate = date("Y-m-d");
        }
        $startTime = strtotime($startDate);
        if (empty($endDate)) {
            $endDate = date("Y-m-d");
            $endTime = strtotime("$endDate +1 day");
        }else{
            $endTime = strtotime($endDate);
        }
        if ($startTime >= $endTime) return json(['code' => -1, 'msg' => '请核对开始时间结束时间']);
        $where[] = ['createTime', '>=', $this->formatTimestampToMongo($startTime)];
        $where[] = ['createTime', '<', $this->formatTimestampToMongo($endTime)];

        if (!empty($userId)) {
            $where['userId'] = $userId;
        }
        return $where;
    }

    public function playerScoreChange(Request $request)
    {
        if ($request->method() == 'GET') {
            $where = $this->_playerScoreChangeAjaxParam();
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
        if ($request->method() == 'POST') {
            return view('player/playerScoreChange/list', ['name' => '']);
        }
        return view('player/playerScoreChange/list', ['name' => '']);
    }

    private function _playerScoreChangeAjaxParamClub()
    {
        $where = [];
        $request = request();
        $getData = check_type($request->all());
        extract($getData);
        if (empty($startDate)) {
            $startDate = date("Y-m-d");
        }
        $startTime = strtotime($startDate);
        if (empty($endDate)) {
            $endDate = date("Y-m-d");
            $endTime = strtotime("$endDate +1 day");
        }else{
            $endTime = strtotime($endDate);
        }


        if ($startTime >= $endTime) return json(['code' => -1, 'msg' => '请核对开始时间结束时间']);
        $where[] = ['createTime', '>=', $this->formatTimestampToMongo($startTime)];
        $where[] = ['createTime', '<', $this->formatTimestampToMongo($endTime)];

        if (!empty($userId)) {
            $where['userId'] = $userId;
        }
        return $where;
    }

    public function playerScoreChangeClub(Request $request)
    {
        if ($request->method() == 'GET') {
            $where = $this->_playerScoreChangeAjaxParamClub();
            if (!is_array($where)) return $where;
            $count = ClubUserScoreChange::where($where)->count();
            $list = ClubUserScoreChange::where($where)->orderBy('createTime', 'desc')->skip($request->skip)->take($request->limit)->get();
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
        if ($request->method() == 'POST') {
            return view('player/clubPlayerScoreChange/list', ['name' => '']);
        }
        return view('player/clubPlayerScoreChange/list', ['name' => '']);
    }

    private function _roomCardChangeAjaxParam()
    {
        $where = [];
        $request = request();
        $getData = check_type($request->all());
        extract($getData);
        if (empty($startDate)) {
            $startDate = date("Y-m-d");
        }
        $startTime = strtotime($startDate);
        if (empty($endDate)) {
            $endDate = date("Y-m-d");
            $endTime = strtotime("$endDate +1 day");
        }else{
            $endTime = strtotime($endDate);
        }


        if ($startTime >= $endTime) return json(['code' => -1, 'msg' => '请核对开始时间结束时间']);
        $where[] = ['createTime', '>=', $this->formatTimestampToMongo($startTime)];
        $where[] = ['createTime', '<', $this->formatTimestampToMongo($endTime)];

        if (!empty($userId)) {
            $where['userId'] = $userId;
        }
        return $where;
    }

    public function roomCardChange(Request $request)
    {
        if ($request->method() == 'GET') {
            $where = $this->_roomCardChangeAjaxParam();
            if (!is_array($where)) return $where;
            $count = RoomCardChange::where($where)->count();
            $list = RoomCardChange::where($where)->orderBy('createTime', 'desc')->skip($request->skip)->take($request->limit)->get();



            if (!empty($list)) {
                foreach ($list as &$item) {
                    $item['createTime'] = $this->formatDate($item['createTime']);
                    $item['beforeRoomCard'] = $this->formatMoneyFromMongo($item['beforeRoomCard']);
                    $item['addRoomCard'] = $this->formatMoneyFromMongo($item['addRoomCard']);
                    $item['afterRoomCard'] = $this->formatMoneyFromMongo($item['afterRoomCard']);
                    $item['changeTypeName'] = resultChangeType($item['changeType']);
                    //otherInfo
                    $item['otherStr'] = "/";
                    //toWhere
                    $item['toWhere'] = "/";
                    if($item['changeType'] == 13){
                        if($item['anotherInfo'] == 1){
                            $item['otherStr'] = "普通商城购买";
                        }elseif ($item['anotherInfo'] == 2){
                            $item['otherStr'] = "代理购买";
                        }
                    }elseif($item['changeType'] == 15){
                        $item['otherStr'] = "接收房卡会员ID为:".$item['anotherInfo'];
                    }elseif($item['changeType'] == 14){
                        $item['otherStr'] = "转出房卡会员ID为:".$item['anotherInfo'];
                    }elseif($item['changeType'] == 16){
                        if(!empty($item['anotherInfo'])){
                            $gameKindArr = static::gameListByRoom($item['anotherInfo']);
                            $item['toWhere'] = $gameKindArr[0]['gameName'];
                        }
                    }



                    $item['remark'] = $item['remark']??'';
                }
            }
            return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $list]);
        }
        if ($request->method() == 'POST') {
            return view('player/roomCardChange/list', ['name' => '']);
        }
        return view('player/roomCardChange/list', ['name' => '']);
    }

    public function roomCardChangeSummary(Request $request)
    {
        if($request->isAjax()) {
            $where = $this->_roomCardChangeAjaxParam();
            if (!is_array($where)) return $where;
            $addRoomCardSum = RoomCardChange::where($where)->sum('addRoomCard');
            $addRoomCardSum = $this->formatMoneyFromMongo($addRoomCardSum);
            return json(['code' => 0, 'msg' => 'ok', 'data' => ['addRoomCardSum' => $addRoomCardSum]]);
        }
    }

    public function playerScoreChangeSummary(Request $request)
    {
        if($request->isAjax()) {
            $where = $this->_playerScoreChangeAjaxParam();
            if (!is_array($where)) return $where;
            //$beforeScoreSum = UserScoreChange::where($where)->sum('beforeScore');
            //$beforeScoreSum = $this->formatMoneyFromMongo($beforeScoreSum);
            $addScoreSum = UserScoreChange::where($where)->sum('addScore');
            $addScoreSum = $this->formatMoneyFromMongo($addScoreSum);
            //$afterScoreSum = UserScoreChange::where($where)->sum('afterScore');
            //$afterScoreSum = $this->formatMoneyFromMongo($afterScoreSum);

            $beforeBankScoreSum = UserScoreChange::where($where)->sum('beforeBankScore');
            $beforeBankScoreSum = $this->formatMoneyFromMongo($beforeBankScoreSum);
            $addBankScoreSum = UserScoreChange::where($where)->sum('addBankScore');
            $addBankScoreSum = $this->formatMoneyFromMongo($addBankScoreSum);
            $afterBankScoreSum = UserScoreChange::where($where)->sum('afterBankScore');
            $afterBankScoreSum = $this->formatMoneyFromMongo($afterBankScoreSum);

            return json(['code' => 0, 'msg' => 'ok', 'data' => ['beforeBankScoreSum' => $beforeBankScoreSum, 'addBankScoreSum' => $addBankScoreSum, 'afterBankScoreSum' => $afterBankScoreSum, 'addScoreSum' => $addScoreSum]]);
        }
    }

    private function _playerRechargeRecordAjaxParam()
    {
        $where = [];
        $request = request();
        $getData = check_type($request->all());
        extract($getData);
        if (empty($startDate)) {
            $startDate = date("Y-m-d");
        }
        $startTime = strtotime($startDate);
        if (empty($endDate)) {
            $endDate = date("Y-m-d");
            $endTime = strtotime("$endDate +1 day");
        }else{
            $endTime = strtotime($endDate);
        }


        if ($startTime >= $endTime) return json(['code' => -1, 'msg' => '请核对开始时间结束时间']);
        $where[] = ['createTime', '>=', $this->formatTimestampToMongo($startTime)];
        $where[] = ['createTime', '<', $this->formatTimestampToMongo($endTime)];

        if (!empty($userId)) {
            $where['userId'] = $userId;
        }
        return $where;
    }

    public function playerRechargeRecord(Request $request)
    {
        if ($request->method() == 'GET') {
            $where = $this->_playerScoreChangeAjaxParam();
            if (!is_array($where)) return $where;
            $count = RechargeOrder::where($where)->count();
            $list = RechargeOrder::where($where)->orderBy('createTime', 'desc')->skip($request->skip)->take($request->limit)->get();
            if (!empty($list)) {
                $rechargeTypeIdNameKV = RechargeType::rechargeTypeIdNameKV();
                foreach ($list as &$item) {
                    $item['createTime'] = $this->formatDate($item['createTime']);
                    $item['applyTime'] = $this->formatDate($item['applyTime']);

                    $item['requestMoney'] = $this->formatMoneyFromMongo($item['requestMoney']);
                    $item['rechargeMoney'] = $this->formatMoneyFromMongo($item['rechargeMoney']);

                    $item['changeTypeName'] = $rechargeTypeIdNameKV[$item['rechargeTypeId']]??'';
                    $item['remark'] = $item['remark']??'';
                }
            }
            return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $list]);
        }
        if ($request->method() == 'POST') {
            return view('player/playerRechargeRecord/list', ['name' => '']);
        }
        return view('player/playerRechargeRecord/list', ['name' => '']);
    }

    public function playerRechargeRecordSummary(Request $request)
    {
        if($request->isAjax()) {
            $where = $this->_playerScoreChangeAjaxParam();
            if (!is_array($where)) return $where;
            $where['status'] = RechargeOrder::ORDER_STATUS_FINISH;
            $requestMoneySum = RechargeOrder::where($where)->sum('requestMoney');
            $requestMoneySum = $this->formatMoneyFromMongo($requestMoneySum);
            $rechargeMoneySum = RechargeOrder::where($where)->sum('rechargeMoney');
            $rechargeMoneySum = $this->formatMoneyFromMongo($rechargeMoneySum);

            return json(['code' => 0, 'msg' => 'ok', 'data' => ['rechargeRequestMoneySum' => $requestMoneySum, 'rechargeRechargeMoneySum' => $rechargeMoneySum]]);
        }
    }

    private function _playerRewardOrderAjaxParam()
    {
        $where = [];
        $request = request();
        $getData = check_type($request->all());
        extract($getData);
        if (empty($startDate)) {
            $startDate = date("Y-m-d");
        }
        $startTime = strtotime($startDate);
        if (empty($endDate)) {
            $endDate = date("Y-m-d");
            $endTime = strtotime("$endDate +1 day");
        }else{
            $endTime = strtotime($endDate);
        }


        if ($startTime >= $endTime) return json(['code' => -1, 'msg' => '请核对开始时间结束时间']);
        $where[] = ['createTime', '>=', $this->formatTimestampToMongo($startTime)];
        $where[] = ['createTime', '<', $this->formatTimestampToMongo($endTime)];

        if (!empty($userId)) {
            $where['userId'] = $userId;
        }
        return $where;
    }

    public function playerRewardOrder(Request $request)
    {
        if ($request->method() == 'GET') {
            $where = $this->_playerScoreChangeAjaxParam();
            if (!is_array($where)) return $where;
            $count = RewardOrder::where($where)->count();
            $list = RewardOrder::where($where)->orderBy('createTime', 'desc')->skip($request->skip)->take($request->limit)->get()->toArray();
            if (!empty($list)) {
                $taskConfigList = TaskConfig::taskConfigIdTitleList();
                $list = merge_array($list, $taskConfigList, 'taskId', 'Id');
                $activityList = Activity::activityIdTitleList();
                $list = merge_array($list, $activityList, 'activityId');
                foreach ($list as &$item) {
                    $item['createTime'] = $this->formatDate($item['createTime']);

                    $item['requestMoney'] = $this->formatMoneyFromMongo($item['requestMoney']);
                    $item['rechargeMoney'] = $this->formatMoneyFromMongo($item['rechargeMoney']);

                    $item['rewardTypeName'] = resultChangeType($item['rewardType']);
                    //$item['remark'] = $item['remark']??'';
                }
            }
            return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $list]);
        }
        if ($request->method() == 'POST') {
            return view('player/playerRewardOrder/list', ['name' => '']);
        }
        return view('player/playerRewardOrder/list', ['name' => '']);
    }

    private function _playerClubRewardOrderAjaxParam()
    {
        $where = [];
        $request = request();
        $getData = check_type($request->all());
        extract($getData);
        if (empty($startDate)) {
            $startDate = date("Y-m-d");
        }
        $startTime = strtotime($startDate);
        if (empty($endDate)) {
            $endDate = date("Y-m-d");
            $endTime = strtotime("$endDate +1 day");
        }else{
            $endTime = strtotime($endDate);
        }
        if ($startTime >= $endTime) return json(['code' => -1, 'msg' => '请核对开始时间结束时间']);
        $where[] = ['createTime', '>=', $this->formatTimestampToMongo($startTime)];
        $where[] = ['createTime', '<', $this->formatTimestampToMongo($endTime)];

        if (!empty($userId)) {
            $where['userId'] = $userId;
        }
        return $where;
    }

    public function playerClubRewardOrder(Request $request)
    {
        if ($request->method() == 'GET') {
            $where = $this->_playerClubRewardOrderAjaxParam();
            if (!is_array($where)) return $where;
            $count = ClubRewardOrder::where($where)->count();
            $list = ClubRewardOrder::where($where)->orderBy('createTime', 'desc')->skip($request->skip)->take($request->limit)->get()->toArray();
            if (!empty($list)) {
                //$taskConfigList = TaskConfig::taskConfigIdTitleList();
                //$list = merge_array($list, $taskConfigList, 'taskId', 'Id');
                //$activityList = Activity::activityIdTitleList();
                //$list = merge_array($list, $activityList, 'activityId');
                foreach ($list as &$item) {
                    $item['createTime'] = $this->formatDate($item['createTime']);
                    $item['requestMoney'] = $this->formatMoneyFromMongo($item['requestMoney']);
                    $item['rechargeMoney'] = $this->formatMoneyFromMongo($item['rechargeMoney']);
                    $item['rewardTypeName'] = resultChangeType($item['activityType']);
                }
            }
            return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $list]);
        }
        if ($request->method() == 'POST') {
            return view('player/playerClubRewardOrder/list', ['name' => '']);
        }
        return view('player/playerClubRewardOrder/list', ['name' => '']);
    }

    public function playerRewardOrderSummary(Request $request)
    {
        if($request->isAjax()) {
            $where = $this->_playerRewardOrderAjaxParam();
            if (!is_array($where)) return $where;
            $requestMoneySum = RewardOrder::where($where)->sum('requestMoney');
            $requestMoneySum = $this->formatMoneyFromMongo($requestMoneySum);
            $rechargeMoneySum = RewardOrder::where($where)->sum('rechargeMoney');
            $rechargeMoneySum = $this->formatMoneyFromMongo($rechargeMoneySum);

            return json(['code' => 0, 'msg' => 'ok', 'data' => ['rewardRequestMoneySum' => $requestMoneySum, 'rewardRechargeMoneySum' => $rechargeMoneySum]]);
        }
    }

    public function playerClubRewardOrderSummary(Request $request)
    {
        if($request->isAjax()) {
            $where = $this->_playerRewardOrderAjaxParam();
            if (!is_array($where)) return $where;
            $requestMoneySum = ClubRewardOrder::where($where)->sum('requestMoney');
            $requestMoneySum = $this->formatMoneyFromMongo($requestMoneySum);
            $rechargeMoneySum = ClubRewardOrder::where($where)->sum('rechargeMoney');
            $rechargeMoneySum = $this->formatMoneyFromMongo($rechargeMoneySum);

            return json(['code' => 0, 'msg' => 'ok', 'data' => ['rewardRequestMoneySum' => $requestMoneySum, 'rewardRechargeMoneySum' => $rechargeMoneySum]]);
        }
    }


    private function _playerExchangeRecordAjaxParam()
    {
        $where = [];
        $request = request();
        $getData = check_type($request->all());
        extract($getData);
        if (empty($startDate)) {
            $startDate = date("Y-m-d");
        }
        $startTime = strtotime($startDate);
        if (empty($endDate)) {
            $endDate = date("Y-m-d");
            $endTime = strtotime("$endDate +1 day");
        }else{
            $endTime = strtotime($endDate);
        }

        if ($startTime >= $endTime) return json(['code' => -1, 'msg' => '请核对开始时间结束时间']);
        $where[] = ['createTime', '>=', $this->formatTimestampToMongo($startTime)];
        $where[] = ['createTime', '<', $this->formatTimestampToMongo($endTime)];

        if (!empty($userId)) {
            $where['userId'] = $userId;
        }
        return $where;
    }

    public function playerExchangeRecord(Request $request)
    {
        if ($request->method() == 'GET') {
            $where = $this->_playerExchangeRecordAjaxParam();
            if (!is_array($where)) return $where;
            $count = ExchangeOrder::where($where)->count();
            $list = ExchangeOrder::where($where)->orderBy('createTime', 'desc')->skip($request->skip)->take($request->limit)->get()->toArray();
            if (!empty($list)) {
                $userIdArr = array_column($list, 'userId'); $userIdArr = array_unique($userIdArr);
                $userList = GameUser::whereIn('userId', $userIdArr)->select('userId','score','bankScore','rechargeAmount','exchangeAmount','regInfo','trueName','nickName')->get()->toArray();
                $list = merge_array($list, $userList, 'userId');
                foreach ($list as &$item) {
                    $item['createTime'] = $this->formatDate($item['createTime']);
                    if ($item['applyTime'] == '0') {
                        $item['applyTime'] = "暂未处理";
                    } else {
                        $item['applyTime'] = $this->formatDate($item['applyTime']);
                    }

                    $item['requestMoney'] = $this->formatMoneyFromMongo($item['requestMoney']);
                    $item['payMoney'] = $this->formatMoneyFromMongo($item['payMoney']);

                    //$item['rewardTypeName'] = resultChangeType($item['rewardType']);
                    //$item['remark'] = $item['remark']??'';
                }
            }
            return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $list]);
        }
        if ($request->method() == 'POST') {
            return view('player/playerExchangeRecord/list', ['name' => '']);
        }
        return view('player/playerExchangeRecord/list', ['name' => '']);
    }

    public function playerExchangeRecordSummary(Request $request)
    {
        if($request->isAjax()) {
            $where = $this->_playerExchangeRecordAjaxParam();
            if (!is_array($where)) return $where;
            $where['status'] = ExchangeOrder::ORDER_STATUS_EXCHANGE_SUCCESS;
            $requestMoneySum = ExchangeOrder::where($where)->sum('requestMoney');
            $requestMoneySum = $this->formatMoneyFromMongo($requestMoneySum);
            $payMoneySum = ExchangeOrder::where($where)->sum('payMoney');
            $payMoneySum = $this->formatMoneyFromMongo($payMoneySum);

            return json(['code' => 0, 'msg' => 'ok', 'data' => ['exchangeRequestMoneySum' => $requestMoneySum, 'exchangePayMoneySum' => $payMoneySum]]);
        }
    }

    private function _playerGameRecordAjaxParam()
    {
        $where = [];
        $request = request();
        $getData = check_type($request->all());
        extract($getData);
        if (empty($startDate)) {
            $startDate = date("Y-m-d");
        }
        $startTime = strtotime($startDate);
        if (empty($endDate)) {
            $endDate = date("Y-m-d");
            $endTime = strtotime("$endDate +1 day");
        }else{
            $endTime = strtotime($endDate);
        }
        if ($startTime >= $endTime) return json(['code' => -1, 'msg' => '请核对开始时间结束时间']);
        $where[] = ['endTime', '>=', $this->formatTimestampToMongo($startTime)];
        $where[] = ['endTime', '<', $this->formatTimestampToMongo($endTime)];

        if (!empty($userId)) {
            $where['userId'] = $userId;
        }
        return $where;
    }

    public function playerGameRecord(Request $request)
    {
        if ($request->method() == 'GET') {
            $where = $this->_playerGameRecordAjaxParam();
            if (!is_array($where)) return $where;
            $count = PlayRecord::where($where)->count();
            $list = PlayRecord::where($where)->orderBy('endTime', 'desc')->skip($request->skip)->take($request->limit)->get()->toArray();
            if (!empty($list)) {
                $gameRoomInfo = getGameRoomInfo();
                $list = merge_array($list, $gameRoomInfo, 'roomId');
                foreach ($list as &$item) {
                    $item['playTime'] = $this->diffTime($item['startTime'], $item['endTime'], '%I:%S');
                    $item['endTime'] = $this->formatDate($item['endTime']);
                    $item['allBet'] = $this->formatMoneyFromMongo($item['allBet']);
                    $item['winScore'] = $this->formatMoneyFromMongo($item['winScore']);
                    $item['revenue'] = $this->formatMoneyFromMongo($item['revenue']);

                }
            }
            return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $list]);
        }
        if ($request->method() == 'POST') {
            return view('player/playerGameRecord/list', ['name' => '']);
        }
        return view('player/playerGameRecord/list', ['name' => '']);
    }

    private function _playerGameRecordAjaxParamClub()
    {
        $where = [];
        $request = request();
        $getData = check_type($request->all());
        extract($getData);
        if (empty($startDate)) {
            $startDate = date("Y-m-d");
        }
        $startTime = strtotime($startDate);
        if (empty($endDate)) {
            $endDate = date("Y-m-d");
            $endTime = strtotime("$endDate +1 day");
        }else{
            $endTime = strtotime($endDate);
        }


        if ($startTime >= $endTime) return json(['code' => -1, 'msg' => '请核对开始时间结束时间']);
        $where[] = ['endTime', '>=', $this->formatTimestampToMongo($startTime)];
        $where[] = ['endTime', '<', $this->formatTimestampToMongo($endTime)];

        if (!empty($userId)) {
            $where['userId'] = $userId;
        }
        return $where;
    }

    public function playerGameRecordClub(Request $request)
    {
        if ($request->method() == 'GET') {
            $where = $this->_playerGameRecordAjaxParamClub();
            if (!is_array($where)) return $where;
            $count = ClubPlayRecord::where($where)->count();
            $list = ClubPlayRecord::where($where)->orderBy('endTime', 'desc')->skip($request->skip)->take($request->limit)->get()->toArray();
            if (!empty($list)) {
                $gameRoomInfo = getGameRoomInfo();
                $list = merge_array($list, $gameRoomInfo, 'roomId');
                foreach ($list as &$item) {
                    $item['playTime'] = $this->diffTime($item['startTime'], $item['endTime'], '%I:%S');
                    $item['endTime'] = $this->formatDate($item['endTime']);
                    $item['allBet'] = $this->formatMoneyFromMongo($item['allBet']);
                    $item['winScore'] = $this->formatMoneyFromMongo($item['winScore']);
                    $item['revenue'] = $this->formatMoneyFromMongo($item['revenue']);

                }
            }
            dd($list);
            return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $list]);
        }
        if ($request->method() == 'POST') {
            return view('player/playerGameRecordClub/list', ['name' => '']);
        }
        return view('player/playerGameRecordClub/list', ['name' => '']);
    }

    public function playerGameRecordSummary(Request $request)
    {
        if($request->isAjax()) {
            $where = $this->_playerGameRecordAjaxParam();
            if (!is_array($where)) return $where;
            $allBetSum = PlayRecord::where($where)->sum('allBet');
            $allBetSum = $this->formatMoneyFromMongo($allBetSum);

            $validBetSum = PlayRecord::where($where)->sum('validBet');
            $validBetSum = $this->formatMoneyFromMongo($validBetSum);

            $winScoreSum = PlayRecord::where($where)->sum('winScore');
            $winScoreSum = $this->formatMoneyFromMongo($winScoreSum);
            $revenueSum = PlayRecord::where($where)->sum('revenue');
            $revenueSum = $this->formatMoneyFromMongo($revenueSum);

            return json(['code' => 0, 'msg' => 'ok', 'data' => ['allBetSum' => $allBetSum, 'validBetSum' => $validBetSum, 'winScoreSum' => $winScoreSum, 'revenueSum' => $revenueSum]]);
        }
    }

    public function playerGameRecordSummaryClub(Request $request)
    {
        if($request->isAjax()) {
            $where = $this->_playerGameRecordAjaxParamClub();
            if (!is_array($where)) return $where;
            $allBetSum = ClubPlayRecord::where($where)->sum('allBet');
            $allBetSum = $this->formatMoneyFromMongo($allBetSum);
            $winScoreSum = ClubPlayRecord::where($where)->sum('winScore');
            $winScoreSum = $this->formatMoneyFromMongo($winScoreSum);
            $revenueSum = ClubPlayRecord::where($where)->sum('revenue');
            $revenueSum = $this->formatMoneyFromMongo($revenueSum);

            return json(['code' => 0, 'msg' => 'ok', 'data' => ['allBetSum' => $allBetSum, 'winScoreSum' => $winScoreSum, 'revenueSum' => $revenueSum]]);
        }
    }

    private function _playerEverydayWinLoseAjaxParam()
    {
        $where = [];
        $request = request();
        $getData = check_type($request->all());
        extract($getData);
        if (empty($startDate)) {
            $startDate = date("Y-m-d");
        }
        $startTime = strtotime($startDate);
        if (empty($endDate)) {
            $endDate = date("Y-m-d");
            $endTime = strtotime("$endDate +1 day");
        }else{
            $endTime = strtotime($endDate);
        }


        if ($startTime >= $endTime) return json(['code' => -1, 'msg' => '请核对开始时间结束时间']);
        $where['endTime'] = ['$gte' => $this->formatTimestampToMongo($startTime), '$lt' =>$this->formatTimestampToMongo($endTime)];

        if (!empty($userId)) {
            $where['userId'] = $userId;
        }
        return $where;
    }

    public function playerEverydayWinLose(Request $request)
    {
        if ($request->method() == 'GET') {
            $where = $this->_playerEverydayWinLoseAjaxParam();
            if (!is_array($where)) return $where;
            $list = Db::connection('mongodb_main')->collection('play_record')->raw()->aggregate([
                [
                    '$match' => $where
                ],
                [
                    '$project' =>
                        [
                            'winScore' => 1,
                            'revenue' => 1,
                            //'Day' => ['$substr' => ['$endTime', 0, 10]],
                            'Day' => ['$substr' => [['$add' => ['$endTime', 28800000]], 0, 10]],
                            'playTime' => ['$divide' => [['$subtract' => ['$endTime', '$startTime']],1000]],
                        ]
                ],
                [
                    '$group' =>
                        [
                            '_id' => '$Day',
                            'gameRound' => ['$sum' => 1],
                            'winScore' => ['$sum' => '$winScore'],
                            'revenue' => ['$sum' => '$revenue'],
                            'playTime' => ['$sum' => '$playTime']
                        ]
                ],
                [
                    '$sort' => ['_id'=>1]
                ]
            ])->toArray();
            $count = count($list);
            if (!empty($list)) {
                foreach ($list as &$item) {
                    $item['playTime'] = Sec2Time($item['playTime']);
                    $item['winScore'] = $this->formatMoneyFromMongo($item['winScore']);
                    $item['revenue'] = $this->formatMoneyFromMongo($item['revenue']);

                }
            }
            return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $list]);
        }
        if ($request->method() == 'POST') {
            return view('player/playerEverydayWinLose/list', ['name' => '']);
        }
        return view('player/playerEverydayWinLose/list', ['name' => '']);
    }

    private function _playerEverydayWinLoseAjaxParamClub()
    {
        $where = [];
        $request = request();
        $getData = check_type($request->all());
        extract($getData);
        if (empty($startDate)) {
            $startDate = date("Y-m-d");
        }
        $startTime = strtotime($startDate);
        if (empty($endDate)) {
            $endDate = date("Y-m-d");
            $endTime = strtotime("$endDate +1 day");
        }else{
            $endTime = strtotime($endDate);
        }


        if ($startTime >= $endTime) return json(['code' => -1, 'msg' => '请核对开始时间结束时间']);
        $where['endTime'] = ['$gte' => $this->formatTimestampToMongo($startTime), '$lt' =>$this->formatTimestampToMongo($endTime)];

        if (!empty($userId)) {
            $where['userId'] = $userId;
        }
        return $where;
    }

    public function playerEverydayWinLoseClub(Request $request)
    {
        if ($request->method() == 'GET') {
            $where = $this->_playerEverydayWinLoseAjaxParamClub();
            if (!is_array($where)) return $where;
            $list = Db::connection('mongodb_club')->collection('play_record')->raw()->aggregate([
                [
                    '$match' => $where
                ],
                [
                    '$project' =>
                        [
                            'winScore' => 1,
                            'revenue' => 1,
                            //'Day' => ['$substr' => ['$endTime', 0, 10]],
                            'Day' => ['$substr' => [['$add' => ['$endTime', 28800000]], 0, 10]],
                            'playTime' => ['$divide' => [['$subtract' => ['$endTime', '$startTime']],1000]],
                        ]
                ],
                [
                    '$group' =>
                        [
                            '_id' => '$Day',
                            'gameRound' => ['$sum' => 1],
                            'winScore' => ['$sum' => '$winScore'],
                            'revenue' => ['$sum' => '$revenue'],
                            'playTime' => ['$sum' => '$playTime']
                        ]
                ],
                [
                    '$sort' => ['_id'=>1]
                ]
            ])->toArray();
            $count = count($list);
            if (!empty($list)) {
                foreach ($list as &$item) {
                    $item['playTime'] = Sec2Time($item['playTime']);
                    $item['winScore'] = $this->formatMoneyFromMongo($item['winScore']);
                    $item['revenue'] = $this->formatMoneyFromMongo($item['revenue']);

                }
            }
            return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $list]);
        }
        if ($request->method() == 'POST') {
            return view('player/playerEverydayWinLoseClub/list', ['name' => '']);
        }
        return view('player/playerEverydayWinLoseClub/list', ['name' => '']);
    }

    public function playerEverydayWinLoseSummary(Request $request)
    {
        if($request->isAjax()) {
            $where = $this->_playerEverydayWinLoseAjaxParam();
            if (!is_array($where)) return $where;


            return json(['code' => 0, 'msg' => 'ok', 'data' => []]);
        }
    }

    public static function getPlayer($where, $column = ['*'])
    {
        return GameUser::where($where)->first($column);//get
    }

    public static function getControPlayer($where, $column = ['*'])
    {
        return ControlUser::where($where)->first($column);//get
    }

    public static function getAndroidPlayer($where, $column = ['*'])
    {
        return AndroidUser::where($where)->first($column);//get
    }

    public static function getPlayerNewVip($newRechargeAmount)
    {
        $vip = 0;
        $vips = Config::getSystemVips();
        $maxLevel = $maxValue = 0;
        foreach ($vips as $val)
        {
            $maxLevel = $val['level'];
            $maxValue = $val['value'];
            if($newRechargeAmount < $val['value']){
                $vip = $val['level']-1;
                break;
            }
        }
        if($newRechargeAmount >= $maxValue) $vip = $maxLevel;
        return (int)$vip;
    }

    public function getPlayerInfo(Request $request)
    {
        $userId = (int)$request->post('userId', 0);
        $source = $request->post('source', '');
        $user = GameUser::where('userId', $userId)->first();
        if (empty($user)) return json(['code' => -1, 'msg' => '会员不存在']);
        $user->trueName = $user->trueName??'未绑定银行卡信息';
        if (($source == 'addAward')) {
            $rewardType = 100;
            $msg = '';
            if (Recharge::checkUserReward($userId, $rewardType)) $msg = RewardOrder::REWARD_TYPE_FOR_GIVE_SCORE[$rewardType] . '已经送过了';
            return json(['code' => 0, 'msg' => $msg, 'data' => $user]);
        }
        return json(['code' => 0, 'msg' => 'ok', 'data' => $user]);
    }

    public function userGameDetailselected(Request $request)
    {
        $gameId = (int)$request->post('gameId', 0);
        $source = $request->post('source', '');
        if(empty($gameId)) return json(['code' => -1, 'msg' => '暂无数据']);
        if ($source == 'club') {
            $rooms = getClubGameRoomInfo($gameId);
        } else {
            $rooms = getGameRoomInfo($gameId);
        }
        return json(['code' => 0, 'msg' => '', 'data' => $rooms]);
    }

    public function showCard(Request $request)
    {
        $oid = $request->get('oid', '');
        $club = $request->get('club', '');
        $friendRoom = $request->get('friendRoom', '');
        if($club){
            $rs = ClubPlayRecord::find($oid, ['cardValue', 'gameId', 'gameInfoId', 'userId', 'cellScore'])->toArray();
            $rs2 = ClubPlayRecord::where("gameInfoId", $rs['gameInfoId'])->get(['userId','cellScore','chairId'])->toArray();
            $userIdArrTemp = UserBetArea($rs2,'club');
        }elseif($friendRoom){
            $rs = FriendsDetailPlayRecord::find($oid, ['cardValue', 'gameId', 'gameInfoId', 'userId', 'cellScore'])->toArray();
            $rs2 = FriendsDetailPlayRecord::where("gameInfoId", $rs['gameInfoId'])->get(['userId','cellScore','chairId'])->toArray();
            $userIdArrTemp = UserBetArea($rs2,"friends");
        }else{
            $rs = PlayRecord::find($oid, ['cardValue', 'gameId', 'gameInfoId', 'userId', 'cellScore'])->toArray();
            $rs2 = PlayRecord::where("gameInfoId", $rs['gameInfoId'])->get(['userId','cellScore','chairId'])->toArray();
            $userIdArrTemp = UserBetArea($rs2,'play');
        }

        $userIdArrTempV2 = [];//一个座位对应一个人
        foreach ($rs2 as $k2 => $v2) {
            $userIdArrTempV2[$v2['chairId']][] = $v2['userId'];
        }


        $cardValue = $rs['cardValue'];
        $gameId = $rs['gameId'];
        $currentUserId = $rs['userId'];
        //我的下注位置
        $betPosition = [];
        if (isset($rs['cellScore']) && !empty($rs['cellScore'])) {
            foreach ($rs['cellScore'] as $cellKey => $cellValue) {
                if($cellValue > 0) $betPosition[] = $cellKey;
            }
        }

        if ($friendRoom) {
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
        }
        $cardTypeArr220 = [
            '0' => '散牌',
            '1' => '散牌',
            '2' => '对子',
            '3' => '顺子',
            '4' => '金花',
            '5' => '顺金',
            '6' => '豹子',
            '7' => '特殊235',
        ];

        $card = $player = [];
        $num = $bankerId = 0;
        if (!empty($cardValue)) {
            if ($gameId == 900) {
                $card = [
                    ['title' => '和'],
                    ['title' => '龙','poker' => substr($cardValue, 0, 2)],                                                           
                    ['title' => '虎','poker' => substr($cardValue, 2, 2)]
                ];

                $bet = substr($cardValue, 4, 2);
                $card[0]['res'] = $card[1]['res'] = $card[2]['res'] = 0;
                switch($bet){
                    case "01": 
                        $card[0]['res'] = 1;  break;
                    case '02':  
                        $card[1]['res'] = 1;  break;  
                    case '03':  
                        $card[2]['res'] = 1;  break;
                }
                decryptBanker($bankerId,$cardValue);
                filterPlayer($player,$userIdArrTemp);
            }
            elseif ($gameId == 920) {
                if(strlen($cardValue) > 37){
                    $cardVal = substr($cardValue,0,strlen($cardValue) - 16);
                }else{
                    $cardVal = $cardValue;
                }

                $card = decomposeCardValue($cardVal, 7);
                $len = count($card);
                $num = 5;
                for($i = 0; $i < $len; $i++) {
                    $card[$i] = substr($card[$i], 1);
                    if ($i < $num) {
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
                if(strlen($cardValue) > 37){
                    decryptBanker($bankerId,$cardValue);
                }
                BetResults($card,$num);
                filterPlayer($player,$userIdArrTemp);
            }
            elseif ($gameId == 930) {
                if(strlen($cardValue) > 57){
                    $cardVal = substr($cardValue,0,strlen($cardValue) - 16);
                }else{
                    $cardVal = $cardValue;
                }

                $card = decomposeCardValue($cardVal, 11);
                $len = count($card);
                $num = 5;
                for($i = 0; $i < $len; $i++) {
                    $card[$i] = substr($card[$i], 1);
                    if ($i < $num) {
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

                if(strlen($cardValue) > 57){
                    decryptBanker($bankerId,$cardValue);
                }

                BetResults($card,$num);
                filterPlayer($player,$userIdArrTemp);
            }
            elseif ($gameId == 720) {
                $card = decomposeCardValue($cardValue, 5);
                $len = count($card);
                $num = 4;
                for($i = 0; $i < $len; $i++) {
                    $card[$i] = substr($card[$i], 1);
                    if ($i < $num) {
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
                
                BetResults($card,$num,$gameId); 
            }
            elseif (in_array($gameId, [860])) {
                //三公
                //3张牌
                //最多座5个人，前30位是牌 31位是庄位置 32-41是抢庄(下注)倍数 42-51是牌型倍数 52-61是输赢(1-赢 2-输 0-不输不赢)
                $card = decomposeCardValue(substr($cardValue, 0, 30), 6);
                $len = count($card);
                for($i = 0; $i < $len; $i++) {
                    $card[$i] = decomposeCardValue($card[$i], 2);
                }
                $card['999'] = [
                    "zhuang" => substr($cardValue, 30, 1),
                    "grabBetMultiple" => array_map('hexdec', decomposeCardValue(substr($cardValue, 31, 10), 2)),
                    "cardTypeMultiple" => array_map('hexdec', decomposeCardValue(substr($cardValue, 41, 10), 2)),
                    "winOrLose" => array_map('hexdec', decomposeCardValue(substr($cardValue, 51, 10), 2)),
                ];
                $num = 5;
            }
            elseif (in_array($gameId, [810, 830, 870, 890])) {
                //5张牌
                //890-看牌抢庄牛牛 870-通比牛牛 810-选牌牛牛 830-抢庄牛牛
                //最多座4个人，前40位是牌 41位是庄位置 42-49是抢庄(下注)倍数 50-57是牌型倍数 58-65是输赢(1-赢 2-输 0-不输不赢)
                $card = decomposeCardValue(substr($cardValue, 0, 40), 10);
                $len = count($card);
                for($i = 0; $i < $len; $i++) {
                    $card[$i] = decomposeCardValue($card[$i], 2);
                }
                $card['999'] = [
                    "zhuang" => substr($cardValue, 40, 1),
                    "grabBetMultiple" => array_map('hexdec', decomposeCardValue(substr($cardValue, 41, 8), 2)),
                    "cardTypeMultiple" => array_map('hexdec', decomposeCardValue(substr($cardValue, 49, 8), 2)),
                    "winOrLose" => array_map('hexdec', decomposeCardValue(substr($cardValue, 57, 8), 2)),
                ];
                $num = 4;
            }
            elseif (in_array($gameId, [850, 880, 820])) {
                //3张牌
                //850-通比金花 820-看牌抢庄金花 880-选牌金花
                //最多座4个人，前24位是牌 25位是庄位置 26-33是抢庄(下注)倍数 34-41是牌型倍数 42-49是输赢(1-赢 2-输 0-不输不赢)
                $card = decomposeCardValue(substr($cardValue, 0, 24), 6);
                $len = count($card);
                for($i = 0; $i < $len; $i++) {
                    $card[$i] = decomposeCardValue($card[$i], 2);
                }
                
                $card['999'] = [
                    "zhuang" => substr($cardValue, 24, 1),
                    "grabBetMultiple" => array_map('hexdec', decomposeCardValue(substr($cardValue, 25, 8), 2)),
                    "cardTypeMultiple" => array_map('hexdec', decomposeCardValue(substr($cardValue, 33, 8), 2)),
                    "winOrLose" => array_map('hexdec', decomposeCardValue(substr($cardValue, 41, 8), 2)),
                ];
                $num = 4;
            }
            elseif ($gameId == 800) {
                //2张牌
                //800-抢庄28杠
                //座4个人，前16位是牌 17位是庄位置 18-25是抢庄(下注)倍数  26-33是输赢(1-赢 2-输 0-不输不赢)
                $card = decomposeCardValue(substr($cardValue, 0, 16), 4);
                $len = count($card);
                for($i = 0; $i < $len; $i++) {
                    $tempArr = decomposeCardValue($card[$i], 2);
                    foreach ($tempArr as &$tempItem) {
                        $tempItem = base_convert($tempItem, 16, 10);
                    }
                    $card[$i] = $tempArr;
                }

                $card['999'] = [
                    "zhuang" => substr($cardValue, 16, 1),
                    "grabBetMultiple" => array_map('hexdec', decomposeCardValue(substr($cardValue, 17, 8), 2)),
                    //"cardTypeMultiple" => array_map('hexdec', decomposeCardValue(substr($cardValue, 33, 8), 2)),
                    "winOrLose" => array_map('hexdec', decomposeCardValue(substr($cardValue, 25, 8), 2)),
                ];
                $num = 4;
            }
            elseif ($gameId == 210) {
                if(strlen($cardValue) > 18){
                    $cardVal = substr($cardValue,0,strlen($cardValue) - 16);
                }else{
                    $cardVal = $cardValue;
                }

                $card = decomposeCardValue($cardVal, 6);
                $len = count($card);
                for($i = 0; $i < $len; $i++) {
                    $card[$i] = decomposeCardValue($card[$i], 2);
                }

                //01对子类型 02顺子类型 03金花类型 04顺金类型 05豹子类型
                $multiple = ['01','02','03','04','05'];
                if(count($card) > 2){
                    $card[0][3] = $card[1][3] = $card[2][3] = 0;

                    if($card[2][0] == '01'){
                        $card[0][3] = 1;
                    }else{
                        $card[1][3] = 1;
                    }

                    if(in_array($card[2][1],$multiple)){
                        $pair = hexdec($card[2][2]);
                        if($card[2][1] == '01'){
                            if($pair >= 9){
                                $card[2][3] = 1;
                            }
                        }else{
                            $card[2][3] = 1;
                        }
                    }
                }

                $userId = [];
                foreach($userIdArrTemp as $k => $v){
                    switch($k){
                        case 0:
                            $userId[2] = $v; break;
                        case 2:
                            $userId[1] = $v; break;
                        default:
                            $userId[0] = $v; break;
                    }
                }                
                $card[2]['userId'] = [];
                $userIdArrTemp = $userId;
                if(strlen($cardValue) > 18){
                    decryptBanker($bankerId,$cardValue);
                }

                filterPlayer($player,$userIdArrTemp);
            }
            elseif ($gameId == 220) {
                $new = 8;
                if ((strlen($cardValue) - 2) % 10 == 0) $new = 10;
                if ($new == 10) {
                    $card2 = decomposeCardValue($cardValue, 10);
                    $temp = array_pop($card2);
                    $len = count($card2);
                    $card = [];
                    for($i = 0; $i < $len; $i++) {
                        $index = substr($card2[$i], 0, 2);
                        $v = substr($card2[$i], 2, 6);
                        $card[$index]["card"] = decomposeCardValue($v, 2);
                        $cardTypeId = substr($card2[$i], 8, 1);
                        $card[$index]["cardType"] = $cardTypeArr220[$cardTypeId];
                        $giveUp = substr($card2[$i], 9, 1);
                        $card[$index]["giveUp"] = $giveUp;
                    }
                    $card["win"] = $temp;
                } elseif ($new == 8) {
                    $card2 = decomposeCardValue($cardValue, 8);
                    $temp = array_pop($card2);
                    $len = count($card2);
                    $card = [];
                    for($i = 0; $i < $len; $i++) {
                        $index = substr($card2[$i], 0, 2);
                        $v = substr($card2[$i], 2);
                        $card[$index]["card"] = decomposeCardValue($v, 2);
                    }
                    $card["win"] = $temp;
                }
                $card['new'] = $new;
                ksort($card);
                $num = count($card) + 1;
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
            }
            elseif ($gameId == 550) {
                $cardArray = explode(";", $cardValue);
                $card = [];
                $card["win"] = '';
                foreach ($cardArray as $c) {
                    $temp = '';
                    $temp = explode(",", $c);
                    $card[$temp['3']] = [array_slice(decomposeCardValue($temp['0'], 2), 0, 3), array_slice(decomposeCardValue($temp['1'], 2), 0, 5), array_slice(decomposeCardValue($temp['2'], 2), 0, 5)];
                    if (isset($temp['4']) && ($temp['4'] == '1')) $card["win"] = $temp['3'];
                }              
            }
            elseif ($gameId == 600) {
                $cardArrayUsers = explode(",", $cardValue);
                $card = [];
                foreach ($cardArrayUsers as $val_u) {
                    $moreSeats = strpos($val_u,"|");
                    if($moreSeats){
                        $seat_array = explode("|", $val_u);
                        foreach ($seat_array as $val_seat){
                            $split_cards = strpos($val_seat,"-");
                            if($split_cards){
                                $split_cards_array = explode("-", $val_seat);
                                foreach ($split_cards_array as $key_split => $val_split){
                                    $val_split_arr = explode("_", $val_split);
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
                            }else{
                                $val_seat_arr = explode("_", $val_seat);
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
                    }else{
                        $split_cards = strpos($val_u,"-");
                        if($split_cards){
                            $split_cards_array = explode("-", $val_u);
                            foreach ($split_cards_array as $key_split => $val_split){
                                $val_split_arr = explode("_", $val_split);
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
                        }else{
                            $val_u_arr = explode("_", $val_u);
                            $seat_num = substr($val_u_arr[0],0,1);
                            $op = substr($val_u_arr[0],1,1);
                            $card_str = substr($val_u_arr[0],2);
                            $card[$seat_num]['op'] = $op;
                            $card[$seat_num]['card_str'] = decomposeCardValue($card_str, 2);
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

                if($card){
                    foreach ($card as $k1 => $v1){
                        if(!isset($v1['op'])){
                            $card[$k1]['division'] = count($v1);
                        }else{
                            $card[$k1]['division'] = 0;
                        }
                    }
                }
            }
            elseif ($gameId == 950) {
                $card['list'] = ['奔驰','宝马','大众','阿尔法罗密欧','保时捷','玛莎拉蒂','兰博基尼','法拉利'];
                $card['1'] = 'ok';                
            }
            elseif ($gameId == 420) {
                $cardTypeArr = [
                    '0' => '散牌',
                    '1' => '一对',
                    '2' => '两对',
                    '3' => '三条',
                    '4' => '顺子',
                    '5' => '同花',
                    '6' => '葫芦',
                    '7' => '四条',
                    '8' => '同花顺',
                    '9' => '皇家同花顺',
                ];
                $cardArray = explode(",", $cardValue);
                $winChairId = $cardArray[1];
                $cardArray = explode("|", $cardArray[0]);
                $len = count($cardArray);
                $card = [];
                for ($i = 0; $i < $len; $i++) {
                    $chairId = substr($cardArray[$i], 0, 1);
                    $tempCard = substr($cardArray[$i], 2, -1);
                    $cardType = substr($cardArray[$i], -1);
                    $card[$chairId] = ['cards' => decomposeCardValue($tempCard, 2), 'cardType' => $cardTypeArr[$cardType], 'winChairId' => $winChairId];
                }
            }
            elseif ($gameId == 450) {
                $cardTypeArr = [
                    '0' => '散牌',
                    '1' => '一对',
                    '2' => '两对',
                    '3' => '三条',
                    '4' => '顺子',
                    '5' => '同花',
                    '6' => '葫芦',
                    '7' => '四条',
                    '8' => '同花顺',
                    '9' => '皇家同花顺',
                ];
                $cardArray = explode(",", $cardValue);
                $cardArrayLen = count($cardArray);
                //座位号0--8
                //可以有多个赢的位置 ,座位0,座位2,座位3,座位4
                $winChairId = [];
                for ($j = 1; $j < $cardArrayLen; $j++) {
                    $winChairId[] = $cardArray[$j];
                }
                $cardArray = explode("|", $cardArray[0]);
                $len = count($cardArray);
                $card = [];
                for ($i = 0; $i < $len; $i++) {
                    $newCardArray = explode("-", $cardArray[$i]);
                    $useCards = "";
                    if (isset($newCardArray[1])) {
                        $cardArray[$i] = $newCardArray[0];
                        $useCards = $newCardArray[1];
                    }
                    $chairId = substr($cardArray[$i], 0, 1);
                    $tempCard = substr($cardArray[$i], 2, -2);
                    $cardType = substr($cardArray[$i], -2, 1);
                    $disCard = substr($cardArray[$i], -1);
                    $card[$chairId] = ['cards' => decomposeCardValue($tempCard, 2), 'useCards' => decomposeCardValue($useCards, 2), 'cardType' => $cardTypeArr[$cardType], 'winChairId' => $winChairId, 'disCard' => $disCard];
                }               
            }
            elseif ($gameId == 910) {
                $cardArray = explode("|", $cardValue);
                $title = ['庄','闲','','','庄对','闲对','和'];
                foreach($cardArray as $k => $v){
                    if(!in_array($k,[2,3])){                      
                        $card[$k]['title'] = $title[$k];
                        $card[$k]['dui'] = $card[$k]['win'] = 0;   
                        if($k < 2){                          
                            $poker = decomposeCardValue($cardArray[$k],2);
                            $card[$k]['poker'] = $poker;                                                                                                                                                                 
                        }elseif($k > 3 && $k < 6){
                            $card[$k]['dui'] = $v;
                        }                        
                        if($k == 6){     
                            switch($v){
                                case 2:
                                    $card[1]['win'] = 1;
                                    break;
                                case 3:
                                    $card[0]['win'] = 1; 
                                    break;
                                case 4:
                                    $card[$k]['win'] = 1;                                       
                            }                                                       
                        }
                    }                                
                }
                //1 =>闲对，2 => 庄对 3 => 闲 , 4  => 庄   5 => 和 
                $card = array_merge($card);
                $userId = [];
                if($userIdArrTemp){
                    foreach($userIdArrTemp as $k => $v){
                        switch($k){
                            case 0:
                                $userId[3] = $v;break;
                            case 1:
                                $userId[2] = $v;break;
                            case 2:
                                $userId[1] = $v;break;
                            case 3:
                                $userId[0] = $v;break;
                            default:
                                $userId[$k] = $v;break;
                        }                         
                    }
                }
                $userIdArrTemp = $userId;
            }
            elseif ($gameId == 940) {
                $card = [
                    ['title' => '庄','mahjong1' => substr($cardValue, 0, 2),'mahjong2' => substr($cardValue, 2, 2)],
                    ['title' => '顺','mahjong1' => substr($cardValue, 4, 2),'mahjong2' => substr($cardValue, 6, 2),'res' => 0],
                    ['title' => '天','mahjong1' => substr($cardValue, 8, 2),'mahjong2' => substr($cardValue, 10, 2),'res' => 0],
                    ['title' => '地','mahjong1' => substr($cardValue, 12, 2),'mahjong2' => substr($cardValue, 14, 2),'res' => 0]
                ];
                foreach ($card as &$item){
                    $item['mahjong1'] = base_convert($item['mahjong1'],16,10);
                    $item['mahjong2'] = base_convert($item['mahjong2'],16,10);
                }
                $bet = substr($cardValue, 16, 2);
                $number2 = base_convert($bet,16,2);
                $number2=str_pad($number2,8,"0",STR_PAD_LEFT);
                $shun = substr($number2,-1,1);
                if($shun == 1)$card[1]['res'] = 1;
                $tian = substr($number2,-2,1);
                if($tian == 1) $card[2]['res'] = 1;
                $di = substr($number2,-3,1);
                if($di == 1) $card[3]['res'] = 1;
                if($shun == $tian && $tian == $di && $di == 1) $card[0]['res'] = 1;
                $bankerId = substr($cardValue,18);
                $bankerId = hexdec(littleToBigEndian($bankerId));
                foreach ($userIdArrTemp as &$itemCurrent){
                    if(count($itemCurrent) > 0){
                        foreach ($itemCurrent as $kk =>$vv){
                            if($vv !== $currentUserId) unset($itemCurrent[$kk]);
                        }
                    }
                }
                filterPlayer($player,$userIdArrTemp);
            }
            elseif ($gameId == 960) {
                //06040630
                //30060406
                $cellScoreText = [
                    '豹子','小','大','单','双','4点','5点','6点','7点','8点','9点','10点','11点','12点','13点','14点','15点','16点','17点'
                ];
                $tempCardValue = substr($cardValue, 0, 8);
                $tempCardValue2 = hexdec(littleToBigEndian($tempCardValue));
                $s1 = $tempCardValue2 & 0xff;
                $s2 = ($tempCardValue2>>8) & 0xff;
                $s3 = ($tempCardValue2>>16) & 0xff;
                //中奖标记
                $jiang = ($tempCardValue2>>24) & 0xff;
                //中奖点数
                $point = $jiang & 0x1f;

                //牌类型
                $cardType = ($jiang>>5) & 0xff;
                $isBaozi = ($cardType>>2) & 0x01;
                $isSingleDouble = ($cardType>>1) & 0x01;
                $isBigSmall = $cardType & 0x01;

                $card = [
                    'dice' => [$s1, $s2, $s3],
                    'point' => $point,
                    'isBaozi' => $isBaozi,// 0 不是豹子， 1 是豹子
                    'isSingleDouble' => $isSingleDouble,// 0表示双， 1 表示单
                    'isBigSmall' => $isBigSmall, //0 表示小， 1 表示大
                    'cellScore' => $rs['cellScore'],
                    'cellScoreText' => $cellScoreText
                ];
                foreach ($card['cellScore'] as &$item) {
                    if ($item > 0) $item = $this->formatMoneyFromMongo($item);
                }
            }
            elseif ($gameId == 620) {
                $cardTypeArr = [
                    '1' => '至尊',
                    '2' => '对子',
                    '3' => '杂九',
                    '4' => '杂八',
                    '5' => '杂七',
                    '6' => '杂五',
                    '7' => '天王',
                    '8' => '地王',
                    '9' => '天杠',
                    '10' => '地杠',
                    '11' => '天高九',
                    '12' => '地高九',
                    '13' => '单牌',
                ];
                //2张牌
                //620-抢庄牌九
                //座4个人，庄家座位号|座位号0的牌值|座位号0的牌型|座位号0的牌的点数|座位号0叫庄倍数|座位号0下注倍数|座位号0自己输赢|
                $cardValueArr = explode("|", $cardValue);
                $zhuang = array_shift($cardValueArr);
                $delete = array_pop($cardValueArr);
                $card[0] = array_slice($cardValueArr, 0, 6);
                $card[1] = array_slice($cardValueArr, 6, 6);
                $card[2] = array_slice($cardValueArr, 12, 6);
                $card[3] = array_slice($cardValueArr, 18, 6);
                foreach ($card as $key => &$value) {
                    $value[6] = 0;
                    if ($key == $zhuang) $value[6] = 1;
                    $value[0] = explode(",", $value[0]);
                    if ($value[1] == 13) {
                        $value[1] = $value[2] . '点';
                    } else {
                        $value[1] = $cardTypeArr[$value[1]];
                    }
                }
            }
        }
        if (in_array($gameId, [600, 900, 210, 920, 930,940])) {
            $usedUserIdArrTemp = $userIdArrTemp;
        } else {
            $usedUserIdArrTemp = $userIdArrTempV2;
        }
        filterPlayRecordUserId($card,$usedUserIdArrTemp,$gameId,$num);
        if($gameId == 940){
//            if($card[0]['res'] == 1 && $bankerId == $currentUserId){
//                $card[0]['userId'] = [$currentUserId];
//            }
            $assignData = [
                'gameId' => $gameId,
                'card' => $card,
                'cardValue' => $cardValue,
                'currentUserId' => $currentUserId,
                'betPosition' => $betPosition,
                'bankerId' => $bankerId ?? 0,
            ];
        }else{
            $assignData = [
                'gameId' => $gameId,
                'card' => $card,
                'cardValue' => $cardValue,
                'currentUserId' => $currentUserId,
                'betPosition' => $betPosition,
                'bankerId' => in_array($bankerId,$player) ? $bankerId : 0,
            ];
        }
        //dd($assignData);
        return view('player/showCard/list', $assignData);
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
        $updateResult = GameUser::whereIn('userId', $_idsArr)->update(['status' => $value]);
        if ($updateResult) {
            Promoter::whereIn('promoterId', $_idsArr)->update(['status' => $value]);
            return json(['code' => 0, 'msg' => '操作成功']);
        }
        return json(['code' => -1, 'msg' => '操作失败']);
    }

    public static function gameKind($where)
    {
        return FriendsGameKind::where($where)->orderBy('sort', 'asc')->get();
    }

    public static function gameListByRoom($gameId)
    {
        $return = [];
        $where = ['gameId' => $gameId];
        $list = static::gameKind($where);
        foreach ($list as $item) {
            $return[] = ["gameId" => $item['gameId'], 'gameName' => $item['gameName']];
        }
        return $return;
    }

    public static function getSonData($promoterId)
    {
        static $a = 0;
        $sonIdArr = [];
        $combine = [];
        $sonData = Agent::getPromoters(['pid' => $promoterId], ['promoterId']);
        if($sonData){
            foreach ($sonData as &$item){
                $sonIdArr[] = $item['promoterId'];
                if($a++ <=3){
                    $sonResult = static::getSonData($item['promoterId']);
                }else{
                    return ['code' => -1];
                }
                $combine = array_merge($sonIdArr,$sonResult);
            }
        }
        return $combine;
    }

    public function userStatusReason(Request $request)
    {
        if ($request->method() == 'GET') {
            $assignData = [
                'reason' => ""
            ];
            return view('player/player/statusReason',$assignData);
        }
        if ($request->method() == 'POST') {
            $_ids = $request->post('_ids');
            $_idsArr = explode(",", $_ids);
            if (empty($_idsArr)) return json(['code' => -1, 'msg' => '参数错误']);
            foreach ($_idsArr as &$val) {
                $val = (int)$val;
            }
            $value = (int)$request->post('value');
            $reason = (string)$request->post('reason');
            $updateResult = GameUser::whereIn('userId', $_idsArr)->update(['status' => $value,'reason' => $reason]);
            if ($updateResult) {
                Promoter::whereIn('promoterId', $_idsArr)->update(['status' => $value]);
                return json(['code' => 0, 'msg' => '操作成功']);
            }
            return json(['code' => -1, 'msg' => '操作失败']);
        }
    }

    public function viewReason(Request $request)
    {
        $getData = check_type($request->get());
        extract($getData);
        $userData = GameUser::where('userId', $userId)->first()->toArray();

        if(!isset($userData['reason'])) $userData['reason']="";
        $assignData = [
            'reason' => $userData['reason']
        ];
        return view('player/player/statusReason', $assignData);
    }
}
