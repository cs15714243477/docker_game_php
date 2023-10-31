<?php
namespace app\model;

//use support\Model;
use Jenssegers\Mongodb\Eloquent\Model;

class PromoterExchangeOrder extends Model
{
    const ORDER_STATUS_TOBEREMITTED = 10;
    const ORDER_STATUS_REJECT = 9;
    const ORDER_STATUS_AUDIT = 8;
    const ORDER_STATUS_EXCHANGE_FAIL = 15;
    const ORDER_STATUS_REMITTING = 17; //汇款中
    const ORDER_STATUS_EXCHANGE_SUCCESS = 18;

    const WITHDRAWTYPE_BANKCARD = 1;
    const WITHDRAWTYPE_ALIPAY = 2;
    const WITHDRAWTYPE_EBANK = 3;
    const WITHDRAWTYPE_USDT = 4;
    const STATUS = [
        '8' => '审核中',
        '9' => '已驳回',
        '10' => '待汇款',
        '15' => '提现失败',
        '18' => '已汇款',
        '30' => '取消订单',
    ];
    const WITHDRAWTYPE = [
        '1' => '银行卡',
        '2' => '支付宝',
        '3' => '网银',
        '4' => 'USDT',
        '5' => '代理转余额'
    ];
    protected $connection = 'mongodb_club';
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'promoter_exchange_order';

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