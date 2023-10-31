<?php
namespace app\controller;

use app\model\ClubPlatformDataRecord;
use app\model\ClubStatPromoterDaily;
use app\model\CoinPlatformDataRecord;
use support\bootstrap\Container;
use support\Request;
use app\model\StatPromoterDaily;
use app\model\GameUser;
use app\model\GameLog;
use support\Db;

class Platform extends Base
{
    public function index(Request $request)
    {
        $act = $request->get('act');
        $controller = Container::get($request->controller);
        if (method_exists($controller, $act)) {
            $before_response = call_user_func([$controller, $act], $request);
            return $before_response;
            if ($before_response instanceof Response) {
                dd('yes');
                return $before_response;
            }
        }
    }

    public function platformView(Request $request)
    {
        return view('platform/platformView');
    }
    public function platFormInfo1(Request $request)
    {
        return view('platform/platFormInfo1');
    }
    public function platFormInfo2(Request $request)
    {

        return view('platform/platFormInfo2');
    }

    public function platFormInfo3(Request $request)
    {
        return view('platform/platFormInfo3');
    }
    public function userScaleInfo(Request $request)
    {
        return view('platform/userScaleInfo');
    }
    public function revenueScaleInfo(Request $request)
    {
        return view('platform/revenueScaleInfo');
    }


    public function platFormInfoold(Request $request)
    {
        $result_data = [];
        $date_t = date("Y-m-d");
        $date_y = date("Y-m-d", strtotime('-1 day'));
        $date_b = date("Y-m-d", strtotime('-2 day'));

        //平台代理统计(系统税收,系统代理佣金,系统提现,系统充值,游戏盈亏,今天总注册人数,今天总活跃数,今天绑定人数,团队人数,有效新增(regActivePeople))
        $promoter_daily = $this->get_promoter_daily($date_y,$date_t);
        $promoter_today_count = $this->get_promoter_daily2($date_t,$date_t);
        $promoter_yes_count = $this->get_promoter_daily2($date_y,$date_y);

        $platform_data_record_t = $this->get_yes_data($date_t);
        $platform_data_record_y = $this->get_yes_data($date_y);
        $platformClubRecordTo = $this->getDataRecord($date_t);
        $platformClubRecordYe = $this->getDataRecord($date_y);
        $promoter_today_club = $this->getPromoterDailyClub($date_t,$date_t);
        $promoter_yes_club = $this->getPromoterDailyClub($date_y,$date_y);

        if(empty($promoter_daily[$date_t]['teamRegPeople'])){
            $promoter_daily[$date_t]['teamRegPeople'] = 0;
        }
        if(empty($promoter_daily[$date_t]['teamRegBindPeople'])){
            $promoter_daily[$date_t]['teamRegBindPeople'] = 0;
        }
        if(empty($promoter_daily[$date_t]['teamRegValidNewBetPeople'])){
            $promoter_daily[$date_t]['teamRegValidNewBetPeople'] = 0;
        }
        if(empty($promoter_daily[$date_t]['teamActivePeople'])){
            $promoter_daily[$date_t]['teamActivePeople'] = 0;
        }

        if(empty($promoter_daily[$date_y]['teamRegPeople'])){
            $promoter_daily[$date_y]['teamRegPeople'] = 0;
        }
        if(empty($promoter_daily[$date_y]['teamRegValidNewBetPeople'])){
            $promoter_daily[$date_y]['teamRegValidNewBetPeople'] = 0;
        }
        if(empty($promoter_daily[$date_y]['teamRegBindPeople'])){
            $promoter_daily[$date_y]['teamRegBindPeople'] = 0;
        }
        if(empty($promoter_daily[$date_y]['teamActivePeople'])){
            $promoter_daily[$date_y]['teamActivePeople'] = 0;
        }

        $result_data['xzyh'] = $this->get_format_result($promoter_daily[$date_t]['teamRegPeople'],$promoter_daily[$date_y]['teamRegPeople'],1);
        $result_data['yxxz'] = $this->get_format_result($promoter_daily[$date_t]['teamRegValidNewBetPeople'],$promoter_daily[$date_y]['teamRegValidNewBetPeople'],1);
        $result_data['zcyh'] = $this->get_format_result($promoter_daily[$date_t]['teamRegBindPeople'],$promoter_daily[$date_y]['teamRegBindPeople'],1);
        $result_data['hyyh'] = $this->get_format_result($promoter_daily[$date_t]['teamActivePeople'],$promoter_daily[$date_y]['teamActivePeople'],1);

        //游戏人数
        $game_user_count = $this->get_game_users($date_y,$date_t);
        if(empty($game_user_count[$date_t])){
            $game_user_count[$date_t] = 0;
        }
        if(empty($game_user_count[$date_y])){
            $game_user_count[$date_y] = 0;
        }
        $result_data['yxrs'] = $this->get_format_result($game_user_count[$date_t],$game_user_count[$date_y],1);

        //新增次日留存
        $keep_user_t = $this->keep_next_day($date_t);//获取昨天新增用户中今天登录的数量
        $keep_user_y = $this->keep_next_day($date_y);//获取前天新增用户中昨天登录的数量

        $new_users_data_b = $this->get_new_users($date_b);//获取前天新增用户数量
        $new_users_data_y = $this->get_new_users($date_y);//获取昨天新增用户数量

        $result_data['crlc'] = $this->percent_format_result($keep_user_t,$keep_user_y,$new_users_data_y,$new_users_data_b);//$promoter_daily[$date_y]['teamRegPeople']
        $result_data['crlc']['vals_t']  = (float)sprintf("%01.2f", $result_data['crlc']['vals_t'] *100);
        //当前在线
        $result_data['dqzx'] = $this->get_on_line();
        //累计用户
        if(empty($promoter_daily[$date_t]['totalTeamPlayerCount'])){
            $promoter_daily[$date_t]['totalTeamPlayerCount'] = 0;
        }
        if(empty($promoter_daily[$date_y]['totalTeamPlayerCount'])){
            $promoter_daily[$date_y]['totalTeamPlayerCount'] = 0;
        }
        $result_data['ljyh'] = $this->get_format_result($promoter_daily[$date_t]['totalTeamPlayerCount'],$promoter_daily[$date_y]['totalTeamPlayerCount'],1);

        //平台(付费用户,首充用户,首充金额,多次充值)统计(stat_recharge_home综合表)
        $recharge_home = $this->get_recharge_home($date_y,$date_t);
        //付费用户
        if(empty($recharge_home[$date_t]['pay_users'])){
            $recharge_home[$date_t]['pay_users'] = 0;
        }
        if(empty($recharge_home[$date_y]['pay_users'])){
            $recharge_home[$date_y]['pay_users'] = 0;
        }
        $result_data['ffyh'] = $this->get_format_result($recharge_home[$date_t]['pay_users'],$recharge_home[$date_y]['pay_users'],1);
        //首充用户
        if(empty($recharge_home[$date_t]['first_charge_user'])){
            $recharge_home[$date_t]['first_charge_user'] = 0;
        }
        if(empty($recharge_home[$date_y]['first_charge_user'])){
            $recharge_home[$date_y]['first_charge_user'] = 0;
        }
        $result_data['scyh'] = $this->get_format_result($recharge_home[$date_t]['first_charge_user'],$recharge_home[$date_y]['first_charge_user'],1);
        //首充金额
        if(empty($recharge_home[$date_t]['first_charge_amount'])){
            $recharge_home[$date_t]['first_charge_amount'] = 0;
        }
        if(empty($recharge_home[$date_y]['first_charge_amount'])){
            $recharge_home[$date_y]['first_charge_amount'] = 0;
        }
        $result_data['scje'] = $this->get_format_result($recharge_home[$date_t]['first_charge_amount'],$recharge_home[$date_y]['first_charge_amount'],1);
        //二次充值比例
        if(empty($recharge_home[$date_t]['second_charge_user'])){
            $recharge_home[$date_t]['second_charge_user'] = 0;
        }
        if(empty($recharge_home[$date_y]['second_charge_user'])){
            $recharge_home[$date_y]['second_charge_user'] = 0;
        }
        $result_data['eccz'] = $this->percent_format_result($recharge_home[$date_t]['second_charge_user'],$recharge_home[$date_y]['second_charge_user'],$recharge_home[$date_t]['pay_users'],$recharge_home[$date_y]['pay_users']);
        $result_data['eccz']['vals_t'] = (float)sprintf("%01.2f", $result_data['eccz']['vals_t']*100)."%";
        //官方税收
        $todayTeamRevenue = $this->formatMoneyFromMongoNo($promoter_today_count[$date_t]['teamRevenue_total'] + $platformClubRecordTo[$date_t]['revenue']);
        $yesTeamRevenue = $this->formatMoneyFromMongoNo($promoter_today_count[$date_t]['teamRevenue_total'] + $platformClubRecordTo[$date_t]['revenue']);
        $result_data['xtss'] = $this->get_format_result($todayTeamRevenue,$yesTeamRevenue,1);

        //代理佣金




//        $agency_commission_arr = $this->get_agency_commission($date_y,$date_t);
//        if(empty($agency_commission_arr[$date_t]['teamProfit'])){
//            $agency_commission_arr[$date_t]['teamProfit'] = 0;
//        }
//        if(empty($agency_commission_arr[$date_y]['teamProfit'])){
//            $agency_commission_arr[$date_y]['teamProfit'] = 0;
//        }
        $todayTlyjData = $this->formatMoneyFromMongoNo($promoter_today_count[$date_t]['teamProfit_total'] + $this->formatMoneyFromMongoNo($promoter_today_club[$date_t]['myProfit_total'] + $promoter_today_club[$date_t]['teamProfit_total']));
        $yesTlyjData = $this->formatMoneyFromMongoNo($promoter_yes_count[$date_y]['teamProfit_total'] + $this->formatMoneyFromMongoNo($promoter_yes_club[$date_y]['myProfit_total'] + $promoter_yes_club[$date_y]['teamProfit_total']));
        $result_data['dlyj'] = $this->get_format_result($todayTlyjData,$yesTlyjData,1);
        //系统提现(兑换)
        if(empty($promoter_daily[$date_t]['teamExchangeAmount'])){
            $promoter_daily[$date_t]['teamExchangeAmount'] = 0;
        }
        if(empty($promoter_daily[$date_y]['teamExchangeAmount'])){
            $promoter_daily[$date_y]['teamExchangeAmount'] = 0;
        }
        $result_data['dh'] = $this->get_format_result($promoter_daily[$date_t]['teamExchangeAmount'],$promoter_daily[$date_y]['teamExchangeAmount'],1);
        //$result_data['dh']['vals_t'] = $this->formatMoneyFromMongo($result_data['dh']['vals_t']);
        //系统充值
        if(empty($promoter_daily[$date_t]['teamRechargeAmount'])){
            $promoter_daily[$date_t]['teamRechargeAmount'] = 0;
        }
        if(empty($promoter_daily[$date_y]['teamRechargeAmount'])){
            $promoter_daily[$date_y]['teamRechargeAmount'] = 0;
        }
        $result_data['cz'] = $this->get_format_result($this->formatMoneyFromMongo($platform_data_record_t[$date_t]['todayRechargeAmount']??0),$this->formatMoneyFromMongo($platform_data_record_y[$date_y]['todayRechargeAmount']??0),1);
        //游戏输赢
//        if(empty($promoter_daily[$date_t]['teamWinScore'])){
//            $promoter_daily[$date_t]['teamWinScore'] = 0;
//        }
//        if(empty($promoter_daily[$date_y]['teamWinScore'])){
//            $promoter_daily[$date_y]['teamWinScore'] = 0;
//        }
//        if(empty($promoter_daily[$date_t]['teamGameWinScore'])){
//            $promoter_daily[$date_t]['teamGameWinScore'] = 0;
//        }
//        if(empty($promoter_daily[$date_y]['teamGameWinScore'])){
//            $promoter_daily[$date_y]['teamGameWinScore'] = 0;
//        }



         $yxsyTo = $this->formatMoneyFromMongoNo($promoter_today_count[$date_t]['teamGameWinScore_total'] + $platformClubRecordTo[$date_t]['platformWinScore']);
         $yxsyYe = $this->formatMoneyFromMongoNo($promoter_yes_count[$date_y]['teamGameWinScore_total'] + $platformClubRecordYe[$date_y]['platformWinScore']);



        $result_data['yxsy'] = $this->get_format_result($yxsyTo,$yxsyYe,1);
        //官方盈亏 = 金币场官方盈亏+俱乐部官方盈亏





        $platform_data_record_t[$date_t]['totalScore'] = !empty($platform_data_record_t[$date_t]['totalScore']) ? $this->formatMoneyFromMongo($platform_data_record_t[$date_t]['totalScore']) : 0;
        $platform_data_record_t[$date_t]['totalBankScore'] = !empty($platform_data_record_t[$date_t]['totalBankScore']) ? $this->formatMoneyFromMongo($platform_data_record_t[$date_t]['totalBankScore']) : 0;
        $platform_data_record_t[$date_t]['totalPromoterScore'] = !empty($platform_data_record_t[$date_t]['totalPromoterScore']) ? $this->formatMoneyFromMongo($platform_data_record_t[$date_t]['totalPromoterScore']) : 0;
        $platform_data_record_t[$date_t]['todayPromoterScore'] = !empty($platform_data_record_t[$date_t]['todayPromoterScore']) ? $this->formatMoneyFromMongo($platform_data_record_t[$date_t]['todayPromoterScore']) : 0;
        $platform_data_record_t[$date_t]['totalRoomCard'] = !empty($platform_data_record_t[$date_t]['totalRoomCard']) ? $this->formatMoneyFromMongo($platform_data_record_t[$date_t]['totalRoomCard']) : 0;
        $platform_data_record_t[$date_t]['totalRewardScore'] = !empty($platform_data_record_t[$date_t]['totalRewardScore']) ? $this->formatMoneyFromMongo($platform_data_record_t[$date_t]['totalRewardScore']) : 0;
        $platform_data_record_t[$date_t]['todayRewardScore'] = !empty($platform_data_record_t[$date_t]['todayRewardScore']) ? $this->formatMoneyFromMongo($platform_data_record_t[$date_t]['todayRewardScore']) : 0;
        $platform_data_record_t[$date_t]['totalClubPromoterScore'] = !empty($platform_data_record_t[$date_t]['totalClubPromoterScore']) ? $this->formatMoneyFromMongo($platform_data_record_t[$date_t]['totalClubPromoterScore']) : 0;
        $platform_data_record_t[$date_t]['todayClubPromoterScore'] = !empty($platform_data_record_t[$date_t]['todayClubPromoterScore']) ? $this->formatMoneyFromMongo($platform_data_record_t[$date_t]['todayClubPromoterScore']) : 0;
        $platform_data_record_t[$date_t]['totalClubRewardScore'] = !empty($platform_data_record_t[$date_t]['totalClubRewardScore']) ? $this->formatMoneyFromMongo($platform_data_record_t[$date_t]['totalClubRewardScore']) : 0;
        $platform_data_record_t[$date_t]['todayClubRewardScore'] = !empty($platform_data_record_t[$date_t]['todayClubRewardScore']) ? $this->formatMoneyFromMongo($platform_data_record_t[$date_t]['todayClubRewardScore']) : 0;
        $platform_data_record_t[$date_t]['todayAllBetScore'] = !empty($platform_data_record_t[$date_t]['todayAllBetScore']) ? $this->formatMoneyFromMongo($platform_data_record_t[$date_t]['todayAllBetScore']) : 0;
        $platform_data_record_t[$date_t]['totalAllBetScore'] = !empty($platform_data_record_t[$date_t]['totalAllBetScore']) ? $this->formatMoneyFromMongo($platform_data_record_t[$date_t]['totalAllBetScore']) : 0;
        $platform_data_record_t[$date_t]['todayValidBetScore'] = !empty($platform_data_record_t[$date_t]['todayValidBetScore']) ? $this->formatMoneyFromMongo($platform_data_record_t[$date_t]['todayValidBetScore']) : 0;
        $platform_data_record_t[$date_t]['totalValidBetScore'] = !empty($platform_data_record_t[$date_t]['totalValidBetScore']) ? $this->formatMoneyFromMongo($platform_data_record_t[$date_t]['totalValidBetScore']) : 0;

        $platform_data_record_y[$date_y]['totalScore'] = !empty($platform_data_record_y[$date_y]['totalScore']) ? $this->formatMoneyFromMongo($platform_data_record_y[$date_y]['totalScore']) : 0;
        $platform_data_record_y[$date_y]['totalBankScore'] = !empty($platform_data_record_y[$date_y]['totalBankScore']) ? $this->formatMoneyFromMongo($platform_data_record_y[$date_y]['totalBankScore']) : 0;
        $platform_data_record_y[$date_y]['totalPromoterScore'] = !empty($platform_data_record_y[$date_y]['totalPromoterScore']) ? $this->formatMoneyFromMongo($platform_data_record_y[$date_y]['totalPromoterScore']) : 0;
        $platform_data_record_y[$date_y]['todayPromoterScore'] = !empty($platform_data_record_y[$date_y]['todayPromoterScore']) ? $this->formatMoneyFromMongo($platform_data_record_y[$date_y]['todayPromoterScore']) : 0;
        $platform_data_record_y[$date_y]['totalRoomCard'] = !empty($platform_data_record_y[$date_y]['totalRoomCard']) ? $this->formatMoneyFromMongo($platform_data_record_y[$date_y]['totalRoomCard']) : 0;
        $platform_data_record_y[$date_y]['totalRewardScore'] = !empty($platform_data_record_y[$date_y]['totalRewardScore']) ? $this->formatMoneyFromMongo($platform_data_record_y[$date_y]['totalRewardScore']) : 0;
        $platform_data_record_y[$date_y]['todayRewardScore'] = !empty($platform_data_record_y[$date_y]['todayRewardScore']) ? $this->formatMoneyFromMongo($platform_data_record_y[$date_y]['todayRewardScore']) : 0;
        $platform_data_record_y[$date_y]['totalClubPromoterScore'] = !empty($platform_data_record_y[$date_y]['totalClubPromoterScore']) ? $this->formatMoneyFromMongo($platform_data_record_y[$date_y]['totalClubPromoterScore']) : 0;
        $platform_data_record_y[$date_y]['todayClubPromoterScore'] = !empty($platform_data_record_y[$date_y]['todayClubPromoterScore']) ? $this->formatMoneyFromMongo($platform_data_record_y[$date_y]['todayClubPromoterScore']) : 0;
        $platform_data_record_y[$date_y]['totalClubRewardScore'] = !empty($platform_data_record_y[$date_y]['totalClubRewardScore']) ? $this->formatMoneyFromMongo($platform_data_record_y[$date_y]['totalClubRewardScore']) : 0;
        $platform_data_record_y[$date_y]['todayClubRewardScore'] = !empty($platform_data_record_y[$date_y]['todayClubRewardScore']) ? $this->formatMoneyFromMongo($platform_data_record_y[$date_y]['todayClubRewardScore']) : 0;
        $platform_data_record_y[$date_y]['todayAllBetScore'] = !empty($platform_data_record_y[$date_y]['todayAllBetScore']) ? $this->formatMoneyFromMongo($platform_data_record_y[$date_y]['todayAllBetScore']) : 0;
        $platform_data_record_y[$date_y]['totalAllBetScore'] = !empty($platform_data_record_y[$date_y]['totalAllBetScore']) ? $this->formatMoneyFromMongo($platform_data_record_y[$date_y]['totalAllBetScore']) : 0;
        $platform_data_record_y[$date_y]['todayValidBetScore'] = !empty($platform_data_record_y[$date_y]['todayValidBetScore']) ? $this->formatMoneyFromMongo($platform_data_record_y[$date_y]['todayValidBetScore']) : 0;
        $platform_data_record_y[$date_y]['totalValidBetScore'] = !empty($platform_data_record_y[$date_y]['totalValidBetScore']) ? $this->formatMoneyFromMongo($platform_data_record_y[$date_y]['totalValidBetScore']) : 0;





        //金币场官方盈亏
        $officialWinLoseCoinTo =  $this->formatMoneyFromMongoNo($promoter_today_count[$date_t]['teamRevenue_total'] + $promoter_today_count[$date_t]['teamGameWinScore_total'] - $promoter_today_count[$date_t]['teamProfit_total'] - $platform_data_record_t[$date_t]['todayRewardScore']);
        $officialWinLoseCoinYe =  $this->formatMoneyFromMongoNo($promoter_yes_count[$date_y]['teamRevenue_total'] + $promoter_yes_count[$date_y]['teamGameWinScore_total'] - $promoter_yes_count[$date_y]['teamProfit_total'] - $platform_data_record_y[$date_y]['todayRewardScore']);

        //俱乐部官方盈亏
        $officialWinLoseClubTo = $platformClubRecordTo[$date_t]['platformProfit'] - $platformClubRecordTo[$date_t]['rewardScore'];
        $officialWinLoseClubYe = $platformClubRecordYe[$date_y]['platformProfit'] - $platformClubRecordYe[$date_y]['rewardScore'];

        //汇总官方盈亏
        $sys_win_lose_t = $this->formatMoneyFromMongoNo($officialWinLoseCoinTo + $officialWinLoseClubTo);
        $sys_win_lose_y = $this->formatMoneyFromMongoNo($officialWinLoseCoinYe + $officialWinLoseClubYe);






//        //金币场官方盈亏
//        $officialWinLoseCoinTo =  $this->formatMoneyFromMongoNo($promoter_today_count[$date_t]['teamRevenue_total'] + $promoter_today_count[$date_t]['teamGameWinScore_total'] - $platform_data_record_t[$date_t]['todayPromoterScore'] - $platform_data_record_t[$date_t]['todayRewardScore']);
//        $officialWinLoseCoinYe =  $this->formatMoneyFromMongoNo($promoter_yes_count[$date_y]['teamRevenue_total'] + $promoter_yes_count[$date_y]['teamGameWinScore_total'] - $platform_data_record_y[$date_y]['todayPromoterScore'] - $platform_data_record_y[$date_y]['todayRewardScore']);
//        //俱乐部官方盈亏
//        $officialWinLoseClubTo = $platformClubRecordTo[$date_t]['platformProfit'] - $platformClubRecordTo[$date_t]['rewardScore'];
//        $officialWinLoseClubYe = $platformClubRecordYe[$date_y]['platformProfit'] - $platformClubRecordYe[$date_y]['rewardScore'];
//        //汇总官方盈亏
//        $sys_win_lose_t = $this->formatMoneyFromMongoNo($officialWinLoseCoinTo + $officialWinLoseClubTo);
//        $sys_win_lose_y = $this->formatMoneyFromMongoNo($officialWinLoseCoinYe + $officialWinLoseClubYe);







        $result_data['xtyk'] = $this->get_format_result($sys_win_lose_t,$sys_win_lose_y,1);

//        //奖励支出
//        if(empty($promoter_daily[$date_t]['teamRewardAmount'])){
//            $promoter_daily[$date_t]['teamRewardAmount'] = 0;
//        }

        $result_data['jlzc'] = $this->formatMoneyFromMongoNo($platform_data_record_t[$date_t]['todayRewardScore'] + $platformClubRecordTo[$date_t]['rewardScore']);
        //俱乐部数据

        $result_data['winScoreClub'] = $platformClubRecordTo[$date_t]['platformWinScore'];//游戏输赢
        $result_data['revenueClub'] = $platformClubRecordTo[$date_t]['revenue'];//税收
        $result_data['gfykClub'] = $platformClubRecordTo[$date_t]['platformProfit'] - $platformClubRecordTo[$date_t]['rewardScore'];//盈亏
        $result_data['rewardScoreClub'] = $platformClubRecordTo[$date_t]['rewardScore'];//奖励
        $result_data['playerCountClub'] = $platformClubRecordTo[$date_t]['gamePlayerCount'];//人数


        return view('platform/platFormInfo', ['data' => $result_data]);
    }

