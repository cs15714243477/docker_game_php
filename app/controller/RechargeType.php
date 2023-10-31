<?php
namespace app\controller;

use app\model\RechargeType as RechargeType28;
use support\Request;
use support\FunctionUpload;

class RechargeType extends Base
{
    public function index(Request $request)
    {
        //return response('hello webman' .$act);
        //return view('index/index', ['name' => 'webman1']);
    }

    public static function rechargeTypeOnlineOffline()
    {
        $online = $offline = [];
        $data = RechargeType28::select('rechargeTypeId','rechargeTypeName','onlineOffline')->get()->toArray();
        foreach ($data as $item) {
            if ($item['onlineOffline'] == RechargeType28::ONLINE) {
                $online[$item['rechargeTypeId']] = $item['rechargeTypeName'];
            } else {
                $offline[$item['rechargeTypeId']] = $item['rechargeTypeName'];
            }
        }
        return ['online' => $online, 'offline' => $offline];
    }

    public static function rechargeTypeIdNameList()
    {
        return RechargeType28::all('rechargeTypeId', 'rechargeTypeName')->toArray();
    }

    public static function rechargeTypeIdNameKV()
    {
        $data = [];
        $list = RechargeType28::all('rechargeTypeId', 'rechargeTypeName')->toArray();
        foreach ($list as $item) {
            $data[$item['rechargeTypeId']] = $item['rechargeTypeName'];
        }
        return $data;
    }

    public function rechargeType(Request $request)
    {
        if ($request->isAjax()) {
            $where = [];
            $getData = $request->all();
            extract($getData);
            $where['onlineOffline'] = 2;
            isset($offline) && $where['onlineOffline'] = 1;
            isset($type) && ($type != '') && $where['type'] = (int)$type;
            isset($status) && ($status != '') && $where['status'] = (int)$status;
            isset($inputSw) && ($inputSw != '') && $where['inputSw'] = (int)$inputSw;

            $count = RechargeType28::where($where)->count();
            $rechargeTypeList = RechargeType28::where($where)->orderBy('status', 'desc')->orderBy('sortId', 'asc')->skip($request->skip)->take($request->limit)->get()->toArray();

            foreach ($rechargeTypeList as $key => $value) {
                $rechargeTypeList[$key]['minMoneyLimit'] = $this->formatMoneyFromMongo($rechargeTypeList[$key]['minMoneyLimit']);
                $rechargeTypeList[$key]['maxMoneyLimit'] = $this->formatMoneyFromMongo($rechargeTypeList[$key]['maxMoneyLimit']);

                $moneyArray = json_decode($rechargeTypeList[$key]['rechargeMoney']);
                if ($moneyArray) {
                    foreach ($moneyArray as $key1 => $value1) {
                        $moneyArray[$key1] = $this->formatMoneyFromMongo($value1);
                    }
                }
                $rechargeTypeList[$key]['rechargeMoney'] = json_encode($moneyArray);
            }

            return json(['code' => 0, 'msg' => 'ok', 'count' =>$count, 'data' => $rechargeTypeList]);
        }
    }

    public function rechargeTypeAdd(Request $request)
    {
        if ($request->isAjax()) {
            print_r($request->post());

            $postData = check_type($request->post());
            extract($postData);
            if (!$rechargeTypeName) {
                return json(['code' => -1, 'msg' => '银行名字不能为空']);
            }
            $rechargeTypeId = RechargeType28::max('rechargeTypeId');
            $rechargeTypeId = (int)$rechargeTypeId+1;
            $insertData = [
                'rechargeTypeId' => $rechargeTypeId,
                'rechargeTypeName' => $rechargeTypeName,
                'rechargeMoney' => "[5000,10000,20000,30000,50000,100000,200000]",
                'minMoneyLimit' => 5000,
                'maxMoneyLimit' => 1000000,
                'sortId' => $rechargeTypeId,
                'status' => 0,
                'inputSw' => 0,
                'type' => 1,
                'rechargeTypeIcon' => '',
                'account' => "{\"no\":\"5656565\",\"name\":\"张三\"}",
                'onlineOffline' => 1,
                'topUp' => 0,
                'giveRate' => 0
            ];
            $insertResult = RechargeType28::insert($insertData);
            if (!$insertResult) return json(['code' => -1, 'msg' => '添加失败']);
            $this->adminLog(["content"=>"添加充值方式【".$rechargeTypeId."】"]);
            return json(['code' => 0, 'msg' => '添加成功']);
        }
        return view('config/RechargeType/add', ['name' => '']);
    }

