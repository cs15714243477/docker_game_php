<?php
namespace app\model;

//use support\Model;
use Jenssegers\Mongodb\Eloquent\Model;

class AdminSafeIp extends Model
{
    protected $connection = 'mongodb_oa';
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'admin_safe_ip';

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