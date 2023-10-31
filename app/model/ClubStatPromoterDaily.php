<?php
namespace app\model;

//use support\Model;
use Jenssegers\Mongodb\Eloquent\Model;
use support\Db;

class ClubStatPromoterDaily extends Model
{
    protected $connection = 'mongodb_club';
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'stat_promoter_daily';

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

    public static function statPromoterData($startDate,$endDate)
    {
        $resultArray = [];
        $startTime = strtotime(trim($startDate));
        $endTime = strtotime(trim($endDate)) + 86400;
        $startTimeMongo = formatTimestampToMongo($startTime);
        $endTimeMongo = formatTimestampToMongo($endTime);

        $where = [];
        $where['date'] = ['$gte' => $startTimeMongo, '$lt' => $endTimeMongo];
        $where['promoterId'] = -1000;


        $rs = Db::connection('mongodb_club')->collection('stat_promoter_daily')->raw()->aggregate([
            [
                '$match' => $where
            ],
            [
                '$project' =>
                    [
                        '_id' => 0,
                        'teamGameWinScore'=>1,//游戏输赢
                        'totalTeamGameWinScore'=>1, //游戏输赢
                        'teamFlowAmount'=>1,//用户下注流水
                        'totalTeamFlowAmount'=>1,//用户下注流水
                        'teamProfit' => 1,//代理提成
                        'totalTeamProfit' => 1,//累计代理提成
                        'myProfit' => 1,
                        'myTeamProfit' => 1,
                        'totalMyProfit' => 1,
                        'totalMyTeamProfit' => 1,
                        'teamRevenue' => 1,//税收
                        'totalTeamRevenue' => 1,//累计
                        'platformProfit' => 1,//官方盈亏
                        'totalPlatformProfit' => 1,//累计
                        'teamRegPromoterNum' => 1,//新增代理
                        'totalTeamRegPromoterNum' => 1,//累计新增代理
                        'teamActiveRegPromoterNum' => 1,//新增有效代理
                        'totalTeamActiveRegPromoterNum' => 1,//累计新增有效代理
                        'transferToScoreAmount' => 1,
                        'totalTransferToScoreAmount' => 1,
                        'teamTransferToScoreAmount'=>1,
                        'totalTeamTransferToScoreAmount'=>1
                    ],
            ],
            [
                '$group' =>
                    [
                        '_id' => null,
                        'teamGameWinScore_total' =>    ['$sum' => '$teamGameWinScore'],
                        'totalTeamGameWinScore_total' =>    ['$sum' => '$totalTeamGameWinScore'],
                        'teamFlowAmount_total' =>    ['$sum' => '$teamFlowAmount'],
                        'totalTeamFlowAmount_total' =>    ['$sum' => '$totalTeamFlowAmount'],
                        'teamProfit_total' =>    ['$sum' => '$teamProfit'],
                        'totalTeamProfit_total' =>    ['$sum' => '$totalTeamProfit'],
                        'myProfit_total' =>    ['$sum' => '$myProfit'],
                        'myTeamProfit_total' =>    ['$sum' => '$myTeamProfit'],
                        'totalMyProfit_total' =>    ['$sum' => '$totalMyProfit'],
                        'totalMyTeamProfit_total' =>    ['$sum' => '$totalMyTeamProfit'],
                        'teamRevenue_total' =>    ['$sum' => '$teamRevenue'],
                        'totalTeamRevenue_total' =>    ['$sum' => '$totalTeamRevenue'],
                        'platformProfit_total' =>    ['$sum' => '$platformProfit'],
                        'totalPlatformProfit_total' =>    ['$sum' => '$totalPlatformProfit'],
                        'teamRegPromoterNum_total' =>    ['$sum' => '$teamRegPromoterNum'],
                        'totalTeamRegPromoterNum_total' =>    ['$sum' => '$totalTeamRegPromoterNum'],
                        'teamActiveRegPromoterNum_total' =>    ['$sum' => '$teamActiveRegPromoterNum'],
                        'totalTeamActiveRegPromoterNum_total' =>    ['$sum' => '$totalTeamActiveRegPromoterNum'],
                        'transferToScoreAmount_total' =>    ['$sum' => '$transferToScoreAmount'],
                        'totalTransferToScoreAmount_total' =>    ['$sum' => '$totalTransferToScoreAmount'],
                        'teamTransferToScoreAmount_total' =>    ['$sum' => '$teamTransferToScoreAmount'],
                        'totalTeamTransferToScoreAmount_total' =>    ['$sum' => '$totalTeamTransferToScoreAmount'],
                    ]
            ]
        ])->toArray();

        if($rs){
            foreach ($rs as &$item){
                $item['teamGameWinScore_total']               = formatMoneyFromMongo($item['teamGameWinScore_total']??0);
                $item['totalTeamGameWinScore_total']          = formatMoneyFromMongo($item['totalTeamGameWinScore_total']??0);
                $item['teamFlowAmount_total']                 = formatMoneyFromMongo($item['teamFlowAmount_total']??0);
                $item['totalTeamFlowAmount_total']            = formatMoneyFromMongo($item['totalTeamFlowAmount_total']??0);
                $item['teamProfit_total']                     = formatMoneyFromMongo($item['teamProfit_total']??0);
                $item['totalTeamProfit_total']                = formatMoneyFromMongo($item['totalTeamProfit_total']??0);
                $item['myProfit_total']                       = formatMoneyFromMongo($item['myProfit_total']??0);
                $item['totalMyProfit_total']                  = formatMoneyFromMongo($item['totalMyProfit_total']??0);
                $item['myTeamProfit_total']                   = formatMoneyFromMongo($item['myTeamProfit_total']??0);
                $item['totalMyTeamProfit_total']              = formatMoneyFromMongo($item['totalMyTeamProfit_total']??0);
                $item['teamRevenue_total']                    = formatMoneyFromMongo($item['teamRevenue_total']??0);
                $item['totalTeamRevenue_total']               = formatMoneyFromMongo($item['totalTeamRevenue_total']??0);
                $item['platformProfit_total']                 = formatMoneyFromMongo($item['platformProfit_total']??0);
                $item['totalPlatformProfit_total']            = formatMoneyFromMongo($item['totalPlatformProfit_total']??0);
                $item['teamRegPromoterNum_total']             = formatMoneyFromMongo($item['teamRegPromoterNum_total']??0);
                $item['totalTeamRegPromoterNum_total']        = formatMoneyFromMongo($item['totalTeamRegPromoterNum_total']??0);
                $item['teamActiveRegPromoterNum_total']       = formatMoneyFromMongo($item['teamActiveRegPromoterNum_total']??0);
                $item['totalTeamActiveRegPromoterNum_total']  = formatMoneyFromMongo($item['totalTeamActiveRegPromoterNum_total']??0);
                $item['transferToScoreAmount_total']          = formatMoneyFromMongo($item['transferToScoreAmount_total']??0);
                $item['totalTransferToScoreAmount_total']     = formatMoneyFromMongo($item['totalTransferToScoreAmount_total']??0);
                $item['teamTransferToScoreAmount_total']      = formatMoneyFromMongo($item['teamTransferToScoreAmount_total']??0);
                $item['totalTeamTransferToScoreAmount_total'] = formatMoneyFromMongo($item['totalTeamTransferToScoreAmount_total']??0);
            }
            $resultArray = $rs;
        }
        return $resultArray;
    }

