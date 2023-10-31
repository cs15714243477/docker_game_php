<?php
use Alipay\EasySDK\Kernel\Factory;
use Alipay\EasySDK\Kernel\Util\ResponseChecker;
use Alipay\EasySDK\Kernel\Config as AliConfig;

function DrawMoney ($order,$exchangeService) {
    $return = [
        'result'      => true,
        'description' => '请求成功，请稍候查看转账结果'
    ];
    try{
        if (in_array($exchangeService['exchangeServiceId'], [5])) {
            $qihangReturn = qiHang($order,$exchangeService);
            if (isset($qihangReturn['result']) && isset($qihangReturn['description'])) return $qihangReturn;
        } elseif (in_array($exchangeService['exchangeServiceId'], [3, 8])) {
            $feiyunReturn = feiYun($order,$exchangeService);
            if (isset($feiyunReturn['result']) && isset($feiyunReturn['description'])) return $feiyunReturn;
        } elseif (in_array($exchangeService['exchangeServiceId'], [9])) {
            $xinReturn = xinXin($order,$exchangeService);
            if (isset($xinReturn['result']) && isset($xinReturn['description'])) return $xinReturn;
        } else {
            $return['result'] = false;
            $return['description'] = '接口通讯故障(3)';
        }
        return $return;
    } catch (\Exception $e) {
        $return['result'] = false;
        $return['description'] = '代付处理异常';
        return $return;
    }
}

