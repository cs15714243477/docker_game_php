<?php
namespace app\controller;

use app\model\AdminGroup;
use PHPGangsta_GoogleAuthenticator;
use support\Request;
use Respect\Validation\Validator as v;
use app\model\AdminUser;
use app\model\AdminSafeIp;

class Login
{
    public function login(Request $request)
    {
        //dd($request->post());
       /* $data = v::input($request->post(), [
            'safeCode' => v::number()->length(6, 6)->setName('安全码'),
            'userName' => v::alnum()->length(4, 16)->setName('用户名'),
            'password' => v::length(4, 64)->setName('密码')
        ]);*/
        $remoteIP = GetIP();
        $rs = AdminSafeIp::where('ip', $remoteIP)->first();
        //print_r($remoteIP);//print_r($rs->ip);
//        if (!$rs) {
//            return json(['code' => -1, 'msg' => 'IP未授权'.$remoteIP]);
//        }
        $userName = !empty($request->post('userName')) ? trim($request->post('userName')) : "";
        $password = !empty($request->post('password')) ? trim($request->post('password')) : "";
        $safeCode = !empty($request->post('safeCode')) ? trim($request->post('safeCode')) : "";
        $user = AdminUser::where('userName', $userName)->first();
        if (!$user) return json(['code' => -1, 'msg' => '账户错误']);
        $password = md5(md5($password) . $user->salt);
        if (strcasecmp($password, $user->loginPass) != 0) return json(['code' => -1, 'msg' => '密码错误']);
        if ($user->status != 1) return json(['code' => -1, 'msg' => '账号已冻结']);
        $adminGroup = AdminGroup::where('groupId', $user->groupId)->first();

        if ($adminGroup)
        {
            //if($safeCode != 147852 || $user->userName != 'admin'){
            if(true){
                $ga = new PHPGangsta_GoogleAuthenticator();
                $googleSecret = $user->googleSecret;//$ga->createSecret();//MNJFIE3I3I7UKL2E
                $checkResult = $ga->verifyCode($googleSecret, $safeCode, 2);
//                if(!$checkResult)
//                {
//                    return json(['code' => -1, 'msg' => '令牌错误']);
//                }
            }

            /*$_SESSION['userId'] = $user->userId;
            $_SESSION['userName'] = $user->userName;
            $_SESSION['groupId'] = $user->groupId;
            $_SESSION['isGroup'] = $user->isGroup;
            $_SESSION['menuPurview'] = explode(",", $adminGroup->menuPurview);*/
            $session = $request->session();
            $session->put(['userId' => $user->userId,
                'userName' => $user->userName,
                'groupId' => $user->groupId,
                'isGroup' => $user->isGroup,
                'menuPurview' => explode(",", $adminGroup->menuPurview)]);

            return json(['code' => 0, 'msg' => '登录成功！']);
        }
        return json(['code' => -1, 'msg' => '账号或者密码错误！']);
    }

    public function logout(Request $request)
    {
        $request->session()->flush();
        return json(['code' => 0, 'msg' => '注销成功！']);
    }
    
}
