<?php
namespace app\model;

//use support\Model;
use Jenssegers\Mongodb\Eloquent\Model;

class RechargeCardNumList extends Model
{
    const LEVEL_NAME = [
        '5' => '白银会员',
        '6' => '黄金会员',
        '7' => '钻石会员',
        '8' => '一级代理',
        '9' => '二级代理',
        '10' => '三级代理',
        '11' => '四级代理',
        '12' => '五级代理',
        '13' => '六级代理',
        '14' => '七级代理',
        '15' => '八级代理',
        '16' => '九级代理',
        '17' => '青铜代理',
        '18' => '白银代理',
        '19' => '黄金代理',
        '20' => '钻石代理',
        '21' => '区域经理',
        '22' => '总代理',
        '23' => '助理',
        '24' => '高级助理',
        '25' => '监事',
        '26' => '高级监事',
        '27' => '副总监',
        '28' => '总监',
        '29' => '元老',
        '30' => '股东',
        '31' => '董事',
        '32' => '合伙人'
    ];
    protected $connection = 'mongodb_friend';
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'recharge_card_num_list';

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