//菲云
function feiYun($order,$exchangeService) {
    $data = [
        "orderid" => $order['orderId'],
        "bank"    => $order['bankName'],
        "card"    => $order['bankCardNum'],
        "name"    => $order['bankCardName'],
        "money"   => $order['requestMoney'],
        'sign'    => md5($exchangeService['M_KEY'] . $order['orderId'])
    ];

    $rs = httpRequest($exchangeService['M_URL'], "POST", $data);
    if (empty($rs)) {
        $return['result'] = false;
        $return['description'] = '接口通讯故障(1)';
        return $return;
    }
    $r = json_decode($rs, true); //json转数组
    if (empty($r)) {
        $return['result'] = false;
        $return['description'] = '接口通讯故障(2)';
        return $return;
    }
    $return['result'] = $r['Result']??false;
    $return['description'] = $r['Description']??'代付接口未知错误';
    return $return;
}
//鑫鑫
function xinXin($order,$exchangeService) {
    $array_banks = [
        '建设银行' => 'CCB',
        '建设' => 'CCB',
        '建行' => 'CCB',
        '中国建设银行' => 'CCB',
        '中国建设' => 'CCB',
        '工商银行' => 'ICBC',
        '中国工商银行' => 'ICBC',
        '工商' => 'ICBC',
        '农业银行' => 'ABC',
        '中国农业银行' => 'ABC',
        '农业' => 'ABC',
        '中国银行' => 'BOC',
        '中国邮政银行' => 'PSBC',
        '中国邮政' => 'PSBC',
        '中国邮政储蓄银行' => 'PSBC',
        '中国邮政储蓄' => 'PSBC',
        '中国储蓄邮政银行' => 'PSBC',
        '邮政储蓄' => 'PSBC',
        '招商银行' => 'CMB',
        '招商' => 'CMB',
        '深圳招商银行' => 'CMB',
        '平安银行' => 'PAB',
        '中国平安' => 'PAB',
        '中国平安银行' => 'PAB',
        '浦发银行' => 'SPDB',
        '浦发' => 'SPDB',
        '上海浦东发展银行' => 'SPDB',
        '交通银行' => 'BCM',
        '中信银行' => 'CITIC',
        '光大银行' => 'CEB',
        '中国光大银行' => 'CEB',
        '兴业银行' => 'CIB',
        '北京银行' => 'BOB',
        '华夏银行' => 'HXB',
        '广发银行' => 'CGB',
        '民生银行' => 'CMBC',
        '中国民生银行' => 'CMBC',
        '厦门银行' => 'BOXM',
    ];
    if (array_key_exists($order['bankName'], $array_banks)) {
        $bankCode = $array_banks[$order['bankName']];
    } else {
        $return['result'] = false;
        $return['description'] = '支付平台不支持对此银行转账';
        return $return;
    }
    $params = [
        "mch_id" => $exchangeService['M_IDX'],
        "out_trade_no" => $order['orderId'],
        "trans_money" => $order['requestMoney'] * 100,
        "bankCode" => $bankCode,
        "service" => 'BL_WAP_DF_DZ',
        "account_name" => $order['bankCardName'],
        "bank_card" => $order['bankCardNum'],
        'notify_url' => $exchangeService['N_URL'],
    ];
    $signPars = "";
    ksort($params);
    foreach ($params as $k => $v) {
        $signPars .= $k . "=" . $v . "&";
    }
    $signPars .= "key={$exchangeService['M_KEY']}";
    $params["sign"] = strtoupper(md5($signPars));
    $rs = httpRequest($exchangeService['M_URL'], "POST", $params);
    if (empty($rs)) {
        $return['result'] = false;
        $return['description'] = '接口通讯故障(1)';
        return $return;
    }
    $r = json_decode($rs, true); //json转数组
    if (empty($r)) {
        $return['result'] = false;
        $return['description'] = '接口通讯故障(2)';
        return $return;
    }
    //交易通信失败
    if ($r['ret_code'] != 'SUCCESS') {
        $return['result'] = false;
        $return['description'] = $r['ret_message'];
        return $return;
    }
    $return['result'] = true;
    $return['description'] = $r['tradeMessage']??'代付接口未知错误';
    return $return;
}
//启航
function qiHang($order,$exchangeService) {
    //file_put_contents(runtime_path() . "/daifu.log", json_encode($exchangeService) . "\n", FILE_APPEND);
    if($order['requestMoney'] < 2000){
        $return['result'] = false;
        $return['description'] = '金额不能小于2000';
        return $return;
    }
    $array_banks = [
        '工商银行' => 'ICBC', '中国工商银行' => 'ICBC', '工商' => 'ICBC',
        '中国农业银行' => 'ABC','农业银行' => 'ABC',
        '建设银行' => 'CCB', '中国建设银行' => 'CCB', '建设' => 'CCB', '建行' => 'CCB', '中国建设' => 'CCB',
        '中国交通银行' => 'BCM',
        '中国银行' => 'BOC',
        '招商银行' => 'CMB', '中国招商银行' => 'CMB', '招商' => 'CMB',
        '中国邮政储蓄银行' => 'PSBC', '中国邮政' => 'PSBC', '邮政储蓄' => 'PSBC', '邮政' => 'PSBC',
        '中国民生银行' => 'CMBC', '民生' => 'CMBC',
        '华夏银行' => 'HXB', '华夏' => 'HXB',
        '兴业' => 'CIB', '兴业银行' => 'CIB', '中国兴业银行' => 'CIB',
        '广东发展银行' => 'CGB','广发银行' => 'CGB',
        '浦发银行' => 'SPDB', '上海浦东发展银行' => 'SPDB',
        '中国光大银行' => 'CEB', '光大银行' => 'CEB', '光大' => 'CEB',
        '中信银行' => 'CNCB', '中信' => 'CNCB',
        '平安银行' => 'PAB', '平安' => 'PAB',
        '北京银行' => 'BCCB',
        '南京银行' => 'NJCB',
        '江苏银行' => 'JSB',
        '宁波银行' => 'NBCB',
        '北京农村商业银行' => 'BJRCB',
        '上海农村商业银行' => 'SHRCB',
        '武汉农村商业银行' => 'WHRCB',
        '深圳农村商业银行' => 'SRCB',
        '湖北省农村信用社' => 'HBRCC',
        '成都银行' => 'BOCD',
        '四川省农村信用社' => 'SCRC',
        '长沙银行' => 'CSCB',
        '浙商银行' => 'CZB',
        '青岛银行' => 'QDCCB',
        '广州银行' => 'GCB',
        '上海银行' => 'SHB',
        '渤海银行' => 'CBHB',
        '徽商银行' => 'HSB',
        '绵阳市商业银行' => 'MYCC',
        '哈尔滨银行' => 'HRBB',
        '汉口银行' => 'HKB',
        '湖北银行' => 'HBB',
    ];
    if (array_key_exists($order['bankName'], $array_banks)) {
        $bankCode = $array_banks[$order['bankName']];
    } else {
        $return['result'] = false;
        $return['description'] = '支付平台不支持对此银行转账';
        return $return;
    }
    $key = explode(",",$exchangeService['M_KEY']);
    //file_put_contents(runtime_path() . "/daifu.log", json_encode($key) . "\n", FILE_APPEND);
    $data = [
        'mid'   => $exchangeService['M_IDX'],
        'time'   => time(),
        'amount'   => $order['requestMoney'],
        'order_no' => $order['orderId'],
        'ip'      => Getip(),
        'bank_code'  => $bankCode,
        'card_no'  => $order['bankCardNum'],
        'holder_name'  => $order['bankCardName'],
        'notify_url'   => $exchangeService['N_URL']
    ];

    $data['sign'] = createSign($data,$key[0]);
    $Apikey = $key[1];
    $header = ['Content-Type: application/json; charset=utf-8','Authorization: ' . "api-key {$Apikey}"];
    //file_put_contents(runtime_path() . "/daifu.log", json_encode($data) . "\n", FILE_APPEND);
    $rs = httpRequestJson($exchangeService['M_URL'], json_encode($data),$header);
    //file_put_contents(runtime_path() . "/daifu.log", $rsJson . "\n", FILE_APPEND);
    if (empty($rs)) {
        $return['result'] = false;
        $return['description'] = '接口通讯故障(1)';
        return $return;
    }
    $r = json_decode($rs, true); //json转数组
    if (empty($r)) {
        $return['result'] = false;
        $return['description'] = '接口通讯故障(2)';
        return $return;
    }
    if($r['code'] != 200){
        $return['result'] = false;
        $return['description'] = $r['message'];
        return $return;
    }
    $return['result'] = true;
    $return['description'] = '请求成功，请稍候查看转账结果';
    return $return;
}

