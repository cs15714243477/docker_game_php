<?php
namespace app\controller;

use app\model\GameKind;

use support\Request;

class Game extends Base
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

    public function gameList(Request $request)
    {
        if ($request->isAjax()) {
            $where = $this->_ajaxParam();
            if (!is_array($where)) return $where;
            $count = GameKind::where($where)->count();
            $list = GameKind::where($where)->orderBy('sort', 'asc')->get()->toArray();
            return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $list]);
        }

        return view('game/list', ['name' => '']);
    }

    public function switchGame(Request $request)
    {
        $postData = check_type($request->post());
        extract($postData);
        $updateData = check_type([$field => $value]);
        $updateResult = GameKind::where('_id', $_id)->update($updateData);
        if (!$updateResult) return json(['code' => -1, 'msg' => '修改失败']);
        return json(['code' => 0, 'msg' => '修改成功']);
    }
}
