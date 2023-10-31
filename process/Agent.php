<?php

namespace process;

use app\model\GameChannelMo as GameChannel;
use app\model\Promoter;
use app\model\PromoterTemplateConfigs;
use app\model\SystemConfig;
use support\bootstrap\Redis;
class Agent
{
    const queueName = "HallServerToWebQueue";
    public function onWorkerStart() {
        //{"type":1, data:{"NO":1,"promoterId":12345,“channelId":1, "time":1639802237}}
        //$demoData = ["type"=>1, "data"=>['NO'=>1,'promoterId' => 62128145, 'channelId' => 1, 'time' => time()]];

        $sleepSecond = 1;
        while (true) {
            //static::batchBuildApk3(); break;
            $day = date("Y-m-d");
            $dayTime = date("Y-m-d H:i:s");
            $log = runtime_path() . "/genAgentShortUrl/genAgentShortUrl".$day.".log";
            $data = Redis::rPop(self::queueName);
            if (empty($data)) {
                //Redis::lPush($queueName, json_encode($demoData));
                sleep($sleepSecond);
                continue;
            }
            dd($dayTime . "--" . $data);
            $param = json_decode($data, true); //$promoterId  $channelId
            $param['promoterId'] = $param['data']['promoterId']??0;
            $param['channelId'] = $param['data']['channelId']??0;
            if ($param['type'] == 1) {
                //生成短链接 打包
                $flagShortUrl = static::genAgentShortUrl($param, $log, $dayTime, $day);
                if ($flagShortUrl) {
                    $flagBuildApk = static::buildApk($param);
                    continue;
                }
                Redis::rPush(self::queueName, $data);
            } elseif ($param['type'] == 2) {
                //重新生成短链接
                $flagShortUrl = static::genAgentShortUrl($param, $log, $dayTime, $day);
                if ($flagShortUrl) {
                    //$flagBuildApk = static::buildApk($param);
                    continue;
                }
                Redis::rPush(self::queueName, $data);
            } elseif ($param['type'] == 0) {
                //批量打包
                //{"data":{"NO":10,"time":1639993639},"type":0}
                static::buildApk($param);
            }
        }
    }

    private static function genAgentShortUrl($param, $log, $dayTime, $data) {
        if (!isset($param['promoterId']) || !isset($param['channelId'])) {
            file_put_contents($log, $dayTime .' 参数错误 '. $data . "\n", FILE_APPEND);
            return false;
        }
        //检测代理是否存在
        $where = ['promoterId' => $param['promoterId']];
        $promoter = Promoter::where($where)->first();
        if (!$promoter) {
            file_put_contents($log, $dayTime .' 无此代理 '. $data . "\n", FILE_APPEND);
            return false;
        }
        //检测渠道是否存在
        $where = ['id' => $param['channelId']];
        $channel = GameChannel::where($where)->first();
        if (!$channel) {
            file_put_contents($log, $dayTime .' 无此渠道 '. $data . "\n", FILE_APPEND);
            return false;
        }
        $tuiDomain = SystemConfig::first();
        $domainArr = $tuiDomain["DOWNLOAD_DOMAIN"];
        $sk = 0;
        if (isset($promoter->tuiDomain)) {
            $sk = array_search($promoter->tuiDomain, $domainArr);
            $sk++;
        }
        if ($sk >= count($domainArr)) {
            $sk = 0;
        }
        $domain = "https://www.{$domainArr[$sk]}/";

        //检测模板设置
        $where = ['agentId' => $param['promoterId']];
        $template = PromoterTemplateConfigs::where($where)->first();
        if (!$template) {
            file_put_contents($log, $dayTime .' 代理模板配置不存在，生成模板数据 '. $data . "\n", FILE_APPEND);
            //$xxtea = new Xxtea();
            do {
                $info = [
                    "promoterId" => $param['promoterId'],
                    "channelId" => $param['channelId'],
                    "NO" => $param['data']['NO'],
                    "rand" => time()
                ];
                $mark = base64_encode(xxtea_encrypt(json_encode($info), ""));
            } while (str_contains($mark, "+") or str_contains($mark, "/"));
            $promotionUrl = $domain . randomStr(rand(3, 5)) . ".html?" . randomStr(rand(3, 5)) . "=" . $mark . "&channelId=" . $param['channelId'];
            $note = "生成于" . $dayTime;
            $insertOneResult = PromoterTemplateConfigs::insert([
                'agentId' => $param['promoterId'],
                'note' => $note,
                'mark' => $mark,
                'createTime' => new \MongoDB\BSON\UTCDateTime,
            ]);
            if (!$insertOneResult) {
                file_put_contents($log, $dayTime .' 代理模板配置不存在，生成模板数据失败 '. $data . "\n", FILE_APPEND);
                return false;
            }
        } else {
            $mark = $template->mark;
            $promotionUrl = $domain . randomStr(rand(3, 5)) . ".html?" . randomStr(rand(3, 5)) . "=" . $mark . "&channelId=" . $param['channelId'];
        }

        $shortUrl = static::Aftfiy($promotionUrl);
        dd($shortUrl);
        if (empty($shortUrl)) {
            file_put_contents($log, $dayTime .' 短链接生成失败 '. $data . "\n", FILE_APPEND);
            return false;
        }
        //打包
        //static::buildApk($param);

        $where = ['promoterId' => $param['promoterId']];
        $updateData = ['isPackage' => 1, "URL" => $shortUrl, "tuiDomain" => $domainArr[$sk], "lastUrlDate" => new \MongoDB\BSON\UTCDateTime];
        $updateResult = Promoter::where($where)->update($updateData);
        if (!$updateResult) {
            file_put_contents($log, $dayTime .' 数据库执行更新失败 '. $data . "\n", FILE_APPEND);
            return false;
        }
        return true;
    }

