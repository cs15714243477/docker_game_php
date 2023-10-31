<?php
namespace app\model;

//use support\Model;
use Jenssegers\Mongodb\Eloquent\Model;

class FriendsUserGameKind extends Model
{
    protected $connection = 'mongodb_friend';
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'user_game_kind';

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

    public function mainPlayRecords()
    {
        return $this->hasMany(FriendsMainPlayRecord::class);
    }
}