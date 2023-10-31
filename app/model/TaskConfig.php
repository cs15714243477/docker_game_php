<?php
namespace app\model;

//use support\Model;
use Jenssegers\Mongodb\Eloquent\Model;

class TaskConfig extends Model
{
    protected $connection = 'mongodb_main';
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'task_config';

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


    const TASKTYPE = [
        '2' => '胜局任务',
        '3' => '牌型任务',
        '1' => '流水任务',
    ];
    const TASKCYCLE = [
        '2' => '每日奖励',
        '1' => '一次性奖励',
    ];
    const ROOMS = [
        '1' => '体验场',
        '2' => '平民场',
        '3' => '贵族场',
        '4' => '管甲场',
    ];
}