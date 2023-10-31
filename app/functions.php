<?php
/**
 * Here is your custom functions.
 */

use MongoDB\BSON\UTCDateTime;
use support\functionUpload;
use app\model\Promoter;

function check_type($obj) {
    $intFields = ['id', 'pkid', 'groupId', 'status', 'userId', 'type', 'sortId', 'withdrawType', 'rewardType', 'promoterId', 'startId', 'endId', 'pid', 'roomId','gameId','taskType','taskType','taskCycle','agentId','updateUrlId','port','lineLevel','isForce','channelId','clubId','enableAndroid','minAndroidTable','maxAndroidTable','clubRewardType','activityType','promoterLevel','planId','timeType','cscore'];
    $strFields = ['searchText', 'trueName', 'searchValue','title', 'loginPass','value','ip','remark','s','rechargeTypeName','clubName','bankCode','bankName'];
    if (is_array($obj)) {
        foreach ($obj as $ok => $ov) {
            if (in_array($ok, $intFields)) $obj[$ok] = intval($ov);
            if (in_array($ok, $strFields)) $obj[$ok] = trim($ov);
        }
    }

    return $obj;
}

function getRandStr($length) {
    $str = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $len = strlen($str) - 1;
    $randstr = '';
    for ($i = 0; $i < $length; $i++) {
        $num = mt_rand(0, $len);
        $randstr .= $str[$num];
    }
    return $randstr;
}

function merge_array($arr1, $arr2, $kleft, $kright = '')
{
    if($kright=='')
        $kright=$kleft;
    $arr3 = array();
    if (!empty($arr2)) {
        foreach ($arr2 as $k2 => $v2)
        {
            if (isset($v2[$kright])) {
                if(isset($v2['_id'])) unset($v2['_id']);
                $arr3[$v2[$kright]] = $v2;
            }
        }
    }
    if (!empty($arr2)) {
        foreach ($arr1 as $k1 => $v1)
        {
            if (isset($v1[$kleft]) && isset($arr3[$v1[$kleft]])) {
                $arr1[$k1] = array_merge((array)$v1, (array)$arr3[$v1[$kleft]]);
            }
        }
    }
    return $arr1;
}

function merge_array_id($arr1, $arr2, $kleft, $kright = '')
{
    if($kright=='')
        $kright=$kleft;
    $arr3 = array();
    if (!empty($arr2)) {
        foreach ($arr2 as $k2 => $v2)
        {
            if (isset($v2[$kright])) {
                $arr3[$v2[$kright]] = $v2;
            }
        }
    }
    if (!empty($arr2)) {
        foreach ($arr1 as $k1 => $v1)
        {
            if (isset($v1[$kleft]) && isset($arr3[$v1[$kleft]])) {
                $arr1[$k1] = array_merge((array)$v1, (array)$arr3[$v1[$kleft]]);
            }
        }
    }
    return $arr1;
}
function setControlPoint($jsonData, $key = 'ControlUserIds')
{
    return \support\bootstrap\Redis::set($key, $jsonData);
}

function getControlPoint($key = 'ControlUserIds') {
    return \support\bootstrap\Redis::get($key);
}

function delControlPoint($key = 'ControlUserIds'){
    \support\bootstrap\Redis::del($key);
}

function sendData2($content, $channel = 'RechargeExchangeScoreToProxyMessage')
{
    return \support\bootstrap\Redis::publish($channel, json_encode($content));
}

function sendDataToQueue($content, $key = 'genUrl')
{
    return \support\bootstrap\Redis::lPush($key, json_encode($content));
}

function Sec2Time($time)
{
    if (is_numeric($time)) {
        $value = array(
            "years" => 0, "days" => 0, "hours" => 0,
            "minutes" => 0, "seconds" => 0,
        );
        if ($time >= 31556926) {
            $value["years"] = floor($time / 31556926);
            $time = ($time % 31556926);
        }
        if ($time >= 86400) {
            $value["days"] = floor($time / 86400);
            $time = ($time % 86400);
        }
        if ($time >= 3600) {
            $value["hours"] = floor($time / 3600);
            $time = ($time % 3600);
        }
        if ($time >= 60) {
            $value["minutes"] = floor($time / 60);
            $time = ($time % 60);
        }
        $value["seconds"] = floor($time);
        //return (array) $value;
        //$t=$value["years"] ."年". $value["days"] ."天"." ". $value["hours"] ."小时". $value["minutes"] ."分".$value["seconds"]."秒";
        //$t = $value["days"] . "天" . " " . $value["hours"] . "小时" . $value["minutes"] . "分" . $value["seconds"] . "秒";
        $t = '';
        if ($value["years"])  $t .= $value["years"] . "年";
        if ($value["days"])  $t .= $value["days"] . "天";
        if ($value["hours"])  $t .= $value["hours"] . "小时";
        if ($value["minutes"])  $t .= $value["minutes"] . "分";
        if ($value["seconds"])  $t .= $value["seconds"] . "秒";
        if (empty($t)) $t .= '0秒';
        return $t;

    } else {
        return '0秒';
    }
}
function Sec3Time($time_str)
{
    if($time_str == "暂未处理") return $time_str;
    $minutes = 0;
    $seconds = 0;
    $timea_arr = explode("天",$time_str);
    if($timea_arr[0]){
        $minutes = intval($timea_arr[0]) * 24 * 60;
    }
    if($timea_arr[1]){
        $timea_hms = explode(":",$timea_arr[1]);

        if($timea_hms[0]){
            $minutes += intval($timea_hms[0]) * 60;
        }
        if($timea_hms[1]){
            $minutes += intval($timea_hms[1]);
        }
        if($timea_hms[2]){
            $seconds += intval($timea_hms[2]);
        }
    }
    if($minutes || $seconds){
        $time_result = $minutes."分钟".$seconds."秒";
    }else{
        $time_result = "0分钟0秒";
    }
    return $time_result;
}

function stringToObjectId($str)
{
    return new \MongoDB\BSON\ObjectId($str);
}