    public function platFormInfo(Request $request)
    {
        $result_data = [];
        $date_t = date("Y-m-d");
        $date_y = date("Y-m-d", strtotime('-1 day'));
        $date_b = date("Y-m-d", strtotime('-2 day'));

        //$promoter_daily = $this->get_promoter_daily($date_y,$date_t);
        $promoter_today_count = $this->get_promoter_daily2($date_t,$date_t);
        $promoter_yes_count = $this->get_promoter_daily2($date_y,$date_y);
        $promoter_daily = StatPromoterDaily::getDataByDate($date_y, $date_t);

        //$platform_data_record_t = $this->get_yes_data($date_t);
        //$platform_data_record_y = $this->get_yes_data($date_y);
        $platform_data_record_t = CoinPlatformDataRecord::getDataByDate($date_t, $date_t);
        $platform_data_record_y = CoinPlatformDataRecord::getDataByDate($date_y, $date_y);

        /*$platformClubRecordTo = $this->getDataRecord($date_t);
        $platformClubRecordYe = $this->getDataRecord($date_y);
        $promoter_today_club = $this->getPromoterDailyClub($date_t,$date_t);
        $promoter_yes_club = $this->getPromoterDailyClub($date_y,$date_y);*/
        $platformClubRecordTo = ClubPlatformDataRecord::getDataByDate($date_t, $date_t);
        $platformClubRecordYe = ClubPlatformDataRecord::getDataByDate($date_y, $date_y);
        $promoter_today_club = ClubStatPromoterDaily::getDataByDate($date_t, $date_t);
        $promoter_yes_club = ClubStatPromoterDaily::getDataByDate($date_y, $date_y);


        $result_data['xzyh'] = $this->get_format_result($promoter_daily[$date_t]['teamRegPeople'],$promoter_daily[$date_y]['teamRegPeople'],1);
        $result_data['yxxz'] = $this->get_format_result($promoter_daily[$date_t]['teamRegValidNewBetPeople'],$promoter_daily[$date_y]['teamRegValidNewBetPeople'],1);
        $result_data['zcyh'] = $this->get_format_result($promoter_daily[$date_t]['teamRegBindPeople'],$promoter_daily[$date_y]['teamRegBindPeople'],1);
        $result_data['hyyh'] = $this->get_format_result($promoter_daily[$date_t]['teamActivePeople'],$promoter_daily[$date_y]['teamActivePeople'],1);
        $result_data['yxrs'] = $this->get_format_result($platform_data_record_t[$date_t]['todayGoldClubBetPeople'],$platform_data_record_y[$date_y]['todayGoldClubBetPeople'],1);



        //新增次日留存
        $keep_user_t = $this->keep_next_day($date_t);//获取昨天新增用户中今天登录的数量
        $keep_user_y = $this->keep_next_day($date_y);//获取前天新增用户中昨天登录的数量

        $new_users_data_b = $this->get_new_users($date_b);//获取前天新增用户数量
        $new_users_data_y = $this->get_new_users($date_y);//获取昨天新增用户数量

        $result_data['crlc'] = $this->percent_format_result($keep_user_t,$keep_user_y,$new_users_data_y,$new_users_data_b);//$promoter_daily[$date_y]['teamRegPeople']
        $result_data['crlc']['vals_t']  = (float)sprintf("%01.2f", $result_data['crlc']['vals_t'] *100);
        //当前在线
        $result_data['dqzx'] = $this->get_on_line();
        //累计用户
        $result_data['ljyh'] = $this->get_format_result($promoter_daily[$date_t]['totalTeamPlayerCount']??0,$promoter_daily[$date_y]['totalTeamPlayerCount']??0,1);

        //平台(付费用户,首充用户,首充金额,多次充值)统计(stat_recharge_home综合表)
        $recharge_home = $this->get_recharge_home($date_y,$date_t);
        //付费用户
        if(empty($recharge_home[$date_t]['pay_users'])){
            $recharge_home[$date_t]['pay_users'] = 0;
        }
        if(empty($recharge_home[$date_y]['pay_users'])){
            $recharge_home[$date_y]['pay_users'] = 0;
        }
        $result_data['ffyh'] = $this->get_format_result($recharge_home[$date_t]['pay_users'],$recharge_home[$date_y]['pay_users'],1);
        //首充用户
        if(empty($recharge_home[$date_t]['first_charge_user'])){
            $recharge_home[$date_t]['first_charge_user'] = 0;
        }
        if(empty($recharge_home[$date_y]['first_charge_user'])){
            $recharge_home[$date_y]['first_charge_user'] = 0;
        }
        $result_data['scyh'] = $this->get_format_result($recharge_home[$date_t]['first_charge_user'],$recharge_home[$date_y]['first_charge_user'],1);
        //首充金额
        if(empty($recharge_home[$date_t]['first_charge_amount'])){
            $recharge_home[$date_t]['first_charge_amount'] = 0;
        }
        if(empty($recharge_home[$date_y]['first_charge_amount'])){
            $recharge_home[$date_y]['first_charge_amount'] = 0;
        }
        $result_data['scje'] = $this->get_format_result($recharge_home[$date_t]['first_charge_amount'],$recharge_home[$date_y]['first_charge_amount'],1);
        //二次充值比例
        if(empty($recharge_home[$date_t]['second_charge_user'])){
            $recharge_home[$date_t]['second_charge_user'] = 0;
        }
        if(empty($recharge_home[$date_y]['second_charge_user'])){
            $recharge_home[$date_y]['second_charge_user'] = 0;
        }
        $result_data['eccz'] = $this->percent_format_result($recharge_home[$date_t]['second_charge_user'],$recharge_home[$date_y]['second_charge_user'],$recharge_home[$date_t]['pay_users'],$recharge_home[$date_y]['pay_users']);
        $result_data['eccz']['vals_t'] = (float)sprintf("%01.2f", $result_data['eccz']['vals_t']*100)."%";
        //官方税收
        $todayTeamRevenue = dataSummary($promoter_daily[$date_t], $promoter_today_club[$date_t], $platform_data_record_t[$date_t], $platformClubRecordTo[$date_t], 'revenue');
        $yesTeamRevenue = dataSummary($promoter_daily[$date_y], $promoter_yes_club[$date_y], $platform_data_record_y[$date_y], $platformClubRecordYe[$date_y], 'revenue');
        $result_data['xtss'] = $this->get_format_result($todayTeamRevenue,$yesTeamRevenue,1);

        //代理佣金

        $todayTlyjData = dataSummary($promoter_daily[$date_t], $promoter_today_club[$date_t], $platform_data_record_t[$date_t], $platformClubRecordTo[$date_t], 'promoterScore');
        $yesTlyjData = dataSummary($promoter_daily[$date_y], $promoter_yes_club[$date_y], $platform_data_record_y[$date_y], $platformClubRecordYe[$date_y], 'promoterScore');
        $result_data['dlyj'] = $this->get_format_result($todayTlyjData,$yesTlyjData,1);
        //系统提现(兑换)

        $result_data['dh'] = $this->get_format_result($platform_data_record_t[$date_t]['todayExchangeAmount']??0,$platform_data_record_y[$date_y]['todayExchangeAmount']??0,1);
        //$result_data['dh']['vals_t'] = $this->formatMoneyFromMongo($result_data['dh']['vals_t']);
        //系统充值
        $result_data['cz'] = $this->get_format_result($platform_data_record_t[$date_t]['todayRechargeAmount']??0,$platform_data_record_y[$date_y]['todayRechargeAmount']??0,1);
        //游戏输赢
//        if(empty($promoter_daily[$date_t]['teamWinScore'])){
//            $promoter_daily[$date_t]['teamWinScore'] = 0;
//        }
//        if(empty($promoter_daily[$date_y]['teamWinScore'])){
//            $promoter_daily[$date_y]['teamWinScore'] = 0;
//        }
//        if(empty($promoter_daily[$date_t]['teamGameWinScore'])){
//            $promoter_daily[$date_t]['teamGameWinScore'] = 0;
//        }
//        if(empty($promoter_daily[$date_y]['teamGameWinScore'])){
//            $promoter_daily[$date_y]['teamGameWinScore'] = 0;
//        }



        $yxsyTo = dataSummary($promoter_daily[$date_t], $promoter_today_club[$date_t], $platform_data_record_t[$date_t], $platformClubRecordTo[$date_t], 'gameWinScore');
        $yxsyYe = dataSummary($promoter_daily[$date_y], $promoter_yes_club[$date_y], $platform_data_record_y[$date_y], $platformClubRecordYe[$date_y], 'gameWinScore');



        $result_data['yxsy'] = $this->get_format_result($yxsyTo,$yxsyYe,1);


        $sys_win_lose_t = dataSummary($promoter_daily[$date_t], $promoter_today_club[$date_t], $platform_data_record_t[$date_t], $platformClubRecordTo[$date_t], 'profitOrLoss');
        $sys_win_lose_y = dataSummary($promoter_daily[$date_y], $promoter_yes_club[$date_y], $platform_data_record_y[$date_y], $platformClubRecordYe[$date_y], 'profitOrLoss');
        $result_data['xtyk'] = $this->get_format_result($sys_win_lose_t,$sys_win_lose_y,1);

//        //奖励支出
//        if(empty($promoter_daily[$date_t]['teamRewardAmount'])){
//            $promoter_daily[$date_t]['teamRewardAmount'] = 0;
//        }

        $result_data['jlzc'] = dataSummary($promoter_daily[$date_t], $promoter_today_club[$date_t], $platform_data_record_t[$date_t], $platformClubRecordTo[$date_t], 'rewardScore');
        //俱乐部数据

        $result_data['winScoreClub'] = $platformClubRecordTo[$date_t]['platformWinScore'];//游戏输赢
        $result_data['revenueClub'] = $platformClubRecordTo[$date_t]['revenue'];//税收
        $result_data['gfykClub'] = $platformClubRecordTo[$date_t]['platformProfit'] - $platformClubRecordTo[$date_t]['rewardScore'];//盈亏
        $result_data['rewardScoreClub'] = $platformClubRecordTo[$date_t]['rewardScore'];//奖励
        $result_data['playerCountClub'] = $platformClubRecordTo[$date_t]['gamePlayerCount'];//人数


        return view('platform/platFormInfo', ['data' => $result_data]);
    }

    public function platOverView1(Request $request)
    {
        if ($request->isAjax()) {
            $date_y = date("Y-m-d", strtotime('-1 day'));
            //平台昨日盈利(会员总充值-会员总兑换-会员总金币-会员保险箱金币-代理可提取金币)
            $platform_data_record = $this->get_yes_data($date_y);
            $platform_data_record[$date_y]['totalRechargeAmount'] = !empty($platform_data_record[$date_y]['totalRechargeAmount']) ? $this->formatMoneyFromMongo($platform_data_record[$date_y]['totalRechargeAmount']) : 0;
            $platform_data_record[$date_y]['totalExchangeAmount'] = !empty($platform_data_record[$date_y]['totalExchangeAmount']) ? $this->formatMoneyFromMongo($platform_data_record[$date_y]['totalExchangeAmount']) : 0;
            $platform_data_record[$date_y]['totalAllBetScore'] = !empty($platform_data_record[$date_y]['totalAllBetScore']) ? $this->formatMoneyFromMongo($platform_data_record[$date_y]['totalAllBetScore']) : 0;
            $platform_data_record[$date_y]['totalValidBetScore'] = !empty($platform_data_record[$date_y]['totalValidBetScore']) ? $this->formatMoneyFromMongo($platform_data_record[$date_y]['totalValidBetScore']) : 0;
            $platform_data_record[$date_y]['totalRevenue'] = !empty($platform_data_record[$date_y]['totalRevenue']) ? $this->formatMoneyFromMongo($platform_data_record[$date_y]['totalRevenue']) : 0;
            $platform_data_record[$date_y]['totalWinScore'] = !empty($platform_data_record[$date_y]['totalWinScore']) ? $this->formatMoneyFromMongo($platform_data_record[$date_y]['totalWinScore']) : 0;
            $platform_data_record[$date_y]['totalScore'] = !empty($platform_data_record[$date_y]['totalScore']) ? $this->formatMoneyFromMongo($platform_data_record[$date_y]['totalScore']) : 0;
            $platform_data_record[$date_y]['totalBankScore'] = !empty($platform_data_record[$date_y]['totalBankScore']) ? $this->formatMoneyFromMongo($platform_data_record[$date_y]['totalBankScore']) : 0;
            $platform_data_record[$date_y]['totalPromoterScore'] = !empty($platform_data_record[$date_y]['totalPromoterScore']) ? $this->formatMoneyFromMongo($platform_data_record[$date_y]['totalPromoterScore']) : 0;
            $platform_data_record[$date_y]['totalPromoterExchange'] = !empty($platform_data_record[$date_y]['totalPromoterExchange']) ? $this->formatMoneyFromMongo($platform_data_record[$date_y]['totalPromoterExchange']) : 0;
            $platform_data_record[$date_y]['totalPromoterCount'] = !empty($platform_data_record[$date_y]['totalPromoterCount']) ? $platform_data_record[$date_y]['totalPromoterCount'] : 0;


            //总盈利 = 会员总充值-会员总兑换-会员总金币-会员保险箱金币-代理可提取金币
            $total_profit = $platform_data_record[$date_y]['totalRechargeAmount'] - $platform_data_record[$date_y]['totalExchangeAmount'] - $platform_data_record[$date_y]['totalScore'] - $platform_data_record[$date_y]['totalBankScore'] - $platform_data_record[$date_y]['totalPromoterScore'];

            $total_profit = $this->formatMoneyFromMongoNo($total_profit);

            //会员总余额 = 会员总金币+会员总保险箱金币
            $player_total_amount = $platform_data_record[$date_y]['totalScore'] + $platform_data_record[$date_y]['totalBankScore'];
            $player_total_amount = $this->formatMoneyFromMongoNo($player_total_amount);
            //会员代理总余额 = 会员总金币+会员总保险箱金币 +代理可提取总金额
            $pl_ag_total_amount = $platform_data_record[$date_y]['totalScore'] + $platform_data_record[$date_y]['totalBankScore'] + $platform_data_record[$date_y]['totalPromoterScore'];
            $pl_ag_total_amount = $this->formatMoneyFromMongoNo($pl_ag_total_amount);

            $data = [
                [
                    "name1" => "会员总充值金额", "vals1" => $platform_data_record[$date_y]['totalRechargeAmount'],
                    "name2" => "会员总充值次数", "vals2" => $platform_data_record[$date_y]['totalRechargeTimes'] ?? 0,
                ],
                [
                    "name1" => "会员总提现金额", "vals1" => $platform_data_record[$date_y]['totalExchangeAmount'],
                    "name2" => "会员总提现次数", "vals2" => $platform_data_record[$date_y]['totalExchangeTimes'] ?? 0,
                ],
                [
                    "name1" => "会员总下注金额", "vals1" => $platform_data_record[$date_y]['totalAllBetScore'],
                    "name2" => "会员总有效下注金额", "vals2" => $platform_data_record[$date_y]['totalValidBetScore'],
                ],
                [
                    "name1" => "会员总税收", "vals1" => $platform_data_record[$date_y]['totalRevenue'],
                    "name2" => "会员总输赢", "vals2" => $platform_data_record[$date_y]['totalWinScore'],
                ],
                [
                    "name1" => "会员总金币", "vals1" => $platform_data_record[$date_y]['totalScore'],
                    "name2" => "会员总保险箱金币", "vals2" => $platform_data_record[$date_y]['totalBankScore'],
                ],
                [
                    "name1" => "代理可提取总金币", "vals1" => $platform_data_record[$date_y]['totalPromoterScore'],
                    "name2" => "代理已提取总金币", "vals2" => $platform_data_record[$date_y]['totalPromoterExchange'],
                ],
                [
                    "name1" => "平台总盈利", "vals1" => $total_profit,
                    "name2" => "代理总数量", "vals2" => $platform_data_record[$date_y]['totalPromoterCount'],
                ],
                [
                    "name1" => "会员总余额", "vals1" => $player_total_amount,
                    "name2" => "会员代理总余额", "vals2" => $pl_ag_total_amount,
                ]
            ];
            return json(['code' => 0, 'msg' => 'ok', 'count' => 0,'data' => $data]);
        }
    }

