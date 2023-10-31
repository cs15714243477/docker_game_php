<?php
namespace app\model;

//use support\Model;
use Jenssegers\Mongodb\Eloquent\Model;

class FriendsMainPlayRecord extends Model
{
    protected $connection = 'mongodb_friend';
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'main_play_record';

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

    public function userGameKind()
    {
        return $this->belongsTo(FriendsUserGameKind::class);
    }
}