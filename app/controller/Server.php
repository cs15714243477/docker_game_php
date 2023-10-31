<?php
namespace app\controller;


use app\model\ServerLstGameVersion;
use support\Request;
use app\model\ServerlstHaList;
use app\model\ServerlstStaticList;
use app\model\SystemConfig;

class Server extends Base
{
    private function _ajaxParam()
    {
        $where = [];
        $request = request();
        $getData = check_type($request->all());
        extract($getData);
        if (!empty($searchText)) {
            $where['ip'] = $searchText;
        }
        return $where;
    }

    public function serverHaList(Request $request)
    {
        if ($request->isAjax()) {
            $where = $this->_ajaxParam();
            if (!is_array($where)) return $where;
            $count = ServerlstHaList::where($where)->count();
            $list = ServerlstHaList::where($where)->orderBy('lineLevel', 'asc')->orderBy('level', 'desc')->skip($request->skip)->take($request->limit)->get();
            return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $list]);
        }
    }

    public function serverStaticList(Request $request)
    {
        if ($request->isAjax()) {
            $count = ServerlstStaticList::count();
            $list = ServerlstStaticList::orderBy('type', 'asc')->orderBy('level', 'desc')->skip($request->skip)->take($request->limit)->get();
            return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $list]);
        }
    }

    public function checkServerStatus(Request $request)
    {
        if ($request->isAjax()) {

            $postData = check_type($request->get());
            extract($postData);

            $h = 0;
            if ($ip && $port) {
                $s = msectime();
                $result = check_port($ip, $port, "1");
                $e = msectime();
                $h = ($e - $s) / 1000;
            }

            if ($result) {
                return json(['code' => 0, 'msg' => '1', 'data' => $h]);
            } else {
                return json(['code' => -1, 'msg' => '0', 'data' => $h]);
            }
        }
    }
    public function addServerLst(Request $request)
    {
        if ($request->isAjax()) {
            $postData = check_type($request->post());
            extract($postData);
            if (!$ip || !$port || !$lineLevel) {
                return json(['code' => -1, 'msg' => 'ip,端口,登记不能为空']);
            }
            $haListId = ServerlstHaList::max('haListId');
            $insertData = [
                'haListId' => intval($haListId+1),
                'ip' => $ip,
                'port' => $port,
                'lineLevel' => $lineLevel,
                'remark' => $remark,
                'status' => 0
            ];
            $insertResult = ServerlstHaList::insert($insertData);
            if (!$insertResult) return json(['code' => -1, 'msg' => '添加失败']);
            $this->adminLog(["content"=>"添加服务器【".$insertData['haListId']."】"]);
            return json(['code' => 0, 'msg' => '添加成功']);
        }
        return view('config/server/add', ['name' => '']);
    }

    public function removeServerlist(Request $request)
    {
        if ($request->post('_ids')) {
            $_ids = $request->post('_ids');
            $idsArr = explode(",", $_ids);
            $removeResult = ServerlstHaList::destroy($idsArr);
            if (!$removeResult) return json(['code' => -1, 'msg' => '删除失败']);
            $this->adminLog(["content"=>"删除服务器数据【".$_ids."】"]);
            return json(['code' => 0, 'msg' => '删除成功']);
        }

        $postData = check_type($request->post());
        extract($postData);
        $removeResult = ServerlstHaList::where('_id', $_id)->delete();
        if (!$removeResult) return json(['code' => -1, 'msg' => '删除失败']);
        $this->adminLog(["content"=>"删除服务器数据【".$_id."】"]);
        return json(['code' => 0, 'msg' => '删除成功']);
    }

    public function serverEdit(Request $request)
    {
        if ($request->isAjax()) {

            $postData = $request->post();

            extract($postData);
            $_id = !empty($_id) ? trim($_id) : false;
            $field = !empty($field) ? trim($field) : false;
            $value = isset($value) ? trim($value) : false;


            if (!$field || !$_id || $value == "") {
                return json(['code' => -1, 'msg' => '信息输入不正确']);
            }

            if ($field == 'port' || $field == 'lineLevel')
                $value = (int)$value;
            
            $updateData = [
                $field=> $value
            ];

            $promoter_update_result = ServerlstHaList::where('_id', $_id)->update($updateData);

            if (!$promoter_update_result) {
                return json(['code' => -1, 'msg' => '修改失败']);
            }else{
                return json(['code' => 0, 'msg' => '修改成功']);
            }
        }
    }

    public function loginproxy(Request $request)
    {
        if ($request->isAjax()) {
            $env = check_env($request->header());
            if (empty($env)) return json(['code' => -1, 'msg' => '此机器不允许刷新']);
            //获取所有数据
            $configs = [];
            $serverlstHaList = ServerlstHaList::select('haListId','ip','port','lineLevel','status')->orderBy('lineLevel', 'asc')->get()->toArray();
            $v = $request->get('v');
            $flag = false;
            if ($v == 'v1') {
                foreach ($serverlstHaList as $key => $value) {
                    unset($value['_id']);
                    $configs[] = $value;
                }
                $flag = create_ServerLst($configs,$env);
            } elseif ($v == 'v2') {
                $configs['s'] = $serverlstHaList;
                $configs['g'] = ServerlstStaticList::all()->toArray();
                $configs['m'] = ServerLstGameVersion::where(['gameId' => 0, 'gameVerId' => 0])->first(['version','downUrl','downUrlIos','isForce'])->toArray();
                $levelPass = $this->getLevelPass();
                if(empty($levelPass) || !is_array($levelPass)) return json(['code' => -1, 'msg' => '获取密码失败失败']);
                $flag = create_ServerLst2($configs, $env, $levelPass);
            }
            if ($flag === true) return json(['code' => 0, 'msg' => '服务器列表已刷新到云端']);

            return json(['code' => -1, 'msg' => '服务器列表刷新失败']);
        }
    }

    public function viewFile(Request $request)
    {
        if ($request->isAjax()) {
            $postData = check_type($request->get());
            extract($postData);
            if (!$s) {
                return json(['code' => -1, 'msg' => '信息输入不正确']);
            }
            $v = $request->get('v');
            $content = file_get_contents($s);
            if ($v == 'v1') {
                $e = new \Aes();
                return response($e->decrypt($content));
            } elseif ($v == 'v2') {
                $levelPass = $this->getLevelPass();
                $aes = new \Aes();
                $content = $aes->decrypt($content);
                $content = json_decode($content, true);
                /*$aes->setKey($levelPass['g']);
                $content['g'] = $aes->decrypt($content['g']);
                $content['g'] = json_decode($content['g'], true);
                $aes->setKey($levelPass['m']);
                $content['m'] = $aes->decrypt($content['m']);
                $content['m'] = json_decode($content['m'], true);*/

                foreach ($content['s'] as $key => &$item) {
                    if ($key == 0) continue;
                    $aes->setKey($levelPass[$key]);
                    $item = $aes->decrypt($item);
                    $item = json_decode($item, true);
                }
                return response(json_encode($content));
            }

        }
    }

    private function getLevelPass()
    {
        $data = SystemConfig::select('level_pass')->get();
        if (!$data) {
            return false;
        }
        return $data[0]['level_pass'];
    }

}
