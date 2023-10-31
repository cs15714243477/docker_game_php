<?php
namespace app\model;

//use support\Model;
use Jenssegers\Mongodb\Eloquent\Model;
use support\Db;

class CoinPlatformDataRecord extends Model
{
    protected $connection = 'mongodb_main';
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
        //$where['clubId'] = 1000;


        $rs = Db::connection('mongodb_main')->collection('platform_data_record')->raw()->aggregate([
            [
                '$match' => $where
            ],
            [
                '$project' =>
                    [
                        '_id' => 0,
                        'todayRechargeAmount' => 1,
                        'todayExchangeAmount' => 1,
                        'todayAllBetScore' => 1,
                        'todayRewardScore' => 1,
                        'todayPromoterScore' => 1,
                    ],
            ],
            [
                '$group' =>
                    [
                        '_id' => null,
                        'todayRechargeAmount' =>    ['$sum' => '$todayRechargeAmount'],
                        'todayExchangeAmount' =>    ['$sum' => '$todayExchangeAmount'],
                        'todayAllBetScore' =>    ['$sum' => '$todayAllBetScore'],
                        'todayRewardScore' =>    ['$sum' => '$todayRewardScore'],
                        'todayPromoterScore' => ['$sum' => '$todayPromoterScore'],
                    ]
            ]
        ])->toArray();

        $resultArray = [];
        if($rs){
            foreach ($rs as &$item){
                $item['todayRechargeAmount'] = formatMoneyFromMongo($item['todayRechargeAmount']??0);
                $item['todayExchangeAmount'] = formatMoneyFromMongo($item['todayExchangeAmount']??0);
                $item['todayAllBetScore'] = formatMoneyFromMongo($item['todayAllBetScore']??0);
                $item['todayRewardScore'] = formatMoneyFromMongo($item['todayRewardScore']??0);
                $item['todayPromoterScore'] = formatMoneyFromMongo($item['todayPromoterScore']??0);
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
        $where['promoterId'] = ['$ne' => 1000];
        $where['date'] = ['$gte' => $startTimeMongo, '$lt' => $endTimeMongo];

        $rs = Db::connection('mongodb_main')->collection('platform_data_record')->raw()->aggregate([
            [
                '$match' => $where
            ],
            [
                '$project' =>
                    [
                        '_id' => 0,
                        'Date' => ['$substr' => [['$add' => ['$date',28800000]], 0, 10]],
                        'totalLoginCount' => 1,
                        'totalGameCount' => 1,
                        'totalOnlineLoginTime' =>1,
                        'totalOnlineGameTime'=>1,
                        'totalRechargeAmount'=>1,
                        'totalRechargeTimes'=>1,
                        'totalExchangeAmount'=>1,
                        'totalExchangeTimes'=>1,
                        'totalAllBetScore'=>1,
                        'totalValidBetScore'=>1,
                        'totalRevenue'=>1,
                        'totalWinScore'=>1,
                        'totalScore'=>1,
                        'totalBankScore'=>1,
                        'todayPromoterScore'=>1,
                        'totalPromoterScore'=>1,
                        'currentPromoterScore'=>1,
                        'totalPromoterExchange'=>1,
                        'totalPromoterCount'=>1,
                        'totalRoomCard'=>1,
                        'todayRewardScore'=>1,
                        'totalRewardScore'=>1,
                        'todayClubPromoterScore'=>1,
                        'totalClubPromoterScore'=>1,
                        'todayClubRewardScore'=>1,
                        'totalClubRewardScore'=>1,
                        'todayRechargeAmount'=>1,
                        'todayExchangeAmount'=>1,
                        'totalExchangePeople'=>1,
                        'todayRechargeTimes'=>1,
                        'todayExchangeTimes'=>1,
                        'todayAllBetScore'=>1,
                        'todayValidBetScore'=>1,
                        'totalBindPeople'=>1,
                        'todayGoldClubBetPeople'=>1,
                        'totalBetPeople'=>1
                    ],
            ],
        ])->toArray();

        if($rs){
            foreach ($rs as &$item){
                $item['totalScore'] = formatMoneyFromMongo($item['totalScore']??0);
                $item['totalBankScore']         = formatMoneyFromMongo($item['totalBankScore']??0);
                $item['totalPromoterScore']     = formatMoneyFromMongo($item['totalPromoterScore']??0);
                $item['currentPromoterScore']   = formatMoneyFromMongo($item['currentPromoterScore']??0);
                $item['todayPromoterScore']     = formatMoneyFromMongo($item['todayPromoterScore']??0);
                $item['totalRoomCard']          = formatMoneyFromMongo($item['totalRoomCard']??0);
                $item['totalRewardScore']       = formatMoneyFromMongo($item['totalRewardScore']??0);
                $item['todayRewardScore']       = formatMoneyFromMongo($item['todayRewardScore']??0);
                $item['totalClubPromoterScore'] = formatMoneyFromMongo($item['totalClubPromoterScore']??0);
                $item['todayClubPromoterScore'] = formatMoneyFromMongo($item['todayClubPromoterScore']??0);
                $item['totalClubRewardScore']   = formatMoneyFromMongo($item['totalClubRewardScore']??0);
                $item['todayClubRewardScore']   = formatMoneyFromMongo($item['todayClubRewardScore']??0);
                $item['todayAllBetScore']       = formatMoneyFromMongo($item['todayAllBetScore']??0);
                $item['totalAllBetScore']       = formatMoneyFromMongo($item['totalAllBetScore']??0);
                $item['todayValidBetScore']     = formatMoneyFromMongo($item['todayValidBetScore']??0);
                $item['totalValidBetScore']     = formatMoneyFromMongo($item['totalValidBetScore']??0);

                $item['todayRechargeAmount']       = formatMoneyFromMongo($item['todayRechargeAmount']??0);
                $item['totalRechargeAmount']       = formatMoneyFromMongo($item['totalRechargeAmount']??0);
                $item['todayExchangeAmount']       = formatMoneyFromMongo($item['todayExchangeAmount']??0);
                $item['totalExchangeAmount']       = formatMoneyFromMongo($item['totalExchangeAmount']??0);

                $resultArray[$item['Date']] = $item;
            }
        }
        return $resultArray;
    }
}