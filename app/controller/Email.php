<?php
namespace app\controller;

use app\model\EmailMo;
use app\model\EmailDel;
use app\model\GameUser;
use support\Request;

class Email extends Base
{
    private function _ajaxParam()
    {
        $where = [];
        $request = request();
        $getData = check_type($request->all());
        extract($getData);
        if (!empty($searchText)) {
            $where['userId'] = (int)$searchText;
        }
        return $where;
    }

    public function emailList(Request $request)
    {
        if ($request->isAjax()) {
            $count = EmailMo::count();
            $list = EmailMo::orderBy('Id', 'desc')->skip($request->skip)->take($request->limit)->get();
            foreach ($list as &$item) {
                $item['rewardScore'] = $this->formatMoneyFromMongo($item['rewardScore']);
                $item['sendTime'] = $this->formatDate($item['sendTime']);
                $item['expireTime'] = $this->formatDate($item['expireTime']);
            }
            return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $list]);
        }
    }
    public function addEmail(Request $request)
    {
        if ($request->isAjax()) {
            $postData = check_type($request->post());
            extract($postData);
            $expireTime = "2038-01-01 23:59:59";
            if (!$sendTime || !$expireTime || !$title || !$content) {
                return json(['code' => -1, 'msg' => '信息输入不正确']);
            }

            if($userId !=0){
                $game_user_count = GameUser::where('userId', $userId)->first();
                if (empty($game_user_count)) {
                    return json(['code' => -1, 'msg' => '用户不存在']);
                }
            }

            if($expireTime && strtotime($expireTime) < time()){
                return json(['code' => -1, 'msg' => '过期时间设置无效']);
            }

            $Id = EmailMo::max('Id');
            if( $rewardScore == 0){
                $mailType = 1;
            }else{
                $rewardScore = $this->formatMoneytoMongo($rewardScore);
                $mailType = 2;
            }
            $insertData = [
                'Id' => intval($Id+1),
                'userId' => $userId,
                'title' => $title,
                'content' => $content,
                'rewardScore' => $rewardScore,
                'mailType' => $mailType,
                'mailDelType' => 2,
                'adminId' => session('userId'),
                'senderName' => session('userName'),
                'sendTime' => $this->formatTimestampToMongo(strtotime($sendTime)),
                'expireTime' => $this->formatTimestampToMongo(strtotime($expireTime)),
                'status' => 0
            ];
            $insertResult = EmailMo::insert($insertData);
            if (!$insertResult) return json(['code' => -1, 'msg' => '添加失败']);
            $this->adminLog(["content"=>"添加邮件【".$insertData['Id']."】"]);
            sendData2(['userId'=>$userId], "NewMailMessage");
            return json(['code' => 0, 'msg' => '添加成功']);
        }
        return view('config/email/add', ['name' => '']);
    }
    public function editEmail(Request $request)
    {
        if ($request->isAjax()) {
            $postData = check_type($request->post());
            extract($postData);
            if (!$sendTime || !$expireTime || !$title || !$content) {
                return json(['code' => -1, 'msg' => '信息输入不正确']);
            }
            if( $rewardScore == 0){
                $mailType = 1;
            }else{
                $rewardScore = $this->formatMoneytoMongo($rewardScore);
                $mailType = 2;
            }
            $updateData = [
                'userId'=> $userId,
                'title'=> $title,
                'content'=> $content,
                'rewardScore' => $rewardScore,
                'mailType' => $mailType,
                'adminId' => session('userId'),
                'senderName' => session('userName'),
                'sendTime' => $this->formatTimestampToMongo(strtotime($sendTime)),
                'expireTime' => $this->formatTimestampToMongo(strtotime($expireTime))
            ];
            $updateResult = EmailMo::where('_id', $_id)->update($updateData);
            if (!$updateResult) return json(['code' => -1, 'msg' => '修改失败']);
            $this->adminLog(["content"=>"修改邮件【".$_id."】"]);
            sendData2(['userId'=>$userId], "NewMailMessage");
            return json(['code' => 0, 'msg' => '修改成功']);
        }
        $_id = $request->get('_id', '');
        $formData = EmailMo::where('_id', $_id)->first();
        $formData->sendTime = $this->formatDate($formData->sendTime);
        $formData->expireTime = $this->formatDate($formData->expireTime);
        $formData->rewardScore = $this->formatMoneyFromMongo($formData->rewardScore);
        return view('config/email/edit', ['formData' => json_encode($formData)]);
    }
    public function removeEmail(Request $request)
    {
        if ($request->post('_ids')) {
            $_ids = $request->post('_ids');
            $idsArr = explode(",", $_ids);
            $removeResult = EmailMo::destroy($idsArr);
            if (!$removeResult) return json(['code' => -1, 'msg' => '删除失败']);
            $this->adminLog(["content"=>"删除邮件【".$_ids."】"]);
            return json(['code' => 0, 'msg' => '删除成功']);
        }

        $postData = check_type($request->post());
        extract($postData);
        $removeResult = EmailMo::where('_id', $_id)->delete();
        if (!$removeResult) return json(['code' => -1, 'msg' => '删除失败']);
        $this->adminLog(["content"=>"删除邮件【".$_id."】"]);
        return json(['code' => 0, 'msg' => '删除成功']);
    }

    public function emailDelList(Request $request)
    {
        if ($request->isAjax()) {
            $where = $this->_ajaxParam();
            $count = EmailDel::where($where)->count();
            $list = EmailDel::where($where)->orderBy('createTime', 'desc')->skip($request->skip)->take($request->limit)->get();
            foreach ($list as &$item) {
                $item['sendTime'] = $this->formatDate($item['sendTime']);
                $item['expireTime'] = $this->formatDate($item['expireTime']);
                $item['rewardScore'] = $this->formatMoneyFromMongo($item['rewardScore']);
            }
            return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $list]);
        }
    }
}
