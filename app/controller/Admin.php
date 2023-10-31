<?php
namespace app\controller;

use app\model\AdminGroup;
use app\model\AdminLog;
use app\model\AdminUser;
use app\model\Menu;
use PHPGangsta_GoogleAuthenticator;
use support\bootstrap\Container;
use support\Request;

class Admin extends Base
{
    public function index(Request $request)
    {
        $act = $request->get('act');
        //dd($act);
        $controller = Container::get($request->controller);
        //dd($controller);
        if (method_exists($controller, $act)) {
            $before_response = call_user_func([$controller, $act], $request);
            return $before_response;
            if ($before_response instanceof Response) {
                dd('yes');
                return $before_response;
            }
        }
        //return response('hello webman' .$act);
        //return view('index/index', ['name' => 'webman1']);
    }

    public function adminRoleList(Request $request)
    {
        if ($request->isAjax()) {
            $where = [];
            if($request->get('name')) {
                $where['name'] = $request->get('name');
            }
            $count = AdminGroup::where($where)->count();
            $list = AdminGroup::where($where)->skip($request->skip)->take($request->limit)->get();
            return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $list]);
        }

        return view('admin/role/list', ['name' => 'webman2']);
    }

    public function addAdminRole(Request $request)
    {
        if ($request->isAjax()) {
            //print_r($request->post());

            $postData = check_type($request->post());
            extract($postData);
            if (!$name) {
                return json(['code' => -1, 'msg' => '信息输入不正确']);
            }
            $groupId = AdminGroup::max('groupId');
            $insertData = [
                'groupId' => intval($groupId+1),
                'name' => $name,
                'basePurview' => '',
                'menuPurview' => '',
                'status' => $status,
                'pStatus' => 1
            ];
            $insertResult = AdminGroup::insert($insertData);
            if (!$insertResult) return json(['code' => -1, 'msg' => '添加失败']);

            //$count = AdminGroup::count();
            //$list = AdminGroup::skip(0)->take(25)->get();
            return json(['code' => 0, 'msg' => '添加成功']);
        }
        return view('admin/role/add', ['name' => 'webman2']);
    }

    public function editAdminRole(Request $request)
    {
        if ($request->isAjax()) {
            //print_r($request->post());
            $postData = check_type($request->post());
            extract($postData);
            if (!$name) {
                return json(['code' => -1, 'msg' => '信息输入不正确']);
            }
            $updateData = [
                'name' => $name,
                'status' => $status,
                'menuPurview' => $menuPurview,
            ];
            $updateResult = AdminGroup::where('_id', $_id)->update($updateData);
            if (!$updateResult) return json(['code' => -1, 'msg' => '修改失败']);
            //session(['menuPurview' => explode(",", $menuPurview)]);
            //$count = AdminGroup::count();
            //$list = AdminGroup::skip(0)->take(25)->get();
            return json(['code' => 0, 'msg' => '修改成功']);
        }
        $groupId = (int)$request->get('groupId', 0);print_r(gettype($groupId));
        $groupData = AdminGroup::where('groupId', $groupId)->first();
        return view('admin/role/edit', ['formData' => json_encode($groupData)]);
    }

    public function switchAdminRole(Request $request)
    {
        $postData = check_type($request->post());
        extract($postData);
        $updateData = [
            $field => (int)$value,
        ];
        print_r($updateData);
        $updateResult = AdminGroup::where('_id', $_id)->update($updateData);
        if (!$updateResult) return json(['code' => -1, 'msg' => '修改失败']);

        //$count = AdminGroup::count();
        //$list = AdminGroup::skip(0)->take(25)->get();
        return json(['code' => 0, 'msg' => '修改成功']);
    }

    public function removeAdminRole(Request $request)
    {
        if ($request->post('_ids')) {
            $idsArr = explode(",", $request->post('_ids'));
            $removeResult = AdminGroup::destroy($idsArr);
            if (!$removeResult) return json(['code' => -1, 'msg' => '删除失败']);
            return json(['code' => 0, 'msg' => '删除成功']);
        }

        $postData = check_type($request->post());
        extract($postData);
        $removeResult = AdminGroup::where('_id', $_id)->delete();
        if (!$removeResult) return json(['code' => -1, 'msg' => '删除失败']);
        return json(['code' => 0, 'msg' => '删除成功']);
    }



    public function adminUserList(Request $request)
    {
        if ($request->isAjax()) {
            dd('ajax');
            $where = [];
            if($request->get('userName')) {
                $where['userName'] = $request->get('userName');
            }
            $count = AdminUser::where($where)->count();
            $list = AdminUser::where($where)->skip($request->skip)->take($request->limit)->get()->toArray();

            $groupList = AdminGroup::all(['groupId', 'name'])->toArray();
            $groupList = unsetFieldFromArray($groupList);
            $list = merge_array($list, $groupList, 'groupId');

            return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $list]);
        }

        return view('admin/user/list', ['name' => 'webman2']);
    }

    public function addAdminUser(Request $request)
    {
        if ($request->isAjax()) {
            //print_r($request->post());

            $postData = check_type($request->post());
            extract($postData);
            if (!$userName) {
                return json(['code' => -1, 'msg' => '信息输入不正确']);
            }
            //$password = "123456";
            $salt = getRandStr(8);
            $loginPass = md5(md5($loginPass) . $salt);
            $ga = new PHPGangsta_GoogleAuthenticator();
            if(empty($googleSecret)){
                $googleSecret = $ga->createSecret();
            }
            $qrCode = $ga->getQRCodeGoogleUrl($userName, $googleSecret,'googleVerify');

            $userId = AdminUser::max('userId');
            $insertData = [
                'userId' => intval($userId+1),
                'groupId' => $groupId,
                'userName' => $userName,
                'loginPass' => $loginPass,
                'salt' => $salt,
                'googleSecret' => $googleSecret,
                'qrCode' => $qrCode,
                'status' => $status,
                'type' => 1,
                'isGroup' => 2,
            ];
            $insertResult = AdminUser::insert($insertData);
            if (!$insertResult) return json(['code' => -1, 'msg' => '添加失败']);
            return json(['code' => 0, 'msg' => '添加成功']);
        }
        $groupList = static::getGroupIdNameKV();

        return view('admin/user/add', ['name' => 'webman2', 'groupList' => $groupList]);
    }

    public function editAdminUser(Request $request)
    {
        if ($request->isAjax()) {
            //print_r($request->post());
            $postData = check_type($request->post());
            extract($postData);
            if (!$userName) {
                return json(['code' => -1, 'msg' => '信息输入不正确']);
            }
            $adminUser = AdminUser::find($_id);
            if (empty($adminUser)) return json(['code' => -1, 'msg' => '用户不存在']);

            $ga = new PHPGangsta_GoogleAuthenticator();
            if(empty($googleSecret)){
                $googleSecret = $ga->createSecret();
            }
            $qrCode = $ga->getQRCodeGoogleUrl($userName, $googleSecret,'googleVerify');
            $updateData = [
                'userName' => $userName,
                'status' => $status,
                'groupId' => $groupId,
                'googleSecret' => $googleSecret,
                'qrCode' => $qrCode,
            ];
            if (!empty($loginPass)) {
                $loginPass = md5(md5($loginPass) . $adminUser->salt);
                $updateData['loginPass'] = $loginPass;
            }
            $updateResult = AdminUser::where('_id', $_id)->update($updateData);
            if (!$updateResult) return json(['code' => -1, 'msg' => '修改失败']);
            return json(['code' => 0, 'msg' => '修改成功']);
        }

        $groupList = static::getGroupIdNameKV();

        $userId = (int)$request->get('userId', 0);
        $userData = AdminUser::where('userId', $userId)->first();
        return view('admin/user/edit', ['formData' => json_encode($userData), 'groupList' => $groupList]);
    }

    public function editAdminUserPwd(Request $request)
    {
        $postData = check_type($request->post());
        extract($postData);
        if (empty($userName)) {
            return json(['code' => -1, 'msg' => '操作失败']);
        }
        if (empty($oldpwd)) {
            return json(['code' => -1, 'msg' => '修改失败，请输入旧密码']);
        }
        if (empty($newpwd1)) {
            return json(['code' => -1, 'msg' => '修改失败，请输入新密码']);
        }
        if (empty($newpwd2)) {
            return json(['code' => -1, 'msg' => '修改失败，请输入确认密码']);
        }
        if ($newpwd1 !== $newpwd2) {
            return json(['code' => -1, 'msg' => '修改失败，新密码和确认密码必须一致']);
        }
        if ($oldpwd === $newpwd2) {
            return json(['code' => -1, 'msg' => '修改失败，新密码与旧密码不能相同']);
        }
        if (strlen($newpwd1) < 6 || strlen($newpwd1) > 18) {
            return json(['code' => -1, 'msg' => '修改失败，新密码长度必须为6-18']);
        }
        $adminUser = AdminUser::where(['userName' => $userName])->first();
        if (empty($adminUser)) return json(['code' => -1, 'msg' => '用户不存在']);
        $oldpwd = md5(md5($oldpwd) . $adminUser->salt);
        $newpwd1 = md5(md5($newpwd1) . $adminUser->salt);
        if ($oldpwd != $adminUser->loginPass) {
            return json(['code' => -1, 'msg' => '修改失败，旧密码输入错误']);
        }
        $adminUser->loginPass = $newpwd1;
        if ($adminUser->save()) {
            $request->session()->flush();
            return json(['code' => 0, 'msg' => '修改成功']);
        }
        return json(['code' => -1, 'msg' => '修改失败']);
    }

    public function switchAdminUser(Request $request)
    {
        $postData = check_type($request->post());
        extract($postData);
        $updateData = [
            $field => (int)$value,
        ];
        $updateResult = AdminUser::where('_id', $_id)->update($updateData);
        if (!$updateResult) return json(['code' => -1, 'msg' => '修改失败']);

        //$count = AdminGroup::count();
        //$list = AdminGroup::skip(0)->take(25)->get();
        return json(['code' => 0, 'msg' => '修改成功']);
    }

    public function removeAdminUser(Request $request)
    {
        if ($request->post('_ids')) {
            $idsArr = explode(",", $request->post('_ids'));
            $removeResult = AdminUser::destroy($idsArr);
            if (!$removeResult) return json(['code' => -1, 'msg' => '删除失败']);
            return json(['code' => 0, 'msg' => '删除成功']);
        }

        $postData = check_type($request->post());
        extract($postData);
        $removeResult = AdminUser::where('_id', $_id)->delete();
        if (!$removeResult) return json(['code' => -1, 'msg' => '删除失败']);
        return json(['code' => 0, 'msg' => '删除成功']);
    }

    public function adminLogList(Request $request)
    {
        if ($request->isAjax()) {
            $where = [];
            if($request->get('content')) {
                $where[] = ['content', 'regex', new \MongoDB\BSON\Regex($request->get('content'), 'i')];
            }
            $count = AdminLog::where($where)->count();

            //dd(AdminLog::where('content', 'regex', )->get()->toArray());
            //dd(AdminLog::where('content', 'like', '%192%')->orderBy('logId', 'desc')->skip($request->skip)->take($request->limit)->toSql());
            $list = AdminLog::where($where)->orderBy('logId', 'desc')->skip($request->skip)->take($request->limit)->get()->toArray();
            $adminUser = AdminUser::all(['userId', 'userName'])->toArray();
            $list = merge_array($list, $adminUser, 'userId');
            foreach ($list as &$item) {
                $item['opDate'] = $this->formatDate($item['opDate']);
            }

            return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $list]);
        }

        return view('admin/log/list', []);
    }

    public static function getGroupIdNameKV()
    {
        $return = [];
        $list = AdminGroup::all()->toArray();
        foreach ($list as $item) {
            $return[$item['groupId']] = $item['name'];
        }
        return $return;
    }

}
