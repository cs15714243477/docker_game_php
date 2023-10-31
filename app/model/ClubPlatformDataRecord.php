<?php
namespace app\model;

//use support\Model;
use Jenssegers\Mongodb\Eloquent\Model;
use support\Db;

class ClubPlatformDataRecord extends Model
{
    protected $connection = 'mongodb_club';
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'platform_data_record';

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

    public static function statPlatformData($startDate,$endDate)
    {
        $startTime = $startDate ?? date("Y-m-d");
        $endTime = $endDate ?? date("Y-m-d");
        $startTime = strtotime(trim($startTime));
        $endTime = strtotime(trim($endTime))+ 86400;
        $startTimeMongo = formatTimestampToMongo($startTime);
        $endTimeMongo = formatTimestampToMongo($endTime);

        $where = [];
        $where['date'] = ['$gte' => $startTimeMongo, '$lt' => $endTimeMongo];
        $where['clubId'] = -1000;


        $rs = Db::connection('mongodb_club')->collection('platform_data_record')->raw()->aggregate([
            [
                '$match' => $where
            ],
            [
                '$project' =>
                    [
                        '_id' => 0,
                        'platformWinScore' => 1,
                        'allBet' => 1,
                        'revenue' => 1,
                        'platformProfit' => 1,
                        'rewardScore' => 1,
                        'promoterScore' => 1,
                    ],
            ],
            [
                '$group' =>
                    [
                        '_id' => null,
                        'platformWinScore' =>    ['$sum' => '$platformWinScore'],
                        'allBet' =>    ['$sum' => '$allBet'],
                        'revenue' =>    ['$sum' => '$revenue'],
                        'platformProfit' =>    ['$sum' => '$platformProfit'],
                        'rewardScore' =>    ['$sum' => '$rewardScore'],
                        'promoterScore' => ['$sum' => '$promoterScore'],
                    ]
            ]
        ])->toArray();

        $resultArray = [];
        if($rs){
            foreach ($rs as &$item){
                $item['platformWinScore'] = formatMoneyFromMongo($item['platformWinScore']??0);
                $item['allBet'] = formatMoneyFromMongo($item['allBet']??0);
                $item['revenue'] = formatMoneyFromMongo($item['revenue']??0);
                $item['platformProfit'] = formatMoneyFromMongo($item['platformProfit']??0);
                $item['rewardScore'] = formatMoneyFromMongo($item['rewardScore']??0);
                $item['promoterScore'] = formatMoneyFromMongo($item['promoterScore']??0);
            }
            $resultArray = $rs[0];
        }
        return $resultArray;
    }

    public static function getDataByDate($startDate, $endDate) {

        $resultArray = [];

        $startTime = strtotime(trim($startDate));
        $endTime = strtotime(trim($endDate)) + 86400;
        $startTimeMongo = formatTimestampToMongo($startTime);
        $endTimeMongo = formatTimestampToMongo($endTime);

        $where = [];
        $where['clubId'] = -1000;
        $where['date'] = ['$gte' => $startTimeMongo, '$lt' => $endTimeMongo];

        $rs = Db::connection('mongodb_club')->collection('platform_data_record')->raw()->aggregate([
            [
                '$match' => $where
            ],
            [
                '$project' =>
                    [
                        '_id' => 0,
                        'Date' => ['$substr' => [['$add' => ['$date',28800000]], 0, 10]],
                        'platformWinScore' => 1,
                        'totalPlatformWinScore' => 1,
                        'allBet' => 1,
                        'totalAllBet' => 1,
                        'validBet' => 1,
                        'totalValidBet' => 1,
                        'revenue' => 1,
                        'agentRevenue' => 1,
                        'totalAgentRevenue' => 1,
                        'totalRevenue' => 1,
                        'platformProfit' =>1,
                        'totalPlatformProfit' =>1,
                        'rewardScore' => 1,
                        'totalRewardScore' => 1,
                        'promoterScore' => 1,
                        'totalPromoterScore' => 1,
                        'todayPromoterScore' => 1,
                        'currentPromoterScore' => 1,
                        'gamePlayerCount'=>1,
                    ],
            ],
        ])->toArray();

        if($rs){
            foreach ($rs as &$item){
                $item['platformWinScore'] =      formatMoneyFromMongo($item['platformWinScore']??0);
                $item['totalPlatformWinScore'] = formatMoneyFromMongo($item['totalPlatformWinScore']??0);
                $item['allBet'] =                formatMoneyFromMongo($item['allBet']??0);
                $item['totalAllBet'] =           formatMoneyFromMongo($item['totalAllBet']??0);
                $item['validBet'] =              formatMoneyFromMongo($item['validBet']??0);
                $item['totalValidBet'] =         formatMoneyFromMongo($item['totalValidBet']??0);
                $item['revenue'] =               formatMoneyFromMongo($item['revenue']??0);
                $item['agentRevenue'] =          formatMoneyFromMongo($item['agentRevenue']??0);
                $item['totalAgentRevenue'] =     formatMoneyFromMongo($item['totalAgentRevenue']??0);
                $item['totalRevenue'] =          formatMoneyFromMongo($item['totalRevenue']??0);
                $item['platformProfit'] =        formatMoneyFromMongo($item['platformProfit']??0);
                $item['totalPlatformProfit'] =   formatMoneyFromMongo($item['totalPlatformProfit']??0);
                $item['rewardScore'] =           formatMoneyFromMongo($item['rewardScore']??0);
                $item['totalRewardScore'] =      formatMoneyFromMongo($item['totalRewardScore']??0);
                $item['promoterScore'] =         formatMoneyFromMongo($item['promoterScore']??0);
                $item['totalPromoterScore'] =    formatMoneyFromMongo($item['totalPromoterScore']??0);
                $item['todayPromoterScore'] =    formatMoneyFromMongo($item['todayPromoterScore']??0);
                $item['currentPromoterScore'] =  formatMoneyFromMongo($item['currentPromoterScore']??0);

                $resultArray[$item['Date']] = $item;
            }
        }
        return $resultArray;
    }
}