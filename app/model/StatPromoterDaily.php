<?php
namespace app\model;

//use support\Model;
use Jenssegers\Mongodb\Eloquent\Model;
use support\Db;

class StatPromoterDaily extends Model
{
    protected $connection = 'mongodb_main';
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
        $where['promoterId'] = 1000;

        $rs = Db::connection('mongodb_main')->collection('stat_promoter_daily')->raw()->aggregate([
            [
                '$match' => $where
            ],
            [
                '$project' =>
                    [
                        '_id' => 0,
                        'teamRevenue' => 1,
                        'totalTeamRevenue' => 1,
                        'teamProfit' => 1,
                        'totalTeamProfit' => 1,//累计代理提成
                        'teamProfitTotal' => 1,//代理总提成
                        'totalTeamProfitTotal' => 1,//累计代理代理总提成
                        'teamExchangeAmount' =>1,
                        'totalTeamExchangeAmount' =>1,
                        'teamRechargeAmount'=>1,
                        'totalTeamRechargeAmount'=>1,
                        'teamGameWinScore'=>1,
                        'totalTeamGameWinScore'=>1,
                        'teamRegBindPeople'=>1,//团队今天注册并绑定人数
                        'totalTeamRegBindPeople'=>1,//累计团队今天注册并绑定人数
                        'teamRegPromoterNum'=>1,//新增代理
                        'totalTeamRegPromoterNum'=>1,//累计新增代理
                        'teamActiveRegPromoterNum'=>1,//新增有效代理数
                        'totalTeamActiveRegPromoterNum'=>1,//累计新增有效代理数
                        'teamFlowAmount'=>1,//团队总下注流水
                        'teamValidFlowAmount'=>1,//团队总有效下注流水
                        'totalTeamFlowAmount'=>1,//累计团队总下注流水
                        'totalTeamValidFlowAmount'=>1,//累计团队总有效下注流水
                        'teamRechargeyNum'=>1,
                        'totalTeamRechargeNum'=>1,
                        'teamExchangeNum'=>1,
                        'totalTeamExchangeNum'=>1,
                        'teamRegValidNewBetPeople'=>1,
                        'totalTeamRegValidNewBetPeople'=>1,
                        'teamRegPeople'=>1,
                        'totalTeamRegPeople'=>1,//累计注册会员人数
                        'transferToScoreAmount'=>1,
                        'totalTransferToScoreAmount'=>1,
                        'teamTransferToScoreAmount'=>1,
                        'totalTeamTransferToScoreAmount'=>1,
                    ],
            ],
            [
                '$group' =>
                    [
                        '_id' => null,
                        'teamRegBindPeople' =>    ['$sum' => '$teamRegBindPeople'],
                        'totalTeamRegBindPeople' =>    ['$sum' => '$totalTeamRegBindPeople'],
                        'teamRegPromoterNum' =>    ['$sum' => '$teamRegPromoterNum'],
                        'totalTeamRegPromoterNum' =>    ['$sum' => '$totalTeamRegPromoterNum'],
                        'teamActiveRegPromoterNum' =>    ['$sum' => '$teamActiveRegPromoterNum'],
                        'totalTeamActiveRegPromoterNum' =>    ['$sum' => '$totalTeamActiveRegPromoterNum'],
                        'teamRechargeAmount' =>    ['$sum' => '$teamRechargeAmount'],
                        'totalTeamRechargeAmount' =>    ['$sum' => '$totalTeamRechargeAmount'],
                        'teamExchangeAmount' =>    ['$sum' => '$teamExchangeAmount'],
                        'totalTeamExchangeAmount' =>    ['$sum' => '$totalTeamExchangeAmount'],
                        'teamFlowAmount' =>    ['$sum' => '$teamFlowAmount'],
                        'teamValidFlowAmount' =>    ['$sum' => '$teamValidFlowAmount'],
                        'totalTeamFlowAmount' =>    ['$sum' => '$totalTeamFlowAmount'],
                        'totalTeamValidFlowAmount' =>    ['$sum' => '$totalTeamValidFlowAmount'],
                        'teamRevenue' =>    ['$sum' => '$teamRevenue'],
                        'totalTeamRevenue' =>    ['$sum' => '$totalTeamRevenue'],
                        'teamGameWinScore' =>    ['$sum' => '$teamGameWinScore'],
                        'totalTeamGameWinScore' =>    ['$sum' => '$totalTeamGameWinScore'],
                        'teamProfit' =>    ['$sum' => '$teamProfit'],
                        'totalTeamProfit' =>    ['$sum' => '$totalTeamProfit'],
                        'teamProfitTotal' =>    ['$sum' => '$teamProfitTotal'],//代理总提成
                        'totalTeamProfitTotal' =>    ['$sum' => '$totalTeamProfitTotal'],//累计代理代理总提成
                        'teamRechargeNum' =>    ['$sum' => '$teamRechargeyNum'],
                        'totalTeamRechargeNum' =>    ['$sum' => '$totalTeamRechargeNum'],
                        'teamExchangeNum' =>    ['$sum' => '$teamExchangeNum'],
                        'totalTeamExchangeNum' =>    ['$sum' => '$totalTeamExchangeNum'],
                        'teamRegValidNewBetPeople' =>    ['$sum' => '$teamRegValidNewBetPeople'],
                        'totalTeamRegValidNewBetPeople' =>    ['$sum' => '$totalTeamRegValidNewBetPeople'],
                        'teamRegPeople' =>    ['$sum' => '$teamRegPeople'],
                        'totalTeamRegPeople' =>    ['$sum' => '$totalTeamRegPeople'],
                        'transferToScoreAmount' =>    ['$sum' => '$transferToScoreAmount'],
                        'totalTransferToScoreAmount' =>    ['$sum' => '$totalTransferToScoreAmount'],
                        'teamTransferToScoreAmount' =>    ['$sum' => '$teamTransferToScoreAmount'],
                        'totalTeamTransferToScoreAmount' =>    ['$sum' => '$totalTeamTransferToScoreAmount'],
                    ]
            ]
        ])->toArray();

