<?php
namespace app\controller;

use app\model\ForcePublicNotice;
use app\model\PublicNotice;
use support\Request;

class Notice extends Base
{
    public function index(Request $request)
    {
        //return response('hello webman' .$act);
        //return view('index/index', ['name' => 'webman1']);
    }

    public function noticeList(Request $request)
    {
        if ($request->isAjax()) {
            $count = PublicNotice::count();
            $list = PublicNotice::orderBy('Id', 'desc')->skip($request->skip)->take($request->limit)->get();
            foreach ($list as &$item) {
                $item['startTime'] = $this->formatDate($item['startTime']);
                $item['expireTime'] = $this->formatDate($item['expireTime']);
            }
            return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $list]);
        }
    }
    public function switchPublicNotice(Request $request)
    {
        $postData = check_type($request->post());
        extract($postData);
        $updateData = check_type([$field => $value]);
        $updateResult = PublicNotice::where('_id', $_id)->update($updateData);
        if (!$updateResult) return json(['code' => -1, 'msg' => '修改失败']);
        return json(['code' => 0, 'msg' => '修改成功']);
    }
    public function addPublicNotice(Request $request)
    {
        if ($request->isAjax()) {
            print_r($request->post());

            $postData = check_type($request->post());
            extract($postData);
            if (!$title || !$content || !$expireTime) {
                return json(['code' => -1, 'msg' => '信息输入不正确']);
            }
            if (mb_strlen($title) > PublicNotice::TITLE_LENGTH) {
                return json(['code' => -1, 'msg' => '标题超出'. PublicNotice::TITLE_LENGTH .'个字符']);
            }
            $Id = PublicNotice::max('Id');
            $insertData = [
                'Id' => intval($Id+1),
                'title' => $title,
                'content' => $content,
                'type' => $type,
                'status' => $status,
                'sortId' => 100,
                'expireTime' => $this->formatTimestampToMongo(strtotime($expireTime)),
                'startTime' => $this->formatTimestampToMongo(time()),
            ];
            if ($insertData['expireTime'] < $insertData['startTime']) {
                return json(['code' => -1, 'msg' => '请确认时间信息']);
            }

            $insertResult = PublicNotice::insert($insertData);
            if (!$insertResult) return json(['code' => -1, 'msg' => '添加失败']);
            $this->adminLog(["content"=>"添加公告【".$insertData['Id']."】"]);
            return json(['code' => 0, 'msg' => '添加成功']);
        }
        return view('notice/add', ['name' => '']);
    }
    public function editPublicNotice(Request $request)
    {
        if ($request->isAjax()) {
            //print_r($request->post());
            $postData = check_type($request->post());
            extract($postData);
            if (!$title || !$content || !$expireTime) {
                return json(['code' => -1, 'msg' => '信息输入不正确']);
            }
            if (mb_strlen($title) > PublicNotice::TITLE_LENGTH) {
                return json(['code' => -1, 'msg' => '标题超出'. PublicNotice::TITLE_LENGTH .'个字符']);
            }
            $updateData = [
                'title' => $title,
                'content' => $content,
                'type' => $type,
                'status' => $status,
                'expireTime' => $this->formatTimestampToMongo(strtotime($expireTime)),
            ];
            $updateResult = PublicNotice::where('_id', $_id)->update($updateData);
            if (!$updateResult) return json(['code' => -1, 'msg' => '修改失败']);
            return json(['code' => 0, 'msg' => '修改成功']);
        }
        $_id = $request->get('_id', '');
        $formData = PublicNotice::where('_id', $_id)->first();
        $formData->expireTime = $this->formatDate($formData->expireTime);
        return view('notice/edit', ['formData' => json_encode($formData)]);
    }
    public function removePublicNotice(Request $request)
    {
        if ($request->post('_ids')) {
            $_ids = $request->post('_ids');
            $idsArr = explode(",", $_ids);
            $removeResult = PublicNotice::destroy($idsArr);
            if (!$removeResult) return json(['code' => -1, 'msg' => '删除失败']);
            $this->adminLog(["content"=>"删除公告【".$_ids."】"]);
            return json(['code' => 0, 'msg' => '删除成功']);
        }

        $postData = check_type($request->post());
        extract($postData);
        $removeResult = PublicNotice::where('_id', $_id)->delete();
        if (!$removeResult) return json(['code' => -1, 'msg' => '删除失败']);
        $this->adminLog(["content"=>"删除公告【".$_id."】"]);
        return json(['code' => 0, 'msg' => '删除成功']);
    }

    public function forceNoticeList(Request $request)
    {
        if ($request->isAjax()) {
            $count = ForcePublicNotice::count();
            $list = ForcePublicNotice::orderBy('createTime', 'desc')->skip($request->skip)->take($request->limit)->get();

            return json(['code' => 0, 'msg' => 'ok', 'count' => $count, 'data' => $list]);
        }
    }
    public function addForcePublicNotice(Request $request)
    {
        if ($request->isAjax()) {
            $postData = check_type($request->post());
            extract($postData);
            if (!$title || !$content) {
                return json(['code' => -1, 'msg' => '信息输入不正确']);
            }
            if (mb_strlen($title) > PublicNotice::TITLE_LENGTH) {
                return json(['code' => -1, 'msg' => '标题超出'. PublicNotice::TITLE_LENGTH .'个字符']);
            }
            $Id = ForcePublicNotice::max('Id');
            $insertData = [
                'Id' => intval($Id+1),
                'title' => $title,
                'content' => $content,
                'startTime' => $this->formatTimestampToMongo(time()),
            ];
            $insertResult = ForcePublicNotice::insert($insertData);
            if (!$insertResult) return json(['code' => -1, 'msg' => '添加失败']);
            $this->adminLog(["content"=>"添加在线用户强制弹窗公告【".$insertData['Id']."】"]);
            return json(['code' => 0, 'msg' => '添加成功']);
        }
        return view('notice/forceNotice/add', ['name' => '']);
    }
    public function switchForcePublicNotice(Request $request)
    {
        $postData = check_type($request->post());
        extract($postData);
        $updateData = check_type([$field => $value]);
        $updateResult = ForcePublicNotice::where('_id', $_id)->update($updateData);
        if (!$updateResult) return json(['code' => -1, 'msg' => '修改失败']);
        return json(['code' => 0, 'msg' => '修改成功']);
    }
    public function removeForcePublicNotice(Request $request)
    {
        if ($request->post('_ids')) {
            $_ids = $request->post('_ids');
            $idsArr = explode(",", $_ids);
            $removeResult = ForcePublicNotice::destroy($idsArr);
            if (!$removeResult) return json(['code' => -1, 'msg' => '删除失败']);
            $this->adminLog(["content"=>"删除公告【".$_ids."】"]);
            return json(['code' => 0, 'msg' => '删除成功']);
        }

        $postData = check_type($request->post());
        extract($postData);
        $removeResult = ForcePublicNotice::where('_id', $_id)->delete();
        if (!$removeResult) return json(['code' => -1, 'msg' => '删除失败']);
        $this->adminLog(["content"=>"删除公告【".$_id."】"]);
        return json(['code' => 0, 'msg' => '删除成功']);
    }
    public function sendForcePublicNotice(Request $request)
    {
        $postData = check_type($request->post());
        extract($postData);
        if (!$_id) return json(['code' => -1, 'msg' => '参数错误']);
        $rs = ForcePublicNotice::find($_id);
        if ($rs) {
            sendData2(['type' => 3, 'title' => $rs['title'], 'content' => $rs['content']], 'NoticeMessage');
            return json(['code' => 0, 'msg' => '发送成功']);
        }
        return json(['code' => -1, 'msg' => '发送失败']);

    }


}
