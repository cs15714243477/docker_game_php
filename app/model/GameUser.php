<?php
namespace app\model;

//use support\Model;
use Jenssegers\Mongodb\Eloquent\Model;

class GameUser extends Model
{
    const COMMON_ACCOUNT = 2;
    const SYSTEM_ACCOUNT = 3;
    const COMMON_ACCOUNT_START_ID = 10000000;
    const ACCOUNT_CLASSIFY = [
        '1' => '全部',
        '2' => '普通会员',
        '3' => '官方账号',
    ];
    const USER_STATUS_ON = 1;
    const USER_STATUS_OFF = 0;
    protected $connection = 'mongodb_main';
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'game_user';

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