//返回金币变动类型
function resultChangeType($in)
{
    $arrList =
        [
            "1" => "注册奖励",
            "2" => "绑定手机奖励",
            "3" => "充值",
            "4" => "兑换金币",
            "5" => "充值返还奖励",
            "6" => "充值补发金币",
            "7" => "支付补发",
            "8" => "官方赠送",
            "9" => "兑换驳回返还",
            "10" => "兑换取消返回",
            "11" => "存保险箱",
            "12" => "从保险箱取出",

            "13" => "金币兑换房卡",
            "14" => "转入房卡",
            "15" => "转出房卡",
            "16" => "消费房卡",
            "17" => "官方赠送房卡",
            "18" => "奖励房卡",

            "19" => "活动奖励",
            "20" => "救济金",
            "21" => "红包",
            "22" => "任务奖励",
            "23" => "邮件补助",
            "24" => "签到",
            "25" => "举报扣币",
            "26" => "举报奖励",
            "27" => "代理金币转入",
            "28" => "代理金币转出",
            "30" => "地推充值到支付代理",
            "31" => "代理回调给会员充值",
            "32" => "全民代理兑换到游戏金币",
            "33" => "代理兑换到游戏金币",
            "34" => "代理兑换到支付宝",
            "35" => "代理兑换到银行卡",
            "80" => "游戏记录",
            "100" => "引流奖励彩金",
            "101" => "注册送88彩金",
            "102" => "连续7天登录投注送彩金",
            "103" => "线下充值返利",
            "104" => "推广送彩金",
            "105" => "直属充值返利",
            "106" => "首充赠送",
            "107" => "跑量奖励",
            "108" => "累计充值奖励",
            "109" => "其他奖励",

            '110' => '(抢庄牛牛)当庄五花牛',//抢庄牛牛(10元/20元桌子)588
            '111' => '(抢庄牛牛)当庄五小牛',//抢庄牛牛(10元/20元桌子)388
            '112' => '(三公专场)当庄爆玖',//三公专场(10元/20元桌子)888
            '113' => '(三公专场)当庄炸弹',//三公专场(10元/20元桌子)666
            '114' => '(炸金花)连赢9把',//炸金花连赢奖励(10元/20元桌子)588
            '115' => '(炸金花)连赢8把',//炸金花连赢奖励(10元/20元桌子)488
            '116' => '(炸金花)连赢7把',//炸金花连赢奖励(10元/20元桌子)388
            '117' => '(炸金花特殊奖励)豹子AAA',//炸金花特殊奖励(10元/20元桌子)588
            '118' => '(炸金花特殊奖励)豹子666',//炸金花特殊奖励(10元/20元桌子)488
            '119' => '(炸金花特殊奖励)豹子888',//炸金花特殊奖励(10元/20元桌子)388

            '120' => '十局内三飞机两全关',//跑的快专场(5元桌) 88
            '121' => '十局内七连对两全关',//跑的快专场(5元桌)108
            '122' => '十局内两炸两全关',//跑的快专场(5元桌)128
            '123' => '十局内四全关',//跑的快专场(5元桌)188
            '124' => '十局内六全关',//跑的快专场(5元桌)388
            '125' => '十局内八全关',//跑的快专场(5元桌)888
            '126' => '十局内十全关',//跑的快专场(5元桌)1888

            '127' => '十局内三飞机两全关',//跑的快专场(10元/20元桌子)158
            '128' => '十局内七连对两全关',//跑的快专场(10元/20元桌子)188
            '129' => '十局内两炸两全关',//跑的快专场(10元/20元桌子)288
            '130' => '十局内四全关',//跑的快专场(10元/20元桌子)588
            '131' => '十局内六全关',//跑的快专场(10元/20元桌子)888
            '132' => '十局内八全关',//跑的快专场(10元/20元桌子)1888
            '133' => '十局内十全关',//跑的快专场(10元/20元桌子)3888
            '134' => '抽奖奖励',//抽奖奖励
            '135' => '推广方案',
            '136' => '上级赠送',
            '137' => '有效投注彩金',
            '138' => '财富攀登彩金',
        ];
    if (isset($arrList[$in])) {
        return $arrList[$in];
    } else {
        return '未知';
    }
}

function mobile($str){
    if (empty($str) || $str == '0') return '';

    $e = new \Aes();
    return $e->decrypt($str);
}

function encr($str)
{
    $e = new \Aes();
    return $e->encrypt($str);
}

function mobile_de($str){
    if (empty($str) || $str == '0') return '';

    $e = new \Aes();
    return $e->decrypt($str);
}

function mobile_en($str){
    if (empty($str) || $str == '0') return '';

    $e = new \Aes();
    return $e->encrypt($str);
}

function mobileShow($mobile)
{
    if (strlen($mobile) != 11) {
        return $mobile;
    }
    $newmobile = substr_replace($mobile, '****', 3, 4);
    return $newmobile;
}

//生成订单号
function orderNumber()
{
    return date("YmdHis") . rand(100, 999);
}

function GetIP()
{
    $ip = request()->getRealIp($safe_mode=false);

    if (strpos($ip, ",")) {
        $ipArr = explode(",", $ip);
        $ip = trim($ipArr[0]);
    }
    return $ip;
}

