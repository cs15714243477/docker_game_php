<?php

class Aes
{
    private $iv = '';
    public $key = '';
    private $method = '';

    function __construct()
    {
        /**
         * $aes 内容
         * 'aes' => [
         * 'key' => '309w4wb42104160d2g6806lv1ki60f98',//aes加密盐
         * 'method' => 'AES-256-CBC',//加密方式
         * 'hex' => '00000000000000000000000000000000',//生成iv参数用,貌似是为了安卓ios相互兼容取的这个值
         * ],           可根据每个用户账号的不同设置不同的key字段
         */
        $aes = [
            'key' => '2020102423102412',
            'method' => 'AES-128-CBC',
            'hex' => '00000000000000000000000000000000'
        ];
        if (strlen($aes['key']) == 32) $aes['method'] = 'AES-256-CBC';
        $this->method = $aes['method'];
        //$this->key = hash('sha256', $aes['key'], true);
        $this->key =  $aes['key'];
        $this->iv = $this->hex2iv($aes['hex']);
    }

    public function setKey($key)
    {
        $this->key = $key;
    }

    public function resetKey()
    {
        $this->key = '2020102423102412';
    }

    /**
     * 加密
     * @param $str
     * @return string
     */
    public function encrypt($str)
    {
        /*$str = $this->padZero($str);
        $encrypt = openssl_encrypt($str, $this->method, $this->key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, $this->iv);
        return bin2hex($encrypt);*/
        $encrypt = openssl_encrypt($str, $this->method, $this->key, OPENSSL_RAW_DATA, $this->iv);
        return base64_encode($encrypt);
    }

    /**
     * 解密
     * @param $str
     * @return string
     */
    public function decrypt($str)
    {
        $decrypted = openssl_decrypt(base64_decode($str), $this->method, $this->key, OPENSSL_RAW_DATA, $this->iv);
        //$decrypted = openssl_decrypt(hex2bin($str), $this->method, $this->key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, $this->iv);
        //$decrypted = $this->unpadZero($decrypted);
        return $decrypted;
    }

    /**
     * 生成iv参数
     * @param $hex
     * @return string
     */
    private function hex2iv($hex)
    {
        $iv = '';
        for ($i = 0; $i < strlen($hex) - 1; $i += 2) {
            $iv .= chr(hexdec($hex[$i] . $hex[$i + 1]));
        }
        return $iv;
    }


    /**
     * padZero
     * @param $str
     * @param $blocksize
     * @return string
     */
    private function padZero($str, $blocksize = 16)
    {
        $pad = $blocksize - (strlen($str) % $blocksize);
        if ($pad == 16) return $str;
        return $str . str_repeat("\0", $pad);
    }

    /**
     * unpadZero
     * @param $str
     * @return string
     */
    private function unpadZero($str)
    {
        return rtrim($str, "\0");

    }
}