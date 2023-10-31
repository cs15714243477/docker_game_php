<?php
namespace app\controller;

use app\model\TaskConfig;
use app\model\GameKind;
use app\model\Counters;
use support\Request;
use support\Db;

class Task extends Base
{
    public function taskList(Request $request)
    {
        if ($request->isAjax()) {
            $data = check_type($request->get());
            extract($data);
            if (!$type) {
                return json(['code' => -1, 'msg' => '信息输入不正确']);
            }
            $count = TaskConfig::where(['taskType'=>$type])->count();
            $list = TaskConfig::where(['taskType'=>$type])->orderBy('Id', 'desc')->skip($request->skip)->take($request->limit)->get()->toArray();
            $game_info = getGameInfo();
            $game_room_info = getGameRoomInfo();
            $list = merge_array($list, $game_info, 'gameId');
            $list = merge_array($list, $game_room_info, 'roomId');

            foreach ($list as $key =>$value) {
                if ($type == 1) $list[$key]['reachNum'] = $this->formatMoneyFromMongo($value['reachNum']);
                $list[$key]['rewardScore'] = $this->formatMoneyFromMongo($value['rewardScore']);
                $list[$key]['createTime'] = $this->formatDate($value['createTime']);
            }
            
            return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $list]);
        }
    }

    public function addTask(Request $request)
    {
        $getData = check_type($request->get());
        extract($getData);
        if ($request->isAjax()) {
            $postData = check_type($request->post());
            extract($postData);
            if (!$gameId || !$roomId || !$title) {
                return json(['code' => -1, 'msg' => '信息输入不正确']);
            }
            if (mb_strlen($title) > 20) {
                return json(['code' => -1, 'msg' => '标题最大支持20个字符']);
            }
            //$Id = TaskConfig::max('Id');

            $Id = $this->getNextSequence('taskId');
            $reachNum = (int)$reachNum;
            if ($taskType == 1) $reachNum = $this->formatMoneytoMongo($reachNum);

            $rewardScore = $this->formatMoneytoMongo($rewardScore);
            $insertData = [
                'Id' => $Id,
                'title' => $title,
                'gameId' => $gameId,
                'roomId' => $roomId,
                'taskType' => $taskType,
                'taskCycle' => $taskCycle,
                'sortId' => $sortId,
                'reachNum' => (int)$reachNum,
                'rewardScore' => $rewardScore,
                'icon' => '',
                'createTime' => $this->formatTimestampToMongo(time()),
            ];

            $insertResult = TaskConfig::insert($insertData);
            if (!$insertResult) return json(['code' => -1, 'msg' => '添加失败']);
            $this->adminLog(["content"=>"添加流水任务【".$insertData['Id']."】"]);
            return json(['code' => 0, 'msg' => '添加成功']);
        }
        $game_kind_arrays = GameKind::where(['status' => 1, 'rooms.status' => 1])->orderBy('sort', 'asc')->get();
        $taskCycle = TaskConfig::TASKCYCLE;
        $taskType = TaskConfig::TASKTYPE;
        $type = intval($type);
        foreach ($taskType as $key => $value) {
            if ($type != $key) unset($taskType[$key]);
        }
        return view('config/task/add', ['gameList' => $game_kind_arrays,'taskCycle' => $taskCycle,'taskType' => $taskType]);
    }
    public function removeTask(Request $request)
    {
        if ($request->post('_ids')) {
            $_ids = $request->post('_ids');
            $idsArr = explode(",", $_ids);
            $removeResult = TaskConfig::destroy($idsArr);
            if (!$removeResult) return json(['code' => -1, 'msg' => '删除失败']);
            $this->adminLog(["content"=>"删除流水任务【".$_ids."】"]);
            return json(['code' => 0, 'msg' => '删除成功']);
        }

        $postData = check_type($request->post());
        extract($postData);
        $removeResult = TaskConfig::where('_id', $_id)->delete();
        if (!$removeResult) return json(['code' => -1, 'msg' => '删除失败']);
        $this->adminLog(["content"=>"删除流水任务【".$_id."】"]);
        return json(['code' => 0, 'msg' => '删除成功']);
    }

    public function getNextSequence($name) {
        $name_seq = Counters::where('name', $name)->first();
        $seq = intval($name_seq['seq']+1);
        $updateData = [
            'seq'=> $seq
        ];
        $updateResult = Counters::where('name', $name)->update($updateData);
        if($updateResult){
            return $name_seq['seq'];
        }else{
            return json(['code' => -1, 'msg' => '添加失败']);
        }
    }
}
