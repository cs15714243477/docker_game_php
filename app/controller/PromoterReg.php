<?php
namespace app\controller;

use app\model\Promoter;
use support\Request;

class PromoterReg extends Base
{
    private function _ajaxParam()
    {
        $where = [];
        $request = request();
        $getData = check_type($request->all());
        extract($getData);
        if (!empty($searchText)) {
            if(count(explode("，",$searchText))>1){
                return json(['code' => -1, 'msg' => '参数错误,不要输入全角逗号']);
            }
            $promoterIdStr = array_map('intval',explode(',', $searchText));
            foreach ($promoterIdStr as $value)

            $value = (int)$value;
                $promoterIds[] = $value;
            if(count($promoterIds) > 0)
                $where = $promoterIdStr;
        }

        return $where;
    }

    public function promoterRegControl(Request $request)
    {
        if ($request->isAjax()) {
            $where = $this->_ajaxParam();

            if (!is_array($where)) return $where;
            if($where){
                $count = Promoter::whereIn('promoterId',$where)->count();

                $promoter_list = Promoter::whereIn('promoterId',$where)->orderBy('promoterId', 'asc')->skip($request->skip)->take($request->limit)->get();
            }else{
                $count = Promoter::count();
                $promoter_list = Promoter::orderBy('promoterId', 'asc')->skip($request->skip)->take($request->limit)->get();
            }
            foreach ($promoter_list as $key => $value) {
                $promoter_list[$key]['initScore'] = $this->formatMoneyFromMongo($promoter_list[$key]['initScore']);
                $promoter_list[$key]['bindScore'] = $this->formatMoneyFromMongo($promoter_list[$key]['bindScore']);
                $promoter_list[$key]['iosGiveScore'] = $this->formatMoneyFromMongo($promoter_list[$key]['iosGiveScore']);
                $promoter_list[$key]['iosBindScore'] = $this->formatMoneyFromMongo($promoter_list[$key]['iosBindScore']);
                $promoter_list[$key]['androidGiveScore'] = $this->formatMoneyFromMongo($promoter_list[$key]['androidGiveScore']);
                $promoter_list[$key]['androidBindScore'] = $this->formatMoneyFromMongo($promoter_list[$key]['androidBindScore']);
                $promoter_list[$key]['simulatorGiveScore'] = $this->formatMoneyFromMongo($promoter_list[$key]['simulatorGiveScore']);
                $promoter_list[$key]['simulatorBindScore'] = $this->formatMoneyFromMongo($promoter_list[$key]['simulatorBindScore']);
            }
            return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $promoter_list]);
        }
    }


    public function promoterRegEdit(Request $request)
    {
        if ($request->isAjax()) {
            $postData = check_type($request->post());
            extract($postData);
            $value = intval($value);


            if (!$field || !$_id || $value == "") {
                return json(['code' => -1, 'msg' => '信息输入不正确']);
            }
            if($field == 'initScore' || $field == 'bindScore' ||
                $field == 'iosGiveScore' || $field == 'iosBindScore' ||
                $field == 'androidGiveScore' || $field == 'androidBindScore' ||
                $field == 'simulatorGiveScore' || $field == 'simulatorBindScore')
                $value = $this->formatMoneytoMongo($value);

            $updateData = [
                $field=> $value
            ];
            $promoter_update_result = Promoter::where('_id', $_id)->update($updateData);

            if (!$promoter_update_result) {
                return json(['code' => -1, 'msg' => '修改失败']);
            }else{
                if ($field == "iosReg")
                    if ($value == 1)
                        $msg = "注册控制启用苹果注册";
                    else
                        $msg = "注册控制关闭苹果注册";
                if ($field == "iosLogin")
                    if ($value == 1)
                        $msg = "注册控制启用苹果登录";
                    else
                        $msg = "注册控制关闭苹果登录";
                if ($field == "androidReg")
                    if ($value == 1)
                        $msg = "注册控制启用安卓注册";
                    else
                        $msg = "注册控制关闭安卓注册";
                if ($field == "androidLogin")
                    if ($value == 1)
                        $msg = "注册控制启用安卓登录";
                    else
                        $msg = "注册控制关闭安卓登录";
                if ($field == "simulatorReg")
                    if ($value == 1)
                        $msg = "注册控制启用模拟器注册";
                    else
                        $msg = "注册控制关闭模拟器注册";
                if ($field == "simulatorLogin")
                    if ($value == 1)
                        $msg = "注册控制启用模拟器登录";
                    else
                        $msg = "注册控制关闭模拟器登录";
                return json(['code' => 0, 'msg' => '修改成功']);
            }
        }
    }
}
