<?php
const SYSTEM_USER_ID_LENGTH = 6;
const COMMON_USER_ID_LENGTH = 8;
const REWARD_ORDER_ID_LENGTH = 35;

const PROMOTER_ID_LENGTH = [5, 8];
const SYSTEM_PROMOTER_ID = 1000;

const GIVE = 999;//赠送
const ACTIVITY_REWARD = 19;
const SYSTEM_REISSUE = 0;
const PAYMENT_REISSUE = 99999999;
const CLUB_ACTIVITY_REWARD = 88888888;
//999-赠送   5-VIP
const GIVE_SCORE_TYPE = ['0' => '官方补发', '99999999' => '支付补发', '88888888' => '俱乐部活动奖励'];
const GIVE_ROOMCARD_TYPE = ['0' => '官方赠送房卡'];
const GIVE_SCORE_ACTIVITY = ['19' => '活动奖励'];

const ISPAY = ['1' => '是', '2' => '否'];
const ISACTIVE = ['1' => '是', '2' => '否'];
const ISNORMAL = ['1' => '是', '2' => '否'];

//阿里云配置信息
const ACCESS_IDX = "LTAI4GCVLZyTmay153oQ5CZA";
const ACCESS_KEY =  "2vHT0XO2F9EOL0CoplqQkssMXbDJj6";
const ACCESS_ENDPOINT = "http://oss-cn-shenzhen.aliyuncs.com";
const ACCESS_BUCKET = "qiqioss";
const ALIYUN_OSS_URL = "https://qiqioss.oss-cn-shenzhen.aliyuncs.com";
const OBJECT_PATH = "rechargeTypeIcon/";
const UPFILE_PATH = BASE_PATH . DIRECTORY_SEPARATOR . 'public' . "/upload/";

const SL_KEY1 = "20200113123456789";//sl签名key1
const SL_KEY2 = "20190113123456789";//sl签名key2

const GOLD_COIN = 1;
const FRIEND_ROOM = 2;
const CLUB = 3;

const DOWN_IP = 'http://47.243.92.221';

const JSVERSION = '2021121018';
