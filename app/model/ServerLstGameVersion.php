<?php
namespace app\model;

//use support\Model;
use Jenssegers\Mongodb\Eloquent\Model;

class ServerLstGameVersion extends Model
{
    protected $connection = 'mongodb_config';
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'serverlst_game_version';

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