    public function platOverView2(Request $request)
    {
        if ($request->isAjax()) {
            $type = (int)$request->get('type', 1);
            $date_t = date("Y-m-d");
            $date_y = date("Y-m-d", strtotime('-1 day'));
            //平台昨日盈利(会员总充值-会员总兑换-会员总金币-会员保险箱金币-代理可提取金币)
            $platform_data_record_t = $this->get_yes_data($date_t);
            $platform_data_record_y = $this->get_yes_data($date_y);

            $platformClubRecordTo = $this->getDataRecord($date_t);
            $platformClubRecordYe = $this->getDataRecord($date_y);

            if($type == 1 || $type == 2){
                $promoter_today_club = $this->getPromoterDailyClub($date_t,$date_t);
                $promoter_yes_club = $this->getPromoterDailyClub($date_y,$date_y);

                $promoter_today_count = $this->get_promoter_daily2($date_t,$date_t);
                $promoter_yes_count = $this->get_promoter_daily2($date_y,$date_y);
                //$agency_commission_arr_t = $this->get_promoter_commission($date_t,$date_t);
                //$agency_commission_arr_y = $this->get_promoter_commission($date_y,$date_y);
                $platform_data_record_t[$date_t]['totalScore'] = !empty($platform_data_record_t[$date_t]['totalScore']) ? $this->formatMoneyFromMongo($platform_data_record_t[$date_t]['totalScore']) : 0;
                $platform_data_record_t[$date_t]['totalBankScore'] = !empty($platform_data_record_t[$date_t]['totalBankScore']) ? $this->formatMoneyFromMongo($platform_data_record_t[$date_t]['totalBankScore']) : 0;
                $platform_data_record_t[$date_t]['totalPromoterScore'] = !empty($platform_data_record_t[$date_t]['totalPromoterScore']) ? $this->formatMoneyFromMongo($platform_data_record_t[$date_t]['totalPromoterScore']) : 0;
                $platform_data_record_t[$date_t]['todayPromoterScore'] = !empty($platform_data_record_t[$date_t]['todayPromoterScore']) ? $this->formatMoneyFromMongo($platform_data_record_t[$date_t]['todayPromoterScore']) : 0;
                $platform_data_record_t[$date_t]['totalRoomCard'] = !empty($platform_data_record_t[$date_t]['totalRoomCard']) ? $this->formatMoneyFromMongo($platform_data_record_t[$date_t]['totalRoomCard']) : 0;
                $platform_data_record_t[$date_t]['totalRewardScore'] = !empty($platform_data_record_t[$date_t]['totalRewardScore']) ? $this->formatMoneyFromMongo($platform_data_record_t[$date_t]['totalRewardScore']) : 0;
                $platform_data_record_t[$date_t]['todayRewardScore'] = !empty($platform_data_record_t[$date_t]['todayRewardScore']) ? $this->formatMoneyFromMongo($platform_data_record_t[$date_t]['todayRewardScore']) : 0;
                $platform_data_record_t[$date_t]['totalClubPromoterScore'] = !empty($platform_data_record_t[$date_t]['totalClubPromoterScore']) ? $this->formatMoneyFromMongo($platform_data_record_t[$date_t]['totalClubPromoterScore']) : 0;
                $platform_data_record_t[$date_t]['todayClubPromoterScore'] = !empty($platform_data_record_t[$date_t]['todayClubPromoterScore']) ? $this->formatMoneyFromMongo($platform_data_record_t[$date_t]['todayClubPromoterScore']) : 0;
                $platform_data_record_t[$date_t]['totalClubRewardScore'] = !empty($platform_data_record_t[$date_t]['totalClubRewardScore']) ? $this->formatMoneyFromMongo($platform_data_record_t[$date_t]['totalClubRewardScore']) : 0;
                $platform_data_record_t[$date_t]['todayClubRewardScore'] = !empty($platform_data_record_t[$date_t]['todayClubRewardScore']) ? $this->formatMoneyFromMongo($platform_data_record_t[$date_t]['todayClubRewardScore']) : 0;
                $platform_data_record_t[$date_t]['todayAllBetScore'] = !empty($platform_data_record_t[$date_t]['todayAllBetScore']) ? $this->formatMoneyFromMongo($platform_data_record_t[$date_t]['todayAllBetScore']) : 0;
                $platform_data_record_t[$date_t]['totalAllBetScore'] = !empty($platform_data_record_t[$date_t]['totalAllBetScore']) ? $this->formatMoneyFromMongo($platform_data_record_t[$date_t]['totalAllBetScore']) : 0;
                $platform_data_record_t[$date_t]['todayValidBetScore'] = !empty($platform_data_record_t[$date_t]['todayValidBetScore']) ? $this->formatMoneyFromMongo($platform_data_record_t[$date_t]['todayValidBetScore']) : 0;
                $platform_data_record_t[$date_t]['totalValidBetScore'] = !empty($platform_data_record_t[$date_t]['totalValidBetScore']) ? $this->formatMoneyFromMongo($platform_data_record_t[$date_t]['totalValidBetScore']) : 0;

                $platform_data_record_t[$date_t]['totalBindPeople'] = isset($platform_data_record_t[$date_t]['totalBindPeople']) ? $platform_data_record_t[$date_t]['totalBindPeople'] : 0;
                $platform_data_record_t[$date_t]['totalBetPeople'] = isset($platform_data_record_t[$date_t]['totalBetPeople']) ? $platform_data_record_t[$date_t]['totalBetPeople']: 0;
                
                $platform_data_record_y[$date_y]['totalScore'] = !empty($platform_data_record_y[$date_y]['totalScore']) ? $this->formatMoneyFromMongo($platform_data_record_y[$date_y]['totalScore']) : 0;
                $platform_data_record_y[$date_y]['totalBankScore'] = !empty($platform_data_record_y[$date_y]['totalBankScore']) ? $this->formatMoneyFromMongo($platform_data_record_y[$date_y]['totalBankScore']) : 0;
                $platform_data_record_y[$date_y]['totalPromoterScore'] = !empty($platform_data_record_y[$date_y]['totalPromoterScore']) ? $this->formatMoneyFromMongo($platform_data_record_y[$date_y]['totalPromoterScore']) : 0;
                $platform_data_record_y[$date_y]['todayPromoterScore'] = !empty($platform_data_record_y[$date_y]['todayPromoterScore']) ? $this->formatMoneyFromMongo($platform_data_record_y[$date_y]['todayPromoterScore']) : 0;
                $platform_data_record_y[$date_y]['totalRoomCard'] = !empty($platform_data_record_y[$date_y]['totalRoomCard']) ? $this->formatMoneyFromMongo($platform_data_record_y[$date_y]['totalRoomCard']) : 0;
                $platform_data_record_y[$date_y]['totalRewardScore'] = !empty($platform_data_record_y[$date_y]['totalRewardScore']) ? $this->formatMoneyFromMongo($platform_data_record_y[$date_y]['totalRewardScore']) : 0;
                $platform_data_record_y[$date_y]['todayRewardScore'] = !empty($platform_data_record_y[$date_y]['todayRewardScore']) ? $this->formatMoneyFromMongo($platform_data_record_y[$date_y]['todayRewardScore']) : 0;
                $platform_data_record_y[$date_y]['totalClubPromoterScore'] = !empty($platform_data_record_y[$date_y]['totalClubPromoterScore']) ? $this->formatMoneyFromMongo($platform_data_record_y[$date_y]['totalClubPromoterScore']) : 0;
                $platform_data_record_y[$date_y]['todayClubPromoterScore'] = !empty($platform_data_record_y[$date_y]['todayClubPromoterScore']) ? $this->formatMoneyFromMongo($platform_data_record_y[$date_y]['todayClubPromoterScore']) : 0;
                $platform_data_record_y[$date_y]['totalClubRewardScore'] = !empty($platform_data_record_y[$date_y]['totalClubRewardScore']) ? $this->formatMoneyFromMongo($platform_data_record_y[$date_y]['totalClubRewardScore']) : 0;
                $platform_data_record_y[$date_y]['todayClubRewardScore'] = !empty($platform_data_record_y[$date_y]['todayClubRewardScore']) ? $this->formatMoneyFromMongo($platform_data_record_y[$date_y]['todayClubRewardScore']) : 0;
                $platform_data_record_y[$date_y]['todayAllBetScore'] = !empty($platform_data_record_y[$date_y]['todayAllBetScore']) ? $this->formatMoneyFromMongo($platform_data_record_y[$date_y]['todayAllBetScore']) : 0;
                $platform_data_record_y[$date_y]['totalAllBetScore'] = !empty($platform_data_record_y[$date_y]['totalAllBetScore']) ? $this->formatMoneyFromMongo($platform_data_record_y[$date_y]['totalAllBetScore']) : 0;
                $platform_data_record_y[$date_y]['todayValidBetScore'] = !empty($platform_data_record_y[$date_y]['todayValidBetScore']) ? $this->formatMoneyFromMongo($platform_data_record_y[$date_y]['todayValidBetScore']) : 0;
                $platform_data_record_y[$date_y]['totalValidBetScore'] = !empty($platform_data_record_y[$date_y]['totalValidBetScore']) ? $this->formatMoneyFromMongo($platform_data_record_y[$date_y]['totalValidBetScore']) : 0;
                $platform_data_record_y[$date_y]['totalBindPeople'] = isset($platform_data_record_y[$date_y]['totalBindPeople']) ? $platform_data_record_y[$date_y]['totalBindPeople']: 0;
                $platform_data_record_y[$date_y]['totalBetPeople'] = isset($platform_data_record_y[$date_y]['totalBetPeople']) ? $platform_data_record_y[$date_y]['totalBetPeople'] : 0;

                //平台总余额 = 会员总金币+会员总保险箱金币+代理可提取总金额+俱乐部代理可提取余额
                $proCanWithdrawCoinTo = $this->formatMoneyFromMongoNo($promoter_today_count[$date_t]['totalTeamProfit_total'] - $promoter_today_count[$date_t]['totalTeamTransferToScoreAmount_total']);
                $proCanWithdrawCoinYe = $this->formatMoneyFromMongoNo($promoter_yes_count[$date_y]['totalTeamProfit_total'] - $promoter_yes_count[$date_y]['totalTeamTransferToScoreAmount_total']);


                $proCanWithdrawClubTo = $this->formatMoneyFromMongoNo($this->formatMoneyFromMongoNo($promoter_today_club[$date_t]['totalMyProfit_total'] + $promoter_today_club[$date_t]['totalTeamProfit_total']) - $promoter_today_club[$date_t]['totalTeamTransferToScoreAmount_total']);
                $proCanWithdrawClubYe = $this->formatMoneyFromMongoNo($this->formatMoneyFromMongoNo($promoter_yes_club[$date_y]['totalMyProfit_total'] + $promoter_yes_club[$date_y]['totalTeamProfit_total']) - $promoter_yes_club[$date_y]['totalTeamTransferToScoreAmount_total']);


                $player_total_amount_t = $platform_data_record_t[$date_t]['totalScore'] + $platform_data_record_t[$date_t]['totalBankScore'] + $proCanWithdrawCoinTo + $proCanWithdrawClubTo;
                $player_total_amount_t = $this->formatMoneyFromMongoNo($player_total_amount_t);
                $player_total_amount_y = $platform_data_record_y[$date_y]['totalScore'] + $platform_data_record_y[$date_y]['totalBankScore'] + $proCanWithdrawCoinYe + $proCanWithdrawClubYe;
                $player_total_amount_y = $this->formatMoneyFromMongoNo($player_total_amount_y);
                //金币场官方盈亏
                $officialWinLoseCoinTo =  $this->formatMoneyFromMongoNo($promoter_today_count[$date_t]['teamRevenue_total'] + $promoter_today_count[$date_t]['teamGameWinScore_total'] - $promoter_today_count[$date_t]['teamProfit_total'] - $platform_data_record_t[$date_t]['todayRewardScore']);
                $officialWinLoseCoinYe =  $this->formatMoneyFromMongoNo($promoter_yes_count[$date_y]['teamRevenue_total'] + $promoter_yes_count[$date_y]['teamGameWinScore_total'] - $promoter_yes_count[$date_y]['teamProfit_total'] - $platform_data_record_y[$date_y]['todayRewardScore']);
                $officialWinLoseCoinToGd =  $this->formatMoneyFromMongoNo($promoter_today_count[$date_t]['totalTeamRevenue_total'] + $promoter_today_count[$date_t]['totalTeamGameWinScore_total'] - $promoter_today_count[$date_t]['totalTeamProfit_total'] - $platform_data_record_t[$date_t]['totalRewardScore']);
                $officialWinLoseCoinYeGd =  $this->formatMoneyFromMongoNo($promoter_yes_count[$date_y]['totalTeamRevenue_total'] + $promoter_yes_count[$date_y]['totalTeamGameWinScore_total'] - $promoter_yes_count[$date_y]['totalTeamProfit_total'] - $platform_data_record_y[$date_y]['totalRewardScore']);
                //俱乐部官方盈亏
                $officialWinLoseClubTo = $platformClubRecordTo[$date_t]['platformProfit'] - $platformClubRecordTo[$date_t]['rewardScore'];
                $officialWinLoseClubYe = $platformClubRecordYe[$date_y]['platformProfit'] - $platformClubRecordYe[$date_y]['rewardScore'];

                $officialWinLoseClubToGd = $platformClubRecordTo[$date_t]['totalPlatformProfit'] - $platformClubRecordTo[$date_t]['totalRewardScore'];
                $officialWinLoseClubYeGd = $platformClubRecordYe[$date_y]['totalPlatformProfit'] - $platformClubRecordYe[$date_y]['totalRewardScore'];
                //汇总官方盈亏
                $TotalofficialWinLoseTo = $this->formatMoneyFromMongoNo($officialWinLoseCoinTo + $officialWinLoseClubTo);
                $TotalofficialWinLoseYe = $this->formatMoneyFromMongoNo($officialWinLoseCoinYe + $officialWinLoseClubYe);
                $TotalofficialWinLoseToGd = $this->formatMoneyFromMongoNo($officialWinLoseCoinToGd + $officialWinLoseClubToGd);
                $TotalofficialWinLoseYeGd = $this->formatMoneyFromMongoNo($officialWinLoseCoinYeGd + $officialWinLoseClubYeGd);
                //金币场税收纯利润


                $totalTaxProCoinTo = $this->formatMoneyFromMongoNo($promoter_today_count[$date_t]['teamRevenue_total'] - $promoter_today_count[$date_t]['teamProfit_total']);
                $totalTaxProCoinYe = $this->formatMoneyFromMongoNo($promoter_yes_count[$date_y]['teamRevenue_total'] - $promoter_yes_count[$date_y]['teamProfit_total']);

                $grandTotalTaxProCoinTo = $this->formatMoneyFromMongoNo($promoter_today_count[$date_t]['totalTeamRevenue_total'] - $promoter_today_count[$date_t]['totalTeamProfit_total']);
                $grandTotalTaxProCoinYe = $this->formatMoneyFromMongoNo($promoter_yes_count[$date_y]['totalTeamRevenue_total'] - $promoter_yes_count[$date_y]['totalTeamProfit_total']);
                //俱乐部税收纯利润
                $totalTaxProClubTo = $this->formatMoneyFromMongoNo($platformClubRecordTo[$date_t]['revenue'] - $this->formatMoneyFromMongoNo($promoter_today_club[$date_t]['myProfit_total'] + $promoter_today_club[$date_t]['teamProfit_total']));
                $totalTaxProClubYe = $this->formatMoneyFromMongoNo($platformClubRecordYe[$date_y]['revenue'] - $this->formatMoneyFromMongoNo($promoter_yes_club[$date_y]['myProfit_total'] + $promoter_yes_club[$date_y]['teamProfit_total']));
                $grandTotalTaxProClubTo = $this->formatMoneyFromMongoNo($platformClubRecordTo[$date_t]['totalRevenue'] - $this->formatMoneyFromMongoNo($promoter_today_club[$date_t]['totalMyProfit_total'] + $promoter_today_club[$date_t]['totalTeamProfit_total']));
                $grandTotalTaxProClubYe = $this->formatMoneyFromMongoNo($platformClubRecordYe[$date_y]['totalRevenue'] - $this->formatMoneyFromMongoNo($promoter_yes_club[$date_y]['totalMyProfit_total'] + $promoter_yes_club[$date_y]['totalTeamProfit_total']));
                //汇总税收纯利润
                $sumTaxProTo = $this->formatMoneyFromMongoNo($totalTaxProCoinTo + $totalTaxProClubTo);
                $sumTaxProYe = $this->formatMoneyFromMongoNo($totalTaxProCoinYe + $totalTaxProClubYe);
                $sumGrandTaxProTo = $this->formatMoneyFromMongoNo($grandTotalTaxProCoinTo + $grandTotalTaxProClubTo);
                $sumGrandTaxProYe = $this->formatMoneyFromMongoNo($grandTotalTaxProCoinYe + $grandTotalTaxProClubYe);

            }else{
                $promoter_today_count = $this->getPromoterDailyClub($date_t,$date_t);

                $promoter_yes_count = $this->getPromoterDailyClub($date_y,$date_y);

                $platform_data_record_t[$date_t]['todayClubRewardScore'] = $this->formatMoneyFromMongo($platform_data_record_t[$date_t]['todayClubRewardScore']??0);
                $platform_data_record_y[$date_y]['todayClubRewardScore'] = $this->formatMoneyFromMongo($platform_data_record_y[$date_y]['todayClubRewardScore']??0);

                $platform_data_record_t[$date_t]['totalClubRewardScore'] = $this->formatMoneyFromMongo($platform_data_record_t[$date_t]['totalClubRewardScore']??0);
                $platform_data_record_y[$date_y]['totalClubRewardScore'] = $this->formatMoneyFromMongo($platform_data_record_y[$date_y]['totalClubRewardScore']??0);

                $platform_data_record_t[$date_t]['totalClubPromoterScore'] = $this->formatMoneyFromMongo($platform_data_record_t[$date_t]['totalClubPromoterScore']??0);
                $platform_data_record_y[$date_y]['totalClubPromoterScore'] = $this->formatMoneyFromMongo($platform_data_record_y[$date_y]['totalClubPromoterScore']??0);

                $platform_data_record_t[$date_t]['todayClubPromoterScore'] = $this->formatMoneyFromMongo($platform_data_record_t[$date_t]['todayClubPromoterScore']??0);
                $platform_data_record_y[$date_y]['todayClubPromoterScore'] = $this->formatMoneyFromMongo($platform_data_record_y[$date_y]['todayClubPromoterScore']??0);
            }



            if($type == 1){
                $data = [
                    [
                        "name" => "注册会员人数",
                        "today" => $promoter_today_count[$date_t]['teamRegPeople_total'],
                        "yesterday" => $promoter_yes_count[$date_y]['teamRegPeople_total'],
                        "todayGrand" => $promoter_today_count[$date_t]['totalTeamRegPeople_total'],
                        "yesterdayGrand" => $promoter_yes_count[$date_y]['totalTeamRegPeople_total'],
                    ],
                    [
                        "name" => "注绑会员人数",
                        "today" => $promoter_today_count[$date_t]['teamRegBindPeople_total'],
                        "yesterday" => $promoter_yes_count[$date_y]['teamRegBindPeople_total'],
                        "todayGrand" => $platform_data_record_t[$date_t]['totalBindPeople'],
                        "yesterdayGrand" => $platform_data_record_y[$date_y]['totalBindPeople'],
                    ],
                    [
                        "name" => "有效会员人数",
                        "today" => $promoter_today_count[$date_t]['teamRegValidNewBetPeople_total'],
                        "yesterday" => $promoter_yes_count[$date_y]['teamRegValidNewBetPeople_total'],
                        "todayGrand" => $platform_data_record_t[$date_t]['totalBetPeople'],
                        "yesterdayGrand" => $platform_data_record_y[$date_y]['totalBetPeople'],
                    ],
                    [
                        "name" => "会员充值",
                        "today" => !empty($platform_data_record_t[$date_t]['todayRechargeAmount']) ? $this->formatMoneyFromMongo($platform_data_record_t[$date_t]['todayRechargeAmount']) : 0,
                        "yesterday" => !empty($platform_data_record_y[$date_y]['todayRechargeAmount']) ? $this->formatMoneyFromMongo($platform_data_record_y[$date_y]['todayRechargeAmount']) :0,
                        "todayGrand" => !empty($platform_data_record_t[$date_t]['totalRechargeAmount']) ? $this->formatMoneyFromMongo($platform_data_record_t[$date_t]['totalRechargeAmount']) :0,
                        "yesterdayGrand" => !empty($platform_data_record_y[$date_y]['totalRechargeAmount']) ? $this->formatMoneyFromMongo($platform_data_record_y[$date_y]['totalRechargeAmount']) :0,
                    ],
                    [
                        "name" => "会员提现",
                        "today" => !empty($platform_data_record_t[$date_t]['todayExchangeAmount']) ? $this->formatMoneyFromMongo($platform_data_record_t[$date_t]['todayExchangeAmount']):0,
                        "yesterday" => !empty($platform_data_record_y[$date_y]['todayExchangeAmount']) ? $this->formatMoneyFromMongo($platform_data_record_y[$date_y]['todayExchangeAmount']):0,
                        "todayGrand" => !empty($platform_data_record_t[$date_t]['totalExchangeAmount']) ? $this->formatMoneyFromMongo($platform_data_record_t[$date_t]['totalExchangeAmount']):0,
                        "yesterdayGrand" => !empty($platform_data_record_y[$date_y]['totalExchangeAmount']) ? $this->formatMoneyFromMongo($platform_data_record_y[$date_y]['totalExchangeAmount']):0,
                    ],
                    [
                        "name" => "会员总充值次数",
                        "today" => $platform_data_record_t[$date_t]['todayRechargeTimes'] ?? 0,
                        "yesterday" => $platform_data_record_y[$date_y]['todayRechargeTimes'] ?? 0,
                        "todayGrand" => $platform_data_record_t[$date_t]['totalRechargeTimes'] ?? 0,
                        "yesterdayGrand" => $platform_data_record_y[$date_y]['totalRechargeTimes'] ?? 0,
                    ],
                    [
                        "name" => "会员总提现次数",
                        "today" => $platform_data_record_t[$date_t]['todayExchangeTimes'] ?? 0,
                        "yesterday" => $platform_data_record_y[$date_y]['todayExchangeTimes'] ?? 0,
                        "todayGrand" => $platform_data_record_t[$date_t]['totalExchangeTimes'] ?? 0,
                        "yesterdayGrand" => $platform_data_record_y[$date_y]['totalExchangeTimes'] ?? 0,
                    ],
                    [
                        "name" => "官方游戏输赢",
                        "today" => $this->formatMoneyFromMongoNo($promoter_today_count[$date_t]['teamGameWinScore_total'] + $platformClubRecordTo[$date_t]['platformWinScore']),
                        "yesterday" => $this->formatMoneyFromMongoNo($promoter_yes_count[$date_y]['teamGameWinScore_total'] + $platformClubRecordYe[$date_y]['platformWinScore']),
                        "todayGrand" => $this->formatMoneyFromMongoNo($promoter_today_count[$date_t]['totalTeamGameWinScore_total'] + $platformClubRecordTo[$date_t]['totalPlatformWinScore']),
                        "yesterdayGrand" => $this->formatMoneyFromMongoNo($promoter_yes_count[$date_y]['totalTeamGameWinScore_total'] + $platformClubRecordYe[$date_y]['totalPlatformWinScore']),
                    ],
                    [
                        "name" => "用户下注流水",
                        "today" => $this->formatMoneyFromMongoNo($platform_data_record_t[$date_t]['todayAllBetScore'] + $platformClubRecordTo[$date_t]['allBet']),
                        "yesterday" => $this->formatMoneyFromMongoNo($platform_data_record_y[$date_y]['todayAllBetScore'] + $platformClubRecordYe[$date_y]['allBet']),
                        "todayGrand" => $this->formatMoneyFromMongoNo($platform_data_record_t[$date_t]['totalAllBetScore'] + $platformClubRecordTo[$date_t]['totalAllBet']),
                        "yesterdayGrand" => $this->formatMoneyFromMongoNo($platform_data_record_y[$date_y]['totalAllBetScore'] + $platformClubRecordYe[$date_y]['totalAllBet']),
                    ],
                    [
                        "name" => "用户有效下注流水",
                        "today" => $this->formatMoneyFromMongoNo($platform_data_record_t[$date_t]['todayValidBetScore'] + $platformClubRecordTo[$date_t]['validBet']),
                        "yesterday" => $this->formatMoneyFromMongoNo($platform_data_record_y[$date_y]['todayValidBetScore'] + $platformClubRecordYe[$date_y]['validBet']),
                        "todayGrand" => $this->formatMoneyFromMongoNo($platform_data_record_t[$date_t]['totalValidBetScore'] + $platformClubRecordTo[$date_t]['totalValidBet']),
                        "yesterdayGrand" => $this->formatMoneyFromMongoNo($platform_data_record_y[$date_y]['totalValidBetScore'] + $platformClubRecordYe[$date_y]['totalValidBet']),
                    ],
                    [
                        "name" => "代理提成",
                        //"today" => $this->formatMoneyFromMongoNo($promoter_today_count[$date_t]['teamProfit_total'] + $this->formatMoneyFromMongoNo($promoter_today_club[$date_t]['myProfit_total'] + $promoter_today_club[$date_t]['teamProfit_total'])),
                        //"yesterday" => $this->formatMoneyFromMongoNo($promoter_yes_count[$date_y]['teamProfit_total'] + $this->formatMoneyFromMongoNo($promoter_yes_club[$date_y]['myProfit_total'] + $promoter_yes_club[$date_y]['teamProfit_total'])),
                        //"todayGrand" => $this->formatMoneyFromMongoNo($promoter_today_count[$date_t]['totalTeamProfit_total'] + $this->formatMoneyFromMongoNo($promoter_today_club[$date_t]['totalMyProfit_total'] + $promoter_today_club[$date_t]['totalTeamProfit_total'])),
                        //"yesterdayGrand" => $this->formatMoneyFromMongoNo($promoter_yes_count[$date_y]['totalTeamProfit_total'] + $this->formatMoneyFromMongoNo($promoter_yes_club[$date_y]['totalMyProfit_total'] + $promoter_yes_club[$date_y]['totalTeamProfit_total'])),
                        "today" => $this->formatMoneyFromMongoNo($platform_data_record_t[$date_t]['todayPromoterScore'] + $this->formatMoneyFromMongoNo($promoter_today_club[$date_t]['myProfit_total'] + $promoter_today_club[$date_t]['teamProfit_total'])),
                        "yesterday" => $this->formatMoneyFromMongoNo($platform_data_record_y[$date_y]['todayPromoterScore'] + $this->formatMoneyFromMongoNo($promoter_yes_club[$date_y]['myProfit_total'] + $promoter_yes_club[$date_y]['teamProfit_total'])),
                        "todayGrand" => $this->formatMoneyFromMongoNo($platform_data_record_t[$date_t]['totalPromoterScore'] + $this->formatMoneyFromMongoNo($promoter_today_club[$date_t]['totalMyProfit_total'] + $promoter_today_club[$date_t]['totalTeamProfit_total'])),
                        "yesterdayGrand" => $this->formatMoneyFromMongoNo($platform_data_record_y[$date_y]['totalPromoterScore'] + $this->formatMoneyFromMongoNo($promoter_yes_club[$date_y]['totalMyProfit_total'] + $promoter_yes_club[$date_y]['totalTeamProfit_total'])),
                    ],
                    [
                        "name" => "系统税收",
                        "today" => $this->formatMoneyFromMongoNo($promoter_today_count[$date_t]['teamRevenue_total'] + $platformClubRecordTo[$date_t]['revenue']),
                        "yesterday" => $this->formatMoneyFromMongoNo($promoter_yes_count[$date_y]['teamRevenue_total'] + $platformClubRecordYe[$date_y]['revenue']),
                        "todayGrand" => $this->formatMoneyFromMongoNo($promoter_today_count[$date_t]['totalTeamRevenue_total'] + $platformClubRecordTo[$date_t]['totalRevenue']),
                        "yesterdayGrand" => $this->formatMoneyFromMongoNo($promoter_yes_count[$date_y]['totalTeamRevenue_total'] + $platformClubRecordYe[$date_y]['totalRevenue']),
                    ],
                    [
                        "name" => "税收纯利润",
                        "today" => $sumTaxProTo,
                        "yesterday" => $sumTaxProYe,
                        "todayGrand" => $sumGrandTaxProTo,
                        "yesterdayGrand" => $sumGrandTaxProYe,
                    ],
                    [
                        "name" => "官方盈亏",
                        "today" => $TotalofficialWinLoseTo,
                        "yesterday" => $TotalofficialWinLoseYe,
                        "todayGrand" => $TotalofficialWinLoseToGd,
                        "yesterdayGrand" => $TotalofficialWinLoseYeGd,
                    ],

                    [
                        "name" => "奖励金额",
                        "today" => $this->formatMoneyFromMongoNo($platform_data_record_t[$date_t]['todayRewardScore'] + $platformClubRecordTo[$date_t]['rewardScore']),
                        "yesterday" => $this->formatMoneyFromMongoNo($platform_data_record_y[$date_y]['todayRewardScore'] + $platformClubRecordYe[$date_y]['rewardScore']),
                        "todayGrand" => $this->formatMoneyFromMongoNo($platform_data_record_t[$date_t]['totalRewardScore'] + $platformClubRecordTo[$date_t]['totalRewardScore']),
                        "yesterdayGrand" => $this->formatMoneyFromMongoNo($platform_data_record_y[$date_y]['totalRewardScore'] + $platformClubRecordYe[$date_y]['totalRewardScore']),
                    ],
                    [
                        "name" => "房卡余额",
                        "today" => '/',
                        "yesterday" => '/',
                        "todayGrand" => $platform_data_record_t[$date_t]['totalRoomCard'],
                        "yesterdayGrand" => $platform_data_record_y[$date_y]['totalRoomCard'],
                    ],
                    [
                        "name" => "金币余额",
                        "today" => '/',
                        "yesterday" => '/',
                        "todayGrand" => $platform_data_record_t[$date_t]['totalScore'],
                        "yesterdayGrand" => $platform_data_record_y[$date_y]['totalScore'],

                    ],
                    [
                        "name" => "保险箱余额",
                        "today" => '/',
                        "yesterday" => '/',
                        "todayGrand" => $platform_data_record_t[$date_t]['totalBankScore'],
                        "yesterdayGrand" => $platform_data_record_y[$date_y]['totalBankScore'],
                    ],
                    [
                        "name" => "平台余额",
                        "today" => '/',
                        "yesterday" => '/',
                        "todayGrand" => $player_total_amount_t,
                        "yesterdayGrand" => $player_total_amount_y,
                    ]
                ];
            }else if($type == 2){
                $data = [
                    [
                        "name" => "官方游戏输赢",
                        "today" => $promoter_today_count[$date_t]['teamGameWinScore_total'],
                        "yesterday" => $promoter_yes_count[$date_y]['teamGameWinScore_total'],
                        "todayGrand" => $promoter_today_count[$date_t]['totalTeamGameWinScore_total'],
                        "yesterdayGrand" => $promoter_yes_count[$date_y]['totalTeamGameWinScore_total'],
                    ],
                    [
                        "name" => "用户下注流水",
                        "today" => $platform_data_record_t[$date_t]['todayAllBetScore'],
                        "yesterday" => $platform_data_record_y[$date_y]['todayAllBetScore'],
                        "todayGrand" => $platform_data_record_t[$date_t]['totalAllBetScore'],
                        "yesterdayGrand" => $platform_data_record_y[$date_y]['totalAllBetScore'],
                    ],
                    [
                        "name" => "用户有效下注流水",
                        "today" => $platform_data_record_t[$date_t]['todayValidBetScore'],
                        "yesterday" => $platform_data_record_y[$date_y]['todayValidBetScore'],
                        "todayGrand" => $platform_data_record_t[$date_t]['totalValidBetScore'],
                        "yesterdayGrand" => $platform_data_record_y[$date_y]['totalValidBetScore'],
                    ],

                    [
                        "name" => "代理提成",
                        //"today" => $promoter_today_count[$date_t]['teamProfit_total'],
                        "today" => $platform_data_record_t[$date_t]['todayPromoterScore'],
                        //"yesterday" => $promoter_yes_count[$date_y]['teamProfit_total'],
                        "yesterday" => $platform_data_record_y[$date_y]['todayPromoterScore'],
                        //"todayGrand" => $promoter_today_count[$date_t]['totalTeamProfit_total'],
                        "todayGrand" => $platform_data_record_t[$date_t]['totalPromoterScore'],
                        //"yesterdayGrand" => $promoter_yes_count[$date_y]['totalTeamProfit_total'],
                        "yesterdayGrand" => $platform_data_record_y[$date_y]['totalPromoterScore'],
                    ],
                    [
                        "name" => "代理已提取金额",
                        "today" => $promoter_today_count[$date_t]['teamTransferToScoreAmount_total'],
                        "yesterday" => $promoter_yes_count[$date_y]['teamTransferToScoreAmount_total'],
                        "todayGrand" => $promoter_today_count[$date_t]['totalTeamTransferToScoreAmount_total'],
                        "yesterdayGrand" => $promoter_yes_count[$date_y]['totalTeamTransferToScoreAmount_total'],
                    ],
                    [
                        "name" => "代理可提取金额",
                        "today" => '/',
                        "yesterday" => '/',
                        //"todayGrand" => $this->formatMoneyFromMongoNo($promoter_today_count[$date_t]['totalTeamProfit_total'] - $promoter_today_count[$date_t]['totalTeamTransferToScoreAmount_total']),
                        //"yesterdayGrand" => $this->formatMoneyFromMongoNo($promoter_yes_count[$date_y]['totalTeamProfit_total'] - $promoter_yes_count[$date_y]['totalTeamTransferToScoreAmount_total']),
                        "todayGrand" => $this->formatMoneyFromMongo($platform_data_record_t[$date_t]['currentPromoterScore']??0),
                        "yesterdayGrand" => '/', //$platform_data_record_t[$date_y]['totalPromoterScore'],

                    ],
                    [
                        "name" => "系统税收",
                        "today" => $promoter_today_count[$date_t]['teamRevenue_total'],
                        "yesterday" => $promoter_yes_count[$date_y]['teamRevenue_total'],
                        "todayGrand" => $promoter_today_count[$date_t]['totalTeamRevenue_total'],
                        "yesterdayGrand" => $promoter_yes_count[$date_y]['totalTeamRevenue_total'],
                    ],
                    [
                        "name" => "税收纯利润",
                        "today" => $this->formatMoneyFromMongoNo($promoter_today_count[$date_t]['teamRevenue_total'] - $promoter_today_count[$date_t]['teamProfit_total']),
                        "yesterday" => $this->formatMoneyFromMongoNo($promoter_yes_count[$date_y]['teamRevenue_total'] - $promoter_yes_count[$date_y]['teamProfit_total']),
                        "todayGrand" => $this->formatMoneyFromMongoNo($promoter_today_count[$date_t]['totalTeamRevenue_total'] - $promoter_today_count[$date_t]['totalTeamProfit_total']),
                        "yesterdayGrand" => $this->formatMoneyFromMongoNo($promoter_yes_count[$date_y]['totalTeamRevenue_total'] - $promoter_yes_count[$date_y]['totalTeamProfit_total']),
                    ],
                    [
                        "name" => "奖励金额",
                        "today" => $platform_data_record_t[$date_t]['todayRewardScore'],
                        "yesterday" => $platform_data_record_y[$date_y]['todayRewardScore'],
                        "todayGrand" => $platform_data_record_t[$date_t]['totalRewardScore'],
                        "yesterdayGrand" => $platform_data_record_y[$date_y]['totalRewardScore'],
                    ],
                    [
                        "name" => "官方盈亏",
                        "today" => $this->formatMoneyFromMongoNo($promoter_today_count[$date_t]['teamRevenue_total'] + $promoter_today_count[$date_t]['teamGameWinScore_total'] - $promoter_today_count[$date_t]['teamProfit_total'] - $platform_data_record_t[$date_t]['todayRewardScore']),//$sys_win_lose_t,
                        "yesterday" => $this->formatMoneyFromMongoNo($promoter_yes_count[$date_y]['teamRevenue_total'] + $promoter_yes_count[$date_y]['teamGameWinScore_total'] - $promoter_yes_count[$date_y]['teamProfit_total'] - $platform_data_record_y[$date_y]['todayRewardScore']),//$sys_win_lose_y,
                        "todayGrand" => $this->formatMoneyFromMongoNo($promoter_today_count[$date_t]['totalTeamRevenue_total'] + $promoter_today_count[$date_t]['totalTeamGameWinScore_total'] - $promoter_today_count[$date_t]['totalTeamProfit_total'] - $platform_data_record_t[$date_t]['totalRewardScore']),
                        "yesterdayGrand" => $this->formatMoneyFromMongoNo($promoter_yes_count[$date_y]['totalTeamRevenue_total'] + $promoter_yes_count[$date_y]['totalTeamGameWinScore_total'] - $promoter_yes_count[$date_y]['totalTeamProfit_total'] - $platform_data_record_y[$date_y]['totalRewardScore']),
                    ],
                    [
                        "name" => "新增代理",
                        "today" =>  $promoter_today_count[$date_t]['teamRegPromoterNum_total'],
                        "yesterday" => $promoter_yes_count[$date_y]['teamRegPromoterNum_total'],
                        "todayGrand" =>  $promoter_today_count[$date_t]['totalTeamRegPromoterNum_total'],
                        "yesterdayGrand" => $promoter_yes_count[$date_y]['totalTeamRegPromoterNum_total'],
                    ],
                    [
                        "name" => "新增有效代理",
                        "today" => $promoter_today_count[$date_t]['teamActiveRegPromoterNum_total'],
                        "yesterday" => $promoter_yes_count[$date_y]['teamActiveRegPromoterNum_total'],
                        "todayGrand" => $promoter_today_count[$date_t]['totalTeamActiveRegPromoterNum_total'],
                        "yesterdayGrand" => $promoter_yes_count[$date_y]['totalTeamActiveRegPromoterNum_total'],
                    ],
                ];
            }else{
                $data = [
                    [
                        "name" => "官方游戏输赢",
                        "today" => $platformClubRecordTo[$date_t]['platformWinScore'],
                        "yesterday" => $platformClubRecordYe[$date_y]['platformWinScore'],
                        "todayGrand" => $platformClubRecordTo[$date_t]['totalPlatformWinScore'],
                        "yesterdayGrand" => $platformClubRecordYe[$date_y]['totalPlatformWinScore'],
                    ],
                    [
                        "name" => "用户下注流水",
                        "today" => $platformClubRecordTo[$date_t]['allBet'],
                        "yesterday" => $platformClubRecordYe[$date_y]['allBet'],
                        "todayGrand" => $platformClubRecordTo[$date_t]['totalAllBet'],
                        "yesterdayGrand" => $platformClubRecordYe[$date_y]['totalAllBet'],
                    ],
                    [
                        "name" => "用户有效下注流水",
                        "today" => $platformClubRecordTo[$date_t]['validBet'],
                        "yesterday" => $platformClubRecordYe[$date_y]['validBet'],
                        "todayGrand" => $platformClubRecordTo[$date_t]['totalValidBet'],
                        "yesterdayGrand" => $platformClubRecordYe[$date_y]['totalValidBet'],
                    ],
                    [
                        "name" => "代理提成",
                        //"today" => $this->formatMoneyFromMongoNo($promoter_today_count[$date_t]['myProfit_total'] + $promoter_today_count[$date_t]['teamProfit_total']),
                        //"today" => $this->formatMoneyFromMongoNo($promoter_today_count[$date_t]['myTeamProfit_total']),
                        "today" => $this->formatMoneyFromMongoNo($platformClubRecordTo[$date_t]['promoterScore']),
                        //"yesterday" => $this->formatMoneyFromMongoNo($promoter_yes_count[$date_y]['myProfit_total'] + $promoter_yes_count[$date_y]['teamProfit_total']),
                        //"yesterday" => $this->formatMoneyFromMongoNo($promoter_yes_count[$date_y]['myTeamProfit_total']),
                        "yesterday" => $this->formatMoneyFromMongoNo($platformClubRecordYe[$date_y]['promoterScore']),
                        //"todayGrand" => $this->formatMoneyFromMongoNo($promoter_today_count[$date_t]['totalMyProfit_total'] + $promoter_today_count[$date_t]['totalTeamProfit_total']),
                        //"todayGrand" => $this->formatMoneyFromMongoNo($promoter_today_count[$date_t]['totalMyTeamProfit_total']),
                        "todayGrand" => $this->formatMoneyFromMongoNo($platformClubRecordTo[$date_t]['totalPromoterScore']),
                        //"yesterdayGrand" => $this->formatMoneyFromMongoNo($promoter_yes_count[$date_y]['totalMyProfit_total'] + $promoter_yes_count[$date_y]['totalTeamProfit_total']),
                        //"yesterdayGrand" => $this->formatMoneyFromMongoNo($promoter_yes_count[$date_y]['totalMyTeamProfit_total']),
                        "yesterdayGrand" => $this->formatMoneyFromMongoNo($platformClubRecordYe[$date_y]['totalPromoterScore']),
                    ],
                    [
                        "name" => "代理已提取金额",
                        "today" => $promoter_today_count[$date_t]['teamTransferToScoreAmount_total'],
                        "yesterday" => $promoter_yes_count[$date_y]['teamTransferToScoreAmount_total'],
                        "todayGrand" => $promoter_today_count[$date_t]['totalTeamTransferToScoreAmount_total'],
                        "yesterdayGrand" => $promoter_yes_count[$date_y]['totalTeamTransferToScoreAmount_total'],
                    ],
                    [
                        //"name" => "俱乐部代理可提取余额",
                        "name" => "俱乐部代理目前可提取余额",
                        "today" => '/',//$this->formatMoneyFromMongoNo($promoter_today_count[$date_t]['myTeamProfit_total'] - $promoter_today_count[$date_t]['transferToScoreAmount_total']),
                        "yesterday" =>'/', //$this->formatMoneyFromMongoNo($promoter_yes_count[$date_y]['myTeamProfit_total'] - $promoter_yes_count[$date_y]['transferToScoreAmount_total']),
                        //"todayGrand" => $this->formatMoneyFromMongoNo($this->formatMoneyFromMongoNo($promoter_today_count[$date_t]['totalMyProfit_total'] + $promoter_today_count[$date_t]['totalTeamProfit_total']) - $promoter_today_count[$date_t]['totalTeamTransferToScoreAmount_total']),
                        //"yesterdayGrand" => $this->formatMoneyFromMongoNo($this->formatMoneyFromMongoNo($promoter_yes_count[$date_y]['totalMyProfit_total'] + $promoter_yes_count[$date_y]['totalTeamProfit_total']) - $promoter_yes_count[$date_y]['totalTeamTransferToScoreAmount_total']),
                        "todayGrand" => $platformClubRecordTo[$date_t]['currentPromoterScore'],
                        "yesterdayGrand" => '/', //$platformClubRecordTo[$date_y]['totalPromoterScore'],

                    ],
                    [
                        "name" => "系统税收",
                        "today" => $platformClubRecordTo[$date_t]['revenue'],
                        "yesterday" => $platformClubRecordYe[$date_y]['revenue'],
                        "todayGrand" => $platformClubRecordTo[$date_t]['totalRevenue'],
                        "yesterdayGrand" => $platformClubRecordYe[$date_y]['totalRevenue'],
                    ],
                    [
                        "name" => "代理税收",
                        "today" => $platformClubRecordTo[$date_t]['agentRevenue'],
                        "yesterday" => $platformClubRecordYe[$date_y]['agentRevenue'],
                        "todayGrand" => $platformClubRecordTo[$date_t]['totalAgentRevenue'],
                        "yesterdayGrand" => $platformClubRecordYe[$date_y]['totalAgentRevenue'],
                    ],
                    [
                        "name" => "税收纯利润",
                        "today" => $this->formatMoneyFromMongoNo($platformClubRecordTo[$date_t]['revenue'] - $this->formatMoneyFromMongoNo($promoter_today_count[$date_t]['myProfit_total'] + $promoter_today_count[$date_t]['teamProfit_total'])),
                        "yesterday" => $this->formatMoneyFromMongoNo($platformClubRecordYe[$date_y]['revenue'] - $this->formatMoneyFromMongoNo($promoter_yes_count[$date_y]['myProfit_total'] + $promoter_yes_count[$date_y]['teamProfit_total'])),
                        "todayGrand" => $this->formatMoneyFromMongoNo($platformClubRecordTo[$date_t]['totalRevenue'] - $this->formatMoneyFromMongoNo($promoter_today_count[$date_t]['totalMyProfit_total'] + $promoter_today_count[$date_t]['totalTeamProfit_total'])),
                        "yesterdayGrand" => $this->formatMoneyFromMongoNo($platformClubRecordYe[$date_y]['totalRevenue'] - $this->formatMoneyFromMongoNo($promoter_yes_count[$date_y]['totalMyProfit_total'] + $promoter_yes_count[$date_y]['totalTeamProfit_total'])),
                    ],
                    [
                        "name" => "奖励金额",
                        "today" => $platformClubRecordTo[$date_t]['rewardScore'],
                        "yesterday" => $platformClubRecordYe[$date_y]['rewardScore'],
                        "todayGrand" => $platformClubRecordTo[$date_t]['totalRewardScore'],
                        "yesterdayGrand" => $platformClubRecordYe[$date_y]['totalRewardScore'],
                    ],
                    [
                        "name" => "官方盈亏",
                        "today" => $this->formatMoneyFromMongoNo($platformClubRecordTo[$date_t]['platformWinScore'] + $platformClubRecordTo[$date_t]['revenue'] - ($this->formatMoneyFromMongoNo($promoter_today_count[$date_t]['myProfit_total'] + $promoter_today_count[$date_t]['teamProfit_total'])) - $platformClubRecordTo[$date_t]['rewardScore']),
                        "yesterday" => $this->formatMoneyFromMongoNo($platformClubRecordYe[$date_y]['platformWinScore'] + $platformClubRecordYe[$date_y]['revenue'] - ($this->formatMoneyFromMongoNo($promoter_yes_count[$date_y]['myProfit_total'] + $promoter_yes_count[$date_y]['teamProfit_total'])) - $platformClubRecordYe[$date_y]['rewardScore']),
                        "todayGrand" => $this->formatMoneyFromMongoNo($platformClubRecordTo[$date_t]['totalPlatformWinScore'] + $platformClubRecordTo[$date_t]['totalRevenue'] - ($this->formatMoneyFromMongoNo($promoter_today_count[$date_t]['totalMyProfit_total'] + $promoter_today_count[$date_t]['totalTeamProfit_total'])) - $platformClubRecordTo[$date_t]['totalRewardScore']),
                        "yesterdayGrand" => $this->formatMoneyFromMongoNo($platformClubRecordYe[$date_y]['totalPlatformWinScore'] + $platformClubRecordYe[$date_y]['totalRevenue'] - ($this->formatMoneyFromMongoNo($promoter_yes_count[$date_y]['totalMyProfit_total'] + $promoter_yes_count[$date_y]['totalTeamProfit_total'])) - $platformClubRecordYe[$date_y]['totalRewardScore']),
                    ],

                    [
                        "name" => "新增盟主合伙人",
                        "today" =>  $promoter_today_count[$date_t]['teamRegPromoterNum_total'],
                        "yesterday" => $promoter_yes_count[$date_y]['teamRegPromoterNum_total'],
                        "todayGrand" =>  $promoter_today_count[$date_t]['totalTeamRegPromoterNum_total'],
                        "yesterdayGrand" => $promoter_yes_count[$date_y]['totalTeamRegPromoterNum_total'],
                    ],
                    [
                        "name" => "新增有效合伙人",
                        "today" => $promoter_today_count[$date_t]['teamActiveRegPromoterNum_total'],
                        "yesterday" => $promoter_yes_count[$date_y]['teamActiveRegPromoterNum_total'],
                        "todayGrand" => $promoter_today_count[$date_t]['totalTeamActiveRegPromoterNum_total'],
                        "yesterdayGrand" => $promoter_yes_count[$date_y]['totalTeamActiveRegPromoterNum_total'],
                    ],
                ];
            }

            return json(['code' => 0, 'msg' => 'ok', 'count' => 0,'data' => $data]);
        }
    }