function getOptions($appId, $name)
{
    $options = new AliConfig();
    $options->protocol = 'https';
    $options->gatewayHost = 'openapi.alipay.com';
    $options->signType = 'RSA2';

    $options->appId = $appId;

    // 为避免私钥随源码泄露，推荐从文件中读取私钥字符串而不是写入源码中
    $aPath = BASE_PATH . DIRECTORY_SEPARATOR . "constant/{$name}/";
    $merchantPrivateKey = file_get_contents($aPath . '163.com_PrivateKey.txt');
    $options->merchantPrivateKey = $merchantPrivateKey;

    $options->alipayCertPath = $aPath . 'alipayCertPublicKey_RSA2.crt';
    $options->alipayRootCertPath = $aPath . 'alipayRootCert.crt';
    $options->merchantCertPath = $aPath . "appCertPublicKey_{$appId}.crt";

    //注：如果采用非证书模式，则无需赋值上面的三个证书路径，改为赋值如下的支付宝公钥字符串即可
    // $options->alipayPublicKey = '<-- 请填写您的支付宝公钥，例如：MIIBIjANBg... -->';

    //可设置异步通知接收服务地址（可选）
    //$options->notifyUrl = "<-- 请填写您的支付类接口异步通知接收服务地址，例如：https://www.test.com/callback -->";

    //可设置AES密钥，调用AES加解密相关接口时需要（可选）
    //$options->encryptKey = "<-- 请填写您的AES密钥，例如：aa4BtZ4tspm2wnXLb1ThQA== -->";
    return $options;
}
function DrawMoney_Ali2($order, $exchangeService){
    $return = [
        'result'      => true,
        'description' => '请求成功，请稍候查看转账结果'
    ];
    if ($exchangeService['exchangeServiceId'] == 12) {
        return meko($order, $exchangeService);
    }
    if ($exchangeService['exchangeServiceId'] == 14) {
        return xindong($order, $exchangeService);
    }
    $newOptions = getOptions($exchangeService['M_IDX'], $exchangeService['controllerName']);
    //echo $newOptions->appId;
    //1. 设置参数（全局只需设置一次）
    try {
        Factory::setOptions($newOptions);
        /*//2. 发起API调用（以支付能力下的统一收单交易创建接口为例）
        $result = Factory::payment()->common()->create("iPhone6 16G", "20200326235526001", "88.88", "2088002656718920");
        $responseChecker = new ResponseChecker();
        //3. 处理响应或异常
        if ($responseChecker->success($result)) {
            echo "调用成功". PHP_EOL;
        } else {
            echo "调用失败，原因：". $result->msg."，".$result->subMsg.PHP_EOL;
        }*/

        //设置系统参数（OpenAPI中非biz_content里的参数）
        //$textParams = array("app_auth_token" => "201712BB_D0804adb2e743078d1822d536956X34");
        $textParams = [
            'app_id' => $newOptions->appId,
        ];

        //设置业务参数（OpenAPI中biz_content里的参数）
        $extendParams = array("identity" => $order['alipayAccount'], "identity_type" => "ALIPAY_LOGON_ID", "name" => $order['alipayName']);
        $bizParams = array(
            "out_biz_no" => $order['orderId'],
            "trans_amount" => $order['payMoney'],
            "product_code" => "TRANS_ACCOUNT_NO_PWD",
            "biz_scene" => "DIRECT_TRANSFER",
            "payee_info" => $extendParams
        );

        $rs = Factory::util()->generic()->execute("alipay.fund.trans.uni.transfer",$textParams, $bizParams);
        //print_r($rs->code);
        //print_r($rs);exit;
        if ($rs->code == 10000) {
            return $return;
        } else {
            $return['result'] = false;
            $return['description'] = $rs->subMsg;
            return $return;
        }

    } catch (Exception $e) {
        //echo "调用失败，". $e->getMessage(). PHP_EOL;;
        $return['result'] = false;
        $return['description'] = $e->getMessage();
        return $return;
    }
}

