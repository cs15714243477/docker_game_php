<?php
namespace app\model;

//use support\Model;
use Jenssegers\Mongodb\Eloquent\Model;

class RoomCardChange extends Model
{
    protected $connection = 'mongodb_friend';
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'room_card_change';

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