    public function platOverView22(Request $request)
    {
        if ($request->isAjax()) {
            $type = (int)$request->get('type', 1);
            $dateT = date("Y-m-d");
            $dateY = date("Y-m-d", strtotime('-1 day'));
            //平台昨日盈利(会员总充值-会员总兑换-会员总金币-会员保险箱金币-代理可提取金币)


            //金币场 1000代理统计一天一条数据
            $coinStatPromoterDailyT = StatPromoterDaily::getDataByDate($dateT, $dateT);
            $coinStatPromoterDailyY = StatPromoterDaily::getDataByDate($dateY, $dateY);
            $coinPlatformDataRecordT = CoinPlatformDataRecord::getDataByDate($dateT, $dateT);
            $coinPlatformDataRecordY = CoinPlatformDataRecord::getDataByDate($dateY, $dateY);
            //俱乐部 -1000
            $clubStatPromoterDailyT = ClubStatPromoterDaily::getDataByDate($dateT, $dateT);
            $clubStatPromoterDailyY = ClubStatPromoterDaily::getDataByDate($dateY, $dateY);
            $clubPlatformDataRecordT = ClubPlatformDataRecord::getDataByDate($dateT, $dateT);
            $clubPlatformDataRecordY = ClubPlatformDataRecord::getDataByDate($dateY, $dateY);


            if($type == 1) {
                $data = [
                    [
                        "name" => "注册会员人数",
                        "today" => $coinStatPromoterDailyT[$dateT]['teamRegPeople']??0,
                        "yesterday" => $coinStatPromoterDailyY[$dateY]['teamRegPeople']??0,
                        "todayGrand" => $coinStatPromoterDailyT[$dateT]['totalTeamRegPeople']??0,
                        "yesterdayGrand" => $coinStatPromoterDailyY[$dateY]['totalTeamRegPeople']??0,
                    ],
                    [
                        "name" => "注绑会员人数",
                        "today" => $coinStatPromoterDailyT[$dateT]['teamRegBindPeople']??0,
                        "yesterday" => $coinStatPromoterDailyY[$dateY]['teamRegBindPeople']??0,
                        "todayGrand" => $coinPlatformDataRecordT[$dateT]['totalBindPeople']??0,
                        "yesterdayGrand" => $coinPlatformDataRecordY[$dateY]['totalBindPeople']??0,
                    ],
                    [
                        "name" => "有效会员人数",
                        "today" => $coinStatPromoterDailyT[$dateT]['teamRegValidNewBetPeople']??0,
                        "yesterday" => $coinStatPromoterDailyY[$dateY]['teamRegValidNewBetPeople']??0,
                        "todayGrand" => $coinPlatformDataRecordT[$dateT]['totalBetPeople']??0,
                        "yesterdayGrand" => $coinPlatformDataRecordY[$dateY]['totalBetPeople']??0,
                    ],
                    [
                        "name" => "会员充值",
                        "today" => $coinPlatformDataRecordT[$dateT]['todayRechargeAmount']??0,
                        "yesterday" => $coinPlatformDataRecordY[$dateY]['todayRechargeAmount']??0,
                        "todayGrand" => $coinPlatformDataRecordT[$dateT]['totalRechargeAmount']??0,
                        "yesterdayGrand" => $coinPlatformDataRecordY[$dateY]['totalRechargeAmount']??0,
                    ],
                    [
                        "name" => "会员提现",
                        "today" => $coinPlatformDataRecordT[$dateT]['todayExchangeAmount']??0,
                        "yesterday" => $coinPlatformDataRecordY[$dateY]['todayExchangeAmount']??0,
                        "todayGrand" => $coinPlatformDataRecordT[$dateT]['totalExchangeAmount']??0,
                        "yesterdayGrand" => $coinPlatformDataRecordY[$dateY]['totalExchangeAmount']??0,
                    ],
                    [
                        "name" => "会员总充值次数",
                        "today" => $coinPlatformDataRecordT[$dateT]['todayRechargeTimes']??0,
                        "yesterday" => $coinPlatformDataRecordY[$dateY]['todayRechargeTimes']??0,
                        "todayGrand" => $coinPlatformDataRecordT[$dateT]['totalRechargeTimes']??0,
                        "yesterdayGrand" => $coinPlatformDataRecordY[$dateY]['totalRechargeTimes']??0,
                    ],
                    [
                        "name" => "会员总提现次数",
                        "today" => $coinPlatformDataRecordT[$dateT]['todayExchangeTimes']??0,
                        "yesterday" => $coinPlatformDataRecordY[$dateY]['todayExchangeTimes']??0,
                        "todayGrand" => $coinPlatformDataRecordT[$dateT]['totalExchangeTimes']??0,
                        "yesterdayGrand" => $coinPlatformDataRecordY[$dateY]['totalExchangeTimes']??0,
                    ],
                    [
                        "name" => "官方游戏输赢",
                        "today" => dataSummary($coinStatPromoterDailyT[$dateT]??[], $clubStatPromoterDailyT[$dateT]??[], $coinPlatformDataRecordT[$dateT]??[], $clubPlatformDataRecordT[$dateT]??[], 'gameWinScore'),
                        "yesterday" => dataSummary($coinStatPromoterDailyY[$dateY]??[], $clubStatPromoterDailyY[$dateY]??[], $coinPlatformDataRecordY[$dateY]??[], $clubPlatformDataRecordY[$dateY]??[], 'gameWinScore'),
                        "todayGrand" => dataSummary($coinStatPromoterDailyT[$dateT]??[], $clubStatPromoterDailyT[$dateT]??[], $coinPlatformDataRecordT[$dateT]??[], $clubPlatformDataRecordT[$dateT]??[], 'gameWinScoreGrand'),
                        "yesterdayGrand" => dataSummary($coinStatPromoterDailyY[$dateY]??[], $clubStatPromoterDailyY[$dateY]??[], $coinPlatformDataRecordY[$dateY]??[], $clubPlatformDataRecordY[$dateY]??[], 'gameWinScoreGrand'),
                    ],
                    [
                        "name" => "用户下注流水",
                        "today" => dataSummary($coinStatPromoterDailyT[$dateT]??[], $clubStatPromoterDailyT[$dateT]??[], $coinPlatformDataRecordT[$dateT]??[], $clubPlatformDataRecordT[$dateT]??[], 'allBet'),
                        "yesterday" => dataSummary($coinStatPromoterDailyY[$dateY]??[], $clubStatPromoterDailyY[$dateY]??[], $coinPlatformDataRecordY[$dateY]??[], $clubPlatformDataRecordY[$dateY]??[], 'allBet'),
                        "todayGrand" => dataSummary($coinStatPromoterDailyT[$dateT]??[], $clubStatPromoterDailyT[$dateT]??[], $coinPlatformDataRecordT[$dateT]??[], $clubPlatformDataRecordT[$dateT]??[], 'allBetGrand'),
                        "yesterdayGrand" => dataSummary($coinStatPromoterDailyY[$dateY]??[], $clubStatPromoterDailyY[$dateY]??[], $coinPlatformDataRecordY[$dateY]??[], $clubPlatformDataRecordY[$dateY]??[], 'allBetGrand'),
                    ],
                    [
                        "name" => "用户有效下注流水",
                        "today" => dataSummary($coinStatPromoterDailyT[$dateT]??[], $clubStatPromoterDailyT[$dateT]??[], $coinPlatformDataRecordT[$dateT]??[], $clubPlatformDataRecordT[$dateT]??[], 'vaildBet'),
                        "yesterday" => dataSummary($coinStatPromoterDailyY[$dateY]??[], $clubStatPromoterDailyY[$dateY]??[], $coinPlatformDataRecordY[$dateY]??[], $clubPlatformDataRecordY[$dateY]??[], 'vaildBet'),
                        "todayGrand" => dataSummary($coinStatPromoterDailyT[$dateT]??[], $clubStatPromoterDailyT[$dateT]??[], $coinPlatformDataRecordT[$dateT]??[], $clubPlatformDataRecordT[$dateT]??[], 'vaildBetGrand'),
                        "yesterdayGrand" => dataSummary($coinStatPromoterDailyY[$dateY]??[], $clubStatPromoterDailyY[$dateY]??[], $coinPlatformDataRecordY[$dateY]??[], $clubPlatformDataRecordY[$dateY]??[], 'vaildBetGrand'),
                    ],
                    [
                        "name" => "代理提成",
                        "today" => dataSummary($coinStatPromoterDailyT[$dateT]??[], $clubStatPromoterDailyT[$dateT]??[], $coinPlatformDataRecordT[$dateT]??[], $clubPlatformDataRecordT[$dateT]??[], 'promoterScore'),
                        "yesterday" => dataSummary($coinStatPromoterDailyY[$dateY]??[], $clubStatPromoterDailyY[$dateY]??[], $coinPlatformDataRecordY[$dateY]??[], $clubPlatformDataRecordY[$dateY]??[], 'promoterScore'),
                        "todayGrand" => dataSummary($coinStatPromoterDailyT[$dateT]??[], $clubStatPromoterDailyT[$dateT]??[], $coinPlatformDataRecordT[$dateT]??[], $clubPlatformDataRecordT[$dateT]??[], 'promoterScoreGrand'),
                        "yesterdayGrand" => dataSummary($coinStatPromoterDailyY[$dateY]??[], $clubStatPromoterDailyY[$dateY]??[], $coinPlatformDataRecordY[$dateY]??[], $clubPlatformDataRecordY[$dateY]??[], 'promoterScoreGrand'),
                    ],
                    [
                        "name" => "系统税收",
                        "today" => dataSummary($coinStatPromoterDailyT[$dateT]??[], $clubStatPromoterDailyT[$dateT]??[], $coinPlatformDataRecordT[$dateT]??[], $clubPlatformDataRecordT[$dateT]??[], 'revenue'),
                        "yesterday" => dataSummary($coinStatPromoterDailyY[$dateY]??[], $clubStatPromoterDailyY[$dateY]??[], $coinPlatformDataRecordY[$dateY]??[], $clubPlatformDataRecordY[$dateY]??[], 'revenue'),
                        "todayGrand" => dataSummary($coinStatPromoterDailyT[$dateT]??[], $clubStatPromoterDailyT[$dateT]??[], $coinPlatformDataRecordT[$dateT]??[], $clubPlatformDataRecordT[$dateT]??[], 'revenueGrand'),
                        "yesterdayGrand" => dataSummary($coinStatPromoterDailyY[$dateY]??[], $clubStatPromoterDailyY[$dateY]??[], $coinPlatformDataRecordY[$dateY]??[], $clubPlatformDataRecordY[$dateY]??[], 'revenueGrand'),
                    ],
                    [
                        "name" => "税收纯利润",
                        "today" => dataSummary($coinStatPromoterDailyT[$dateT]??[], $clubStatPromoterDailyT[$dateT]??[], $coinPlatformDataRecordT[$dateT]??[], $clubPlatformDataRecordT[$dateT]??[], 'taxProfit'),
                        "yesterday" => dataSummary($coinStatPromoterDailyY[$dateY]??[], $clubStatPromoterDailyY[$dateY]??[], $coinPlatformDataRecordY[$dateY]??[], $clubPlatformDataRecordY[$dateY]??[], 'taxProfit'),
                        "todayGrand" => dataSummary($coinStatPromoterDailyT[$dateT]??[], $clubStatPromoterDailyT[$dateT]??[], $coinPlatformDataRecordT[$dateT]??[], $clubPlatformDataRecordT[$dateT]??[], 'taxProfitGrand'),
                        "yesterdayGrand" => dataSummary($coinStatPromoterDailyY[$dateY]??[], $clubStatPromoterDailyY[$dateY]??[], $coinPlatformDataRecordY[$dateY]??[], $clubPlatformDataRecordY[$dateY]??[], 'taxProfitGrand'),
                    ],
                    [
                        "name" => "官方盈亏",
                        "today" => dataSummary($coinStatPromoterDailyT[$dateT]??[], $clubStatPromoterDailyT[$dateT]??[], $coinPlatformDataRecordT[$dateT]??[], $clubPlatformDataRecordT[$dateT]??[], 'profitOrLoss'),
                        "yesterday" => dataSummary($coinStatPromoterDailyY[$dateY]??[], $clubStatPromoterDailyY[$dateY]??[], $coinPlatformDataRecordY[$dateY]??[], $clubPlatformDataRecordY[$dateY]??[], 'profitOrLoss'),
                        "todayGrand" => dataSummary($coinStatPromoterDailyT[$dateT]??[], $clubStatPromoterDailyT[$dateT]??[], $coinPlatformDataRecordT[$dateT]??[], $clubPlatformDataRecordT[$dateT]??[], 'profitOrLossGrand'),
                        "yesterdayGrand" => dataSummary($coinStatPromoterDailyY[$dateY]??[], $clubStatPromoterDailyY[$dateY]??[], $coinPlatformDataRecordY[$dateY]??[], $clubPlatformDataRecordY[$dateY]??[], 'profitOrLossGrand'),
                    ],

                    [
                        "name" => "奖励金额",
                        "today" => dataSummary($coinStatPromoterDailyT[$dateT]??[], $clubStatPromoterDailyT[$dateT]??[], $coinPlatformDataRecordT[$dateT]??[], $clubPlatformDataRecordT[$dateT]??[], 'rewardScore'),
                        "yesterday" => dataSummary($coinStatPromoterDailyY[$dateY]??[], $clubStatPromoterDailyY[$dateY]??[], $coinPlatformDataRecordY[$dateY]??[], $clubPlatformDataRecordY[$dateY]??[], 'rewardScore'),
                        "todayGrand" => dataSummary($coinStatPromoterDailyT[$dateT]??[], $clubStatPromoterDailyT[$dateT]??[], $coinPlatformDataRecordT[$dateT]??[], $clubPlatformDataRecordT[$dateT]??[], 'rewardScoreGrand'),
                        "yesterdayGrand" => dataSummary($coinStatPromoterDailyY[$dateY]??[], $clubStatPromoterDailyY[$dateY]??[], $coinPlatformDataRecordY[$dateY]??[], $clubPlatformDataRecordY[$dateY]??[], 'rewardScoreGrand'),
                    ],
                    [
                        "name" => "房卡余额",
                        "today" => '/',
                        "yesterday" => '/',
                        "todayGrand" => $coinPlatformDataRecordT[$dateT]['totalRoomCard']??0,
                        "yesterdayGrand" => $coinPlatformDataRecordY[$dateY]['totalRoomCard']??0,
                    ],
                    [
                        "name" => "金币余额",
                        "today" => '/',
                        "yesterday" => '/',
                        "todayGrand" => $coinPlatformDataRecordT[$dateT]['totalScore']??0,
                        "yesterdayGrand" => $coinPlatformDataRecordY[$dateY]['totalScore']??0,

                    ],
                    [
                        "name" => "保险箱余额",
                        "today" => '/',
                        "yesterday" => '/',
                        "todayGrand" => $coinPlatformDataRecordT[$dateT]['totalBankScore']??0,
                        "yesterdayGrand" => $coinPlatformDataRecordY[$dateY]['totalBankScore']??0,
                    ],
                    [
                        "name" => "平台余额",
                        "today" => '/',
                        "yesterday" => '/',
                         "todayGrand" => dataSummary($coinStatPromoterDailyT[$dateT]??[], $clubStatPromoterDailyT[$dateT]??[], $coinPlatformDataRecordT[$dateT]??[], $clubPlatformDataRecordT[$dateT]??[], 'playerTotalAmountGrand'),
                        "yesterdayGrand" => dataSummary($coinStatPromoterDailyY[$dateY]??[], $clubStatPromoterDailyY[$dateY]??[], $coinPlatformDataRecordY[$dateY]??[], $clubPlatformDataRecordY[$dateY]??[], 'playerTotalAmountGrand'),
                    ]
                ];
            } else if($type == 2) {
                $data = [
                    [
                        "name" => "官方游戏输赢",
                        "today" => $coinStatPromoterDailyT[$dateT]['teamGameWinScore']??0,
                        "yesterday" => $coinStatPromoterDailyY[$dateY]['teamGameWinScore']??0,
                        "todayGrand" => $coinStatPromoterDailyT[$dateT]['totalTeamGameWinScore']??0,
                        "yesterdayGrand" => $coinStatPromoterDailyY[$dateY]['totalTeamGameWinScore']??0,
                    ],
                    [
                        "name" => "用户下注流水",
                        "today" => $coinPlatformDataRecordT[$dateT]['todayAllBetScore']??0,
                        "yesterday" => $coinPlatformDataRecordY[$dateY]['todayAllBetScore']??0,
                        "todayGrand" => $coinPlatformDataRecordT[$dateT]['totalAllBetScore']??0,
                        "yesterdayGrand" => $coinPlatformDataRecordY[$dateY]['totalAllBetScore']??0,
                    ],
                    [
                        "name" => "用户有效下注流水",
                        "today" => $coinPlatformDataRecordT[$dateT]['todayValidBetScore']??0,
                        "yesterday" => $coinPlatformDataRecordY[$dateY]['todayValidBetScore']??0,
                        "todayGrand" => $coinPlatformDataRecordT[$dateT]['totalValidBetScore']??0,
                        "yesterdayGrand" => $coinPlatformDataRecordY[$dateY]['totalValidBetScore']??0,
                    ],
                    [
                        "name" => "代理提成",
                        "today" => $coinPlatformDataRecordT[$dateT]['todayPromoterScore']??0,
                        "yesterday" => $coinPlatformDataRecordY[$dateY]['todayPromoterScore']??0,
                        "todayGrand" => $coinPlatformDataRecordT[$dateT]['totalPromoterScore']??0,
                        "yesterdayGrand" => $coinPlatformDataRecordY[$dateY]['totalPromoterScore']??0,
                    ],
                    [
                        "name" => "代理已提取金额",
                        "today" => $coinStatPromoterDailyT[$dateT]['teamTransferToScoreAmount']??0,
                        "yesterday" => $coinStatPromoterDailyY[$dateY]['teamTransferToScoreAmount']??0,
                        "todayGrand" => $coinStatPromoterDailyT[$dateT]['totalTeamTransferToScoreAmount']??0,
                        "yesterdayGrand" => $coinStatPromoterDailyY[$dateY]['totalTeamTransferToScoreAmount']??0,
                    ],
                    [
                        "name" => "代理可提取金额",
                        "today" => '/',
                        "yesterday" => '/',
                        "todayGrand" => $coinPlatformDataRecordT[$dateT]['currentPromoterScore']??0,
                        "yesterdayGrand" => '/',

                    ],
                    [
                        "name" => "系统税收",
                        "today" => $coinStatPromoterDailyT[$dateT]['teamRevenue']??0,
                        "yesterday" => $coinStatPromoterDailyY[$dateY]['teamRevenue']??0,
                        "todayGrand" => $coinStatPromoterDailyT[$dateT]['totalTeamRevenue']??0,
                        "yesterdayGrand" => $coinStatPromoterDailyY[$dateY]['totalTeamRevenue']??0,
                    ],
                    [
                        "name" => "奖励金额",
                        "today" => $coinPlatformDataRecordT[$dateT]['todayRewardScore']??0,
                        "yesterday" => $coinPlatformDataRecordY[$dateY]['todayRewardScore']??0,
                        "todayGrand" => $coinPlatformDataRecordT[$dateT]['totalRewardScore']??0,
                        "yesterdayGrand" => $coinPlatformDataRecordY[$dateY]['totalRewardScore']??0,
                    ],
                    [
                        "name" => "新增代理",
                        "today" =>  $coinStatPromoterDailyT[$dateT]['teamRegPromoterNum']??0,
                        "yesterday" => $coinStatPromoterDailyY[$dateY]['teamRegPromoterNum']??0,
                        "todayGrand" =>  $coinStatPromoterDailyT[$dateT]['totalTeamRegPromoterNum']??0,
                        "yesterdayGrand" => $coinStatPromoterDailyY[$dateY]['totalTeamRegPromoterNum']??0,
                    ],
                    [
                        "name" => "新增有效代理",
                        "today" => $coinStatPromoterDailyT[$dateT]['teamActiveRegPromoterNum']??0,
                        "yesterday" => $coinStatPromoterDailyY[$dateY]['teamActiveRegPromoterNum']??0,
                        "todayGrand" => $coinStatPromoterDailyT[$dateT]['totalTeamActiveRegPromoterNum']??0,
                        "yesterdayGrand" => $coinStatPromoterDailyY[$dateY]['totalTeamActiveRegPromoterNum']??0,
                    ],
                    [
                        "name" => "税收纯利润",//系统税收-代理提成
                        "today" => taxProfit($coinStatPromoterDailyT[$dateT]??[], $coinPlatformDataRecordT[$dateT]??[]),
                        "yesterday" => taxProfit($coinStatPromoterDailyY[$dateY]??[], $coinPlatformDataRecordY[$dateY]??[]),
                        "todayGrand" => taxProfitGrand($coinStatPromoterDailyT[$dateT]??[], $coinPlatformDataRecordT[$dateT]??[]),
                        "yesterdayGrand" => taxProfitGrand($coinStatPromoterDailyY[$dateY]??[], $coinPlatformDataRecordY[$dateY]??[]),
                    ],
                    [
                        "name" => "官方盈亏",
                        "today" => profitOrLoss($coinStatPromoterDailyT[$dateT]??[], $coinPlatformDataRecordT[$dateT]??[]),
                        "yesterday" => profitOrLoss($coinStatPromoterDailyY[$dateY]??[], $coinPlatformDataRecordY[$dateY]??[]),
                        "todayGrand" => profitOrLossGrand($coinStatPromoterDailyT[$dateT]??[], $coinPlatformDataRecordT[$dateT]??[]),
                        "yesterdayGrand" => profitOrLossGrand($coinStatPromoterDailyY[$dateY]??[], $coinPlatformDataRecordY[$dateY]??[]),
                    ],
                ];
            }else{
                $data = [
                    [
                        "name" => "官方游戏输赢",
                        "today" => $clubPlatformDataRecordT[$dateT]['platformWinScore']??0,
                        "yesterday" => $clubPlatformDataRecordY[$dateY]['platformWinScore']??0,
                        "todayGrand" => $clubPlatformDataRecordT[$dateT]['totalPlatformWinScore']??0,
                        "yesterdayGrand" => $clubPlatformDataRecordY[$dateY]['totalPlatformWinScore']??0,
                    ],
                    [
                        "name" => "用户下注流水",
                        "today" => $clubPlatformDataRecordT[$dateT]['allBet']??0,
                        "yesterday" => $clubPlatformDataRecordY[$dateY]['allBet']??0,
                        "todayGrand" => $clubPlatformDataRecordT[$dateT]['totalAllBet']??0,
                        "yesterdayGrand" => $clubPlatformDataRecordY[$dateY]['totalAllBet']??0,
                    ],
                    [
                        "name" => "用户有效下注流水",
                        "today" => $clubPlatformDataRecordT[$dateT]['validBet']??0,
                        "yesterday" => $clubPlatformDataRecordY[$dateY]['validBet']??0,
                        "todayGrand" => $clubPlatformDataRecordT[$dateT]['totalValidBet']??0,
                        "yesterdayGrand" => $clubPlatformDataRecordY[$dateY]['totalValidBet']??0,
                    ],
                    [
                        "name" => "代理提成",
                        "today" => $clubPlatformDataRecordT[$dateT]['promoterScore']??0,
                        "yesterday" => $clubPlatformDataRecordY[$dateY]['promoterScore']??0,
                        "todayGrand" => $clubPlatformDataRecordT[$dateT]['totalPromoterScore']??0,
                        "yesterdayGrand" => $clubPlatformDataRecordY[$dateY]['totalPromoterScore']??0,
                    ],
                    [
                        "name" => "代理已提取金额",
                        "today" => $clubStatPromoterDailyT[$dateT]['teamTransferToScoreAmount']??0,
                        "yesterday" => $clubStatPromoterDailyY[$dateY]['teamTransferToScoreAmount']??0,
                        "todayGrand" => $clubStatPromoterDailyT[$dateT]['totalTeamTransferToScoreAmount']??0,
                        "yesterdayGrand" => $clubStatPromoterDailyY[$dateY]['totalTeamTransferToScoreAmount']??0,
                    ],
                    [
                        "name" => "俱乐部代理目前可提取余额",
                        "today" => '/',
                        "yesterday" =>'/',
                        "todayGrand" => $clubPlatformDataRecordT[$dateT]['currentPromoterScore']??0,
                        "yesterdayGrand" => '/',

                    ],
                    [
                        "name" => "系统税收",
                        "today" => $clubPlatformDataRecordT[$dateT]['revenue']??0,
                        "yesterday" => $clubPlatformDataRecordY[$dateY]['revenue']??0,
                        "todayGrand" => $clubPlatformDataRecordT[$dateT]['totalRevenue']??0,
                        "yesterdayGrand" => $clubPlatformDataRecordY[$dateY]['totalRevenue']??0,
                    ],
                    [
                        "name" => "代理税收",
                        "today" => $clubPlatformDataRecordT[$dateT]['agentRevenue']??0,
                        "yesterday" => $clubPlatformDataRecordY[$dateY]['agentRevenue']??0,
                        "todayGrand" => $clubPlatformDataRecordT[$dateT]['totalAgentRevenue']??0,
                        "yesterdayGrand" => $clubPlatformDataRecordY[$dateY]['totalAgentRevenue']??0,
                    ],
                    [
                        "name" => "奖励金额",
                        "today" => $clubPlatformDataRecordT[$dateT]['rewardScore']??0,
                        "yesterday" => $clubPlatformDataRecordY[$dateY]['rewardScore']??0,
                        "todayGrand" => $clubPlatformDataRecordT[$dateT]['totalRewardScore']??0,
                        "yesterdayGrand" => $clubPlatformDataRecordY[$dateY]['totalRewardScore']??0,
                    ],
                    [
                        "name" => "新增盟主合伙人",
                        "today" =>  $clubStatPromoterDailyT[$dateT]['teamRegPromoterNum']??0,
                        "yesterday" => $clubStatPromoterDailyY[$dateY]['teamRegPromoterNum']??0,
                        "todayGrand" =>  $clubStatPromoterDailyT[$dateT]['totalTeamRegPromoterNum']??0,
                        "yesterdayGrand" => $clubStatPromoterDailyY[$dateY]['totalTeamRegPromoterNum']??0,
                    ],
                    [
                        "name" => "新增有效合伙人",
                        "today" => $clubStatPromoterDailyT[$dateT]['teamActiveRegPromoterNum']??0,
                        "yesterday" => $clubStatPromoterDailyY[$dateY]['teamActiveRegPromoterNum']??0,
                        "todayGrand" => $clubStatPromoterDailyT[$dateT]['totalTeamActiveRegPromoterNum']??0,
                        "yesterdayGrand" => $clubStatPromoterDailyY[$dateY]['totalTeamActiveRegPromoterNum']??0,
                    ],



                    [
                        "name" => "税收纯利润",
                        "today" => clubTaxProfit($clubPlatformDataRecordT[$dateT]??[]),
                        "yesterday" => clubTaxProfit($clubPlatformDataRecordY[$dateY]??[]),
                        "todayGrand" => clubTaxProfitGrand($clubPlatformDataRecordT[$dateT]??[]),
                        "yesterdayGrand" => clubTaxProfitGrand($clubPlatformDataRecordY[$dateY]??[]),
                    ],
                    [
                        "name" => "官方盈亏",
                        "today" => clubProfitOrLoss($clubPlatformDataRecordT[$dateT]??[]),
                        "yesterday" => clubProfitOrLoss($clubPlatformDataRecordY[$dateY]??[]),
                        "todayGrand" => clubProfitOrLossGrand($clubPlatformDataRecordT[$dateT]??[]),
                        "yesterdayGrand" => clubProfitOrLossGrand($clubPlatformDataRecordY[$dateY]??[]),
                    ],
                ];
            }

            return json(['code' => 0, 'msg' => 'ok', 'count' => 0,'data' => $data]);
        }
    }

