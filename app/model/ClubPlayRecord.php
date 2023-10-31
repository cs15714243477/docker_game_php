<?php
namespace app\model;

//use support\Model;
use Jenssegers\Mongodb\Eloquent\Model;
use support\Db;

class ClubPlayRecord extends Model
{
    protected $connection = 'mongodb_club';
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'play_record';

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