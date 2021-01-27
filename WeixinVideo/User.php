<?php

namespace WeixinVideo;

use WeixinVideo\Kernel\BaseApi;

class User extends BaseApi
{
    /**
     * 获取身份凭证
     * @param $finderUsername
     * @return mixed
     */
    public function auth_set_finder($finderUsername, $cookie)
    {
        $api_url = self::BASE_API_CHANNELS . '/cgi-bin/mmfinderassistant-bin/auth/auth_set_finder';
        $params = [
            'finderUsername' => $finderUsername,
            'rawKeyBuff' => null,
            '_log_finder_id' => null,
            'timestamp' => (string)$this->getMillisecond(),
        ];

        $header = [
            'Accept:application/json', 'Content-Type:application/json', 'Cookie:' . $cookie
        ];
        $result = $this->https_request($api_url, json_encode($params), $header);
        return json_decode($result, true);
    }
}