    public function platOverView3(Request $request)
    {
        if ($request->isAjax()) {
            $begin_time_t=mktime(0,0,0,date('m'),1,date('Y'));
            $begin_time_y=mktime(23,59,59,date('m'),date('t'),date('Y'));

            $date_t_m_s = date("Y-m-d",$begin_time_t);//本月开始日期
            $date_t_m_e = date("Y-m-d",$begin_time_y);//本月结束日期


            $begin_time_y = strtotime(date('Y-m-01 00:00:00',strtotime('-1 month')));
            $end_time_y = strtotime(date("Y-m-d 23:59:59", strtotime(-date('d').'day')));

            $date_y_m_s = date("Y-m-d",$begin_time_y);//上月开始日期
            $date_y_m_e = date("Y-m-d",$end_time_y);//上月结束日期
            //俱乐部平台本月上月游戏输赢
            $platformClubRecordTo = $this->getPlatFormClubSum($date_t_m_s,$date_t_m_e);
            $platformClubRecordYe = $this->getPlatFormClubSum($date_y_m_s,$date_y_m_e);

            //金币场日报数据
            $promoter_today_count = $this->get_promoter_daily2($date_t_m_s,$date_t_m_e);
            $promoter_yes_count = $this->get_promoter_daily2($date_y_m_s,$date_y_m_e);

            $date_m_t = date("Y-m-d");

            //以前的写法
            $platform_data_record_t = $this->get_yes_data($date_m_t);
            $platform_data_record_y = $this->get_yes_data($date_y_m_e);

            //新写法
            $date_t = date("Y-m-d");//今天
            $end_time_y = strtotime(date("Y-m-d 23:59:59", strtotime(-date('d').'day')));
            $date_y = date("Y-m-d",$end_time_y);//上月结束日期
            $platform_data_record_t2 = $this->get_yes_data($date_t);
            $platform_data_record_y2 = $this->get_yes_data($date_y);
            $platform_data_record_t2 = array_shift($platform_data_record_t2);
            $platform_data_record_y2 = array_shift($platform_data_record_y2);


            $platformCoinRecordTo = $this->getPlatFormCoinSum($date_t_m_s,$date_t_m_e);
            $platformCoinRecordYe = $this->getPlatFormCoinSum($date_y_m_s,$date_y_m_e);

            $promoter_today_club = $this->getPromoterDailyClub($date_t_m_s,$date_t_m_e);
            $promoter_yes_club = $this->getPromoterDailyClub($date_y_m_s,$date_y_m_e);

            $platform_data_record_y[$date_y_m_e]['totalScore'] = !empty($platform_data_record_y[$date_y_m_e]['totalScore']) ? $this->formatMoneyFromMongo($platform_data_record_y[$date_y_m_e]['totalScore']) : 0;
            $platform_data_record_y[$date_y_m_e]['totalBankScore'] = !empty($platform_data_record_y[$date_y_m_e]['totalBankScore']) ? $this->formatMoneyFromMongo($platform_data_record_y[$date_y_m_e]['totalBankScore']) : 0;
            $platform_data_record_y[$date_y_m_e]['totalPromoterScore'] = !empty($platform_data_record_y[$date_y_m_e]['totalPromoterScore']) ? $this->formatMoneyFromMongo($platform_data_record_y[$date_y_m_e]['totalPromoterScore']) : 0;

            $platform_data_record_t[$date_m_t]['totalScore'] = !empty($platform_data_record_t[$date_m_t]['totalScore']) ? $this->formatMoneyFromMongo($platform_data_record_t[$date_m_t]['totalScore']) : 0;
            $platform_data_record_t[$date_m_t]['totalBankScore'] = !empty($platform_data_record_t[$date_m_t]['totalBankScore']) ? $this->formatMoneyFromMongo($platform_data_record_t[$date_m_t]['totalBankScore']) : 0;
            $platform_data_record_t[$date_m_t]['totalPromoterScore'] = !empty($platform_data_record_t[$date_m_t]['totalPromoterScore']) ? $this->formatMoneyFromMongo($platform_data_record_t[$date_m_t]['totalPromoterScore']) : 0;

            $player_total_amount_t = $platform_data_record_t[$date_m_t]['totalScore'] + $platform_data_record_t[$date_m_t]['totalBankScore']+$platform_data_record_t[$date_m_t]['totalPromoterScore'];
            $player_total_amount_y = $platform_data_record_y[$date_y_m_e]['totalScore'] + $platform_data_record_y[$date_y_m_e]['totalBankScore']+$platform_data_record_y[$date_y_m_e]['totalPromoterScore'];

            $player_total_amount_t = round($player_total_amount_t,2);
            $player_total_amount_y = round($player_total_amount_y,2);

            $date_t = $date_t_m_s;
            $date_y = $date_y_m_s;

            //金币场税收纯利润
            //$this->formatMoneyFromMongoNo($promoter_today_count[$date_t]['teamRevenue_total'] + $platformClubRecordTo[$date_t]['revenue']),
            $totalTaxProCoinTo = $this->formatMoneyFromMongoNo($promoter_today_count[$date_t]['teamRevenue_total']);
            $totalTaxProCoinYe = $this->formatMoneyFromMongoNo($promoter_yes_count[$date_y]['teamRevenue_total']);
            //俱乐部税收纯利润
            $totalTaxProClubTo = $this->formatMoneyFromMongoNo($platformClubRecordTo['revenue']);
            $totalTaxProClubYe = $this->formatMoneyFromMongoNo($platformClubRecordYe['revenue']);
            //汇总税收纯利润
            $sumTaxProTo = $this->formatMoneyFromMongoNo($totalTaxProCoinTo + $totalTaxProClubTo);
            $sumTaxProYe = $this->formatMoneyFromMongoNo($totalTaxProCoinYe + $totalTaxProClubYe);

            //金币场官方盈亏
            $officialWinLoseCoinTo =  $this->formatMoneyFromMongoNo($promoter_today_count[$date_t]['teamRevenue_total'] + $promoter_today_count[$date_t]['teamGameWinScore_total'] - $promoter_today_count[$date_t]['teamProfit_total'] - $platformCoinRecordTo['todayRewardScore']);
            $officialWinLoseCoinYe =  $this->formatMoneyFromMongoNo($promoter_yes_count[$date_y]['teamRevenue_total'] + $promoter_yes_count[$date_y]['teamGameWinScore_total'] - $promoter_yes_count[$date_y]['teamProfit_total'] - $platformCoinRecordYe['todayRewardScore']);

            //俱乐部官方盈亏
            $officialWinLoseClubTo = $platformClubRecordTo['platformProfit'] - $platformClubRecordTo['rewardScore'];
            $officialWinLoseClubYe = $platformClubRecordYe['platformProfit'] - $platformClubRecordYe['rewardScore'];

            //汇总官方盈亏
            $TotalofficialWinLoseTo = $this->formatMoneyFromMongoNo($officialWinLoseCoinTo + $officialWinLoseClubTo);
            $TotalofficialWinLoseYe = $this->formatMoneyFromMongoNo($officialWinLoseCoinYe + $officialWinLoseClubYe);

            //官方盈亏 = 税收 + 输赢 - 代理佣金
//            $sys_win_lose_t = $promoter_today_count[$date_t]['teamRevenue_total'] + $promoter_today_count[$date_t]['teamGameWinScore_total'] - $agency_commission_arr_t[$date_t]['teamProfit_total'];
//            $sys_win_lose_t = round($sys_win_lose_t,2);
//            $sys_win_lose_y = $promoter_yes_count[$date_y]['teamRevenue_total'] + $promoter_yes_count[$date_y]['teamGameWinScore_total'] - $agency_commission_arr_y[$date_y]['teamProfit_total'];
//            $sys_win_lose_y = round($sys_win_lose_y,2);

            $data = [
                [
                    "name" => "注册会员人数",
                    "currentMon" => $promoter_today_count[$date_t]['teamRegPeople_total'],
                    "lastMon" => $promoter_yes_count[$date_y]['teamRegPeople_total'],
                ],
               /* [
                    "name" => "注绑会员人数",
                    "currentMon" => $promoter_today_count[$date_t]['teamRegBindPeople_total'],
                    "lastMon" => $promoter_yes_count[$date_y]['teamRegBindPeople_total'],
                ],
                [
                    "name" => "有效会员人数",
                    "currentMon" => $promoter_today_count[$date_t]['teamRegValidNewBetPeople_total'],
                    "lastMon" => $promoter_yes_count[$date_y]['teamRegValidNewBetPeople_total'],
                ],*/
               /* [
                    "name" => "注绑会员人数",
                    "currentMon" => $platform_data_record_t2['totalBindPeople'],
                    "lastMon" => $platform_data_record_y2['totalBindPeople'],
                ],
                [
                    "name" => "有效会员人数",
                    "currentMon" => $platform_data_record_t2['totalBetPeople'],
                    "lastMon" => $platform_data_record_y2['totalBetPeople'],
                ],*/
                [
                    "name" => "新增代理",
                    "currentMon" => $promoter_today_count[$date_t]['teamRegPromoterNum_total'],
                    "lastMon" =>  $promoter_yes_count[$date_y]['teamRegPromoterNum_total'],
                ],
                [
                    "name" => "新增有效代理",
                    "currentMon" => $promoter_today_count[$date_t]['teamActiveRegPromoterNum_total'],
                    "lastMon" => $promoter_yes_count[$date_y]['teamActiveRegPromoterNum_total'],
                ],

                [
                    "name" => "会员充值",
                    "currentMon" => !empty($platformCoinRecordTo['todayRechargeAmount']) ? $platformCoinRecordTo['todayRechargeAmount'] : 0,
                    "lastMon" => !empty($platformCoinRecordYe['todayRechargeAmount']) ? $platformCoinRecordYe['todayRechargeAmount'] :0,
                ],

                [
                    "name" => "会员提现",
                    "currentMon" => !empty($platformCoinRecordTo['todayExchangeAmount']) ? $platformCoinRecordTo['todayExchangeAmount']:0,
                    "lastMon" => !empty($platformCoinRecordYe['todayExchangeAmount']) ? $platformCoinRecordYe['todayExchangeAmount']:0,
                ],

                [
                    "name" => "官方游戏输赢",
                    "currentMon" => $this->formatMoneyFromMongoNo($promoter_today_count[$date_t]['teamGameWinScore_total'] + $platformClubRecordTo['platformWinScore']),
                    "lastMon" => $this->formatMoneyFromMongoNo($promoter_yes_count[$date_y]['teamGameWinScore_total'] + $platformClubRecordYe['platformWinScore']),
                ],
                [
                    "name" => "用户下注流水",
                    "currentMon" => $this->formatMoneyFromMongoNo($platformCoinRecordTo['todayAllBetScore'] + $platformClubRecordTo['allBet']),
                    "lastMon" => $this->formatMoneyFromMongoNo($platformCoinRecordYe['todayAllBetScore'] + $platformClubRecordYe['allBet']),
                ],

                //[
//                    "name" => "代理提成",
//                    "today" => $this->formatMoneyFromMongoNo($promoter_today_count[$date_t]['teamProfit_total'] + $this->formatMoneyFromMongoNo($promoter_today_club[$date_t]['myProfit_total'] + $promoter_today_club[$date_t]['teamProfit_total'])),
//                    "yesterday" => $this->formatMoneyFromMongoNo($promoter_yes_count[$date_y]['teamProfit_total'] + $this->formatMoneyFromMongoNo($promoter_yes_club[$date_y]['myProfit_total'] + $promoter_yes_club[$date_y]['teamProfit_total'])),
//                    "todayGrand" => $this->formatMoneyFromMongoNo($promoter_today_count[$date_t]['totalTeamProfit_total'] + $this->formatMoneyFromMongoNo($promoter_today_club[$date_t]['totalMyProfit_total'] + $promoter_today_club[$date_t]['totalTeamProfit_total'])),
//                    "yesterdayGrand" => $this->formatMoneyFromMongoNo($promoter_yes_count[$date_y]['totalTeamProfit_total'] + $this->formatMoneyFromMongoNo($promoter_yes_club[$date_y]['totalMyProfit_total'] + $promoter_yes_club[$date_y]['totalTeamProfit_total'])),
                //],
//                [
//                    "name" => "系统税收",
//                    "today" => $this->formatMoneyFromMongoNo($promoter_today_count[$date_t]['teamRevenue_total'] + $platformClubRecordTo[$date_t]['revenue']),
//                    "yesterday" => $this->formatMoneyFromMongoNo($promoter_yes_count[$date_y]['teamRevenue_total'] + $platformClubRecordYe[$date_y]['revenue']),
//                    "todayGrand" => $this->formatMoneyFromMongoNo($promoter_today_count[$date_t]['totalTeamRevenue_total'] + $platformClubRecordTo[$date_t]['totalRevenue']),
//                    "yesterdayGrand" => $this->formatMoneyFromMongoNo($promoter_yes_count[$date_y]['totalTeamRevenue_total'] + $platformClubRecordYe[$date_y]['totalRevenue']),
//                ],

                [
                    "name" => "代理提成",
                    "currentMon" => $this->formatMoneyFromMongoNo($promoter_today_count[$date_t]['teamProfit_total'] + $this->formatMoneyFromMongoNo($promoter_today_club[$date_t]['myProfit_total'] + $promoter_today_club[$date_t]['teamProfit_total'])),
                    "lastMon" => $this->formatMoneyFromMongoNo($promoter_yes_count[$date_y]['teamProfit_total'] + $this->formatMoneyFromMongoNo($promoter_yes_club[$date_y]['myProfit_total'] + $promoter_yes_club[$date_y]['teamProfit_total'])),
                ],
                [
                    "name" => "总税收",
                    "currentMon" => $sumTaxProTo,
                    "lastMon" => $sumTaxProYe,
                ],
                [
                    "name" => "官方盈亏",
                    "currentMon" => $TotalofficialWinLoseTo,
                    "lastMon" => $TotalofficialWinLoseYe,
                ],
                [
                    "name" => "会员总充值次数",
                    "currentMon" => $promoter_today_count[$date_t]['teamRechargeyNum_total'],
                    "lastMon" => $promoter_yes_count[$date_y]['teamRechargeyNum_total'],
                ],
                [
                    "name" => "会员总提现次数",
                    "currentMon" => $promoter_today_count[$date_t]['teamExchangeNum_total'],
                    "lastMon" => $promoter_yes_count[$date_y]['teamExchangeNum_total'],
                ],
                [
                    "name" => "用户余额",
                    "currentMon" =>$player_total_amount_t,
                    "lastMon" => $player_total_amount_y,
                ],
            ];
            return json(['code' => 0, 'msg' => 'ok', 'count' => 0,'data' => $data]);
        }
    }