    public function rechargeTypeIconUpload(Request $request)
    {
        $file = $request->file('file');
        $getData = $request->all();
        extract($getData);
        $rechargeTypeId = !empty($rechargeTypeId) ? (int) $rechargeTypeId : 0;
        if (!$rechargeTypeId) {
            return json(['code' => -1, 'msg' => '参数错误']);
        }
        if ($file && $file->isValid()) {
            $uploadFile = $file->move(UPFILE_PATH.$file->getUploadName());
            if ($uploadFile) {
                $upload = new functionUpload();
                $rs = $upload->aliOssUpload(1, $uploadFile->getFilename());
                if ($rs) {
                    $where = [];
                    $where['rechargeTypeId'] = $rechargeTypeId;
                    $updateData = [
                        'rechargeTypeIcon'=> $rs
                    ];
                    $updateResult = RechargeType28::where($where)->update($updateData);
                    if (!$updateResult) return json(['code' => -1, 'msg' => '上传失败']);
                    $this->adminLog(["content"=>"充值类型上传图标【".$rechargeTypeId."】"]);
                    return json(['code' => 0, 'msg' => '上传成功']);
                }
            }else{
                return json(['code' => -1, 'msg' => '上传失败']);
            }
        }else{
            return json(['code' => -1, 'msg' => '上传失败']);
        }
    }

    public function rechargeTypeInputSw(Request $request)
    {
        if ($request->isAjax()) {
            $postData = $request->post();
            extract($postData);

            $value = isset($value) ? (int)$value : false;
            if (!$field || !$_id) {
                return json(['code' => -1, 'msg' => '参数错误']);
            }
            if($value == 1)
            {
                $strInfo = "输入框状态开启";
            }else
            {
                $strInfo = "输入框状态关闭";
            }
            $row = RechargeType28::where('_id', $_id)->first();
            if(empty($row))return json(['code' => -1, 'msg' => '无该条数据']);
            $where = [];
            $where['_id'] = $_id;
            $updateData = [
                'inputSw'=> $value
            ];
            $updateResult = RechargeType28::where($where)->update($updateData);
            if (!$updateResult) return json(['code' => -1, 'msg' => ''.$strInfo.'失败']);
            $rechargeTypeId = $row['rechargeTypeId'];
            $msg = $strInfo."：(".$rechargeTypeId.")";
            $this->adminLog(["content"=>$msg]);
            return json(['code' => 0, 'msg' => ''.$strInfo.'成功']);
        }
    }

    public function rechargeTypeStatus(Request $request)
    {
        if ($request->isAjax()) {
            $postData = $request->post();
            extract($postData);

            $value = isset($value) ? (int)$value : false;
            if (!$field || !$_id) {
                return json(['code' => -1, 'msg' => '参数错误']);
            }
            if($value == 1)
            {
                $strInfo = "通道状态开启";
            }else
            {
                $strInfo = "通道状态关闭";
            }

            $row = RechargeType28::where('_id', $_id)->first();

            if(empty($row))return json(['code' => -1, 'msg' => '无该条数据']);
            $where = [];
            $where['_id'] = $_id;
            $updateData = [
                'status'=> $value
            ];
            $updateResult = RechargeType28::where($where)->update($updateData);
            if (!$updateResult) return json(['code' => -1, 'msg' => ''.$strInfo.'失败']);
            $rechargeTypeId = $row['rechargeTypeId'];
            $msg = $strInfo."：(".$rechargeTypeId.")";
            $this->adminLog(["content"=>$msg]);
            return json(['code' => 0, 'msg' => ''.$strInfo.'成功']);
        }
    }

    public function editChargeType(Request $request)
    {
        if ($request->isAjax()) {
            $postData = $request->post();
            extract($postData);
            $rechargeTypeId = !empty($rechargeTypeId) ? (int)$rechargeTypeId : false;
            $field = !empty($field) ? trim($field) : false;
            $value = !empty($value) ? trim($value) : false;
            if (!$rechargeTypeId || !$field) {
                return json(['code' => -1, 'msg' => '参数错误']);
            }
            if ($value == "") {
                return json(['code' => -1, 'msg' => '修改值不可以为空']);
            }
            if (in_array($field, ['topUp', 'giveRate']) && !in_array(session('groupId'), [1, 8])) {
                return json(['code' => -1, 'msg' => '没有操作权限!']);
            }
            if($field == 'rechargeMoney')
            {
                $moneyArray = json_decode($value);
                foreach ($moneyArray as $key => $value) {
                    $moneyArray[$key] = $this->formatMoneytoMongo($moneyArray[$key]);
                }
                $value = json_encode($moneyArray);
            }
            if(in_array($field, ['sortId', 'type', 'onlineOffline'])) {
                $value = (int)$value;
            }

            if($field == 'minMoneyLimit' || $field == 'maxMoneyLimit') {
                $value = (int)$value;
                $value = $this->formatMoneytoMongo($value);
            }
            $where = [];
            $where['rechargeTypeId'] = $rechargeTypeId;
            $updateData = [
                $field=> $value
            ];
            $updateResult = RechargeType28::where($where)->update($updateData);
            if (!$updateResult) return json(['code' => -1, 'msg' => '修改失败']);
            return json(['code' => 0, 'msg' => '修改成功']);
        }
    }

}
