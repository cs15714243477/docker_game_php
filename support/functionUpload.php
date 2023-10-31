<?php
namespace support;

use OSS\OssClient;
use OSS\Core\OssException;


class functionUpload {
    function aliOssUpload($type, $value) {

        $ossClient = new OssClient(ACCESS_IDX, ACCESS_KEY, ACCESS_ENDPOINT);
        $uploadstatus   =   array();
        if ($type == 1 && !empty($value)) {
            $objectnames  =   OBJECT_PATH.$value;
            $upfilenames  =   UPFILE_PATH.$value;
            $rs   =   $ossClient->uploadFile(ACCESS_BUCKET, $objectnames, $upfilenames);
            if($rs['url']){
                return $rs['url'];
            }else{
                return $rs;
            }

        }
        elseif ($type == 2 && $value) {
            if($handle = opendir(UPFILE_PATH)){
                while (false !== ($file = readdir($handle))){
                    if ($file != '.' && $file != '..') {
                        $upfilenames    =   UPFILE_PATH.$file;
                        $objectnames    =   OBJECT_PATH.$file;
                        $uploadstatus[]   =   $ossClient->uploadFile(ACCESS_BUCKET, $objectnames, $upfilenames);
                    }
                }
            }
            closedir($handle);
        }
        return  $uploadstatus;
    }
}