    public function platOverView33(Request $request)
    {
        if ($request->isAjax()) {
            $begin_time_t=mktime(0,0,0,date('m'),1,date('Y'));
            $begin_time_y=mktime(23,59,59,date('m'),date('t'),date('Y'));

            $date_t_m_s = date("Y-m-d",$begin_time_t);//本月开始日期
            $date_t_m_e = date("Y-m-d",$begin_time_y);//本月结束日期


            $begin_time_y = strtotime(date('Y-m-01 00:00:00',strtotime('-1 month')));
            $end_time_y = strtotime(date("Y-m-d 23:59:59", strtotime(-date('d').'day')));

            $date_y_m_s = date("Y-m-d",$begin_time_y);//上月开始日期
            $date_y_m_e = date("Y-m-d",$end_time_y);//上月结束日期
            //俱乐部平台本月上月游戏输赢
            $platformClubRecordTo = $this->getPlatFormClubSum($date_t_m_s,$date_t_m_e);
            $platformClubRecordYe = $this->getPlatFormClubSum($date_y_m_s,$date_y_m_e);

            //金币场日报数据
            $promoter_today_count = $this->get_promoter_daily2($date_t_m_s,$date_t_m_e);
            $promoter_yes_count = $this->get_promoter_daily2($date_y_m_s,$date_y_m_e);

            $date_m_t = date("Y-m-d");

            //以前的写法
            $platform_data_record_t = $this->get_yes_data($date_m_t);
            $platform_data_record_y = $this->get_yes_data($date_y_m_e);

            //新写法
            $date_t = date("Y-m-d");//今天
            $end_time_y = strtotime(date("Y-m-d 23:59:59", strtotime(-date('d').'day')));
            $date_y = date("Y-m-d",$end_time_y);//上月结束日期
            $platform_data_record_t2 = $this->get_yes_data($date_t);
            $platform_data_record_y2 = $this->get_yes_data($date_y);
            $platform_data_record_t2 = array_shift($platform_data_record_t2);
            $platform_data_record_y2 = array_shift($platform_data_record_y2);


            $platformCoinRecordTo = $this->getPlatFormCoinSum($date_t_m_s,$date_t_m_e);
            $platformCoinRecordYe = $this->getPlatFormCoinSum($date_y_m_s,$date_y_m_e);

            $promoter_today_club = $this->getPromoterDailyClub($date_t_m_s,$date_t_m_e);
            $promoter_yes_club = $this->getPromoterDailyClub($date_y_m_s,$date_y_m_e);


            $date_t = $date_t_m_s;
            $date_y = $date_y_m_s;

            $promoterThisMonthData = StatPromoterDaily::statPromoterData($date_t_m_s,$date_t_m_e);
            $promoterLastMonthData = StatPromoterDaily::statPromoterData($date_y_m_s,$date_y_m_e);
            $platformThisMonthData = CoinPlatformDataRecord::statPlatformData($date_t_m_s,$date_t_m_e);
            $platformLastMonthData = CoinPlatformDataRecord::statPlatformData($date_y_m_s,$date_y_m_e);

            $clubPromoterThisMonthData = ClubStatPromoterDaily::statPromoterData($date_t_m_s,$date_t_m_e);
            $clubPromoterLastMonthData = ClubStatPromoterDaily::statPromoterData($date_y_m_s,$date_y_m_e);
            $clubPlatformThisMonthData = ClubPlatformDataRecord::statPlatformData($date_t_m_s,$date_t_m_e);
            $clubPlatformLastMonthData = ClubPlatformDataRecord::statPlatformData($date_y_m_s,$date_y_m_e);

            $data = [
                [
                    "name" => "注册会员人数",
                    "currentMon" => $promoterThisMonthData['teamRegPeople']??0,
                    "lastMon" => $promoterLastMonthData['teamRegPeople']??0,
                ],
                [
                    "name" => "新增代理",
                    "currentMon" => $promoterThisMonthData['teamRegPromoterNum']??0,
                    "lastMon" =>  $promoterLastMonthData['teamRegPromoterNum']??0,
                ],
                [
                    "name" => "新增有效代理",
                    "currentMon" => $promoterThisMonthData['teamActiveRegPromoterNum']??0,
                    "lastMon" => $promoterLastMonthData['teamActiveRegPromoterNum']??0,
                ],
                [
                    "name" => "会员充值",
                    "currentMon" => $platformThisMonthData['todayRechargeAmount']??0,
                    "lastMon" => $platformLastMonthData['todayRechargeAmount']??0,
                ],
                [
                    "name" => "会员提现",
                    "currentMon" => $platformThisMonthData['todayExchangeAmount']??0,
                    "lastMon" => $platformLastMonthData['todayExchangeAmount']??0,
                ],
                [
                    "name" => "会员总充值次数",
                    "currentMon" => $promoterThisMonthData['teamRechargeNum']??0,
                    "lastMon" => $promoterLastMonthData['teamRechargeNum']??0,
                ],
                [
                    "name" => "会员总提现次数",
                    "currentMon" => $promoterThisMonthData['teamExchangeNum']??0,
                    "lastMon" => $promoterLastMonthData['teamExchangeNum']??0,
                ],
                [
                    "name" => "官方游戏输赢",
                    "currentMon" => dataSummary($promoterThisMonthData, $clubPromoterThisMonthData, $platformThisMonthData, $clubPlatformThisMonthData, 'gameWinScore'),
                    "lastMon" => dataSummary($promoterLastMonthData, $clubPromoterLastMonthData, $platformLastMonthData, $clubPlatformLastMonthData, 'gameWinScore'),
                ],
                [
                    "name" => "用户下注流水",
                    "currentMon" => dataSummary($promoterThisMonthData, $clubPromoterThisMonthData, $platformThisMonthData, $clubPlatformThisMonthData, 'allBet'),
                    "lastMon" => dataSummary($promoterLastMonthData, $clubPromoterLastMonthData, $platformLastMonthData, $clubPlatformLastMonthData, 'allBet'),
                ],
                [
                    "name" => "代理提成",
                    "currentMon" => dataSummary($promoterThisMonthData, $clubPromoterThisMonthData, $platformThisMonthData, $clubPlatformThisMonthData, 'promoterScore'),
                    "lastMon" => dataSummary($promoterLastMonthData, $clubPromoterLastMonthData, $platformLastMonthData, $clubPlatformLastMonthData, 'promoterScore'),
                ],
                [
                    "name" => "总税收",
                    "currentMon" => dataSummary($promoterThisMonthData, $clubPromoterThisMonthData, $platformThisMonthData, $clubPlatformThisMonthData, 'revenue'),
                    "lastMon" => dataSummary($promoterLastMonthData, $clubPromoterLastMonthData, $platformLastMonthData, $clubPlatformLastMonthData, 'revenue'),
                ],
                [
                    "name" => "官方盈亏",
                    "currentMon" => dataSummary($promoterThisMonthData, $clubPromoterThisMonthData, $platformThisMonthData, $clubPlatformThisMonthData, 'profitOrLoss'),
                    "lastMon" => dataSummary($promoterLastMonthData, $clubPromoterLastMonthData, $platformLastMonthData, $clubPlatformLastMonthData, 'profitOrLoss'),
                ],
                /*[
                    "name" => "用户余额",
                    "currentMon" => dataSummary($promoterThisMonthData, $clubPromoterThisMonthData, $platformThisMonthData, $clubPlatformThisMonthData, 'playerTotalAmountGrand'),
                    "lastMon" => dataSummary($promoterLastMonthData, $clubPromoterLastMonthData, $platformLastMonthData, $clubPlatformLastMonthData, 'playerTotalAmountGrand'),
                ],*/
            ];
            return json(['code' => 0, 'msg' => 'ok', 'count' => 0,'data' => $data]);
        }
    }

    public function userScalePart(Request $request)
    {
        if ($request->isAjax()) {
            $postData = $request->post();
            extract($postData);
            $new_data = [];
            $dateValue = !empty($dateRange) ? trim($dateRange) : false;
            $userType = !empty($type) ? (int)$type : 3;
            if ($dateValue) {
                $dateValue = explode('~', $dateValue);
                $date_start = $dateValue[0] ?? date("Y-m-d");//"2020-12-28";//date("Y-m-d");
                $date_end = $dateValue[1] ?? date("Y-m-d");//"2020-12-27";//date("Y-m-d", strtotime('-1 day'));
            }else{
                //默认最近一周
                $date_start = date("Y-m-d", strtotime('-7 day'));
                $date_end = date("Y-m-d");
            }

            //日期列表
            $date_range = $this->getDateFromRange($date_start,$date_end);
            $title = $series_name = "新增用户";
            //平台代理统计(系统税收,系统代理佣金,系统提现,系统充值,游戏盈亏,今天总注册人数,今天总活跃数,今天绑定人数,团队人数)
            //$promoter_daily = $this->get_promoter_daily($date_start,$date_end);
            //金币场 1000代理统计一天一条数据
            $promoter_daily = StatPromoterDaily::getDataByDate($date_start, $date_end);
            $platformDaily = CoinPlatformDataRecord::getDataByDate($date_start, $date_end);
            //新增用户
            if($userType == 3){
                $title = $series_name = "新增用户";
                foreach ($date_range as $item_date){
                    $new_data[$item_date] = !empty($promoter_daily[$item_date]['teamRegPeople']) ? $promoter_daily[$item_date]['teamRegPeople'] : 0;
                }
            }

            //有效新增
            if($userType == 4){
                $title = $series_name = "有效新增";
                foreach ($date_range as $item_date){
                    $new_data[$item_date] = !empty($promoter_daily[$item_date]['teamRegValidNewBetPeople']) ? $promoter_daily[$item_date]['teamRegValidNewBetPeople'] : 0;
                }
            }
            //注册用户
            if($userType == 5){
                $title = $series_name = "注册用户";
                foreach ($date_range as $item_date){
                    $new_data[$item_date] = !empty($promoter_daily[$item_date]['teamRegBindPeople']) ? $promoter_daily[$item_date]['teamRegBindPeople'] : 0;
                }
            }
            //活跃用户
            if($userType == 6){
                $title = $series_name = "活跃用户";
                foreach ($date_range as $item_date){
                    $new_data[$item_date] = !empty($promoter_daily[$item_date]['teamActivePeople']) ? $promoter_daily[$item_date]['teamActivePeople'] : 0;
                }
            }
            //游戏人数
            if($userType == 7){
                $title = $series_name = "游戏人数";
                /*$game_user = $this->get_game_users($date_start,$date_end);
                foreach ($date_range as $item_date){
                    $new_data[$item_date] = !empty($game_user[$item_date]) ? $game_user[$item_date] : 0;
                }*/
                foreach ($date_range as $item_date){
                    $new_data[$item_date] = !empty($platformDaily[$item_date]['todayGoldClubBetPeople']) ? $platformDaily[$item_date]['todayGoldClubBetPeople'] : 0;
                }
            }
            //新增次日留存
            if($userType == 19){
                $title = $series_name = "新增次日留存(百分比%)";
                foreach ($date_range as $item_date){
                    $item_date_y = date("Y-m-d", strtotime('-1 day',strtotime($item_date)));
                    $keep_user_t = $this->keep_next_day($item_date);//获取昨天新增用户中今天登录的数量
                    $new_users_data_y = $this->get_new_users($item_date_y);//获取昨天新增用户数量
                    $result_data = $this->next_day_retention($keep_user_t,$new_users_data_y);
                    $new_data[$item_date] = (float)sprintf("%01.2f", $result_data['vals_t']*100);//保留两位四舍五入
                }
            }
            //累计用户
            if($userType == 8){
                $title = $series_name = "累计用户";
                foreach ($date_range as $item_date){
                    $new_data[$item_date] = !empty($promoter_daily[$item_date]['totalTeamPlayerCount']) ? $promoter_daily[$item_date]['totalTeamPlayerCount'] : 0;
                }
            }

            $categories = array_keys($new_data);
            $series_data = array_values($new_data);

            $data['categories'] = $categories;
            $data['series_data'] = $series_data;
            $data['series_name'] = $series_name;
            $data['title'] = $title;
            return json(['code' => 0, 'msg' => 'ok', 'data' => $data]);
        }
    }

    public function revenueScalePart(Request $request)
    {
        if ($request->isAjax()) {
            $postData = $request->post();
            extract($postData);
            $new_data = [];
            $categories = [];
            $series_data = [];
            $dateValue = !empty($dateRange) ? trim($dateRange) : false;
            $userType = !empty($type) ? (int)$type : 9;

            if ($dateValue) {
                $dateValue = explode('~', $dateValue);

                $date_start = $dateValue[0] ?? date("Y-m-d");
                $date_end = $dateValue[1] ?? date("Y-m-d");
            }else{
                //默认最近一周
                $date_start = date("Y-m-d", strtotime('-7 day'));
                $date_end = date("Y-m-d");
            }

            //日期列表
            $date_range = $this->getDateFromRange($date_start,$date_end);
            $title = $series_name = "付费用户";

            //平台(付费用户,首充用户,首充金额,多次充值)统计(stat_recharge_home综合表)
            if($userType == 9 || $userType == 10 || $userType == 11 || $userType == 12){
                $recharge_home = $this->get_recharge_home($date_start,$date_end);
            }else{
                //平台代理统计(系统税收,系统代理佣金,系统提现,系统充值,游戏盈亏)
                $promoter_daily = $this->get_promoter_daily($date_start,$date_end);
                $platformDaily = CoinPlatformDataRecord::getDataByDate($date_start, $date_end);
            }
            //付费用户
            if($userType == 9){
                $title = $series_name = "付费用户";
                foreach ($date_range as $item_date){
                    $new_data[$item_date] = !empty($recharge_home[$item_date]['pay_users']) ? $recharge_home[$item_date]['pay_users'] : 0;
                }
            }

            //首充用户
            if($userType == 10){
                $title = $series_name = "首充用户";
                foreach ($date_range as $item_date){
                    $new_data[$item_date] = !empty($recharge_home[$item_date]['first_charge_user']) ? $recharge_home[$item_date]['first_charge_user'] : 0;
                }
            }

            //首充金额
            if($userType == 11){
                $title = $series_name = "首充金额";
                foreach ($date_range as $item_date){
                    $new_data[$item_date] = !empty($recharge_home[$item_date]['first_charge_amount']) ? $recharge_home[$item_date]['first_charge_amount'] : 0;
                }
            }


            //二次充值比例
            if($userType == 12){
                $title = $series_name = "二次充值比例(百分比%)";
                foreach ($date_range as $item_date){
                    $new_data[$item_date]['pay_users'] = !empty($recharge_home[$item_date]['pay_users']) ? $recharge_home[$item_date]['pay_users'] : 0;
                }
                foreach ($date_range as $item_date){
                    if(isset($recharge_home[$item_date]['second_charge_user']) && $recharge_home[$item_date]['second_charge_user']){
                        if($new_data[$item_date]['pay_users']){
                            $second_pay_per = $recharge_home[$item_date]['second_charge_user']/$new_data[$item_date]['pay_users'];
                            $new_data[$item_date] = (float)sprintf("%01.2f", $second_pay_per*100);
                        }else{
                            $new_data[$item_date] = 0;
                        }
                    }else{
                        $new_data[$item_date] = 0;
                    }
                }
            }


            //系统税收
            if($userType == 13){
                $title = $series_name = "系统税收";
                foreach ($date_range as $item_date){
                    $new_data[$item_date] = !empty($promoter_daily[$item_date]['teamRevenue']) ? $promoter_daily[$item_date]['teamRevenue'] : 0;
                }
            }

            //游戏输赢
            if($userType == 14){
                $title = $series_name = "游戏输赢";
                foreach ($date_range as $item_date){
                    $new_data[$item_date] = !empty($promoter_daily[$item_date]['teamGameWinScore']) ? $promoter_daily[$item_date]['teamGameWinScore'] : 0;
                }
            }

            //代理佣金
            if($userType == 15){
                $title = $series_name = "代理佣金";
                /*$agency_commission_arr = $this->get_agency_commission($date_start,$date_end);
                foreach ($date_range as $item_date){
                    $new_data[$item_date] = !empty($agency_commission_arr[$item_date]['teamProfit']) ? $agency_commission_arr[$item_date]['teamProfit'] : 0;
                }*/
                foreach ($date_range as $item_date){
                    $new_data[$item_date] = !empty($platformDaily[$item_date]['todayPromoterScore']) ? $platformDaily[$item_date]['todayPromoterScore'] : 0;
                }
            }
            //金币场官方盈亏
            if($userType == 16){
                $title = $series_name = "官方盈亏";
                foreach ($date_range as $item_date){
                    if (isset($promoter_daily[$item_date]) && isset($platformDaily[$item_date])) {
                        $new_data[$item_date] = profitOrLoss($promoter_daily[$item_date], $platformDaily[$item_date]);
                    } else {
                        $new_data[$item_date] = 0;
                    }

                }
            }

            //提现
            if($userType == 17){
                $title = $series_name = "提现";
                foreach ($date_range as $item_date){
                    $new_data[$item_date] = !empty($platformDaily[$item_date]['todayExchangeAmount']) ? $platformDaily[$item_date]['todayExchangeAmount'] : 0;
                }
            }


            //充值
            if($userType == 18){
                $title = $series_name = "充值";
                foreach ($date_range as $item_date){
                    $new_data[$item_date] = !empty($platformDaily[$item_date]['todayRechargeAmount']) ? $platformDaily[$item_date]['todayRechargeAmount'] : 0;
                }
            }

            $categories = array_keys($new_data);
            $series_data = array_values($new_data);

            $data['categories'] = $categories;
            $data['series_data'] = $series_data;
            $data['series_name'] = $series_name;
            $data['title'] = $title;
            return json(['code' => 0, 'msg' => 'ok', 'data' => $data]);
        }
    }