//http请求
function httpRequest($url, $method, $postfields = null, $headers = array(), $debug = false)
{
    $method = strtoupper($method);
    $ci = curl_init();
    /* Curl settings */
    curl_setopt($ci, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
    curl_setopt($ci, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.2; WOW64; rv:34.0) Gecko/20100101 Firefox/34.0");
    curl_setopt($ci, CURLOPT_CONNECTTIMEOUT, 60); /* 在发起连接前等待的时间，如果设置为0，则无限等待 */
    curl_setopt($ci, CURLOPT_TIMEOUT, 7); /* 设置cURL允许执行的最长秒数 */
    curl_setopt($ci, CURLOPT_RETURNTRANSFER, true);
    switch ($method) {
        case "POST":
            curl_setopt($ci, CURLOPT_POST, true);
            if (!empty($postfields)) {
                $tmpdatastr = is_array($postfields) ? http_build_query($postfields) : $postfields;
                curl_setopt($ci, CURLOPT_POSTFIELDS, $tmpdatastr);
            }
            break;
        default:
            curl_setopt($ci, CURLOPT_CUSTOMREQUEST, $method); /* //设置请求方式 */
            break;
    }
    $ssl = preg_match('/^https:\/\//i', $url) ? TRUE : FALSE;
    curl_setopt($ci, CURLOPT_URL, $url);
    if ($ssl) {
        curl_setopt($ci, CURLOPT_SSL_VERIFYPEER, FALSE); // https请求 不验证证书和hosts
        curl_setopt($ci, CURLOPT_SSL_VERIFYHOST, FALSE); // 不从证书中检查SSL加密算法是否存在
    }
    //curl_setopt($ci, CURLOPT_HEADER, true); /*启用时会将头文件的信息作为数据流输出*/
    curl_setopt($ci, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ci, CURLOPT_MAXREDIRS, 2);/*指定最多的HTTP重定向的数量，这个选项是和CURLOPT_FOLLOWLOCATION一起使用的*/
    curl_setopt($ci, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ci, CURLINFO_HEADER_OUT, true);
    /*curl_setopt($ci, CURLOPT_COOKIE, $Cookiestr); * *COOKIE带过去** */
    $response = curl_exec($ci);
    $requestinfo = curl_getinfo($ci);
    $http_code = curl_getinfo($ci, CURLINFO_HTTP_CODE);
    if ($debug) {
        echo "=====post data======\r\n";
        var_dump($postfields);
        echo "=====info===== \r\n";
        print_r($requestinfo);
        echo "=====response=====\r\n";
        print_r($response);
    }
    curl_close($ci);
    return $response;
}

function getGameRoomInfo($gameId = 0)
{
    $filter = ['status' => 1];
    if($gameId) $filter['gameId'] = (int)$gameId;
    $gameRoomArrays = \app\model\GameKind::where($filter)->select('rooms.roomId', 'rooms.roomName')->orderBy('sort', 'asc')->get();
    $gameRoomInfo = array();
    if ($gameRoomArrays) {
        foreach ($gameRoomArrays as $key => $value) {
            if($value['rooms']){
                foreach ($value['rooms'] as $key1 => $value1) {
                    $gameRoomInfo[] = ['roomId' => $value1['roomId'], 'roomName' => $value1['roomName']];
                }
            }
        }
    }
    return $gameRoomInfo;
}

function getGameRoomInfoAll($gameId = 0)
{
    $filter = [];
    if($gameId) $filter['gameId'] = (int)$gameId;
    $gameRoomArrays = \app\model\GameKind::where($filter)->select('rooms.roomId', 'rooms.roomName')->orderBy('sort', 'asc')->get();
    $gameRoomInfo = array();
    if ($gameRoomArrays) {
        foreach ($gameRoomArrays as $key => $value) {
            if($value['rooms']){
                foreach ($value['rooms'] as $key1 => $value1) {
                    $gameRoomInfo[] = ['roomId' => $value1['roomId'], 'roomName' => $value1['roomName']];
                }
            }
        }
    }
    return $gameRoomInfo;
}

function getGameInfo($gameId = 0)
{
    $where = ['status' => 1];
    if($gameId)
        $where['gameId'] = (int)$gameId;
    $game_kind_arrays = \app\model\GameKind::where($where)->select('gameId','gameName')->orderBy('sort', 'asc')->get();
    $game_info = [];
    if ($game_kind_arrays) {
        foreach ($game_kind_arrays as $key => $value) {
            $game_info[] = ['gameId' => $value['gameId'], 'gameName' => $value['gameName']];
        }
    }
    return $game_info;
}

function getGameInfoGameRoomInfo($gameId = 0)
{
    $where = ['status' => 1];
    if($gameId)
        $where['gameId'] = (int)$gameId;
    $game_kind_arrays = \app\model\GameKind::where($where)->select('gameId','gameName','rooms.roomId','rooms.roomName')->orderBy('sort', 'asc')->get()->toArray();

    $game_room_info = array();
    if ($game_kind_arrays) {
        foreach ($game_kind_arrays as $key => $value) {
            $game_room_info[$key]["gameId"] = $value["gameId"];
            $game_room_info[$key]["gameName"] = $value["gameName"];
            foreach ($value['rooms'] as $key1 => $value1) {
                $game_room_info[$key]["rooms"][] = ['roomId' => $value1['roomId'], 'roomName' => $value1['roomName']];
            }
        }
    }
    return $game_room_info;
}

function getClubGameInfo($gameId = 0)
{
    $where = ['status' => 1];
    if($gameId)
        $where['gameId'] = (int)$gameId;
    $game_kind_arrays = \app\model\GameKind::where($where)->select('gameId','gameName')->orderBy('sort', 'asc')->get();
    $game_info = [];
    if ($game_kind_arrays) {
        $clubGame = clubGame();
        foreach ($game_kind_arrays as $key => $value) {
            if (!in_array($value["gameId"], $clubGame)) continue;
            $game_info[] = ['gameId' => $value['gameId'], 'gameName' => $value['gameName']];
        }
    }
    return $game_info;
}

function getClubGameInfoGameRoomInfo($gameId = 0)
{
    $where = ['status' => 1];
    if($gameId) $where['gameId'] = (int)$gameId;
    $game_kind_arrays = \app\model\GameKind::where($where)->select('gameId','gameName','rooms.roomId','rooms.roomName')->orderBy('sort', 'asc')->get()->toArray();

    $game_room_info = array();
    if ($game_kind_arrays) {
        $clubGame = clubGame();
        $clubRoomName = clubRoomName();
        foreach ($game_kind_arrays as $key => $value) {
            if (!in_array($value["gameId"], $clubGame)) continue;
            $game_room_info[$key]["gameId"] = $value["gameId"];
            $game_room_info[$key]["gameName"] = $value["gameName"];
            foreach ($value['rooms'] as $key1 => $value1) {
                //$game_room_info[$key]["rooms"][] = ['roomId' => $value1['roomId'], 'roomName' => $value1['roomName']];
                if (!isset($clubRoomName[$value1['roomId']])) continue;
                $game_room_info[$key]["rooms"][] = ['roomId' => $value1['roomId'], 'roomName' => $clubRoomName[$value1['roomId']]['roomName']];
            }
        }
    }
    return $game_room_info;
}
function getClubGameRoomInfo($gameId = 0)
{
    $filter = ['status' => 1];
    if($gameId) $filter['gameId'] = (int)$gameId;
    $gameRoomArrays = \app\model\GameKind::where($filter)->select('gameId', 'gameName', 'rooms.roomId', 'rooms.roomName')->orderBy('sort', 'asc')->get();
    $gameRoomInfo = [];
    if ($gameRoomArrays) {
        $clubRoomName = clubRoomName();
        foreach ($gameRoomArrays as $key => $value) {
            if($value['rooms']){
                foreach ($value['rooms'] as $key1 => $value1) {
                    //$gameRoomInfo[] = ['roomId' => $value1['roomId'], 'roomName' => $value1['roomName']];
                    if (!isset($clubRoomName[$value1['roomId']])) continue;
                    $gameRoomInfo[] = ['roomId' => $value1['roomId'], 'roomName' => $clubRoomName[$value1['roomId']]['roomName'], 'gameName' => $value["gameName"]];
                }
            }
        }
    }
    return $gameRoomInfo;
}
function clubGame() {
    return [220, 300, 860, 890, 100, 420, 870, 550, 900, 210, 930, 920, 800, 940];
}
function clubRoomName() {
    $arr = [
        2201 => ['roomId' => 2201, 'roomName' => '1元必闷1轮'],
        2202 => ['roomId' => 2202, 'roomName' => '2元必闷1轮'],
        2203 => ['roomId' => 2203, 'roomName' => '5元必闷2轮'],
        2204 => ['roomId' => 2204, 'roomName' => '10元必闷3轮'],
        3001 => ['roomId' => 3001, 'roomName' => '0.01元/-张'],
        3002 => ['roomId' => 3002, 'roomName' => '1元/-张'],
        3003 => ['roomId' => 3003, 'roomName' => '5元/-张'],
        3004 => ['roomId' => 3004, 'roomName' => '20元/-张'],
        8601 => ['roomId' => 8601, 'roomName' => '三公1元底'],
        8602 => ['roomId' => 8602, 'roomName' => '三公5元底'],
        8603 => ['roomId' => 8603, 'roomName' => '三公10元底'],
        8604 => ['roomId' => 8604, 'roomName' => '三公20元底'],
        8901 => ['roomId' => 8901, 'roomName' => '看牌牛牛1元底'],
        8902 => ['roomId' => 8902, 'roomName' => '看牌牛牛5元底'],
        8903 => ['roomId' => 8903, 'roomName' => '看牌牛牛10元底'],
        8904 => ['roomId' => 8904, 'roomName' => '看牌牛牛20元底'],
        1001 => ['roomId' => 1001, 'roomName' => '0.01元/斗地主'],
        1002 => ['roomId' => 1002, 'roomName' => '1元/斗地主'],
        1003 => ['roomId' => 1003, 'roomName' => '5元/斗地主'],
        1004 => ['roomId' => 1004, 'roomName' => '20元/斗地主'],
        4201 => ['roomId' => 4201, 'roomName' => '1元/梭哈'],
        4202 => ['roomId' => 4202, 'roomName' => '5元/梭哈'],
        4203 => ['roomId' => 4203, 'roomName' => '20元/梭哈'],
        4204 => ['roomId' => 4204, 'roomName' => '50元/梭哈'],
        8701 => ['roomId' => 8701, 'roomName' => '通比牛1元底'],
        8702 => ['roomId' => 8702, 'roomName' => '通比牛5元底'],
        8703 => ['roomId' => 8703, 'roomName' => '通比牛10元底'],
        8704 => ['roomId' => 8704, 'roomName' => '通比牛20元底'],
        5501 => ['roomId' => 5501, 'roomName' => '十三水1元底'],
        5502 => ['roomId' => 5502, 'roomName' => '十三水10元底'],
        5503 => ['roomId' => 5503, 'roomName' => '十三水20元底'],
        5504 => ['roomId' => 5504, 'roomName' => '十三水50元底'],
        8001 => ['roomId' => 8001, 'roomName' => '抢庄二八杠1元底'],
        8002 => ['roomId' => 8002, 'roomName' => '抢庄二八杠3元底'],
        8003 => ['roomId' => 8003, 'roomName' => '抢庄二八杠10元底'],
        8004 => ['roomId' => 8004, 'roomName' => '抢庄二八杠50元底'],

        9001 => ['roomId' => 9001, 'roomName' => '龙虎大战(俱乐部-1号桌)'],
        9002 => ['roomId' => 9002, 'roomName' => '龙虎大战(俱乐部-2号桌)'],
        9003 => ['roomId' => 9003, 'roomName' => '龙虎大战(俱乐部-3号桌)'],
        2101 => ['roomId' => 2101, 'roomName' => '红黑大战(俱乐部-1号桌)'],
        2102 => ['roomId' => 2102, 'roomName' => '红黑大战(俱乐部-2号桌)'],
        2103 => ['roomId' => 2103, 'roomName' => '红黑大战(俱乐部-3号桌)'],
        9301 => ['roomId' => 9301, 'roomName' => '百人牛牛(俱乐部-1号桌)'],
        9302 => ['roomId' => 9302, 'roomName' => '百人牛牛(俱乐部-2号桌)'],
        9303 => ['roomId' => 9303, 'roomName' => '百人牛牛(俱乐部-3号桌)'],
        9201 => ['roomId' => 9201, 'roomName' => '百人金花(俱乐部-1号桌)'],
        9202 => ['roomId' => 9202, 'roomName' => '百人金花(俱乐部-2号桌)'],
        9203 => ['roomId' => 9203, 'roomName' => '百人金花(俱乐部-3号桌)'],
        9401 => ['roomId' => 9401, 'roomName' => '百人二八杠(俱乐部-1号桌)'],
        9402 => ['roomId' => 9402, 'roomName' => '百人二八杠(俱乐部-2号桌)'],
        9403 => ['roomId' => 9403, 'roomName' => '百人二八杠(俱乐部-3号桌)'],
    ];

    return $arr;
}

function getRate()
{
    $sysconfig = \app\model\SystemConfig::first();
    $rs = trim(file_get_contents($sysconfig->USDT_RATE_URL));
    $rs = substr($rs, 3);
    return intval($rs * 100);
}

//端口检测
function check_port($ip, $port, $timeout)
{
    $conn = @fsockopen($ip, $port, $errno, $errstr, $timeout);
    if ($conn) {
        fclose($conn);
        return 1;
    } else {
        return 0;
    }
}
function check_env($header)
{
    $ext = 'local';
    $headInfo = $header['host'];
    $headerArr = explode(':',$headInfo);
    if(stristr($headerArr[0],'192.168.128.156')){
        $ext = 'test';
    }elseif(stristr($headerArr[0],'admin.vypfdu.com')){
        $ext = 'prod';
    }elseif(stristr($headerArr[0],'47.243.92.221')){
        $ext = 'prod';
    }elseif(stristr($headerArr[0],'cmsyfb.ng28.uk')){
        $ext = 'preview';
    }
    return $ext;
}
function create_ServerLst($configs,$ext)
{
    $upload = new functionUpload();
    $newConfigs = [];
    foreach ($configs as $config) {
        $newConfigs[$config['lineLevel']][] = $config;
    }
    $content = json_encode($configs, JSON_UNESCAPED_SLASHES);
    $content = encr($content);
    $file_name = md5(md5(SL_KEY1) . SL_KEY2) . '.' . $ext;
    $file = UPFILE_PATH . $file_name;
    if (file_put_contents($file, $content)) {
        $uploadres = $upload->aliOssUpload(1, $file_name);
    }

    foreach ($newConfigs as $nk => $nc) {
        if (in_array($nk, ['1000', '1001', '1002'])) continue;
        $nc = array_merge($nc, $newConfigs['1000'], $newConfigs['1001'], $newConfigs['1002']);
        if ($nk == '6') {
            $nc = array_merge($nc, $newConfigs['0'], $newConfigs['1'], $newConfigs['2'], $newConfigs['3'], $newConfigs['4'], $newConfigs['5']);
        } elseif ($nk == '5') {
            $nc = array_merge($nc, $newConfigs['0'], $newConfigs['1'], $newConfigs['2'], $newConfigs['3'], $newConfigs['4']);
        } elseif ($nk == '4') {
            $nc = array_merge($nc, $newConfigs['0'], $newConfigs['1'], $newConfigs['2'], $newConfigs['3']);
        } elseif ($nk == '3') {
            $nc = array_merge($nc, $newConfigs['0'], $newConfigs['1'], $newConfigs['2']);
        } elseif ($nk == '2') {
            $nc = array_merge($nc, $newConfigs['0'], $newConfigs['1']);
        } elseif ($nk == '1') {
            $nc = array_merge($nc, $newConfigs['0']);
        }
        $content = json_encode($nc, JSON_UNESCAPED_SLASHES);
        $content = encr($content);
        $file_name = md5(md5(SL_KEY1) . SL_KEY2) . $nk . '.' . $ext;
        $file = UPFILE_PATH . $file_name;
        if (file_put_contents($file, $content)) {
            $uploadres = $upload->aliOssUpload(1, $file_name);
        }
    }
    return true;
}

function create_ServerLst2($configs, $ext, $levelPass)
{
    $upload = new functionUpload();
    $aes = new \Aes();
    $newConfigs = [];
    foreach ($configs['s'] as $config) {
        $newConfigs[$config['lineLevel']][] = $config;
    }
    foreach ($newConfigs as $nk => &$nc) {
        if (in_array($nk, ['10', '1000', '1001', '1002', '1003'])) continue;
        if ($nk == '0') {
            $nc = array_merge($nc, $newConfigs['10'], $newConfigs['1000'], $newConfigs['1001'], $newConfigs['1002'], $newConfigs['1003']);
            continue;
        }
        $nc = json_encode($nc, JSON_UNESCAPED_SLASHES);
        $aes->setKey($levelPass[$nk]);
        $nc = $aes->encrypt($nc);
    }
    unset($newConfigs['10'], $newConfigs['1000'], $newConfigs['1001'], $newConfigs['1002'], $newConfigs['1003']);
    $configs['s'] = $newConfigs;

    /*$configs['g'] = json_encode($configs['g'], JSON_UNESCAPED_SLASHES);
    $aes->setKey($levelPass['g']);
    $configs['g'] = $aes->encrypt($configs['g']);

    $configs['m'] = json_encode($configs['m'], JSON_UNESCAPED_SLASHES);
    $aes->setKey($levelPass['m']);
    $configs['m'] = $aes->encrypt($configs['m']);*/

    $aes->resetKey();
    $content = json_encode($configs, JSON_UNESCAPED_SLASHES);
    $content = $aes->encrypt($content);
    //echo "<pre>";print_r($configs);exit;
    $file_name = md5(md5(SL_KEY1) . '20190113123456790') . '.' . $ext;
    $file = UPFILE_PATH . $file_name;
    if (file_put_contents($file, $content)) {
        $uploadres = $upload->aliOssUpload(1, $file_name);
    }
    return true;
    //写入redis
//    $redis = new redisent\Redis(REDIS_DSN);
//    $redis->set($file_name, $content);
}

function hidestr($string, $start = 0, $length = 0, $re = '*') {
    if (empty($string)) return false;
    $strarr = array();
    $mb_strlen = mb_strlen($string);
    while ($mb_strlen) {//循环把字符串变为数组
        $strarr[] = mb_substr($string, 0, 1, 'utf8');
        $string = mb_substr($string, 1, $mb_strlen, 'utf8');
        $mb_strlen = mb_strlen($string);
    }
    $strlen = count($strarr);
    $begin  = $start >= 0 ? $start : ($strlen - abs($start));
    $end    = $last   = $strlen - 1;
    if ($length > 0) {
        $end  = $begin + $length - 1;
    } elseif ($length < 0) {
        $end -= abs($length);
    }
    for ($i=$begin; $i<=$end; $i++) {
        $strarr[$i] = $re;
    }
    if ($begin >= $end || $begin >= $last || $end > $last) return false;
    return implode('', $strarr);
}


function msectime()
{
    list($msec, $sec) = explode(' ', microtime());
    $msectime = (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
    return $msectime;
}

function getDateFromRange($startdate, $enddate){
    $stimestamp = strtotime($startdate);
    $etimestamp = strtotime($enddate);
    $days = ($etimestamp-$stimestamp)/86400+1;
    $date = [];
    for($i=0; $i<$days; $i++){
        $date[] = date('Y-m-d', $stimestamp+(86400*$i));
    }
    return $date;
}

function dd($obj) {
    echo "\n";
    print_r($obj);
}

function unsetFieldFromArray($arr, $k = '_id') {
    if (is_array($arr) && !empty($arr)) {
        foreach ($arr as &$item) {
            if (isset($item[$k])) {
                unset($item[$k]);
            }
        }
    }
    return $arr;
}

function gTree($tree, $pid)
{
    $p = [];
    if (isset($tree[$pid])) {
        foreach ($tree[$pid] as $t) {
            $p[] = ['pid'=>$pid, 'zhishu'=>0, 'userId'=>$t];
            if (isset($tree[$t])) {
                $tt = gTree($tree, $t);
                if ($tt) {
                    foreach ($tt as $ttt) {
                        $p[] = $ttt;
                    }
                }
            }
        }
    }
    //echo '<pre>';print_r($p);
    return $p;
}

function teamPeople($pid)
{
    $userIdArr = [];
    $rs = \app\model\GameUser::where(["promoterId" => $pid])->select("userId")->get()->toArray();
    if (!empty($rs)) {
        foreach ($rs as $r) {
            $userIdArr[] = $r['userId'];
            $userIdArr2 = teamPeople($r['userId']);
            if (!empty($userIdArr2)) {
                $userIdArr = array_merge($userIdArr, $userIdArr2);
            }
        }
    }
    return $userIdArr;
}

function ys($v) {
    $m = [
        '0' => '03',
        '1' => '13',
        '2' => '23',
        '3' => '33',
        '4' => '04',
        '5' => '14',
        '6' => '24',
        '7' => '34',
        '8' => '05',
        '9' => '15',
        '10' => '25',
        '11' => '35',
        '12' => '06',
        '13' => '16',
        '14' => '26',
        '15' => '36',
        '16' => '07',
        '17' => '17',
        '18' => '27',
        '19' => '37',
        '20' => '08',
        '21' => '18',
        '22' => '28',
        '23' => '38',
        '24' => '09',
        '25' => '19',
        '26' => '29',
        '27' => '39',
        '28' => '0A',
        '29' => '1A',
        '30' => '2A',
        '31' => '3A',
        '32' => '0B',
        '33' => '1B',
        '34' => '2B',
        '35' => '3B',
        '36' => '0C',
        '37' => '1C',
        '38' => '2C',
        '39' => '3C',
        '40' => '0D',
        '41' => '1D',
        '42' => '2D',
        '43' => '3D',
        '44' => '01',
        '45' => '11',
        '46' => '21',
        '47' => '31',
        '48' => '02',
        '49' => '12',
        '50' => '22',
        '51' => '32',
        '52' => 'small',
        '53' => 'big',
    ];
    if (isset($m[$v])) return $m[$v];
    return '00';
}

function decomposeCardValue($cardValue, $len) {
    $items = [];
    $totalLen = strlen($cardValue);
    $start = 0;
    do {
        $items[] = substr($cardValue, $start, $len);
        $start += $len;
    } while($start < ($totalLen - 1));
    return $items;
}

function filterPlayRecordUserId(&$card,$userId,$gameId,$num = 0){
    if(in_array($gameId,[900, 620])){
        foreach($userId as $k => $v){
            $card[$k]['userId'] = $v;
        }
    }

    if(in_array($gameId,[940])){
        foreach($userId as $k => $v){
            $card[$k+1]['userId'] = $v;
        }
    }

    if(in_array($gameId,[720])){
        foreach($card as $k => $v){    
            if($k < $num){
                $card[$k]['userId'] = filterUserId($userId,$k -1);
            }                                                   
        }
    }

    if(in_array($gameId,[920,930])){
        foreach($card as $k => $v){    
            if($k < $num - 1){
                $card[$k + 1]['userId'] = filterUserId($userId,$k);
            }                                                   
        }
    }

    if(in_array($gameId,[830,860,810,870,890,220,850,880,820,800])){
        foreach($card as $k => $v){    
            if($k < $num){
                $card[$k]['userId'] = filterUserId($userId,$k);
            }        
        }        
    }

    if(in_array($gameId,[550,910,450,420,600,300,100,210])){
        foreach($card as $k => $v){  
            if(is_numeric($k) && !in_array($k,[999,9999])){
                $card[$k]['userId'] = filterUserId($userId,$k);
            }                   
        }
    }

    if(in_array($gameId,[950])){
        $cart = [];
        foreach($card['list'] as $k => $v){
            $data = [
                'name' => $v,
                'userId' => filterUserId($userId,$k)
            ]; 
            $cart[] = $data;           
        }
        $card['list'] = $cart;
    }

    return $card;
}

function filterUserId($data,$index){
    $userId = [];
    foreach($data as $k => $v){
        if($k == $index){
            $userId = $v;
        }
    }
    return array_unique($userId);
}

function BetResults(&$card,$num,$gameId = 0){
    $res = $card[$num];
    foreach($card as $k => $v){
        if($k < $num){
            if($gameId == 720){
                $card[$k]['res'] = $res[$k];
            }else{
                $card[$k][] = $res[$k];
            }           
        }                   
    }
}

function CheckUserBet(&$data,$index){
    $state = false;
    if($data){
        foreach ($data as $k => $v) {
            if($v > 0){
                $state = true;
            }
        }

        if(!$state){
            $data[$index + 1] = 999;
        }
    }

}

function UserBetArea($data,$type){
    $bet_area = [];
    if(!$data){
        return $bet_area;
    }
    if(in_array($type,['club','play'])){
        foreach ($data as $k => $v) {
            CheckUserBet($v['cellScore'],$k);
            if($v['cellScore']) {
                foreach ($v['cellScore'] as $key => $val) {
                    if($val){
                        $bet_area[$key][]= $v['userId'];
                    }
                }
            }
        }
    }

    if(in_array($type,['friends'])){
        foreach ($data as $k => $v) {
            $bet_area[$v['chairId']][] = $v['userId'];
        }
    }

    return $bet_area;
}

function littleToBigEndian($little) {
    return implode('',array_reverse(str_split($little,2)));
}

function decryptBanker(&$bankerId,$cardValue,$num = 16){
    $banker = substr($cardValue,strlen($cardValue) - $num,strlen($cardValue));
    if($banker){
        $bankerId = hexdec(littleToBigEndian($banker));
    }
}

function filterPlayer(&$player,$userId){
    if(!$userId){
        return $player;
    }
    foreach ($userId as $k => $v){
        foreach ($v as $k1 => $v1){
            $player[] = $v1;
        }
    }

    $player = array_unique($player);
}

function getClubRole($clubMember){
    $name = "";
    if (!empty($clubMember)) {
        if($clubMember['status'] == 1){
            $name = "会员";
        }else if ($clubMember['status'] == 2 && $clubMember['clubId'] !== $clubMember['userId']){
            $name = "合伙人";
        }else{
            $name = "盟主";
        }
    }
    return $name;
}

function getPromoterId(){
    $promoter = Promoter::select('promoterId')->get() -> toArray();
    $promoterIds = [];
    if($promoter) {
        foreach ($promoter as $k => $v) {
            $promoterIds[] = $v['promoterId'];
        }
    }
    return $promoterIds;
}

function httpRequestJson($url, $data,$header = array())
{
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($curl, CURLOPT_POST, 1);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
    curl_setopt($curl, CURLOPT_HEADER, 0);
    if(!$header){
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json; charset=utf-8',
                'Content-Length: ' . strlen($data),
            )
        );
    }else{
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
    }
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    $result = curl_exec($curl);
    curl_close($curl);
    return $result;
}