        if($rs){
            foreach ($rs as &$item){

                $item['teamRechargeAmount']             = formatMoneyFromMongo($item['teamRechargeAmount']??0);
                $item['totalTeamRechargeAmount']        = formatMoneyFromMongo($item['totalTeamRechargeAmount']??0);
                $item['teamExchangeAmount']             = formatMoneyFromMongo($item['teamExchangeAmount']??0);
                $item['totalTeamExchangeAmount']        = formatMoneyFromMongo($item['totalTeamExchangeAmount']??0);
                $item['teamFlowAmount']                 = formatMoneyFromMongo($item['teamFlowAmount']??0);
                $item['teamValidFlowAmount']            = formatMoneyFromMongo($item['teamValidFlowAmount']??0);
                $item['totalTeamFlowAmount']            = formatMoneyFromMongo($item['totalTeamFlowAmount']??0);
                $item['totalTeamValidFlowAmount']       = formatMoneyFromMongo($item['totalTeamValidFlowAmount']??0);

                $item['teamRevenue']                    = formatMoneyFromMongo($item['teamRevenue']??0);
                $item['totalTeamRevenue']               = formatMoneyFromMongo($item['totalTeamRevenue']??0);
                $item['teamGameWinScore']               = formatMoneyFromMongo($item['teamGameWinScore']??0);
                $item['totalTeamGameWinScore']          = formatMoneyFromMongo($item['totalTeamGameWinScore']??0);
                $item['teamProfit']                     = formatMoneyFromMongo($item['teamProfit']??0);
                $item['totalTeamProfit']                = formatMoneyFromMongo($item['totalTeamProfit']??0);
                $item['teamProfitTotal']                = formatMoneyFromMongo($item['teamProfitTotal']??0);
                $item['totalTeamProfitTotal']           = formatMoneyFromMongo($item['totalTeamProfitTotal']??0);

                $item['transferToScoreAmount']          = formatMoneyFromMongo($item['transferToScoreAmount']??0);
                $item['totalTransferToScoreAmount']     = formatMoneyFromMongo($item['totalTransferToScoreAmount']??0);

                $item['teamTransferToScoreAmount']      = formatMoneyFromMongo($item['teamTransferToScoreAmount']??0);
                $item['totalTeamTransferToScoreAmount'] = formatMoneyFromMongo($item['totalTeamTransferToScoreAmount']??0);
            }
            $resultArray = $rs[0];
        }
        return $resultArray;
    }

    public static function getDataByDate($startDate, $endDate)
    {
        $resultArray = [];
        $startTime = strtotime(trim($startDate));
        $startTimeMongo = formatTimestampToMongo($startTime);
        $endTime = strtotime(trim($endDate)) + 86400;
        $endTimeMongo = formatTimestampToMongo($endTime);

        $where = [];
        $where['date'] = ['$gte' => $startTimeMongo, '$lt' => $endTimeMongo];
        $where['promoterId'] = 1000;

        $rs = Db::connection('mongodb_main')->collection('stat_promoter_daily')->raw()->aggregate([
            [
                '$match' => $where
            ],
            [
                '$project' =>
                    [
                        '_id' => 0,
                        'Date' => ['$substr' => [['$add' => ['$date',28800000]], 0, 10]],
                        'teamRevenue' => 1,
                        'totalTeamRevenue' => 1,
                        'teamProfit' => 1,
                        'totalTeamProfit' => 1,//累计代理提成
                        'teamProfitTotal' => 1,//代理总提成
                        'totalTeamProfitTotal' => 1,//累计代理代理总提成
                        'teamExchangeAmount' =>1,
                        'totalTeamExchangeAmount' =>1,
                        'teamRechargeAmount'=>1,
                        'totalTeamRechargeAmount'=>1,
                        'teamGameWinScore'=>1,
                        'totalTeamGameWinScore'=>1,
                        'teamRegBindPeople'=>1,//团队今天注册并绑定人数
                        'teamActivePeople'=>1,
                        'totalTeamRegBindPeople'=>1,//累计团队今天注册并绑定人数
                        'teamRegPromoterNum'=>1,//新增代理
                        'totalTeamRegPromoterNum'=>1,//累计新增代理
                        'teamActiveRegPromoterNum'=>1,//新增有效代理数
                        'totalTeamActiveRegPromoterNum'=>1,//累计新增有效代理数
                        'teamFlowAmount'=>1,//团队总下注流水
                        'teamValidFlowAmount'=>1,//团队总有效下注流水
                        'totalTeamFlowAmount'=>1,//累计团队总下注流水
                        'totalTeamValidFlowAmount'=>1,//累计团队总有效下注流水
                        'teamRechargeyNum'=>1,
                        'totalTeamRechargeNum'=>1,
                        'teamExchangeNum'=>1,
                        'totalTeamExchangeNum'=>1,
                        'teamRegValidNewBetPeople'=>1,
                        'totalTeamRegValidNewBetPeople'=>1,
                        'teamRegPeople'=>1,
                        'totalTeamRegPeople'=>1,//累计注册会员人数
                        'totalTeamPlayerCount'=>1,
                        'transferToScoreAmount'=>1,
                        'totalTransferToScoreAmount'=>1,
                        'teamTransferToScoreAmount'=>1,
                        'totalTeamTransferToScoreAmount'=>1,
                    ],
            ]
        ])->toArray();

        if($rs){
            foreach ($rs as &$item){

                $item['teamRechargeAmount']             = formatMoneyFromMongo($item['teamRechargeAmount']??0);
                $item['totalTeamRechargeAmount']        = formatMoneyFromMongo($item['totalTeamRechargeAmount']??0);
                $item['teamExchangeAmount']             = formatMoneyFromMongo($item['teamExchangeAmount']??0);
                $item['totalTeamExchangeAmount']        = formatMoneyFromMongo($item['totalTeamExchangeAmount']??0);
                $item['teamFlowAmount']                 = formatMoneyFromMongo($item['teamFlowAmount']??0);
                $item['teamValidFlowAmount']            = formatMoneyFromMongo($item['teamValidFlowAmount']??0);
                $item['totalTeamFlowAmount']            = formatMoneyFromMongo($item['totalTeamFlowAmount']??0);
                $item['totalTeamValidFlowAmount']       = formatMoneyFromMongo($item['totalTeamValidFlowAmount']??0);

                $item['teamRevenue']                    = formatMoneyFromMongo($item['teamRevenue']??0);
                $item['totalTeamRevenue']               = formatMoneyFromMongo($item['totalTeamRevenue']??0);
                $item['teamGameWinScore']               = formatMoneyFromMongo($item['teamGameWinScore']??0);
                $item['totalTeamGameWinScore']          = formatMoneyFromMongo($item['totalTeamGameWinScore']??0);
                $item['teamProfit']                     = formatMoneyFromMongo($item['teamProfit']??0);
                $item['totalTeamProfit']                = formatMoneyFromMongo($item['totalTeamProfit']??0);
                $item['teamProfitTotal']                = formatMoneyFromMongo($item['teamProfitTotal']??0);
                $item['totalTeamProfitTotal']           = formatMoneyFromMongo($item['totalTeamProfitTotal']??0);

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