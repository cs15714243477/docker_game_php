<?php
namespace app\model;

//use support\Model;
use Jenssegers\Mongodb\Eloquent\Model;

class RewardOrder extends Model
{
    const ORDER_STATUS_PAID = 2;
    const ORDER_STATUS_FINISH = 4;
    const ORDER_STATUS_CANCLE = 5;
    const ORDER_STATUS_TIMEOUT_CANCLE = 6;
    const REWARD_TYPE = [
        "1" => "注册奖励",
        "2" => "绑定手机奖励",
        "8" => "官方赠送",
        "19" => "活动奖励",
        "20" => "救济金",
        "21" => "红包",
        "22" => "任务奖励",
        "23" => "邮件补助",
        "24" => "签到",
        "26" => "举报奖励",
        "100" => "引流奖励彩金",
        "101" => "注册送88彩金",
        "102" => "连续7天登陆投注送彩金",
        "103" => "线下充值返利",
        "104" => "推广送彩金",
        "105" => "直属充值返利",
        "106" => "首充赠送",
        "107" => "跑量奖励",
        "108" => "累计充值奖励",
        "109" => "其他奖励",
        '134' => '抽奖奖励',
        '135' => '推广方案',
        '136' => '上级赠送',
        '137' => '有效投注彩金',
        '138' => '财富攀登彩金',
    ];
    const REWARD_TYPE_FOR_GIVE_SCORE = [
        '100' => '引流奖励彩金',
        '101' => '注册送88彩金',
        '102' => '连续7天登录投注送彩金',
        '103' => '线下充值返利',
        '105' => '直属充值返利',
        '106' => '首充赠送',
        '107' => '跑量奖励',
        '108' => '累计充值奖励',
        '109' => '其他奖励',
        '134' => '抽奖奖励',
        '135' => '推广方案',
        '136' => '上级赠送',
        '137' => '有效投注彩金',
        '138' => '财富攀登彩金',
    ];

    const REWARD_TYPE_FOR_EXCHANGE_STATISTICAL = [
        '100' => '引流奖励彩金',
        '101' => '注册送88彩金',
        '102' => '连续7天登录投注送彩金',
        '103' => '线下充值返利',
        '105' => '直属充值返利',
        '106' => '首充赠送',
        '107' => '跑量奖励',
        '108' => '累计充值奖励',
        '109' => '其他奖励',
        '134' => '抽奖奖励',
        '135' => '推广方案',
        '136' => '上级赠送',
        '137' => '有效投注彩金',
        '138' => '财富攀登彩金',
    ];
    protected $connection = 'mongodb_main';
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'reward_order';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = '_id';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;
}