function createSign($args,$key){
    $sign = '';
    $signPars = dataFormat($args);
    $signPars = substr($signPars, 0, strlen($signPars) - 1);
    $sign=base64_encode(hash_hmac('sha1', $signPars, $key, true));
    return $sign;
}

function dataFormat($args){
    $signPars = "";
    ksort($args);
    foreach ($args as $k => $v) {
        if ("" != $v && "sign" != $k) {
            $signPars .= $k . "=" . $v . "&";
        }
    }

    return $signPars;
}

function export_path()
{
    return BASE_PATH . DIRECTORY_SEPARATOR . 'public/byqexport';
}

function formatTimestampToMongo($timestamp)
{
    return new UTCDateTime($timestamp*1000);
}

function formatMoneyFromMongo($money)
{
    return round($money*0.01, 2);
}

function formatMoneyFromMongoNo($money)
{
    return (float)sprintf("%.2f",substr(sprintf("%.3f", $money), 0, -1));
}

function arraySort($array,$keys,$type='asc'){
    if(!is_array($array)||empty($array)||!in_array(strtolower($type),array('asc','desc'))) return '';
    $keysvalue=array();
    foreach($array as $key=>$val){
        if(!empty($val[$keys])){
            //$val[$keys]=str_replace('-','',$val[$keys]);//考虑负数情况
            $val[$keys]=str_replace(' ','',$val[$keys]);
            $val[$keys]=str_replace(':','',$val[$keys]);
            $keysvalue[] =$val[$keys];
        }else{
            $keysvalue[] =0;
        }
    }
    asort($keysvalue);//key值排序
    reset($keysvalue);//指针重新指向数组第一个
    foreach($keysvalue as $key=>$vals){
        $keysort[]=$key;
    }
    $keysvalue=array();
    $count=count($keysort);
    if(strtolower($type)!='asc'){
        for($i=$count-1;$i>=0;$i--){
            $keysvalue[]=$array[$keysort[$i]];
        }
    }else{
        for($i=0;$i<$count;$i++){
            $keysvalue[]=$array[$keysort[$i]];
        }
    }
    return $keysvalue;
}

