<?php
namespace app\controller;

use app\model\ServerlstUpdateUrl;
use support\Request;

class GameUpdate extends Base
{
    private function _ajaxParam()
    {
        $where = [];
        $request = request();
        $getData = check_type($request->all());
        extract($getData);
        if (!empty($searchText)) {
            if(is_numeric($searchText)) {
                $where['updateUrlId'] = (int)$searchText;
            }else{
                $where['updateUrl'] = $searchText;
            }
        }
        return $where;
    }

    public function gameUpdateList(Request $request)
    {
        if ($request->isAjax()) {
            $where = $this->_ajaxParam();
            if (!is_array($where)) return $where;
            $count = ServerlstUpdateUrl::where($where)->count();
            $list = ServerlstUpdateUrl::where($where)->orderBy('updateUrlId', 'desc')->skip($request->skip)->take($request->limit)->get();
            return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $list]);
        }
    }
    public function addGameUpdate(Request $request)
    {
        if ($request->isAjax()) {
            $postData = check_type($request->post());
            extract($postData);
            if (!$updateUrl) {
                return json(['code' => -1, 'msg' => '地址不能为空']);
            }
            $updateUrlId = ServerlstUpdateUrl::max('updateUrlId');
            $insertData = [
                'updateUrlId' => intval($updateUrlId+1),
                'updateUrl' => $updateUrl
            ];
            $insertResult = ServerlstUpdateUrl::insert($insertData);
            if (!$insertResult) return json(['code' => -1, 'msg' => '添加失败']);
            $this->adminLog(["content"=>"添加热更地址【".$insertData['updateUrlId']."】"]);
            return json(['code' => 0, 'msg' => '添加成功']);
        }
        return view('config/gameUpdate/add', ['name' => '']);
    }

    public function removeGameUpdate(Request $request)
    {
        if ($request->post('_ids')) {
            $_ids = $request->post('_ids');
            $idsArr = explode(",", $_ids);
            $removeResult = ServerlstUpdateUrl::destroy($idsArr);
            if (!$removeResult) return json(['code' => -1, 'msg' => '删除失败']);
            $this->adminLog(["content"=>"删除热更数据【".$_ids."】"]);
            return json(['code' => 0, 'msg' => '删除成功']);
        }

        $postData = check_type($request->post());
        extract($postData);
        $removeResult = ServerlstUpdateUrl::where('_id', $_id)->delete();
        if (!$removeResult) return json(['code' => -1, 'msg' => '删除失败']);
        $this->adminLog(["content"=>"删除热更数据【".$_id."】"]);
        return json(['code' => 0, 'msg' => '删除成功']);
    }

    public function editGameUpdate(Request $request)
    {
        if ($request->isAjax()) {
            $postData = check_type($request->post());
            extract($postData);
            if (!$field || !$_id) {
                return json(['code' => -1, 'msg' => '信息输入不正确']);
            }

            $updateData = [
                $field=> $value
            ];
            $updateResult = ServerlstUpdateUrl::where('_id', $_id)->update($updateData);
            if (!$updateResult) return json(['code' => -1, 'msg' => '修改失败']);
            return json(['code' => 0, 'msg' => '修改成功']);
        }
    }
}