function meko($order, $exchangeService) {
    $params = [
        "mchid" => $exchangeService['M_IDX'],
        "out_trade_no" => $order['orderId'],
        "money" => $order['payMoney'],
        "bankname" => "支付宝",
        "subbranch" => 1,
        "province" => 1,
        "city" => 1,
        "paypassword" => '123456',
        "accountname" => $order['alipayName'],
        "cardnumber" => $order['alipayAccount'],
        'notifyurl' => $exchangeService['N_URL'],
    ];
    $signPars = "";
    ksort($params);
    foreach ($params as $k => $v) {
        $signPars .= $k . "=" . $v . "&";
    }
    $signPars .= "key={$exchangeService['M_KEY']}";
    $params["pay_md5sign"] = strtoupper(md5($signPars));
    $rs = httpRequest($exchangeService['M_URL'], "POST", $params);
    file_put_contents(runtime_path() . "/meko.log", date("Y-m-d H:i:s") . " 提交数据：". json_encode($params) . " 返回数据" . $rs . "\n", FILE_APPEND);
    if (empty($rs)) {
        $return['result'] = false;
        $return['description'] = '接口通讯故障(1)';
        return $return;
    }
    $r = json_decode($rs, true); //json转数组
    if (empty($r)) {
        $return['result'] = false;
        $return['description'] = '接口通讯故障(2)';
        return $return;
    }
    //交易通信失败
    if ($r['status'] != 'success') {
        $return['result'] = false;
        $return['description'] = $r['msg'];
        return $return;
    }
    $return['result'] = true;
    $return['description'] = $r['msg']??'代付接口未知错误';
    $return['agentpayOrderId'] = $r['transaction_id']??'';
    return $return;
}

function xindong($order, $exchangeService) {
    $params = [
        "pid" => $exchangeService['M_IDX'],
        "money" => $order['payMoney'],
        "act" => 'alipay',
        "pass" => "123456",
        "username" => $order['alipayName'],
        "account" => $order['alipayAccount'],
        'key' => $exchangeService['M_KEY'],
    ];
    /*$signPars = "";
    ksort($params);
    foreach ($params as $k => $v) {
        $signPars .= $k . "=" . $v . "&";
    }
    $signPars .= "key={$exchangeService['M_KEY']}";
    $params["sign"] = strtoupper(md5($signPars));*/
    $rs = httpRequest($exchangeService['M_URL'], "POST", $params);
    file_put_contents(runtime_path() . "/xindong.log", date("Y-m-d H:i:s") . " 提交数据：". json_encode($params) . " 返回数据" . $rs . "\n", FILE_APPEND);
    if (empty($rs)) {
        $return['result'] = false;
        $return['description'] = '接口通讯故障(1)';
        return $return;
    }
    $r = json_decode($rs, true); //json转数组
    if (empty($r)) {
        $return['result'] = false;
        $return['description'] = '接口通讯故障(2)';
        return $return;
    }
    //交易通信失败
    if ($r['code'] != 1) {
        $return['result'] = false;
        $return['description'] = $r['msg'];
        return $return;
    }
    $return['result'] = true;
    $return['description'] = $r['msg']??'代付接口未知错误';
    $return['agentpayOrderId'] = $r['order']??'';
    return $return;
}
