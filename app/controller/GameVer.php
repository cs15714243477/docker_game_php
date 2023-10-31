<?php
namespace app\controller;

use app\model\ServerLstGameVersion;
use support\Request;

class GameVer extends Base
{
    private function _ajaxParam()
    {
        $where = [];
        $request = request();
        $getData = check_type($request->all());
        extract($getData);
        if (!empty($searchType)) {
            if($searchType == "gameVerId" || $searchType == "gameId") {
                if(!empty($searchText)){
                    $where[$searchType] = (int)$searchText;
                }
            }elseif ($searchType == "version"){
                if(!empty($searchText)){
                    $where[$searchType] = $searchText;
                }
            }
        }
        return $where;
    }

    public function gameVerList(Request $request)
    {
        if ($request->isAjax()) {
            $where = $this->_ajaxParam();
            if (!is_array($where)) return $where;
            $count = ServerLstGameVersion::where($where)->count();
            $list = ServerLstGameVersion::where($where)->orderBy('gameVerId', 'desc')->skip($request->skip)->take($request->limit)->get();
            return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $list]);
        }
    }
    public function addGameVer(Request $request)
    {
        if ($request->isAjax()) {
            $postData = check_type($request->post());
            extract($postData);
            if (!$gameVerId || !$gameId || !$version) {
                return json(['code' => -1, 'msg' => 'gameVerId,游戏id,版本号不能为空']);
            }
            $gameVerId = ServerLstGameVersion::max('gameVerId');
            $insertData = [
                'gameVerId' => intval($gameVerId+1),
                'gameId' => $gameId,
                'version' => $version,
                'remark' => $remark
            ];
            $insertResult = ServerLstGameVersion::insert($insertData);
            if (!$insertResult) return json(['code' => -1, 'msg' => '添加失败']);
            $this->adminLog(["content"=>"添加游戏版本配置【".$insertData['gameVerId']."】"]);
            return json(['code' => 0, 'msg' => '添加成功']);
        }
        return view('config/gameVer/add', ['name' => '']);
    }

    public function editGameVer(Request $request)
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
            $updateResult = ServerLstGameVersion::where('_id', $_id)->update($updateData);
            if (!$updateResult) return json(['code' => -1, 'msg' => '修改失败']);
            return json(['code' => 0, 'msg' => '修改成功']);
        }
    }

    public function removeGameVer(Request $request)
    {
        if ($request->post('_ids')) {
            $_ids = $request->post('_ids');
            $idsArr = explode(",", $_ids);
            $removeResult = ServerLstGameVersion::destroy($idsArr);
            if (!$removeResult) return json(['code' => -1, 'msg' => '删除失败']);
            $this->adminLog(["content"=>"删除游戏版本配置【".$_ids."】"]);
            return json(['code' => 0, 'msg' => '删除成功']);
        }

        $postData = check_type($request->post());
        extract($postData);
        $removeResult = ServerLstGameVersion::where('_id', $_id)->delete();
        if (!$removeResult) return json(['code' => -1, 'msg' => '删除失败']);
        $this->adminLog(["content"=>"删除游戏版本配置【".$_id."】"]);
        return json(['code' => 0, 'msg' => '删除成功']);
    }

    public function switchGameVer(Request $request)
    {
        $postData = check_type($request->post());
        extract($postData);
        $updateData = check_type([$field => $value]);
        $updateResult = ServerLstGameVersion::where('_id', $_id)->update($updateData);
        if (!$updateResult) return json(['code' => -1, 'msg' => '修改失败']);
        return json(['code' => 0, 'msg' => '修改成功']);
    }
}