    public static function getDataByDate($startDate,$endDate)
    {
        $resultArray = [];
        $startTime = strtotime(trim($startDate));
        $startTimeMongo = formatTimestampToMongo($startTime);
        $endTime = strtotime(trim($endDate)) + 86400;
        $endTimeMongo = formatTimestampToMongo($endTime);

        $where = [];
        $where['date'] = ['$gte' => $startTimeMongo, '$lt' => $endTimeMongo];
        $where['promoterId'] = -1000;


        $rs = Db::connection('mongodb_club')->collection('stat_promoter_daily')->raw()->aggregate([
            [
                '$match' => $where
            ],
            [
                '$project' =>
                    [
                        '_id' => 0,
                        'Date' => ['$substr' => [['$add' => ['$date',28800000]], 0, 10]],
                        'teamGameWinScore'=>1,//游戏输赢
                        'totalTeamGameWinScore'=>1, //游戏输赢
                        'teamFlowAmount'=>1,//用户下注流水
                        'totalTeamFlowAmount'=>1,//用户下注流水
                        'teamProfit' => 1,//代理提成
                        'totalTeamProfit' => 1,//累计代理提成
                        'myProfit' => 1,
                        'myTeamProfit' => 1,
                        'totalMyProfit' => 1,
                        'totalMyTeamProfit' => 1,
                        'teamRevenue' => 1,//税收
                        'totalTeamRevenue' => 1,//累计
                        'platformProfit' => 1,//官方盈亏
                        'totalPlatformProfit' => 1,//累计
                        'teamRegPromoterNum' => 1,//新增代理
                        'totalTeamRegPromoterNum' => 1,//累计新增代理
                        'teamActiveRegPromoterNum' => 1,//新增有效代理
                        'totalTeamActiveRegPromoterNum' => 1,//累计新增有效代理
                        'transferToScoreAmount' => 1,
                        'totalTransferToScoreAmount' => 1,
                        'teamTransferToScoreAmount'=>1,
                        'totalTeamTransferToScoreAmount'=>1
                    ],
            ]
        ])->toArray();
        
        if($rs){
            foreach ($rs as &$item){
                $item['teamGameWinScore']               = formatMoneyFromMongo($item['teamGameWinScore']??0);
                $item['totalTeamGameWinScore']          = formatMoneyFromMongo($item['totalTeamGameWinScore']??0);
                $item['teamFlowAmount']                 = formatMoneyFromMongo($item['teamFlowAmount']??0);
                $item['totalTeamFlowAmount']            = formatMoneyFromMongo($item['totalTeamFlowAmount']??0);
                $item['teamProfit']                     = formatMoneyFromMongo($item['teamProfit']??0);
                $item['totalTeamProfit']                = formatMoneyFromMongo($item['totalTeamProfit']??0);
                $item['myProfit']                       = formatMoneyFromMongo($item['myProfit']??0);
                $item['totalMyProfit']                  = formatMoneyFromMongo($item['totalMyProfit']??0);
                $item['myTeamProfit']                   = formatMoneyFromMongo($item['myTeamProfit']??0);
                $item['totalMyTeamProfit']              = formatMoneyFromMongo($item['totalMyTeamProfit']??0);
                $item['teamRevenue']                    = formatMoneyFromMongo($item['teamRevenue']??0);
                $item['totalTeamRevenue']               = formatMoneyFromMongo($item['totalTeamRevenue']??0);
                $item['platformProfit']                 = formatMoneyFromMongo($item['platformProfit']??0);
                $item['totalPlatformProfit']            = formatMoneyFromMongo($item['totalPlatformProfit']??0);
                $item['transferToScoreAmount']          = formatMoneyFromMongo($item['transferToScoreAmount']??0);
                $item['totalTransferToScoreAmount']     = formatMoneyFromMongo($item['totalTransferToScoreAmount']??0);
                $item['teamTransferToScoreAmount']      = formatMoneyFromMongo($item['teamTransferToScoreAmount']??0);
                $item['totalTeamTransferToScoreAmount'] = formatMoneyFromMongo($item['totalTeamTransferToScoreAmount']??0);

                $resultArray[$item['Date']] = $item;
            }
        }
        return $resultArray;
    }

}