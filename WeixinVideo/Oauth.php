<?php

namespace WeixinVideo;

use WeixinVideo\Kernel\BaseApi;

class Oauth extends BaseApi
{
    public function connect()
    {
        $api_url = self::BASE_API . '/weixin/oauth/auth_login_code';
        return $this->https_post($api_url);
    }

    public function login_status($token)
    {
        $api_url = 'https://channels.weixin.qq.com/cgi-bin/mmfinderassistant-bin/auth/auth_login_status';
        $params = [
            'token' => $token,
            'timestamp' => (string)$this->getMillisecond()
        ];
        $post = [
            'rawKeyBuff' => null,
            'token' => $token,
            '_log_finder_id' => null,
            'timestamp' => (string)$this->getMillisecond(),
        ];

        $api_url .= '?' . http_build_query($params);

        return $this->https_post($api_url, $post, false, true);
    }

    public function set_oauth($params)
    {
        if (empty($params['md5file']) && !$this->remote_file_exists($params['video']))
            return ['code' => 0, 'info' => '视频文件不存在'];

        $api_url = self::BASE_API . '/weixin/oauth/set_oauth';

        return $this->https_post($api_url, $params);
    }
}
