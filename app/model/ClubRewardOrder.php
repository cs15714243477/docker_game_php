<?php
namespace app\model;

//use support\Model;
use Jenssegers\Mongodb\Eloquent\Model;

class ClubRewardOrder extends Model
{
    const ORDER_STATUS_PAID = 2;
    const ORDER_STATUS_FINISH = 4;
    const ORDER_STATUS_CANCLE = 5;
    const ORDER_STATUS_TIMEOUT_CANCLE = 6;
    const CLUB_REWARD_TYPE_FOR_GIVE_SCORE = [
        '110' => '当庄五花牛',//抢庄牛牛(10元/20元桌子)588
        '111' => '当庄五小牛',//抢庄牛牛(10元/20元桌子)388
        '112' => '当庄爆玖',//三公专场(10元/20元桌子)888
        '113' => '当庄炸弹',//三公专场(10元/20元桌子)666
        '114' => '连赢9把',//炸金花连赢奖励(10元/20元桌子)588
        '115' => '连赢8把',//炸金花连赢奖励(10元/20元桌子)488
        '116' => '连赢7把',//炸金花连赢奖励(10元/20元桌子)388
        '117' => '豹子AAA',//炸金花特殊奖励(10元/20元桌子)588
        '118' => '豹子666',//炸金花特殊奖励(10元/20元桌子)488
        '119' => '豹子888',//炸金花特殊奖励(10元/20元桌子)388

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
    ];



    protected $connection = 'mongodb_club';
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