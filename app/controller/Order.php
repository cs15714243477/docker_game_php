<?php
namespace app\controller;

use app\model\ExchangeBlockInfo;
use app\model\ExchangeOrder;
use app\model\GameUser;
use app\model\RechargeOrder;
use app\model\ClubRewardOrder;
use app\model\RewardOrder;
use support\bootstrap\Container;
use support\Db;
use support\Request;

class Order extends Base
{
    public function index(Request $request)
    {
        $act = $request->get('act');
        $actMap = ['global_more' => 'globalData'];
        if (isset($actMap[$act])) $act = $actMap[$act];
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

    public function rechargeList(Request $request)
    {
        extract(RechargeType::rechargeTypeOnlineOffline());
        $assignData = [
            'online' => $online,
            'offline' => $offline,
            'status' => RechargeOrder::STATUS,
            'sp' => RechargeOrder::CLASSIFY,
            'isSys' => GameUser::ACCOUNT_CLASSIFY,
        ];
        /*$smarty->assign('menuPurview', $_SESSION['menuPurview']);
        $smarty->assign('recharge_online', $recharge_type_array_online);
        $smarty->assign('recharge_off_online', $recharge_type_array_off_online);*/
        return view('order/recharge/list', $assignData);
    }

    public function exchangeList(Request $request)
    {
        $assignData = [
            'status' => ExchangeOrder::STATUS,
            'withdrawType' => ExchangeOrder::WITHDRAWTYPE,
            'isSys' => GameUser::ACCOUNT_CLASSIFY,
        ];
        return view('order/exchange/list', $assignData);
    }

    public function agentExchangeList(Request $request)
    {
        return view('order/agentExchange/list', ['data' => '']);
    }

    public function rewardList(Request $request)
    {
        $assignData = [
            'rewardType' => RewardOrder::REWARD_TYPE,
            'isSys' => GameUser::ACCOUNT_CLASSIFY,
        ];
        return view('order/reward/list', $assignData);
    }

    public function clubRewardList(Request $request)
    {
        $assignData = [
            'activityType' => ClubRewardOrder::CLUB_REWARD_TYPE_FOR_GIVE_SCORE,
            'isSys' => GameUser::ACCOUNT_CLASSIFY,
        ];
        return view('order/clubReward/list', $assignData);
    }

    public function giveScore(Request $request)
    {

        $assignData = [
            'giveScoreType' => GIVE_SCORE_TYPE,
            'clubRewardType' => ClubRewardOrder::CLUB_REWARD_TYPE_FOR_GIVE_SCORE,
            'isSys' => GameUser::ACCOUNT_CLASSIFY,
        ];
        return view('order/recharge/add', $assignData);
    }

    public function giveScoreAward(Request $request)
    {
        $rewardType = RewardOrder::REWARD_TYPE_FOR_GIVE_SCORE;
        unset($rewardType['135']);
        $assignData = [
            'giveScoreType' => GIVE_SCORE_ACTIVITY,
            'rewardType' => $rewardType,
            'isSys' => GameUser::ACCOUNT_CLASSIFY,
        ];
        return view('order/recharge/addAward', $assignData);
    }

    public function blockList(Request $request)
    {
        if ($request->isAjax()) {
            $count = ExchangeBlockInfo::count();
            $list = ExchangeBlockInfo::orderBy('Id', 'desc')->skip($request->skip)->take($request->limit)->get()->toArray();
            return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $list]);
        }

        return view('order/block/list', ['data' => '']);
    }
    
    public function switchBlockList(Request $request)
    {
        $postData = check_type($request->post());
        extract($postData);
        $updateData = check_type([$field => $value]);
        $updateResult = ExchangeBlockInfo::where('_id', $_id)->update($updateData);
        if (!$updateResult) return json(['code' => -1, 'msg' => '修改失败']);
        return json(['code' => 0, 'msg' => '修改成功']);
    }

    public function blockAdd(Request $request)
    {
        if ($request->method() == 'POST') {
            $postData = check_type($request->post());
            extract($postData);
            $Id = ExchangeBlockInfo::max('Id');
            $insertData = [
                'Id' => intval($Id+1),
                'userId' => $userId,
                'bankCardNum' => $bankCardNum??'',
                'bankCardName' => $bankCardName??'',
                'alipayAccount' => $alipayAccount??'',
                'alipayName' => $alipayName??'',
            ];
            $insertResult = ExchangeBlockInfo::insert($insertData);
            if (!$insertResult) return json(['code' => -1, 'msg' => '添加失败']);
            $this->adminLog(["content"=>"添加提现黑名单【".$insertData['Id']."】"]);
            return json(['code' => 0, 'msg' => '添加成功']);
        }
        return view('order/block/add', []);
    }

    public function adminCreateExchangeOrder(Request $request)
    {

        $assignData = [
            'giveScoreType' => GIVE_SCORE_ACTIVITY,
            'rewardType' => RewardOrder::REWARD_TYPE_FOR_GIVE_SCORE,
            'isSys' => GameUser::ACCOUNT_CLASSIFY,

            'withdrawType' => ExchangeOrder::WITHDRAWTYPE,
        ];
        dd($assignData);
        return view('order/exchange/add', $assignData);
    }

}