    function getDateFromRange($startdate, $enddate){
        $stimestamp = strtotime($startdate);
        $etimestamp = strtotime($enddate);
        $days = ($etimestamp-$stimestamp)/86400+1;
        $date = [];
        for($i=0; $i<$days; $i++){
            $date[] = date('Y-m-d', $stimestamp+(86400*$i));
        }
        return $date;
    }

//平台代理统计(系统税收,系统代理佣金,系统提现,系统充值,游戏盈亏)
    function get_promoter_daily($startDate,$endDate)
    {
        $startTime = $startDate ?? date("Y-m-d");
        $endTime = $endDate ?? date("Y-m-d");
        $startTime = strtotime(trim($startTime));
        $endTime = strtotime(trim($endTime)) + 86400;
        $startTimeMongo = $this->formatTimestampToMongo($startTime);
        $endTimeMongo = $this->formatTimestampToMongo($endTime);


        $where = [];
        $where['promoterId'] = 1000;
        $where['date'] = ['$gte' => $startTimeMongo, '$lt' => $endTimeMongo];

        $data_array = Db::connection('mongodb_main')->collection('stat_promoter_daily')->raw()->aggregate([
            [
                '$match' => $where
            ],
            [
                '$project' =>
                    [
                        '_id' => 0,
                        //'Date' => ['$substr' => [ '$date', 0, 10]],
                        'Date' => ['$substr' => [['$add' => ['$date',28800000]], 0, 10]],
                        'teamRevenue' => 1,
                        'teamProfit' => 1,
                        'teamExchangeAmount' =>1,
                        'teamRechargeAmount'=>1,
                        'teamWinScore'=>1,
                        'teamGameWinScore'=>1,
                        'teamRegPeople'=>1,
                        //'teamRegBindPeople'=>1,
                        'teamActivePeople'=>1,
                        'totalTeamPlayerCount'=>1,
                        'regActivePeople'=>1,
                        'teamExchangePeople'=>1,//团队今天总兑换人数
                        'teamExchangeNum'=>1,//团队今天总兑换笔数
                        'teamRegBindPeople'=>1,//团队今天注册并绑定人数
                        'teamRegValidNewBetPeople'=>1,//团队今天新增有下注会员
                        'teamRegPromoterNum'=>1,//团队今天注册代理数
                        'teamActiveRegPromoterNum'=>1,//团队今天注册有效代理数
                        'teamRewardAmount'//奖励支出
                    ],
            ],
            [
                '$sort' => ['Date'=>1]
            ]
        ])->toArray();


        $result_array = [];
        foreach ($data_array as $item){
            $item['teamGameWinScore'] = $this->formatMoneyFromMongo($item['teamGameWinScore']);
            $item['teamRechargeAmount'] = $this->formatMoneyFromMongo($item['teamRechargeAmount']);
            $item['teamRevenue'] = $this->formatMoneyFromMongo($item['teamRevenue']);
            $item['teamWinScore'] = $this->formatMoneyFromMongo($item['teamWinScore']);
            $item['teamExchangeAmount'] = $this->formatMoneyFromMongo($item['teamExchangeAmount']);
            $result_array[$item['Date']] = $item;//$item[$field];
        }
        return $result_array;
    }
    function get_format_result($data_t,$data_y,$num=0){
        $data['vals_t'] = 0;
        $data['vals_y'] = 0;
        $data['vals_r'] = 0;
        if($num == 1){
            $data['vals_t'] = $data_t;
            $data['vals_y'] = $data_y;
        }else{
            if($data_t){
                foreach ($data_t as $item){
                    $data['vals_t'] =$item;
                }
            }
            if($data_y){
                foreach ($data_y as $item){
                    $data['vals_y'] =$item;
                }
            }
        }
        $data['vals_r'] = $this->get_chain_ration($data['vals_t'],$data['vals_y']);
        return $data;
    }

    function get_game_users($startDate,$endDate)
    {
        $startTime = $startDate ?? date("Y-m-d");
        $endTime = $endDate ?? date("Y-m-d");
        $startTime = strtotime(trim($startTime));
        $endTime = strtotime(trim($endTime)) + 86400;
        $startTimeMongo = $this->formatTimestampToMongo($startTime);
        $endTimeMongo = $this->formatTimestampToMongo($endTime);

        $where = [];
        $where['userId'] = ['$gte' => GameUser::COMMON_ACCOUNT_START_ID];
        $where['createTime'] = ['$gte' => $startTimeMongo, '$lt' => $endTimeMongo];

        $data_array = Db::connection('mongodb_main')->collection('game_log')->raw()->aggregate([
            [
                '$match' => $where
            ],
            [
                '$project' =>
                    [
                        'Date' => ['$substr' => [['$add' => ['$createTime',28800000]], 0, 10]],
                        'userId' => 1,
                    ],
            ],
            [
                '$group' => [
                    '_id'=>[
                        'Date'=>'$Date',
                        'userId'=>'$userId'
                    ],
                    'Date'=> ['$first'=>'$Date']
                ]
            ],
            [
                '$sort' => ['Date'=>1]
            ]
        ])->toArray();
        $result_array = $this->get_format_pub($data_array);
        return $result_array;
    }


    //新增次日留存(昨天新增用户中,今天登录的数量)
    function keep_next_day($date)
    {
        //当天登录的时间段
        $startTimeTodFmt = $date ?? date("Y-m-d");
        $startTimeTod = strtotime(trim($startTimeTodFmt));
        $endTimeTod = strtotime(trim($startTimeTodFmt)) + 86400;
        $startTimeTodMongo = $this->formatTimestampToMongo($startTimeTod);
        $endTimeTodMongo = $this->formatTimestampToMongo($endTimeTod);
        //昨天新增用户时间段
        $startTimeYesFmt = date("Y-m-d", strtotime('-1 day',strtotime($startTimeTodFmt)));
        $startTimeYes = strtotime(trim($startTimeYesFmt));
        $endTimeYes = strtotime(trim($startTimeYesFmt)) + 86400;
        $startTimeYesMongo = $this->formatTimestampToMongo($startTimeYes);
        $endTimeYesMongo = $this->formatTimestampToMongo($endTimeYes);
        //条件
        $where = [];
        $where['userId'] = ['$gte' => GameUser::COMMON_ACCOUNT_START_ID];
        $where['regInfo.time'] = ['$gte' => $startTimeYesMongo, '$lt' => $endTimeYesMongo];
        $where['lastLogin.time'] = ['$gte' => $startTimeTodMongo, '$lt' => $endTimeTodMongo];
        $data_array = Db::connection('mongodb_main')->collection('game_user')->raw()->aggregate([
                [
                    '$match' => $where
                ],
                [
                    '$project' =>
                        [
                            'userId' => 1
                        ],
                ],
                [
                    '$group' => [
                        '_id'=>null,
                        'count' => ['$sum' => 1],
                    ]
                ]
            ])->toArray();
        return $data_array[0]['count'] ?? 0;
    }

    //获取新增用户
    function get_new_users($startDate)
    {
        $startTime = $startDate ?? date("Y-m-d");
        $endTime = $startTime;
        $startTime = strtotime(trim($startTime));
        $endTime = strtotime(trim($endTime)) + 86400;


        $startTimeMongo = $this->formatTimestampToMongo($startTime);
        $endTimeMongo = $this->formatTimestampToMongo($endTime);

        $where = [];
        $where['userId'] = ['$gte' => GameUser::COMMON_ACCOUNT_START_ID];
        $where['regInfo.time'] = ['$gte' => $startTimeMongo, '$lt' => $endTimeMongo];

        $data_array = Db::connection('mongodb_main')->collection('game_user')->raw()->aggregate([
                [
                    '$match' => $where
                ],
                [
                    '$project' =>
                        [
                            'userId' => 1,
                        ],
                ],
                [
                    '$group' => [
                        '_id'=>null,
                        'count' => ['$sum' => 1],
                    ]
                ]
            ])->toArray();
        return $data_array[0]['count'] ?? 0;
    }

    public function get_on_line()
    {
        $startTimeFmt = date("Y-m-d");
        $startTime = strtotime(trim($startTimeFmt));
        $endTime = strtotime(trim($startTimeFmt)) + 86400;

        $startTimeMongo = $this->formatTimestampToMongo($startTime);
        $endTimeMongo = $this->formatTimestampToMongo($endTime);

        $where = [];
        $where['statTime'] = ['$gte' => $startTimeMongo, '$lt' => $endTimeMongo];
        $where['roomId'] = 999;

        //金币场
        $dataArrayCoin = Db::connection('mongodb_main')->collection('stat_room_online')->raw()->aggregate([
            [
                '$match' => $where
            ],
            [
                '$project' =>
                    [
                        '_id' => 0,
                        'statValue' => 1,
                        'iosValue' => 1,
                        'androidValue' => 1,
                        'simulatorValue' => 1,
                    ],
            ]
        ])->toArray();
        //俱乐部
        $dataArrayClub = Db::connection('mongodb_club')->collection('stat_room_online')->raw()->aggregate([
            [
                '$match' => $where
            ],
            [
                '$project' =>
                    [
                        '_id' => 0,
                        'statValue' => 1,
                        'iosValue' => 1,
                        'androidValue' => 1,
                        'simulatorValue' => 1,
                    ],
            ]
        ])->toArray();
        $numberCoin = $dataArrayCoin[0]['statValue'] ?? 0;
        $numberClub = $dataArrayClub[0]['statValue'] ?? 0;
        $number = $numberCoin + $numberClub;
        return $number;
    }

    //平台(付费用户,首充用户,首充金额,多次充值)统计(stat_recharge_home综合表)
    public function get_recharge_home($startDate,$endDate)
    {
        $startTime = $startDate ?? date("Y-m-d");
        $endTime = $endDate ?? date("Y-m-d");
        $startTime = strtotime(trim($startTime));
        $endTime = strtotime(trim($endTime)) + 86400;

        $startTimeMongo = $this->formatTimestampToMongo($startTime);
        $endTimeMongo = $this->formatTimestampToMongo($endTime);

        $where = [];

        $where['createDate'] = ['$gte' => $startTimeMongo, '$lt' => $endTimeMongo];
        $where['rechargeTypeId'] = ['$in' => [-3,-4,-5]];

        $data_array = Db::connection('mongodb_main')->collection('stat_recharge_home')->raw()->aggregate([
            [
                '$match' => $where
            ],
            [
                '$project' =>
                    [
                        '_id' => 0,
                        'Date' => ['$substr' => [['$add' => ['$createDate',28800000]], 0, 10]],
                        'rechargeTypeId' => 1,
                        'rechargePeople' => 1,
                        'rechargeAmount' => 1
                    ],
            ],
            [
                '$sort' => ['Date'=>1]
            ]
        ])->toArray();

        $result_array = [];
        foreach ($data_array as $item){
            if($item['rechargeTypeId'] == -3){
                $result_array[$item['Date']]['pay_users'] = $item['rechargePeople'] ?? 0;//系统总付费用户
            }
            if($item['rechargeTypeId'] == -5){
                $result_array[$item['Date']]['first_charge_user'] = $item['rechargePeople'] ?? 0;//首充用户
                $result_array[$item['Date']]['first_charge_amount'] = $this->formatMoneyFromMongo($item['rechargeAmount']) ?? 0;//首充金额
            }
            if($item['rechargeTypeId'] == -4){
                $result_array[$item['Date']]['second_charge_user'] = $item['rechargePeople'] ?? 0; //二次充值人数
            }
        }
        return $result_array;
    }

    function get_agency_commission($startDate,$endDate)
    {
        $startTime = $startDate ?? date("Y-m-d");
        $endTime = $endDate ?? date("Y-m-d");
        $startTime = strtotime(trim($startTime));
        $endTime = strtotime(trim($endTime)) + 86400;

        $startTimeMongo = $this->formatTimestampToMongo($startTime);
        $endTimeMongo = $this->formatTimestampToMongo($endTime);

        $where = [];
        $where['promoterId'] = ['$ne' => 1000];
        $where['date'] = ['$gte' => $startTimeMongo, '$lt' => $endTimeMongo];

        $data_array = Db::connection('mongodb_main')->collection('stat_promoter_daily')->raw()->aggregate([
            [
                '$match' => $where
            ],
            [
                '$project' =>
                    [
                        'Date' => ['$substr' => [['$add' => ['$date',28800000]], 0, 10]],
                        'teamProfit' => 1,
                    ],
            ],
            [
                '$group' => [
                    '_id'=>[
                        'Date'=>'$Date',
                    ],
                    'teamProfit' => ['$sum' => '$teamProfit'],
                    'Date'=> ['$first'=>'$Date']
                ]
            ],
            [
                '$sort' => ['Date'=>-1]
            ]
        ])->toArray();

        $result_array = [];

        foreach ($data_array as $key => $item){
            $result_array[$item['Date']]['teamProfit'] = $this->formatMoneyFromMongo($item['teamProfit']) ?? 0;
        }
        return $result_array;
    }

    public function percent_format_result($keep_user_t,$keep_user_y,$new_users_data_y,$new_users_data_b){
        $data['vals_t'] = 0;
        $data['vals_y'] = 0;
        $data['vals_r'] = 0;

        if($new_users_data_y == 0){
            $data['vals_t'] = 0;
        }else{
            $data['vals_t'] = $keep_user_t/$new_users_data_y;

        }

        if($new_users_data_b == 0){
            $data['vals_y'] = 0;
        }else{
            $data['vals_y'] = $keep_user_y/$new_users_data_b;
        }
        $data['vals_r'] = $this->get_chain_ration($data['vals_t'],$data['vals_y']);
        return $data;
    }

    public function get_format_pub($data_array){
        $result_array = [];
        if ($data_array && sizeof($data_array) > 0){
            foreach ($data_array as $key =>$item){
                if($result_array){
                    foreach ($result_array as $k=>$v){
                        if($item['Date'] == $k){
                            $result_array[$k] = $v + 1;
                        }else{
                            $result_array[$item['Date']] =1;
                        }
                    }
                }else{
                    $result_array[$item['Date']] =1;
                }
            }
        }
        return $result_array;
    }

    public function get_chain_ration($vals1_t,$vals1_y){
        $vals1_t = $vals1_t ?? 0;
        $vals1_y = $vals1_y ?? 0;
        if($vals1_y == 0){
            $vals1_r =($vals1_t - $vals1_y);
        }else{
            $vals1_r =($vals1_t - $vals1_y)/abs($vals1_y);
        }
        $vals1_r = sprintf("%01.2f", $vals1_r*100).'%';
        if($vals1_r > 0){
            $vals1_r = "<span style='color:red'>(环比↑$vals1_r)</span>";
        }elseif($vals1_r == 0){
            $vals1_r = "<span style='color:green'>(环比$vals1_r)</span>";
        }else{
            $vals1_r = "<span style='color:green'>(环比↓$vals1_r)</span>";
        }
        return $vals1_r;
    }