//设置锁
function setMutex($type, $orderId, $timeout = 10)
{
    $curTime = time();
    $key = "{$type}:{$orderId}";
    $flag = \support\bootstrap\Redis::set($key, $curTime, 'EX', $timeout, "NX");
    if ($flag) {
        return true;
    }
    return false;
}

//释放锁
function delMutex($type, $orderId){
    $key = "{$type}:{$orderId}";
    \support\bootstrap\Redis::del($key);
}

//统计更新频率
function statisticalInterval() {
    $key = "stat_money_interval";
    return \support\bootstrap\Redis::get($key);
}

function randomStr($length = 8, $char = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ')
{
    if (!is_int($length) || $length < 0) {
        return false;
    }

    $string = '';
    for ($i = $length; $i > 0; $i--) {
        $string .= $char[mt_rand(0, strlen($char) - 1)];
    }

    return $string;
}

//金币场税收纯利润 //系统税收-代理提成
function taxProfit($statPromoterDailyData, $PlatformDataRecord) {
    if (empty($statPromoterDailyData) || empty($PlatformDataRecord)) return 0;
    return formatMoneyFromMongoNo($statPromoterDailyData['teamRevenue'] - $PlatformDataRecord['todayPromoterScore']);
}
function taxProfitGrand($statPromoterDailyData, $PlatformDataRecord) {
    if (empty($statPromoterDailyData) || empty($PlatformDataRecord)) return 0;
    return formatMoneyFromMongoNo($statPromoterDailyData['totalTeamRevenue'] - $PlatformDataRecord['totalPromoterScore']);
}
//金币场官方盈亏 //系统税收 + 游戏输赢 - 代理提成 - 奖励金额
function profitOrLoss($statPromoterDailyData, $PlatformDataRecord) {
    if (empty($statPromoterDailyData) || empty($PlatformDataRecord)) return 0;
    return formatMoneyFromMongoNo($statPromoterDailyData['teamRevenue'] + $statPromoterDailyData['teamGameWinScore'] - $PlatformDataRecord['todayPromoterScore'] - $PlatformDataRecord['todayRewardScore']);
}
function profitOrLossGrand($statPromoterDailyData, $PlatformDataRecord) {
    if (empty($statPromoterDailyData) || empty($PlatformDataRecord)) return 0;
    return formatMoneyFromMongoNo($statPromoterDailyData['totalTeamRevenue'] + $statPromoterDailyData['totalTeamGameWinScore'] - $PlatformDataRecord['totalPromoterScore'] - $PlatformDataRecord['totalRewardScore']);
}

//俱乐部税收纯利润 //系统税收-代理提成
function clubTaxProfit($PlatformDataRecord) {
    if (empty($PlatformDataRecord)) return 0;
    return formatMoneyFromMongoNo($PlatformDataRecord['revenue'] - $PlatformDataRecord['promoterScore']);
}
function clubTaxProfitGrand($PlatformDataRecord) {
    if (empty($PlatformDataRecord)) return 0;
    return formatMoneyFromMongoNo($PlatformDataRecord['totalRevenue'] - $PlatformDataRecord['totalPromoterScore']);
}
//俱乐部官方盈亏 //系统税收 + 游戏输赢 - 代理提成 - 奖励金额
function clubProfitOrLoss($PlatformDataRecord) {
    if (empty($PlatformDataRecord)) return 0;
    return formatMoneyFromMongoNo($PlatformDataRecord['revenue'] + $PlatformDataRecord['platformWinScore'] - $PlatformDataRecord['promoterScore'] - $PlatformDataRecord['rewardScore']);
}
function clubProfitOrLossGrand($PlatformDataRecord) {
    if (empty($PlatformDataRecord)) return 0;
    return formatMoneyFromMongoNo($PlatformDataRecord['totalRevenue'] + $PlatformDataRecord['totalPlatformWinScore'] - $PlatformDataRecord['totalPromoterScore'] - $PlatformDataRecord['totalRewardScore']);
}

//汇总-（金币场+俱乐部）
function dataSummary($coinStatPromoterDailyData, $clubStatPromoterDailyData, $coinPlatformDataRecord, $clubPlatformDataRecord, $field) {
    $return = 0;
    if (empty($coinStatPromoterDailyData) || empty($coinPlatformDataRecord) || empty($clubPlatformDataRecord)) return $return;
    if ($field == 'gameWinScore') $return = $coinStatPromoterDailyData['teamGameWinScore'] + $clubPlatformDataRecord['platformWinScore'];
    if ($field == 'gameWinScoreGrand') $return = $coinStatPromoterDailyData['totalTeamGameWinScore'] + $clubPlatformDataRecord['totalPlatformWinScore'];
    if ($field == 'allBet') $return = $coinStatPromoterDailyData['teamFlowAmount'] + $clubPlatformDataRecord['allBet'];
    if ($field == 'allBetGrand') $return = $coinStatPromoterDailyData['totalTeamFlowAmount'] + $clubPlatformDataRecord['totalAllBet'];
    if ($field == 'vaildBet') $return = $coinStatPromoterDailyData['teamValidFlowAmount'] + $clubPlatformDataRecord['validBet'];
    if ($field == 'vaildBetGrand') $return = $coinStatPromoterDailyData['totalTeamValidFlowAmount'] + $clubPlatformDataRecord['totalValidBet'];
    if ($field == 'revenue') $return = $coinStatPromoterDailyData['teamRevenue'] + $clubPlatformDataRecord['revenue'];
    if ($field == 'revenueGrand') $return = $coinStatPromoterDailyData['totalTeamRevenue'] + $clubPlatformDataRecord['totalRevenue'];

    if ($field == 'rewardScore') $return = $coinPlatformDataRecord['todayRewardScore'] + $clubPlatformDataRecord['rewardScore'];
    if ($field == 'rewardScoreGrand') $return = $coinPlatformDataRecord['totalRewardScore'] + $clubPlatformDataRecord['totalRewardScore'];
    if ($field == 'promoterScore') $return = $coinPlatformDataRecord['todayPromoterScore'] + $clubPlatformDataRecord['promoterScore'];
    if ($field == 'promoterScoreGrand') $return = $coinPlatformDataRecord['totalPromoterScore'] + $clubPlatformDataRecord['totalPromoterScore'];

    //金币场的税收纯利润 + 俱乐部的税收纯利润
    if ($field == 'taxProfit') {
        $return = taxProfit($coinStatPromoterDailyData, $coinPlatformDataRecord) + clubTaxProfit($clubPlatformDataRecord);
    }
    if ($field == 'taxProfitGrand') {
        $return = taxProfitGrand($coinStatPromoterDailyData, $coinPlatformDataRecord) + clubTaxProfitGrand($clubPlatformDataRecord);
    }
    //金币场官方盈亏 + 俱乐部官方盈亏
    if ($field == 'profitOrLoss') {
        $return = profitOrLoss($coinStatPromoterDailyData, $coinPlatformDataRecord) + clubProfitOrLoss($clubPlatformDataRecord);
    }
    if ($field == 'profitOrLossGrand') {
        $return = profitOrLossGrand($coinStatPromoterDailyData, $coinPlatformDataRecord) + clubProfitOrLossGrand($clubPlatformDataRecord);
    }
    //平台余额
    if ($field == 'playerTotalAmountGrand') {
        $return = $coinPlatformDataRecord['totalScore'] + $coinPlatformDataRecord['totalBankScore'] + $coinPlatformDataRecord['currentPromoterScore'] + $clubPlatformDataRecord['currentPromoterScore'];
    }


    return formatMoneyFromMongoNo($return);
}