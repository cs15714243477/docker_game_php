<?php
namespace app\controller;

use app\model\Bank;
use support\bootstrap\Container;
use support\Request;
use app\model\SystemConfig;

class Config extends Base
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

    public function notice(Request $request)
    {
        return view('notice/list', ['name' => '']);
    }

    public function promoterLevel(Request $request)
    {
        return view('agent/promoterLevel/list', ['data' => '']);
    }

    public function systemConfig(Request $request)
    {
        if ($request->isAjax()) {
            $data = check_type($request->post());
            extract($data);
            if (!$_id) {
                return json(['code' => -1, 'msg' => '信息输入不正确']);
            }
            $upData['register_reward_score'] = (int)$this->formatMoneytoMongo($data['register_reward_score']);
            $upData['bind_mobile_reward_score'] = (int)$this->formatMoneytoMongo($data['bind_mobile_reward_score']);
            $upData['exchange_min_money_one_times'] = (int)$this->formatMoneytoMongo($data['exchange_min_money_one_times']);
            $upData['exchange_max_money_one_times'] = (int)$this->formatMoneytoMongo($data['exchange_max_money_one_times']);
            $upData['exchange_min_money_one_times_alipay'] = (int)$this->formatMoneytoMongo($data['exchange_min_money_one_times_alipay']);
            $upData['exchange_max_money_one_times_alipay'] = (int)$this->formatMoneytoMongo($data['exchange_max_money_one_times_alipay']);
            $upData['exchange_min_money_one_times_usdt'] = (int)$this->formatMoneytoMongo($data['exchange_min_money_one_times_usdt']);
            $upData['exchange_max_money_one_times_usdt'] = (int)$this->formatMoneytoMongo($data['exchange_max_money_one_times_usdt']);
            $upData['exchange_min_left_money'] = (int)$this->formatMoneytoMongo($data['exchange_min_left_money']);
            $upData['ip_max_register_count'] = (int)$data['ip_max_register_count'];
            $upData['exchange_times_one_day'] = (int)$data['exchange_times_one_day'];
            $upData['exchange_interval'] = (int)$data['exchange_interval'];
            $upData['enable_exchange'] = (int)$data['enable_exchange'];
            $upData['exchange_fee'] = (int)$data['exchange_fee'];
            $upData['anti_emulator'] = (int)$data['anti_emulator'];
            $upData['bind_mobile_serial'] = (int)$data['bind_mobile_serial'];
            $upData['APPLY_CLUB_QQ'] = $data['APPLY_CLUB_QQ'];
            $upData['WW_DOWNLOAD_URL'] = $data['WW_DOWNLOAD_URL'];
            $upData['APPLY_CLUB_WECHAT'] = $data['APPLY_CLUB_WECHAT'];
            $upData['APPLY_CLUB_WW'] = $data['APPLY_CLUB_WW'];
            $upData['OFFICIAL_WEBSITE'] = $data['OFFICIAL_WEBSITE'];
            $upData['promoter_rebate'] = (int)$data['promoter_rebate'];
            if (($upData['promoter_rebate'] < 0) || ($upData['promoter_rebate'] > 100)) return json(['code' => -1, 'msg' => '代理返佣比例的取值范围是 0--100']);

            $updateResult = SystemConfig::where('_id', $_id)->update($upData);
            if (!$updateResult) return json(['code' => -1, 'msg' => '修改失败']);
            return json(['code' => 0, 'msg' => '修改成功']);
        }

        $data = SystemConfig::first();
        $data['register_reward_score'] = $this->formatMoneyFromMongo($data['register_reward_score']);
        $data['bind_mobile_reward_score'] = $this->formatMoneyFromMongo($data['bind_mobile_reward_score']);
        $data['exchange_min_money_one_times'] = $this->formatMoneyFromMongo($data['exchange_min_money_one_times']);
        $data['exchange_max_money_one_times'] = $this->formatMoneyFromMongo($data['exchange_max_money_one_times']);
        $data['exchange_min_money_one_times_alipay'] = $this->formatMoneyFromMongo($data['exchange_min_money_one_times_alipay']);
        $data['exchange_max_money_one_times_alipay'] = $this->formatMoneyFromMongo($data['exchange_max_money_one_times_alipay']);
        $data['exchange_min_money_one_times_usdt'] = $this->formatMoneyFromMongo($data['exchange_min_money_one_times_usdt']);
        $data['exchange_max_money_one_times_usdt'] = $this->formatMoneyFromMongo($data['exchange_max_money_one_times_usdt']);
        $data['exchange_min_left_money'] = $this->formatMoneyFromMongo($data['exchange_min_left_money']);
        return view('config/systemConfig/edit', ['data' => $data]);
    }

    public static function getSystemVips()
    {
        $rs = SystemConfig::first();
        return $rs->vips;
    }

    public function gameUpdate(Request $request)
    {
        return view('config/gameUpdate/list', ['name' => '']);
    }

    public function gameVer(Request $request)
    {
        return view('config/gameVer/list', ['name' => '']);
    }

    public function gameChannel(Request $request)
    {
        return view('config/gameChannel/list', ['name' => '']);
    }

    public function email(Request $request)
    {
        return view('config/email/list', ['name' => '']);
    }

    public function task(Request $request)
    {
        return view('config/task/list', ['name' => '']);
    }

    public function serverHAList(Request $request)
    {
        return view('config/server/list', ['name' => '']);
    }

    public function promoterRegControl(Request $request)
    {
        return view('config/regControl/list', ['name' => '']);
    }

    public function serverlst(Request $request)
    {
        $menuPurview = $request->session('menuPurview');
        return view('config/chanGenera/list', ['menuPurview' => $menuPurview]);
    }

    public function androidList(Request $request)
    {
        $roomList = getGameRoomInfo();
        return view('config/android/list', ['roomList' => $roomList]);
    }

    public function stockConfig(Request $request)
    {
        return view('config/stock/list');
    }

    public function clubStockConfig(Request $request)
    {
        return view('config/clubStock/list');
    }

    public function exchangeServiceList(Request $request)
    {
        return view('config/exchangeServer/list');
    }

    public function rechargeType(Request $request)
    {
        return view('config/rechargeType/list');
    }

    public function bankList(Request $request)
    {
        if ($request->isAjax()) {
            $data = check_type($request->get());
            extract($data);
            $where = [];
            if (!empty($bankCode)) $where['bankCode'] = $bankCode;
            if (!empty($bankName)) $where['bankName'] = $bankName;
            $count = Bank::count();
            //$list = Bank::orderBy('Id', 'desc')->skip($request->skip)->take($request->limit)->get()->toArray();
            $list = Bank::where($where)->skip($request->skip)->take($request->limit)->get()->toArray();
            return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $list]);
        }
        return view('config/bank/list');
    }
}
