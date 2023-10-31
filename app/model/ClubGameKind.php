<?php
namespace app\model;

//use support\Model;
use Jenssegers\Mongodb\Eloquent\Model;

class ClubGameKind extends Model
{
    protected $connection = 'mongodb_club';
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'game_kind';

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

    public static function gameAllRoomInfo() {
        $filter = [];
        $gameRoomArrays = static::where($filter)->select('rooms.roomId', 'rooms.roomName')->orderBy('sort', 'asc')->get();
        $gameRoomInfo = [];
        if ($gameRoomArrays) {
            foreach ($gameRoomArrays as $key => $value) {
                if($value['rooms']){
                    foreach ($value['rooms'] as $key1 => $value1) {
                        $gameRoomInfo[] = ['roomId' => $value1['roomId'], 'roomName' => $value1['roomName']];
                    }
                }
            }
        }
        return $gameRoomInfo;
    }

    public static function gameRoomInfoByGameId($gameId) {
        $filter = ['gameId' => $gameId];
        $gameRoomArrays = static::where($filter)->select('rooms.roomId', 'rooms.roomName')->orderBy('sort', 'asc')->get();
        $gameRoomInfo = [];
        if ($gameRoomArrays) {
            foreach ($gameRoomArrays as $key => $value) {
                if($value['rooms']){
                    foreach ($value['rooms'] as $key1 => $value1) {
                        $gameRoomInfo[] = ['roomId' => $value1['roomId'], 'roomName' => $value1['roomName']];
                    }
                }
            }
        }
        return $gameRoomInfo;
    }
}