    public function get_promoter_daily2($startDate,$endDate)
    {
        $startTime = $startDate ?? date("Y-m-d");
        $endTime = $endDate ?? date("Y-m-d");
        $startTime = strtotime(trim($startTime));
        $endTime = strtotime(trim($endTime))+ 86400;
        $aTime = date('Y-m-d H:i:s',$startTime);
        $bTime = date('Y-m-d H:i:s',$endTime);
        $startTimeMongo = $this->formatTimestampToMongo($startTime);
        $endTimeMongo = $this->formatTimestampToMongo($endTime);

        $where = [];
        $where['date'] = ['$gte' => $startTimeMongo, '$lt' => $endTimeMongo];
        $where['promoterId'] = 1000;

        $data_array = Db::connection('mongodb_main')->collection('stat_promoter_daily')->raw()->aggregate([
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
                        'teamRegBindPeople_total' =>    ['$sum' => '$teamRegBindPeople'],
                        'totalTeamRegBindPeople_total' =>    ['$sum' => '$totalTeamRegBindPeople'],
                        'teamRegPromoterNum_total' =>    ['$sum' => '$teamRegPromoterNum'],
                        'totalTeamRegPromoterNum_total' =>    ['$sum' => '$totalTeamRegPromoterNum'],
                        'teamActiveRegPromoterNum_total' =>    ['$sum' => '$teamActiveRegPromoterNum'],
                        'totalTeamActiveRegPromoterNum_total' =>    ['$sum' => '$totalTeamActiveRegPromoterNum'],
                        'teamRechargeAmount_total' =>    ['$sum' => '$teamRechargeAmount'],
                        'totalTeamRechargeAmount_total' =>    ['$sum' => '$totalTeamRechargeAmount'],
                        'teamExchangeAmount_total' =>    ['$sum' => '$teamExchangeAmount'],
                        'totalTeamExchangeAmount' =>    ['$sum' => '$totalTeamExchangeAmount'],
                        'teamFlowAmount_total' =>    ['$sum' => '$teamFlowAmount'],
                        'teamValidFlowAmount_total' =>    ['$sum' => '$teamValidFlowAmount'],
                        'totalTeamFlowAmount_total' =>    ['$sum' => '$totalTeamFlowAmount'],
                        'totalTeamValidFlowAmount_total' =>    ['$sum' => '$totalTeamValidFlowAmount'],
                        'teamRevenue_total' =>    ['$sum' => '$teamRevenue'],
                        'totalTeamRevenue_total' =>    ['$sum' => '$totalTeamRevenue'],
                        'teamGameWinScore_total' =>    ['$sum' => '$teamGameWinScore'],
                        'totalTeamGameWinScore_total' =>    ['$sum' => '$totalTeamGameWinScore'],
                        'teamProfit_total' =>    ['$sum' => '$teamProfit'],
                        'totalTeamProfit_total' =>    ['$sum' => '$totalTeamProfit'],
                        'teamProfitTotal_total' =>    ['$sum' => '$teamProfitTotal'],//代理总提成
                        'totalTeamProfitTotal_total' =>    ['$sum' => '$totalTeamProfitTotal'],//累计代理代理总提成
                        'teamRechargeyNum_total' =>    ['$sum' => '$teamRechargeyNum'],
                        'totalTeamRechargeNum_total' =>    ['$sum' => '$totalTeamRechargeNum'],
                        'teamExchangeNum_total' =>    ['$sum' => '$teamExchangeNum'],
                        'totalTeamExchangeNum_total' =>    ['$sum' => '$totalTeamExchangeNum'],
                        'teamRegValidNewBetPeople_total' =>    ['$sum' => '$teamRegValidNewBetPeople'],
                        'totalTeamRegValidNewBetPeople_total' =>    ['$sum' => '$totalTeamRegValidNewBetPeople'],
                        'teamRegPeople_total' =>    ['$sum' => '$teamRegPeople'],
                        'totalTeamRegPeople_total' =>    ['$sum' => '$totalTeamRegPeople'],
                        'transferToScoreAmount_total' =>    ['$sum' => '$transferToScoreAmount'],
                        'totalTransferToScoreAmount_total' =>    ['$sum' => '$totalTransferToScoreAmount'],
                        'teamTransferToScoreAmount_total' =>    ['$sum' => '$teamTransferToScoreAmount'],
                        'totalTeamTransferToScoreAmount_total' =>    ['$sum' => '$totalTeamTransferToScoreAmount'],
                    ]
            ]
        ])->toArray();
        $result_array = [];
        if($data_array){
            foreach ($data_array as $item){
                $result_array[$startDate]['teamRegBindPeople_total'] = !empty($item['teamRegBindPeople_total']) ? $item['teamRegBindPeople_total'] : 0;
                $result_array[$startDate]['totalTeamRegBindPeople_total'] = !empty($item['totalTeamRegBindPeople_total']) ? $item['totalTeamRegBindPeople_total'] : 0;

                $result_array[$startDate]['teamRegPromoterNum_total'] = !empty($item['teamRegPromoterNum_total']) ? $item['teamRegPromoterNum_total'] : 0;
                $result_array[$startDate]['totalTeamRegPromoterNum_total'] = !empty($item['totalTeamRegPromoterNum_total']) ? $item['totalTeamRegPromoterNum_total'] : 0;
                $result_array[$startDate]['teamActiveRegPromoterNum_total'] = !empty($item['teamActiveRegPromoterNum_total']) ? $item['teamActiveRegPromoterNum_total'] : 0;
                $result_array[$startDate]['totalTeamActiveRegPromoterNum_total'] = !empty($item['totalTeamActiveRegPromoterNum_total']) ? $item['totalTeamActiveRegPromoterNum_total'] : 0;

                $result_array[$startDate]['teamRechargeAmount_total'] = !empty($item['teamRechargeAmount_total']) ? $item['teamRechargeAmount_total']/100 : 0;
                $result_array[$startDate]['totalTeamRechargeAmount_total'] = !empty($item['totalTeamRechargeAmount_total']) ? $item['totalTeamRechargeAmount_total']/100 : 0;
                $result_array[$startDate]['teamExchangeAmount_total'] = !empty($item['teamExchangeAmount_total']) ? $item['teamExchangeAmount_total']/100 : 0;
                $result_array[$startDate]['totalTeamExchangeAmount_total'] = !empty($item['totalTeamExchangeAmount_total']) ? $item['totalTeamExchangeAmount_total']/100 : 0;
                $result_array[$startDate]['teamFlowAmount_total'] = !empty($item['teamFlowAmount_total']) ? $item['teamFlowAmount_total']/100 : 0;
                $result_array[$startDate]['teamValidFlowAmount_total'] = !empty($item['teamValidFlowAmount_total']) ? $item['teamValidFlowAmount_total']/100 : 0;
                $result_array[$startDate]['totalTeamFlowAmount_total'] = !empty($item['totalTeamFlowAmount_total']) ? $item['totalTeamFlowAmount_total']/100 : 0;
                $result_array[$startDate]['totalTeamValidFlowAmount_total'] = !empty($item['totalTeamValidFlowAmount_total']) ? $item['totalTeamValidFlowAmount_total']/100 : 0;

                $result_array[$startDate]['teamRevenue_total'] = !empty($item['teamRevenue_total']) ? $item['teamRevenue_total']/100 : 0;
                $result_array[$startDate]['totalTeamRevenue_total'] = !empty($item['totalTeamRevenue_total']) ? $item['totalTeamRevenue_total']/100 : 0;
                $result_array[$startDate]['teamGameWinScore_total'] = !empty($item['teamGameWinScore_total']) ? $item['teamGameWinScore_total']/100 : 0;
                $result_array[$startDate]['totalTeamGameWinScore_total'] = !empty($item['totalTeamGameWinScore_total']) ? $item['totalTeamGameWinScore_total']/100 : 0;
                $result_array[$startDate]['teamProfit_total'] = !empty($item['teamProfit_total']) ? $item['teamProfit_total']/100 : 0;
                $result_array[$startDate]['totalTeamProfit_total'] = !empty($item['totalTeamProfit_total']) ? $item['totalTeamProfit_total']/100 : 0;
                $result_array[$startDate]['teamProfitTotal_total'] = !empty($item['teamProfitTotal_total']) ? $item['teamProfitTotal_total']/100 : 0;
                $result_array[$startDate]['totalTeamProfitTotal_total'] = !empty($item['totalTeamProfitTotal_total']) ? $item['totalTeamProfitTotal_total']/100 : 0;
                $result_array[$startDate]['teamRechargeyNum_total'] = !empty($item['teamRechargeyNum_total']) ? $item['teamRechargeyNum_total'] : 0;
                $result_array[$startDate]['totalTeamRechargeNum_total'] = !empty($item['totalTeamRechargeNum_total']) ? $item['totalTeamRechargeNum_total'] : 0;
                $result_array[$startDate]['teamExchangeNum_total'] = !empty($item['teamExchangeNum_total']) ? $item['teamExchangeNum_total'] : 0;
                $result_array[$startDate]['totalTeamExchangeNum_total'] = !empty($item['totalTeamExchangeNum_total']) ? $item['totalTeamExchangeNum_total'] : 0;
                $result_array[$startDate]['teamRegValidNewBetPeople_total'] = !empty($item['teamRegValidNewBetPeople_total']) ? $item['teamRegValidNewBetPeople_total'] : 0;
                $result_array[$startDate]['totalTeamRegValidNewBetPeople_total'] = !empty($item['totalTeamRegValidNewBetPeople_total']) ? $item['totalTeamRegValidNewBetPeople_total'] : 0;
                $result_array[$startDate]['teamRegPeople_total'] = !empty($item['teamRegPeople_total']) ? $item['teamRegPeople_total'] : 0;
                $result_array[$startDate]['totalTeamRegPeople_total'] = !empty($item['totalTeamRegPeople_total']) ? $item['totalTeamRegPeople_total'] : 0;

                $result_array[$startDate]['transferToScoreAmount_total'] = !empty($item['transferToScoreAmount_total']) ? $this->formatMoneyFromMongo($item['transferToScoreAmount_total']) : 0;
                $result_array[$startDate]['totalTransferToScoreAmount_total'] = !empty($item['totalTransferToScoreAmount_total']) ? $this->formatMoneyFromMongo($item['totalTransferToScoreAmount_total']) : 0;

                $result_array[$startDate]['teamTransferToScoreAmount_total'] = !empty($item['teamTransferToScoreAmount_total']) ? $this->formatMoneyFromMongo($item['teamTransferToScoreAmount_total']) : 0;
                $result_array[$startDate]['totalTeamTransferToScoreAmount_total'] = !empty($item['totalTeamTransferToScoreAmount_total']) ? $this->formatMoneyFromMongo($item['totalTeamTransferToScoreAmount_total']) : 0;
            }
        }else{
            $result_array[$startDate]['teamRegBindPeople_total'] = 0;
            $result_array[$startDate]['totalTeamRegBindPeople_total'] = 0;
            $result_array[$startDate]['teamRegPromoterNum_total'] = 0;
            $result_array[$startDate]['totalTeamRegPromoterNum_total'] = 0;
            $result_array[$startDate]['teamActiveRegPromoterNum_total'] = 0;
            $result_array[$startDate]['totalTeamActiveRegPromoterNum_total'] = 0;
            $result_array[$startDate]['teamRechargeAmount_total'] = 0;
            $result_array[$startDate]['totalTeamRechargeAmount_total'] = 0;
            $result_array[$startDate]['teamExchangeAmount_total'] = 0;
            $result_array[$startDate]['totalTeamExchangeAmount_total'] = 0;
            $result_array[$startDate]['teamFlowAmount_total'] = 0;
            $result_array[$startDate]['teamValidFlowAmount_total'] = 0;
            $result_array[$startDate]['totalTeamFlowAmount_total'] = 0;
            $result_array[$startDate]['totalTeamValidFlowAmount_total'] = 0;
            $result_array[$startDate]['teamRevenue_total'] = 0;
            $result_array[$startDate]['totalTeamRevenue_total'] = 0;
            $result_array[$startDate]['teamGameWinScore_total'] = 0;
            $result_array[$startDate]['totalTeamGameWinScore_total'] = 0;
            $result_array[$startDate]['teamProfit_total'] = 0;
            $result_array[$startDate]['totalTeamProfit_total'] = 0;
            $result_array[$startDate]['teamProfitTotal_total'] = 0;
            $result_array[$startDate]['totalTeamProfitTotal_total'] = 0;
            $result_array[$startDate]['teamRechargeyNum_total'] = 0;
            $result_array[$startDate]['totalTeamRechargeNum_total'] = 0;
            $result_array[$startDate]['teamExchangeNum_total'] = 0;
            $result_array[$startDate]['totalTeamExchangeNum_total'] = 0;
            $result_array[$startDate]['teamRegValidNewBetPeople_total'] = 0;
            $result_array[$startDate]['totalTeamRegValidNewBetPeople_total'] = 0;
            $result_array[$startDate]['teamRegPeople_total'] = 0;
            $result_array[$startDate]['totalTeamRegPeople_total'] = 0;
            $result_array[$startDate]['transferToScoreAmount_total'] = 0;
            $result_array[$startDate]['totalTransferToScoreAmount_total'] = 0;

            $result_array[$startDate]['teamTransferToScoreAmount_total'] = 0;
            $result_array[$startDate]['totalTeamTransferToScoreAmount_total'] = 0;
        }
        return $result_array;
    }

    public function getPromoterDailyClub($startDate,$endDate)
    {
        $startTime = $startDate ?? date("Y-m-d");
        $endTime = $endDate ?? date("Y-m-d");
        $startTime = strtotime(trim($startTime));
        $endTime = strtotime(trim($endTime))+ 86400;
        $startTimeMongo = $this->formatTimestampToMongo($startTime);
        $endTimeMongo = $this->formatTimestampToMongo($endTime);

        $where = [];
        $where['date'] = ['$gte' => $startTimeMongo, '$lt' => $endTimeMongo];
        $where['promoterId'] = -1000;


        $data_array = Db::connection('mongodb_club')->collection('stat_promoter_daily')->raw()->aggregate([
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

        $result_array = [];
        if($data_array){
            foreach ($data_array as $item){
                $result_array[$startDate]['teamGameWinScore_total'] = !empty($item['teamGameWinScore_total']) ? $item['teamGameWinScore_total']/100 : 0;
                $result_array[$startDate]['totalTeamGameWinScore_total'] = !empty($item['totalTeamGameWinScore_total']) ? $item['totalTeamGameWinScore_total']/100 : 0;
                $result_array[$startDate]['teamFlowAmount_total'] = !empty($item['teamFlowAmount_total']) ? $item['teamFlowAmount_total']/100 : 0;
                $result_array[$startDate]['totalTeamFlowAmount_total'] = !empty($item['totalTeamFlowAmount_total']) ? $item['totalTeamFlowAmount_total']/100 : 0;
                $result_array[$startDate]['teamProfit_total'] = !empty($item['teamProfit_total']) ? $item['teamProfit_total']/100 : 0;
                $result_array[$startDate]['totalTeamProfit_total'] = !empty($item['totalTeamProfit_total']) ? $item['totalTeamProfit_total']/100 : 0;
                $result_array[$startDate]['myProfit_total'] = !empty($item['myProfit_total']) ? $item['myProfit_total']/100 : 0;
                $result_array[$startDate]['totalMyProfit_total'] = !empty($item['totalMyProfit_total']) ? $item['totalMyProfit_total']/100 : 0;
                $result_array[$startDate]['myTeamProfit_total'] = !empty($item['myTeamProfit_total']) ? $this->formatMoneyFromMongo($item['myTeamProfit_total']) : 0;
                $result_array[$startDate]['totalMyTeamProfit_total'] = !empty($item['totalMyTeamProfit_total']) ? $this->formatMoneyFromMongo($item['totalMyTeamProfit_total']) : 0;
                $result_array[$startDate]['teamRevenue_total'] = !empty($item['teamRevenue_total']) ? $item['teamRevenue_total']/100 : 0;
                $result_array[$startDate]['totalTeamRevenue_total'] = !empty($item['totalTeamRevenue_total']) ? $item['totalTeamRevenue_total']/100 : 0;
                $result_array[$startDate]['platformProfit_total'] = !empty($item['platformProfit_total']) ? $item['platformProfit_total']/100 : 0;
                $result_array[$startDate]['totalPlatformProfit_total'] = !empty($item['totalPlatformProfit_total']) ? $item['totalPlatformProfit_total']/100 : 0;
                $result_array[$startDate]['teamRegPromoterNum_total'] = !empty($item['teamRegPromoterNum_total']) ? $item['teamRegPromoterNum_total'] : 0;
                $result_array[$startDate]['totalTeamRegPromoterNum_total'] = !empty($item['totalTeamRegPromoterNum_total']) ? $item['totalTeamRegPromoterNum_total'] : 0;
                $result_array[$startDate]['teamActiveRegPromoterNum_total'] = !empty($item['teamActiveRegPromoterNum_total']) ? $item['teamActiveRegPromoterNum_total'] : 0;
                $result_array[$startDate]['totalTeamActiveRegPromoterNum_total'] = !empty($item['totalTeamActiveRegPromoterNum_total']) ? $item['totalTeamActiveRegPromoterNum_total'] : 0;
                $result_array[$startDate]['transferToScoreAmount_total'] = !empty($item['transferToScoreAmount_total']) ? $this->formatMoneyFromMongo($item['transferToScoreAmount_total']) : 0;
                $result_array[$startDate]['totalTransferToScoreAmount_total'] = !empty($item['totalTransferToScoreAmount_total']) ? $this->formatMoneyFromMongo($item['totalTransferToScoreAmount_total']) : 0;

                $result_array[$startDate]['teamTransferToScoreAmount_total'] = !empty($item['teamTransferToScoreAmount_total']) ? $this->formatMoneyFromMongo($item['teamTransferToScoreAmount_total']) : 0;
                $result_array[$startDate]['totalTeamTransferToScoreAmount_total'] = !empty($item['totalTeamTransferToScoreAmount_total']) ? $this->formatMoneyFromMongo($item['totalTeamTransferToScoreAmount_total']) : 0;
            }
        }else{
            $result_array[$startDate]['teamGameWinScore_total'] = 0;
            $result_array[$startDate]['totalTeamGameWinScore_total'] = 0;

            $result_array[$startDate]['teamFlowAmount_total'] = 0;
            $result_array[$startDate]['totalTeamFlowAmount_total'] = 0;

            $result_array[$startDate]['teamProfit_total'] = 0;
            $result_array[$startDate]['totalTeamProfit_total'] = 0;

            $result_array[$startDate]['myProfit_total'] = 0;
            $result_array[$startDate]['totalMyProfit_total'] = 0;

            $result_array[$startDate]['myTeamProfit_total'] = 0;
            $result_array[$startDate]['totalMyTeamProfit_total'] = 0;

            $result_array[$startDate]['teamRevenue_total'] = 0;
            $result_array[$startDate]['totalTeamRevenue_total'] = 0;

            $result_array[$startDate]['platformProfit_total'] = 0;
            $result_array[$startDate]['totalPlatformProfit_total'] = 0;

            $result_array[$startDate]['teamRegPromoterNum_total'] = 0;
            $result_array[$startDate]['totalTeamRegPromoterNum_total'] = 0;
            $result_array[$startDate]['teamActiveRegPromoterNum_total'] = 0;
            $result_array[$startDate]['totalTeamActiveRegPromoterNum_total'] = 0;
            $result_array[$startDate]['transferToScoreAmount_total'] = 0;
            $result_array[$startDate]['totalTransferToScoreAmount_total'] = 0;

            $result_array[$startDate]['teamTransferToScoreAmount_total'] = 0;
            $result_array[$startDate]['totalTeamTransferToScoreAmount_total'] = 0;

        }
        return $result_array;
    }

    public function getPlatFormClubSum($startDate,$endDate)
    {
        $startTime = $startDate ?? date("Y-m-d");
        $endTime = $endDate ?? date("Y-m-d");
        $startTime = strtotime(trim($startTime));
        $endTime = strtotime(trim($endTime))+ 86400;
        $startTimeMongo = $this->formatTimestampToMongo($startTime);
        $endTimeMongo = $this->formatTimestampToMongo($endTime);

        $where = [];
        $where['date'] = ['$gte' => $startTimeMongo, '$lt' => $endTimeMongo];
        $where['clubId'] = -1000;


        $data_array = Db::connection('mongodb_club')->collection('platform_data_record')->raw()->aggregate([
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
                    ]
            ]
        ])->toArray();

        $result_array = [];
        if($data_array){
            foreach ($data_array as $item){
                $result_array['platformWinScore'] = !empty($item['platformWinScore']) ? $this->formatMoneyFromMongo($item['platformWinScore']) : 0;
                $result_array['allBet'] = !empty($item['allBet']) ? $this->formatMoneyFromMongo($item['allBet']) : 0;
                $result_array['revenue'] = !empty($item['revenue']) ? $this->formatMoneyFromMongo($item['revenue']) : 0;
                $result_array['platformProfit'] = !empty($item['platformProfit']) ? $this->formatMoneyFromMongo($item['platformProfit']) : 0;
                $result_array['rewardScore'] = !empty($item['rewardScore']) ? $this->formatMoneyFromMongo($item['rewardScore']) : 0;
            }
        }else{
            $result_array['platformWinScore'] = 0;
            $result_array['allBet'] = 0;
            $result_array['revenue'] = 0;
            $result_array['platformProfit'] = 0;
            $result_array['rewardScore'] = 0;

        }
        return $result_array;
    }

    public function getPlatFormCoinSum($startDate,$endDate)
    {
        $startTime = $startDate ?? date("Y-m-d");
        $endTime = $endDate ?? date("Y-m-d");
        $startTime = strtotime(trim($startTime));
        $endTime = strtotime(trim($endTime))+ 86400;
        $startTimeMongo = $this->formatTimestampToMongo($startTime);
        $endTimeMongo = $this->formatTimestampToMongo($endTime);

        $where = [];
        $where['date'] = ['$gte' => $startTimeMongo, '$lt' => $endTimeMongo];
        //$where['clubId'] = 1000;


        $data_array = Db::connection('mongodb_main')->collection('platform_data_record')->raw()->aggregate([
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
                    ]
            ]
        ])->toArray();

        $result_array = [];
        if($data_array){
            foreach ($data_array as $item){
                $result_array['todayRechargeAmount'] = !empty($item['todayRechargeAmount']) ? $this->formatMoneyFromMongo($item['todayRechargeAmount']) : 0;
                $result_array['todayExchangeAmount'] = !empty($item['todayExchangeAmount']) ? $this->formatMoneyFromMongo($item['todayExchangeAmount']) : 0;
                $result_array['todayAllBetScore'] = !empty($item['todayAllBetScore']) ? $this->formatMoneyFromMongo($item['todayAllBetScore']) : 0;
                $result_array['todayRewardScore'] = !empty($item['todayRewardScore']) ? $this->formatMoneyFromMongo($item['todayRewardScore']) : 0;
            }
        }else{
            $result_array['todayRechargeAmount'] = 0;
            $result_array['todayExchangeAmount'] = 0;
            $result_array['todayAllBetScore'] = 0;
            $result_array['todayRewardScore'] = 0;

        }
        return $result_array;
    }

    public function get_promoter_commission($startDate,$endDate)
    {
        $startTime = $startDate ?? date("Y-m-d");
        $endTime = $endDate ?? date("Y-m-d");
        $startTime = strtotime(trim($startTime));
        $endTime = strtotime(trim($endTime))+ 86400;
        $startTimeMongo = $this->formatTimestampToMongo($startTime);
        $endTimeMongo = $this->formatTimestampToMongo($endTime);

        $where = [];
        $where['promoterId'] = ['$ne' => 1000];
        $where['date'] = ['$gte' => $startTimeMongo, '$lt' => $endTimeMongo];

        $data_array = Db::connection('mongodb_main')->collection('stat_promoter_daily')->raw()->aggregate([
            [
                '$match' => $where
            ],
            [
                '$project' =>
                    [
                        '_id' => 0,
                        'teamProfit' => 1,
                        'totalTeamProfit'=>1
                    ],
            ],
            [
                '$group' =>
                    [
                        '_id' => null,
                        'teamProfit_total' =>    ['$sum' => '$teamProfit'],
                        'totalTeamProfit_total' =>    ['$sum' => '$totalTeamProfit'],
                    ]
            ],
        ])->toArray();

        $result_array = [];
        if($data_array){
            foreach ($data_array as $item){
                $result_array[$startDate]['teamProfit_total'] = !empty($item['teamProfit_total']) ? $this->formatMoneyFromMongo($item['teamProfit_total']) : 0;
                $result_array[$startDate]['totalTeamProfit_total'] = !empty($item['totalTeamProfit_total']) ? $this->formatMoneyFromMongo($item['totalTeamProfit_total']) : 0;
            }
        }else{
            $result_array[$startDate]['teamProfit_total'] = 0;
            $result_array[$startDate]['totalTeamProfit_total'] = 0;
        }
        return $result_array;
    }

    public function getPromoterCommissionClub($startDate,$endDate)
    {
        $startTime = $startDate ?? date("Y-m-d");
        $endTime = $endDate ?? date("Y-m-d");
        $startTime = strtotime(trim($startTime));
        $endTime = strtotime(trim($endTime))+ 86400;
        $startTimeMongo = $this->formatTimestampToMongo($startTime);
        $endTimeMongo = $this->formatTimestampToMongo($endTime);

        $where = [];
        $where['promoterId'] = ['$ne' => 1000];
        $where['date'] = ['$gte' => $startTimeMongo, '$lt' => $endTimeMongo];

        $data_array = Db::connection('mongodb_main')->collection('stat_promoter_daily')->raw()->aggregate([
            [
                '$match' => $where
            ],
            [
                '$project' =>
                    [
                        '_id' => 0,
                        'teamProfit' => 1,
                        'totalTeamProfit'=>1
                    ],
            ],
            [
                '$group' =>
                    [
                        '_id' => null,
                        'teamProfit_total' =>    ['$sum' => '$teamProfit'],
                        'totalTeamProfit_total' =>    ['$sum' => '$totalTeamProfit'],
                    ]
            ],
        ])->toArray();

        $result_array = [];
        if($data_array){
            foreach ($data_array as $item){
                $result_array[$startDate]['teamProfit_total'] = !empty($item['teamProfit_total']) ? $this->formatMoneyFromMongo($item['teamProfit_total']) : 0;
                $result_array[$startDate]['totalTeamProfit_total'] = !empty($item['totalTeamProfit_total']) ? $this->formatMoneyFromMongo($item['totalTeamProfit_total']) : 0;
            }
        }else{
            $result_array[$startDate]['teamProfit_total'] = 0;
            $result_array[$startDate]['totalTeamProfit_total'] = 0;
        }
        return $result_array;
    }

    public function get_yes_data($endDate)
    {
//        $startTime = $startDate ?? date("Y-m-d");
//        $startTime = strtotime(trim($startTime));
//        $endTime = $startTime;
//        $endTime = strtotime(trim($endTime))+ 86400;


        $endTime = $endDate ?? date("Y-m-d", strtotime('-1 day'));

        $endTime = strtotime(trim($endTime))+ 86400;



        $endTimeMongo = $this->formatTimestampToMongo($endTime);

        $where = [];
        $where['promoterId'] = ['$ne' => 1000];
        $where['date'] = ['$lt' => $endTimeMongo];

        $data_array = Db::connection('mongodb_main')->collection('platform_data_record')->raw()->aggregate([
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
                        'totalBetPeople'=>1
                    ],
            ],
        ])->toArray();
        $result_array = [];
        if($data_array){
            foreach ($data_array as $item){
                $result_array[$item['Date']] = $item;
            }
        }
        return $result_array;
    }
    public function getDataRecord($endDate)
    {
        $endTime = $endDate ?? date("Y-m-d", strtotime('-1 day'));
        $endTime = strtotime(trim($endTime))+ 86400;
        $endTimeMongo = $this->formatTimestampToMongo($endTime);
        $where = [];
        $where['clubId'] = -1000;
        $where['date'] = ['$lt' => $endTimeMongo];

        $data_array = Db::connection('mongodb_club')->collection('platform_data_record')->raw()->aggregate([
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
        $result_array = [];

        if($data_array){
            foreach ($data_array as $item){
                if($item['Date'] == $endDate){
                    $result_array[$endDate]['platformWinScore'] = !empty($item['platformWinScore']) ? $this->formatMoneyFromMongo($item['platformWinScore']) : 0;
                    $result_array[$endDate]['totalPlatformWinScore'] = !empty($item['totalPlatformWinScore']) ? $this->formatMoneyFromMongo($item['totalPlatformWinScore']) : 0;
                    $result_array[$endDate]['allBet'] = !empty($item['allBet']) ? $this->formatMoneyFromMongo($item['allBet']) : 0;
                    $result_array[$endDate]['totalAllBet'] = !empty($item['totalAllBet']) ? $this->formatMoneyFromMongo($item['totalAllBet']) : 0;
                    $result_array[$endDate]['validBet'] = !empty($item['validBet']) ? $this->formatMoneyFromMongo($item['validBet']) : 0;
                    $result_array[$endDate]['totalValidBet'] = !empty($item['totalValidBet']) ? $this->formatMoneyFromMongo($item['totalValidBet']) : 0;
                    $result_array[$endDate]['revenue'] = !empty($item['revenue']) ? $this->formatMoneyFromMongo($item['revenue']) : 0;
                    $result_array[$endDate]['agentRevenue'] = !empty($item['agentRevenue']) ? $this->formatMoneyFromMongo($item['agentRevenue']) : 0;
                    $result_array[$endDate]['totalAgentRevenue'] = !empty($item['totalAgentRevenue']) ? $this->formatMoneyFromMongo($item['totalAgentRevenue']) : 0;
                    $result_array[$endDate]['totalRevenue'] = !empty($item['totalRevenue']) ? $this->formatMoneyFromMongo($item['totalRevenue']) : 0;
                    $result_array[$endDate]['platformProfit'] = !empty($item['platformProfit']) ? $this->formatMoneyFromMongo($item['platformProfit']) : 0;
                    $result_array[$endDate]['totalPlatformProfit'] = !empty($item['totalPlatformProfit']) ? $this->formatMoneyFromMongo($item['totalPlatformProfit']) : 0;

                    $result_array[$endDate]['rewardScore'] = !empty($item['rewardScore']) ? $this->formatMoneyFromMongo($item['rewardScore']) : 0;
                    $result_array[$endDate]['totalRewardScore'] = !empty($item['totalRewardScore']) ? $this->formatMoneyFromMongo($item['totalRewardScore']) : 0;
                    $result_array[$endDate]['promoterScore'] = !empty($item['promoterScore']) ? $this->formatMoneyFromMongo($item['promoterScore']) : 0;
                    $result_array[$endDate]['totalPromoterScore'] = !empty($item['totalPromoterScore']) ? $this->formatMoneyFromMongo($item['totalPromoterScore']) : 0;
                    $result_array[$endDate]['todayPromoterScore'] = !empty($item['todayPromoterScore']) ? $this->formatMoneyFromMongo($item['todayPromoterScore']) : 0;
                    $result_array[$endDate]['currentPromoterScore'] = !empty($item['currentPromoterScore']) ? $this->formatMoneyFromMongo($item['currentPromoterScore']) : 0;
                    $result_array[$endDate]['gamePlayerCount'] = !empty($item['gamePlayerCount']) ? $item['gamePlayerCount'] : 0;
                }else{
                    $result_array[$endDate]['platformWinScore'] = 0;
                    $result_array[$endDate]['totalPlatformWinScore'] = 0;
                    $result_array[$endDate]['allBet'] = 0;
                    $result_array[$endDate]['totalAllBet'] = 0;
                    $result_array[$endDate]['validBet'] = 0;
                    $result_array[$endDate]['totalValidBet'] = 0;
                    $result_array[$endDate]['revenue'] = 0;
                    $result_array[$endDate]['agentRevenue'] = 0;
                    $result_array[$endDate]['totalAgentRevenue'] = 0;
                    $result_array[$endDate]['totalRevenue'] = 0;
                    $result_array[$endDate]['platformProfit'] = 0;
                    $result_array[$endDate]['totalPlatformProfit'] = 0;

                    $result_array[$endDate]['rewardScore'] = 0;
                    $result_array[$endDate]['totalRewardScore'] = 0;
                    $result_array[$endDate]['promoterScore'] = 0;
                    $result_array[$endDate]['totalPromoterScore'] = 0;
                    $result_array[$endDate]['todayPromoterScore'] = 0;
                    $result_array[$endDate]['currentPromoterScore'] = 0;
                    $result_array[$endDate]['gamePlayerCount'] = 0;
                }
            }
        }else{
            $result_array[$endDate]['platformWinScore'] = 0;
            $result_array[$endDate]['totalPlatformWinScore'] = 0;
            $result_array[$endDate]['allBet'] = 0;
            $result_array[$endDate]['totalAllBet'] = 0;
            $result_array[$endDate]['validBet'] = 0;
            $result_array[$endDate]['totalValidBet'] = 0;
            $result_array[$endDate]['revenue'] = 0;
            $result_array[$endDate]['agentRevenue'] = 0;
            $result_array[$endDate]['totalAgentRevenue'] = 0;
            $result_array[$endDate]['totalRevenue'] = 0;
            $result_array[$endDate]['platformProfit'] = 0;
            $result_array[$endDate]['totalPlatformProfit'] = 0;

            $result_array[$endDate]['rewardScore'] = 0;
            $result_array[$endDate]['totalRewardScore'] = 0;
            $result_array[$endDate]['promoterScore'] = 0;
            $result_array[$endDate]['totalPromoterScore'] = 0;
            $result_array[$endDate]['todayPromoterScore'] = 0;
            $result_array[$endDate]['currentPromoterScore'] = 0;
            $result_array[$endDate]['gamePlayerCount'] = 0;
        }



//        if($data_array){
//            foreach ($data_array as $item){
//                $result_array[$item['Date']] = $item;
//            }
//        }
        return $result_array;
    }
    public function next_day_retention($keep_user_t,$new_users_data_y){
        $data['vals_t'] = 0;
        if($new_users_data_y == 0){
            $data['vals_t'] = 0;
        }else{
            $data['vals_t'] = $keep_user_t/$new_users_data_y;
        }
        return $data;
    }
}
