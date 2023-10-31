<?php
namespace app\model;

//use support\Model;
use Jenssegers\Mongodb\Eloquent\Model;

class RechargeOrder extends Model
{
    const ORDER_STATUS_PAID = 2;
    const ORDER_STATUS_FINISH = 4;
    const ORDER_STATUS_CANCLE = 5;
    const ORDER_STATUS_TIMEOUT_CANCLE = 6;
    const STATUS = [
        '1' => '未支付',
        '2' => '已支付',
        '4' => '已完成',
        '5' => '已取消',
        '6' => '超时取消',
    ];
    const CLASSIFY_OFFLINE = 1;
    const CLASSIFY_ONLINE = 2;
    const CLASSIFY_KEFU = 1;
    const CLASSIFY_SYSTEM_REISSUE = 4;
    const CLASSIFY_PAYMENT_REISSUE = 5;
    const CLASSIFY = [
        '1' => '线下充值',
        '2' => '线上充值',
        '3' => '客服充值',
        '4' => '运营补发',
        '5' => '支付补发',
    ];
    protected $connection = 'mongodb_main';
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'recharge_order';

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