<?php
namespace app\model;

//use support\Model;
use Jenssegers\Mongodb\Eloquent\Model;

class RechargeRoomCard extends Model
{
    const RECHARGE_RC_TYPE = [
        '0' => '全部',
        '1' => '商城购买',
        '2' => '代理商城购买',
        '3' => '官方赠送房卡',
    ];
    protected $connection = 'mongodb_friend';
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'recharge_room_card';

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