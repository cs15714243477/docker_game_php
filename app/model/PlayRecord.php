<?php
namespace app\model;

//use support\Model;
use Jenssegers\Mongodb\Eloquent\Model;
use support\Db;

class PlayRecord extends Model
{
    protected $connection = 'mongodb_main';
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


    public static function playerProfit($where,$type,$skip = 0,$limit = 0){
        switch ($type){
            case 'user':
                $gameId = isset($where['where']['gameId']) ? (int)$where['where']['gameId'] : 0;
                $roomId = isset($where['where']['roomId']) ? (int)$where['where']['roomId'] : 0;
                $promoterId = isset($where['where']['promoterId']) ? (int)$where['where']['promoterId'] : 0;
                $userId = $where['userId'];
                $userArr = isset($where['where']['userId']) ? $where['where']['userId'] : '';

                $filter = [
                    'isAndroid' => $where['where']['isAndroid'],
                    'endTime' => $where['where']['endTime'],
                ];

                if($userId && $promoterId && $promoterId != $userId){
                    $filter['userId'] = $userArr;
                    $filter['promoterId'] = $promoterId;
                }else{
                    if($userId){
                        $filter['userId'] = $userArr;
                    }else{
                        if($promoterId){
                            if($userArr){
                                if(isset($filter['userId'])){
                                    $filter['userId'] += $userArr;
                                }else{
                                    $filter['userId'] = $userArr;
                                }
                            }

                            if(isset($filter['userId'])){
                                $filter['userId'] += ['$eq' => $promoterId];
                            }else{
                                $filter['userId'] = $promoterId;
                            }
                        }
                    }
                }

                if($gameId > 0){
                    $filter['gameId'] = $gameId;
                }

                if($roomId > 0){
                    $filter['roomId'] =$roomId;
                }

                $offset = ['$skip' => $skip];
                break;
            case 'list':
                $filter = $where['where'];
                $offset = ['$skip' => $skip];
                $page = ['$limit' => $limit];
                break;
            case 'count':
                $filter = $where['where'];
                $offset = ['$skip' => $skip];
                break;
            case  'statistics':
                $filter = $where['where'];
                break;
        }
        if(in_array($type,['user','count'])){
            $data = Db::connection('mongodb_main')->collection('play_record')->raw()->aggregate([
                [
                    '$match' => $filter
                ],
                [
                    '$project' =>
                        [
                            'userId'=>1,
                            'gameId'=>1,
                            'roomId'=>1,
                            'allBet'=>1,
                            'validBet'=>1,
                            'winScore'=>1,
                            'platformWinScore' => 1,
                            'revenue' => 1,
                            'agentRevenue' => 1,
                            'playTime'=>1
                        ]
                ],
                [
                    '$group' =>
                        [
                            '_id' => $where['group'],
                            'gameRound' => ['$sum' => 1],
                            'allBet' => ['$sum' => '$allBet'],
                            'validBet' => ['$sum' => '$validBet'],
                            'winScore' => ['$sum' => '$winScore'],
                            'platformWinScore' => ['$sum' => '$platformWinScore'],
                            'revenue' => ['$sum' => '$revenue'],
                            'agentRevenue' => ['$sum' => '$agentRevenue'],
                            'playTime' => ['$sum' => '$playTime']
                        ]
                ],
                $where['options'],
                $offset,
                [
                    '$project' =>
                        [
                            'userId'=>'$_id.userId',
                            'gameId'=>'$_id.gameId',
                            'roomId'=>'$_id.roomId',
                            'allBet'=>1,
                            'validBet'=>1,
                            'gameRound'=>1,
                            'winScore'=>1,
                            'platformWinScore' => 1,
                            'revenue' => 1,
                            'agentRevenue' => 1,
                            'playTime'=>1
                        ]
                ]
            ])->toArray();
        }

        if($type == 'list'){
            $data = Db::connection('mongodb_main')->collection('play_record')->raw()->aggregate([
                [
                    '$match' => $filter
                ],
                [
                    '$project' =>
                        [
                            'userId'=>1,
                            'gameId'=>1,
                            'roomId'=>1,
                            'allBet'=>1,
                            'validBet'=>1,
                            'winScore'=>1,
                            'platformWinScore' => 1,
                            'revenue' => 1,
                            'agentRevenue' => 1,
                            'playTime'=>1
                        ]
                ],
                [
                    '$group' =>
                        [
                            '_id' => $where['group'],
                            'gameRound' => ['$sum' => 1],
                            'allBet' => ['$sum' => '$allBet'],
                            'validBet' => ['$sum' => '$validBet'],
                            'winScore' => ['$sum' => '$winScore'],
                            'platformWinScore' => ['$sum' => '$platformWinScore'],
                            'revenue' => ['$sum' => '$revenue'],
                            'agentRevenue' => ['$sum' => '$agentRevenue'],
                            'playTime' => ['$sum' => '$playTime']
                        ]
                ],
                $where['options'],
                $offset,
                $page,
                [
                    '$project' =>
                        [
                            'userId'=>'$_id.userId',
                            'gameId'=>'$_id.gameId',
                            'roomId'=>'$_id.roomId',
                            'allBet'=>1,
                            'validBet'=>1,
                            'gameRound'=>1,
                            'winScore'=>1,
                            'platformWinScore' => 1,
                            'revenue' => 1,
                            'agentRevenue' => 1,
                            'playTime'=>1
                        ]
                ]
            ])->toArray();
        }

        if($type == 'list2'){
            $data = Db::connection('mongodb_main')->collection('play_record')->raw()->aggregate([
                [
                    '$match' => $where['where']
                ],
                [
                    '$project' =>
                        [
                            'userId'=>1,
                            'gameId'=>1,
                            'roomId'=>1,
                            'allBet'=>1,
                            'validBet'=>1,
                            'winScore'=>1,
                            'platformWinScore' => 1,
                            'revenue' => 1,
                            'agentRevenue' => 1,
                            'playTime'=>1
                        ]
                ],
                [
                    '$group' =>
                        [
                            '_id' => $where['group'],
                            'gameRound' => ['$sum' => 1],
                            'allBet' => ['$sum' => '$allBet'],
                            'validBet' => ['$sum' => '$validBet'],
                            'winScore' => ['$sum' => '$winScore'],
                            'platformWinScore' => ['$sum' => '$platformWinScore'],
                            'revenue' => ['$sum' => '$revenue'],
                            'agentRevenue' => ['$sum' => '$agentRevenue'],
                            'playTime' => ['$sum' => '$playTime']
                        ]
                ],
                [
                    '$project' =>
                        [
                            'userId'=>'$_id.userId',
                            'gameId'=>'$_id.gameId',
                            'roomId'=>'$_id.roomId',
                            'allBet'=>1,
                            'validBet'=>1,
                            'gameRound'=>1,
                            'winScore'=>1,
                            'platformWinScore' => 1,
                            'revenue' => 1,
                            'agentRevenue' => 1,
                            'playTime'=>1
                        ]
                ]
            ])->toArray();
        }

        if($type == 'statistics'){
            $data = Db::connection('mongodb_main')->collection('play_record')->raw()->aggregate([
                [
                    '$match' => $filter
                ],
                [
                    '$project' =>
                        [
                            'userId'=>1,
                            'gameId'=>1,
                            'roomId'=>1,
                            'allBet'=>1,
                            'winScore'=>1,
                            'platformWinScore' => 1,
                            'revenue' => 1,
                            'agentRevenue' => 1,
                            'playTime'=>1
                        ]
                ],
                [
                    '$group' =>
                        [
                            '_id' => null,

                            'allBet' => ['$sum' => '$allBet'],
                            'winScore' => ['$sum' => '$winScore'],
                            'platformWinScore' => ['$sum' => '$platformWinScore'],
                            'revenue' => ['$sum' => '$revenue'],
                            'agentRevenue' => ['$sum' => '$agentRevenue'],
                        ]
                ],
                [
                    '$project' =>
                        [
                            'userId'=>'$_id.userId',
                            'allBet'=>1,
                            'winScore'=>1,
                            'platformWinScore' => 1,
                            'revenue' => 1,
                            'agentRevenue' => 1,
                        ]
                ]
            ])->toArray();
        }
        return $data;
    }
}