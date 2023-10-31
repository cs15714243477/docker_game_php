<?php
namespace app\controller;

use app\model\ClubGameKind;
use support\Request;
use app\model\GameKind;

class Stock extends Base
{
    public function stockConfig(Request $request)
    {
        if ($request->isAjax()) {
            $gameRoomArrays = GameKind::select('gameId','gameName','rooms.roomId','rooms.roomName','rooms.totalStock','rooms.totalStockLowerLimit','rooms.totalStockHighLimit','rooms.systemKillAllRatio','rooms.systemReduceRatio','rooms.changeCardRatio','rooms.totalStockSecondLowerLimit','rooms.totalStockSecondHighLimit')
            ->orderBy('sort', 'asc')->get()->toArray();
            $gameRoomInfo = [];
            if ($gameRoomArrays) {
                foreach ($gameRoomArrays as $key => $value) {
                    foreach ($value['rooms'] as $key1 => $value1) {
                        $gameRoomInfo[] = [
                            'gameId' => $value['gameId'],
                            'gameName' => $value['gameName'],
                            'roomId' => $value1['roomId'], 'roomName' => $value1['roomName'],
                            'totalStock' => $this->formatMoneyFromMongo($value1['totalStock']),
                            'totalStockLowerLimit' => isset($value1['totalStockLowerLimit']) ? $this->formatMoneyFromMongo($value1['totalStockLowerLimit']) : 0,
                            'totalStockHighLimit' => isset($value1['totalStockHighLimit']) ? $this->formatMoneyFromMongo($value1['totalStockHighLimit']) : 0,
                            'totalStockSecondLowerLimit' => isset($value1['totalStockSecondLowerLimit']) ? $this->formatMoneyFromMongo($value1['totalStockSecondLowerLimit']) : 0,
                            'totalStockSecondHighLimit' => isset($value1['totalStockSecondHighLimit']) ? $this->formatMoneyFromMongo($value1['totalStockSecondHighLimit']) : 0,
                            'systemKillAllRatio' => $value1['systemKillAllRatio'],
                            'systemReduceRatio' => $value1['systemReduceRatio'],
                            'changeCardRatio' => $value1['changeCardRatio']
                        ];
                    }
                }
            }
            return json(['code' => 0, 'msg' => 'ok', 'count' => count($gameRoomInfo), 'data' => $gameRoomInfo]);
        }
    }

    public function stockConfigEdit(Request $request)
    {
        if ($request->isAjax()) {
            $postData = $request->post();
            extract($postData);
            $gameId = !empty($gameId) ? (int)$gameId : false;
            $roomId = !empty($roomId) ? (int)$roomId : false;
            $field = !empty($field) ? trim($field) : false;
            $value = isset($value) ? (int)$value : 0;
            if (!$gameId || !$roomId || !$field) {
                return json(['code' => -1, 'msg' => '参数错误']);
            }
            if (in_array($field, ['totalStockLowerLimit', 'totalStockHighLimit', 'totalStockSecondLowerLimit', 'totalStockSecondHighLimit'])) {
                $value = $this->formatMoneytoMongo($value);
            }
            $where = [];

            $where['gameId'] = $gameId;
            $where['rooms.roomId'] = $roomId;

            $updateData = [
                'rooms.$.'.$field.''=>$value
            ];
            $updateResult = GameKind::where($where)->update($updateData);
            if (!$updateResult) return json(['code' => -1, 'msg' => '修改失败']);
            return json(['code' => 0, 'msg' => '修改成功']);
        }
    }

    public function clubStockConfig(Request $request)
    {
        if ($request->isAjax()) {
            $gameRoomArrays = ClubGameKind::select('gameId','gameName','rooms.roomId','rooms.roomName','rooms.totalStock','rooms.totalStockLowerLimit','rooms.totalStockHighLimit','rooms.systemKillAllRatio','rooms.systemReduceRatio','rooms.changeCardRatio','rooms.totalStockSecondLowerLimit','rooms.totalStockSecondHighLimit')
                ->orderBy('sort', 'asc')->get()->toArray();
            $gameRoomInfo = [];
            if ($gameRoomArrays) {
                foreach ($gameRoomArrays as $key => $value) {
                    foreach ($value['rooms'] as $key1 => $value1) {
                        $gameRoomInfo[] = [
                            'gameId' => $value['gameId'],
                            'gameName' => $value['gameName'],
                            'roomId' => $value1['roomId'], 'roomName' => $value1['roomName'],
                            'totalStock' => $this->formatMoneyFromMongo($value1['totalStock']),
                            'totalStockLowerLimit' => isset($value1['totalStockLowerLimit']) ? $this->formatMoneyFromMongo($value1['totalStockLowerLimit']) : 0,
                            'totalStockHighLimit' => isset($value1['totalStockHighLimit']) ? $this->formatMoneyFromMongo($value1['totalStockHighLimit']) : 0,
                            'totalStockSecondLowerLimit' => isset($value1['totalStockSecondLowerLimit']) ? $this->formatMoneyFromMongo($value1['totalStockSecondLowerLimit']) : 0,
                            'totalStockSecondHighLimit' => isset($value1['totalStockSecondHighLimit']) ? $this->formatMoneyFromMongo($value1['totalStockSecondHighLimit']) : 0,
                            'systemKillAllRatio' => $value1['systemKillAllRatio'],
                            'systemReduceRatio' => $value1['systemReduceRatio'],
                            'changeCardRatio' => $value1['changeCardRatio']
                        ];
                    }
                }
            }
            return json(['code' => 0, 'msg' => 'ok', 'count' => count($gameRoomInfo), 'data' => $gameRoomInfo]);
        }
    }

    public function clubStockConfigEdit(Request $request)
    {
        if ($request->isAjax()) {
            $postData = $request->post();
            extract($postData);
            $gameId = !empty($gameId) ? (int)$gameId : false;
            $roomId = !empty($roomId) ? (int)$roomId : false;
            $field = !empty($field) ? trim($field) : false;
            $value = isset($value) ? (int)$value : 0;
            if (!$gameId || !$roomId || !$field) {
                return json(['code' => -1, 'msg' => '参数错误']);
            }
            if (in_array($field, ['totalStockLowerLimit', 'totalStockHighLimit', 'totalStockSecondLowerLimit', 'totalStockSecondHighLimit'])) {
                $value = $this->formatMoneytoMongo($value);
            }
            $where = [];

            $where['gameId'] = $gameId;
            $where['rooms.roomId'] = $roomId;

            $updateData = [
                'rooms.$.'.$field.''=>$value
            ];
            $updateResult = ClubGameKind::where($where)->update($updateData);
            if (!$updateResult) return json(['code' => -1, 'msg' => '修改失败']);
            return json(['code' => 0, 'msg' => '修改成功']);
        }
    }
}
