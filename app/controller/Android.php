<?php
namespace app\controller;

use support\Request;
use app\model\AndroidUser;
use app\model\GameUser;
use app\model\GameKind;

class Android extends Base
{
    private function _ajaxParam()
    {
        $where = [];
        $request = request();
        $getData = $request->all();
        extract($getData);
        if(isset($actived) && $actived !== ''){
            $where['actived'] = (int)$actived;
        }
        if(isset($actived) && $status !== ''){
            $where['status'] = (int)$status;
        }
        if(!empty($roomId)){
            $where['roomId'] = (int)$roomId;
        }
        return $where;
    }

    public function androidData(Request $request)
    {
        if ($request->isAjax()) {
            $where = $this->_ajaxParam();
            if (!is_array($where)) return $where;
            $count = AndroidUser::where($where)->count();
            $androidUserList = AndroidUser::where($where)->orderBy('roomId', 'asc')->orderBy('userId', 'asc')->skip($request->skip)->take($request->limit)->get()->toArray();
            $gameRoomInfo = getGameRoomInfo();
            $androidUserList = merge_array($androidUserList, $gameRoomInfo, 'roomId');
            foreach ($androidUserList as $key => $value) {
                $androidUserList[$key]['takeMinScore'] = $this->formatMoneyFromMongo($androidUserList[$key]['takeMinScore']);
                $androidUserList[$key]['takeMaxScore'] = $this->formatMoneyFromMongo($androidUserList[$key]['takeMaxScore']);
            }
            return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $androidUserList]);
        }
    }

    public function addAndroid(Request $request)
    {
        if ($request->isAjax()) {
            $postData = check_type($request->post());
            extract($postData);
            $minScore = !empty($minScore) ? (int)$this->formatMoneytoMongo($minScore) : 0;
            $maxScore = !empty($maxScore) ? (int)$this->formatMoneytoMongo($maxScore) : 0;
            if (!$roomId || !$addNum || !$minScore || !$maxScore) {
                return json(['code' => -1, 'msg' => '参数错误']);
            }
            for ($i = 0; $i < $addNum; $i++) {
                $androidUserId = 0;
                $gameUser = null;
                $androidUser = null;
                do {
                    $androidUserId = mt_rand(10000000, 99999999);
                    $gameUser = GameUser::where('userId', $androidUserId)->first();
                    $androidUser = AndroidUser::where('userId', $androidUserId)->first();
                }while($gameUser || $androidUser);
                $insertOneResult = [
                    'userId' => $androidUserId,
                    'roomId' => $roomId,
                    'enterTime' => '08:00:00',
                    'leaveTime' => '14:00:00',
                    'takeMinScore' => $minScore,
                    'takeMaxScore' => $maxScore,
                    'location' => '',
                    'status' => 1,
                    'actived' => 0
                ];
                $insertResult = AndroidUser::insert($insertOneResult);
                if (!$insertResult) return json(['code' => -1, 'msg' => '添加失败']);
                $this->adminLog(["content"=>"添加机器人配置【".$androidUserId."】"]);
            }
            return json(['code' => 0, 'msg' => '添加成功'.$i.'个机器人!']);
        }

        $roomList = getGameRoomInfo();
        return view('config/android/add', ['roomList' => $roomList]);
    }

    public function androidListEditScore(Request $request)
    {
        if ($request->isAjax()) {
            $postData = $request->post();
            extract($postData);
            $minScore = !empty($minScore) ? (int)$this->formatMoneytoMongo($minScore) : 0;
            $maxScore = !empty($maxScore) ? (int)$this->formatMoneytoMongo($maxScore) : 0;
            if (!$userId || !$minScore || !$maxScore) {
                return json(['code' => -1, 'msg' => '参数错误']);
            }
            $userId_array = [];
            foreach ($userId as $key => $value) {
                $userId_array[] = (int)$value;
            }

            $roomId_array = [];
            foreach ($roomId as $key => $value) {
                $roomId_array[] = (int)$value;
            }

            $updateData = [
                'takeMinScore'=> $minScore,
                'takeMaxScore'=> $maxScore
            ];
            $updateResult = AndroidUser::whereIn('userId', $userId_array)->update($updateData);
            if (!$updateResult) return json(['code' => -1, 'msg' => '更新失败']);

            $user_str = implode(",",$userId_array);
            $minScore = $this->formatMoneyFromMongo($minScore);
            $maxScore = $this->formatMoneyFromMongo($maxScore);
            $msg = "机器人配置修改携带金额：(".$user_str."),最小金币:(".$minScore.")最大金币:(".$maxScore.")";
            $this->adminLog(["content"=>"机器人配置批量【".$msg."】"]);
            return json(['code' => 0, 'msg' => '更新成功']);
        }
    }

    public function removeAndroid(Request $request)
    {
        if ($request->isAjax()) {
            $postData = $request->post();
            extract($postData);
            $userId = !empty($userId) ? $userId : false;
            if (!$userId) {
                return json(['code' => -1, 'msg' => '未选择数据']);
            }

            $userIdArray = [];
            foreach ($userId as $key => $value) {
                $userIdArray[] = (int)$value;
            }

            $removeResult = AndroidUser::whereIn('userId', $userIdArray)->delete();
            if (!$removeResult) return json(['code' => -1, 'msg' => ''.$strInfo.'失败']);
            $userStr = implode(",",$userIdArray);
            $msg = "机器人配置批量删除：(".$userStr.")";
            $this->adminLog(["content"=>"机器人配置批量【".$msg."】"]);
            return json(['code' => 0, 'msg' => '删除成功']);
        }
    }

    public function androidListStatus(Request $request)
    {
        if ($request->isAjax()) {
            $postData = $request->post();
            extract($postData);
            $type = (int) $type;
            if (!$userId || !$type) {//type:1启用2禁用
                return json(['code' => -1, 'msg' => '参数错误']);
            }

            $userId_array = [];
            foreach ($userId as $key => $value) {
                $userId_array[] = (int)$value;
            }

            $roomId_array = [];
            foreach ($roomId as $key => $value) {
                $roomId_array[] = (int)$value;
            }
            if($type == 1){
                $strInfo = "启用";
                $updateData = [
                    'status'=> 1
                ];
            }else{
                $strInfo = "禁用";
                $updateData = [
                    'status'=> 0
                ];
            }

            $updateResult = AndroidUser::whereIn('userId', $userId_array)->update($updateData);
            if (!$updateResult) return json(['code' => -1, 'msg' => ''.$strInfo.'失败']);
            $user_str = implode(",",$userId_array);
            $msg = "机器人配置批量".$strInfo."：(".$user_str.")";
            $this->adminLog(["content"=>"机器人配置批量".$strInfo."【".$msg."】"]);
            return json(['code' => 0, 'msg' => ''.$strInfo.'成功']);
        }
    }

    public function androidTakeOn(Request $request)
    {
        if ($request->isAjax()) {
            $game_room_arrays = GameKind::select('gameId','gameName','rooms.roomId','rooms.roomName','rooms.androidCount')->orderBy('sort', 'asc')->orderBy('rooms.roomId', 'asc')->get()->toArray();
            $gameRoomInfo = [];
            if ($game_room_arrays) {
                foreach ($game_room_arrays as $key => $value) {
                    foreach ($value['rooms'] as $key1 => $value1) {
                        $gameRoomInfo[] = ['gameId' => $value['gameId'], 'gameName' => $value['gameName'],
                            'roomId' => $value1['roomId'], 'roomName' => $value1['roomName'],
                            'androidCount' => $value1['androidCount']];
                    }
                }
            }
            return json(['code' => 0, 'msg' => 'ok', 'count' => count($gameRoomInfo), 'data' => $gameRoomInfo]);
        }
        return view('config/android/androidTakeOn');
    }

    public function androidListUpdateCount(Request $request)
    {
        if ($request->isAjax()) {
            $postData = check_type($request->post());
            extract($postData);
            if (!$gameId || !$roomId ) {
                return json(['code' => -1, 'msg' => '参数错误']);
            }
           // return json(['code' => 0, 'msg' => '更新成功']);
        }
    }

    public function androidTakeOnEdit(Request $request)
    {
        if ($request->isAjax()) {
            $postData = $request->post();
            extract($postData);
            $gameId = !empty($gameId) ? (int) $gameId : false;
            $roomId = !empty($roomId) ? (int) $roomId : false;
            $field = !empty($field) ? trim($field) :false;
            $value = isset($value) ? (int) $value :0;

            if (!$gameId || !$roomId || !$field) {
                return json(['code' => -1, 'msg' => '参数错误']);
            }

            $where = [];
            $where['gameId'] = $gameId;
            $where['rooms.roomId'] = $roomId;
            $updateData = [
                'rooms.$.'.$field.''=>$value
            ];
            $updateResult = GameKind::where($where)->update($updateData);
            if (!$updateResult) return json(['code' => -1, 'msg' => '更新失败']);
            $msg = "gameId为".$gameId."roomId为".$roomId."的".$field."字段更改为".$value;
            $this->adminLog(["content"=>"机器人上桌配置更新【".$msg."】"]);
            return json(['code' => 0, 'msg' => '更新成功']);
        }
    }

}
