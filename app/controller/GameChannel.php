<?php
namespace app\controller;

use app\model\GameChannelMo;
use support\Request;

class GameChannel extends Base
{
    private function _ajaxParam()
    {
        $where = [];
        $request = request();
        $getData = check_type($request->all());
        extract($getData);
        if (!empty($searchType) && !empty($searchText)) {
            if($searchType == "id" || $searchType == "agentId") {
                $where[$searchType] = (int)$searchText;
            }elseif ($searchType == "name" || $searchType == "webURL"){
                $where[$searchType] = $searchText;
            }
        }
        return $where;
    }

    public function gameChannelList(Request $request)
    {
        if ($request->isAjax()) {
            $where = $this->_ajaxParam();
            if (!is_array($where)) return $where;
            $count = GameChannelMo::where($where)->count();
            $list = GameChannelMo::where($where)->orderBy('id', 'asc')->skip($request->skip)->take($request->limit)->get();
            return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $list]);
        }
    }
    public function addGameChannel(Request $request)
    {
        if ($request->isAjax()) {
            $postData = check_type($request->post());
            extract($postData);
            if (!$name || !$agentId) {
                return json(['code' => -1, 'msg' => '渠道名称,总代id']);
            }
            $id = GameChannelMo::max('id');
            $insertData = [
                'id' => intval($id+1),
                'name' => $name,
                'agentId' => $agentId,
                'webURL' => $webURL,
                'QRCodeURL' => $QRCodeURL,
                'realQRCodeURL' => $QRCodeURL,
                'downloadURL' => $downloadURL,
                'updateUrlId' => $updateUrlId,
                'status' => 1,
                'createTime' => new \MongoDB\BSON\UTCDateTime
            ];
            $insertResult = GameChannelMo::insert($insertData);
            if (!$insertResult) return json(['code' => -1, 'msg' => '添加失败']);
            $this->adminLog(["content"=>"添加渠道配置【".$insertData['id']."】"]);
            return json(['code' => 0, 'msg' => '添加成功']);
        }
        return view('config/gameChannel/add', ['name' => '']);
    }

    public function editGameChannel(Request $request)
    {
        if ($request->isAjax()) {
            $postData = check_type($request->post());
            extract($postData);
            if (!$field || !$_id) {
                return json(['code' => -1, 'msg' => '信息输入不正确']);
            }
            if($field == "updateUrlId" || $field == "agentId"){
                $value = (int)$value;
            }

            $updateData = [
                $field=> $value
            ];
            $updateResult = GameChannelMo::where('_id', $_id)->update($updateData);
            if (!$updateResult) return json(['code' => -1, 'msg' => '修改失败']);
            return json(['code' => 0, 'msg' => '修改成功']);
        }
    }

    public function removeGameChannerl(Request $request)
    {
        if ($request->post('_ids')) {
            $_ids = $request->post('_ids');
            $idsArr = explode(",", $_ids);
            $removeResult = GameChannelMo::destroy($idsArr);
            if (!$removeResult) return json(['code' => -1, 'msg' => '删除失败']);
            $this->adminLog(["content"=>"删除渠道配置【".$_ids."】"]);
            return json(['code' => 0, 'msg' => '删除成功']);
        }

        $postData = check_type($request->post());
        extract($postData);
        $removeResult = GameChannelMo::where('_id', $_id)->delete();
        if (!$removeResult) return json(['code' => -1, 'msg' => '删除失败']);
        $this->adminLog(["content"=>"删除渠道配置【".$_id."】"]);
        return json(['code' => 0, 'msg' => '删除成功']);
    }
}
