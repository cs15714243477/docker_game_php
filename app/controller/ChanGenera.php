<?php
namespace app\controller;

use support\Request;
use app\model\Promoter;
use app\model\GameChannelMo;

class ChanGenera extends Base
{
    private function _ajaxParam()
    {
        $where = [];
        $request = request();
        $getData = check_type($request->all());
        extract($getData);
        $where['pid'] = 0;
        if (!empty($searchType) && !empty($searchText)) {
            if($searchType == 1) {
                $where['promoterId'] = (int)$searchText;
            }
        }
        return $where;
    }

    public function chanGeneraList(Request $request)
    {
        if ($request->isAjax()) {
            $where = $this->_ajaxParam();
            if (!is_array($where)) return $where;
            $count = Promoter::where($where)->count();
            $promoterList = Promoter::where($where)->select('promoterId','promoterName','channelId','account','rate','curRate')->skip($request->skip)->take($request->limit)->get()->toArray();
            $gameChannelList = GameChannelMo::get()->toArray();
            $promoterList = merge_array($promoterList, $gameChannelList, 'channelId', 'id');
            return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $promoterList]);
        }
    }
}