    private static function Aftfiy($promotionUrl) {
        $api_url = "http://byq.aftfiy.cn/dwz.php";
        $api_url .= "?type=vx&longurl=".urlencode(filter_var($promotionUrl, FILTER_SANITIZE_URL));//."&format=json";

        $res = httpRequest($api_url, 'get');
        $data = json_decode($res, true);
        if (isset($data['ae_url']) && !empty($data['ae_url'])) {
            return $data['ae_url'];
        }
        return "";
    }

    private static function buildApk($param) {
        $mathorP = runtime_path() . '/apk1/byq.apk';
        $promoterP = runtime_path() . "/apk/{$param['data']['NO']}.apk";
        $walle = runtime_path() . '/apk1/walle-cli-all.jar';
        $command = "java -jar {$walle} put -c {$param['data']['NO']} {$mathorP} {$promoterP}";
        dd($command);
        exec($command, $output);
        dd($output);
    }

    private static function batchBuildApk() {
        $param = ["channelId"=>1, 'promoterId'=>0];
        for ($i=1;$i<=100;$i++) {
            $param['promoterId'] = $i;
            $mathorP = runtime_path() . '/apk/byq.apk';
            $promoterP = runtime_path() . "/apk/1_{$param['channelId']}_{$param['promoterId']}.apk";
            $walle = runtime_path() . '/apk/walle-cli-all.jar';
            $command = "java -jar {$walle} put -c {$param['promoterId']} {$mathorP} {$promoterP}";
            dd($command);
            exec($command, $output);
            dd($output);
        }
    }

    private static function batchBuildApk2() {
        $param = ["channelId"=>1, 'promoterId'=>0];
        $pidStrArr = [];
        for ($i=1;$i<=100;$i++) {
            $pidStrArr[] = $i;
        }
        $pidStr = implode(",", $pidStrArr);
        dd($pidStr);
        $mathorP = runtime_path() . '/apk/byq.apk';
        //$promoterP = runtime_path() . "/apk/1_{$param['channelId']}_{$param['promoterId']}.apk";
        $walle = runtime_path() . '/apk/walle-cli-all.jar';
        $command = "java -jar {$walle} batch -c {$pidStr} {$mathorP}";
        dd($command);
        exec($command, $output);
        dd($output);
    }

    private static function batchBuildApk3() {

        $mathorP = runtime_path() . '/apk/byq.apk';
        //$promoterP = runtime_path() . "/apk/1_{$param['channelId']}_{$param['promoterId']}.apk";
        $walle = runtime_path() . '/apk/walle-cli-all.jar';
        $pidStr = runtime_path() . '/apk/channel';
        $command = "java -jar {$walle} batch -f {$pidStr} {$mathorP}";
        dd($command);
        exec($command, $output);
        dd($output);
    }
}