<?php

namespace WeixinVideo\Kernel;


/**
 * 内核
 * Class BaseApi
 * @package WeixinVideo\Weixin\Kernel
 */
class BaseApi
{
    const BASE_API = "http://cloud.huijibaoman.com";
    const BASE_API_CHANNELS = "https://channels.weixin.qq.com";
    const BASE_API_VIDEO = "https://finder.video.qq.com";
    public $client_authcode = null;
    public $response = null;
    public $curl = null;
    public $packet = 524288;
    public $task_count = 3;
    public $is_command = false;

    public function __construct($config)
    {
        $this->is_command = !empty($config['is_command']) ? !!$config['is_command'] : false;
        $this->client_authcode = $config['client_authcode'];
    }

    public function toArray()
    {
        return $this->response ? json_decode($this->response, true) : true;
    }

    public function https_get($url, $params = [])
    {
        $params['client_authcode'] = $this->client_authcode;
        if ($params) {
            $url = $url . '?' . http_build_query($params);
        }
        $this->response = $this->https_request($url);
        $result = json_decode($this->response, true);
        return $result['data'];
    }

    public function https_post($url, $data = [], $is_cloud = true, $is_header = false)
    {
        if ($is_cloud === true) {
            $data['client_authcode'] = $this->client_authcode;
            if (!isset($data['token'])) $data['token'] = !empty($_SESSION['video_token']) ? $_SESSION['video_token'] : '';
        }
        $header = [
            'Accept:application/json', 'Content-Type:application/json'
        ];
        $this->response = $this->https_request($url, json_encode($data), $header, $is_header);
        return false === $is_header ? json_decode($this->response, true) : $this->response;
    }

    public function https_request($url, $data = null, $headers = null, $is_header = false, $timeout = 60)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        if (!empty($data)) {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 20);
        curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
        if ($is_header === true) curl_setopt($curl, CURLOPT_HEADER, 1);
        if (!empty($headers)) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        }
        $output = curl_exec($curl);
        curl_close($curl);
        if ($is_header === true) $output = $this->ihttp_response_parse($output);

        return $output;
    }

    public function remote_file_exists($url_file)
    {
        $url_file = trim($url_file);
        if (empty($url_file)) return false;

        $url_arr = parse_url($url_file);
        if (!is_array($url_arr) || empty($url_arr)) return false;

        $file_headers = @get_headers($url_file);
        if ($file_headers[0] == 'HTTP/1.1 404 Not Found') return false;
        else return true;
    }

    public function send_file($authkey, $block_stream, $filename = '0.mp4', $index = null)
    {
        $params = [
            'seq' => $this->getMillisecond() . '.' . $this->random(4, true),
            'weixinnum' => $block_stream['weixinnum'], 'apptype' => 251, 'filetype' => $block_stream['filetype'], 'authkey' => $authkey,
            'filekey' => $filename, 'totalsize' => $block_stream['filesize'], 'fileuuid' => $block_stream['fileuuid'],
            'rangestart' => $block_stream['start'], 'rangeend' => $block_stream['end'], 'blockmd5' => $block_stream['md5'],
        ];

        $api_url = self::BASE_API_VIDEO . '/snsuploadbig';
        return $this->https_byte($api_url, $params, $block_stream['stream'], $index);
    }

    public function https_byte($url, $options, $video_stream, $index = null)
    {
        $boundary = $this->random(16);
        $params = "------WebKitFormBoundary{$boundary}\r\n"
            . "Content-Disposition: form-data; name=\"ver\"\r\n"
            . "\r\n1\r\n"
            . "------WebKitFormBoundary{$boundary}\r\n"
            . "Content-Disposition: form-data; name=\"seq\"\r\n"
            . "\r\n"
            . $options['seq'] . "\r\n"
            . "------WebKitFormBoundary{$boundary}\r\n"
            . "Content-Disposition: form-data; name=\"weixinnum\"\r\n"
            . "\r\n"
            . $options['weixinnum'] . "\r\n"
            . "------WebKitFormBoundary{$boundary}\r\n"
            . "Content-Disposition: form-data; name=\"apptype\"\r\n"
            . "\r\n"
            . $options['apptype'] . "\r\n"
            . "------WebKitFormBoundary{$boundary}\r\n"
            . "Content-Disposition: form-data; name=\"filetype\"\r\n"
            . "\r\n"
            . $options['filetype'] . "\r\n"
            . "------WebKitFormBoundary{$boundary}\r\n"
            . "Content-Disposition: form-data; name=\"authkey\"\r\n"
            . "\r\n"
            . $options['authkey'] . "\r\n"
            . "------WebKitFormBoundary{$boundary}\r\n"
            . "Content-Disposition: form-data; name=\"hasthumb\"\r\n"
            . "\r\n0\r\n"
            . "------WebKitFormBoundary{$boundary}\r\n"
            . "Content-Disposition: form-data; name=\"filekey\"\r\n"
            . "\r\n"
            . $options['filekey'] . "\r\n"
            . "------WebKitFormBoundary{$boundary}\r\n"
            . "Content-Disposition: form-data; name=\"totalsize\"\r\n"
            . "\r\n"
            . $options['totalsize'] . "\r\n"
            . "------WebKitFormBoundary{$boundary}\r\n"
            . "Content-Disposition: form-data; name=\"fileuuid\"\r\n"
            . "\r\n"
            . $options['fileuuid'] . "\r\n"
            . "------WebKitFormBoundary{$boundary}\r\n"
            . "Content-Disposition: form-data; name=\"rangestart\"\r\n"
            . "\r\n"
            . $options['rangestart'] . "\r\n"
            . "------WebKitFormBoundary{$boundary}\r\n"
            . "Content-Disposition: form-data; name=\"rangeend\"\r\n"
            . "\r\n"
            . $options['rangeend'] . "\r\n"
            . "------WebKitFormBoundary{$boundary}\r\n"
            . "Content-Disposition: form-data; name=\"blockmd5\"\r\n"
            . "\r\n"
            . $options['blockmd5'] . "\r\n"
            . "------WebKitFormBoundary{$boundary}\r\n"
            . "Content-Disposition: form-data; name=\"forcetranscode\"\r\n"
            . "\r\n0\r\n"
            . "------WebKitFormBoundary{$boundary}\r\n"
            . "Content-Disposition: form-data; name=\"filedata\"; filename=\"blob\"\r\n"
            . "Content-Type: application/octet-stream\r\n"
            . "\r\n"
            . $video_stream . "\r\n"
            . "------WebKitFormBoundary{$boundary}--";

        $first_newline = strpos($params, "\r\n");
        $multipart_boundary = substr($params, 2, $first_newline - 2);
        $request_headers = array();
        $request_headers[] = 'content-length: ' . strlen($params);
        $request_headers[] = 'content-type: multipart/form-data; boundary=' . $multipart_boundary;
        $request_headers[] = 'accept: application/json, text/plain, */*';
        $request_headers[] = 'accept-encoding: gzip, deflate, br';
        $request_headers[] = 'accept-language: zh-CN,zh;q=0.9';
        $request_headers[] = 'origin: https://channels.weixin.qq.com';
        $request_headers[] = 'referer: https://channels.weixin.qq.com/';
        $request_headers[] = 'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/88.0.4324.104 Safari/537.36';

        if (function_exists('curl_init') && function_exists('curl_exec')) {
            if (!is_null($index)) {
                $this->curl[$index] = $this->doCurl($url, $params, $request_headers);
                return true;
            } else {
                $curl = $this->doCurl($url, $params, $request_headers);
                $output = curl_exec($curl);
                if (curl_errno($curl)) $error = curl_error($curl);
                true === $this->is_command || curl_close($curl);
                if (!empty($error)) return ['code' => 0, 'info' => $error];

                return $output ? json_decode($output, true) : ['code' => 0, 'info' => '文件上传失败'];
            }
        } else return ['code' => 0, 'info' => '未安装CURL扩展'];
    }

    function ihttp_response_parse($data, $chunked = false)
    {
        $rlt = array();

        $pos = strpos($data, "\r\n\r\n");
        $split1[0] = substr($data, 0, $pos);
        $split1[1] = substr($data, $pos + 4, strlen($data));

        $split2 = explode("\r\n", $split1[0], 2);
        preg_match('/^(\S+) (\S+) (.*)$/', $split2[0], $matches);
        $rlt['code'] = !empty($matches[2]) ? $matches[2] : 200;
        $rlt['status'] = !empty($matches[3]) ? $matches[3] : 'OK';
        $rlt['responseline'] = !empty($split2[0]) ? $split2[0] : '';
        $rlt['headers'] = [];
        $header = explode("\r\n", $split2[1]);
        $isgzip = false;
        $ischunk = false;
        foreach ($header as $v) {
            $pos = strpos($v, ':');
            $key = substr($v, 0, $pos);
            $value = trim(substr($v, $pos + 1));
            if (isset($rlt['headers'][$key]) && is_array($rlt['headers'][$key])) {
                $rlt['headers'][$key][] = $value;
            } elseif (!empty($rlt['headers'][$key])) {
                $temp = $rlt['headers'][$key];
                unset($rlt['headers'][$key]);
                $rlt['headers'][$key][] = $temp;
                $rlt['headers'][$key][] = $value;
            } else {
                $rlt['headers'][$key] = $value;
            }
            if (!$isgzip && strtolower($key) == 'content-encoding' && strtolower($value) == 'gzip') {
                $isgzip = true;
            }
            if (!$ischunk && strtolower($key) == 'transfer-encoding' && strtolower($value) == 'chunked') {
                $ischunk = true;
            }
        }
        if ($chunked && $ischunk) {
            $rlt['content'] = $this->ihttp_response_parse_unchunk($split1[1]);
        } else {
            $rlt['content'] = $split1[1];
        }
        if ($isgzip && function_exists('gzdecode')) {
            $rlt['content'] = gzdecode($rlt['content']);
        }

        $rlt['meta'] = $data;
        if ($rlt['code'] == '100') {
            return $this->ihttp_response_parse($rlt['content']);
        }
        return $rlt;
    }

    function ihttp_response_parse_unchunk($str = null)
    {
        if (!is_string($str) or strlen($str) < 1) {
            return false;
        }
        $eol = "\r\n";
        $add = strlen($eol);
        $tmp = $str;
        $str = '';
        do {
            $tmp = ltrim($tmp);
            $pos = strpos($tmp, $eol);
            if ($pos === false) {
                return false;
            }
            $len = hexdec(substr($tmp, 0, $pos));
            if (!is_numeric($len) or $len < 0) {
                return false;
            }
            $str .= substr($tmp, ($pos + $add), $len);
            $tmp = substr($tmp, ($len + $pos + $add));
            $check = trim($tmp);
        } while (!empty($check));
        unset($tmp);
        return $str;
    }

    function ihttp_parse_url($url, $set_default_port = false)
    {
        if (empty($url)) return false;
        $urlset = parse_url($url);
        if (!empty($urlset['scheme']) && !in_array($urlset['scheme'], array('http', 'https'))) {
            return ['code' => 0, 'info' => '只能使用 http 及 https 协议'];
        }
        if (empty($urlset['path'])) {
            $urlset['path'] = '/';
        }
        if (!empty($urlset['query'])) {
            $urlset['query'] = "?{$urlset['query']}";
        }
        if (!strpos($url, 'https://') === false && !extension_loaded('openssl')) {
            if (!extension_loaded("openssl")) {
                return ['code' => 0, 'info' => '请开启您PHP环境的openssl'];
            }
        }

        if ($set_default_port && empty($urlset['port'])) {
            $urlset['port'] = $urlset['scheme'] == 'https' ? '443' : '80';
        }
        return $urlset;
    }

    function ihttp_socketopen($hostname, $port = 80, &$errno, &$errstr, $timeout = 15)
    {
        $fp = '';
        if (function_exists('fsockopen')) {
            $fp = @fsockopen($hostname, $port, $errno, $errstr, $timeout);
        } elseif (function_exists('pfsockopen')) {
            $fp = @pfsockopen($hostname, $port, $errno, $errstr, $timeout);
        } elseif (function_exists('stream_socket_client')) {
            $fp = @stream_socket_client($hostname . ':' . $port, $errno, $errstr, $timeout);
        }
        return $fp;
    }

    function ihttp_build_httpbody($url, $body, $request_headers)
    {
        $urlset = $this->ihttp_parse_url($url, true);
        if (!empty($urlset['ip'])) {
            $extra['ip'] = $urlset['ip'];
        }
        $fdata = "POST {$urlset['path']}{$urlset['query']} HTTP/1.1\r\n";
        $fdata .= "Accept: application/json, text/plain, */*\r\n";
        $fdata .= "Accept-Language: zh-cn\r\n";
        $fdata .= "Host: {$urlset['host']}\r\n";
        $fdata .= "User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64; rv:9.0.1) Gecko/20100101 Firefox/9.0.1\r\n";
        if (function_exists('gzdecode')) {
            $fdata .= "Accept-Encoding: gzip, deflate\r\n";
        }
        if (!empty($request_headers) && is_array($request_headers)) {
            foreach ($request_headers as $opt => $value) {
                $fdata .= $value . "\r\n";
            }
        }
        $fdata .= "Connection: close\r\n\r\n";
//        var_dump($fdata);
        $fdata .= $body . "\r\n";
        return $fdata;
    }

    public function doCurl($url, $params, $request_headers, $timeout = 60)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $request_headers);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 20);
        curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);

        return $curl;
    }

    public function getMillisecond()
    {
        list($t1, $t2) = explode(' ', microtime());
        return (float)sprintf('%.0f', (floatval($t1) + floatval($t2)) * 1000);
    }

    public function mkdirs($path)
    {
        if (!is_dir($path)) {
            $this->mkdirs(dirname($path));
            mkdir($path);
        }

        return is_dir($path);
    }

    public function getUUID()
    {
        // Generate 128 bit random sequence
        $randmax_bits = strlen(base_convert(mt_getrandmax(), 10, 2));  // how many bits is mt_getrandmax()
        $x = '';
        while (strlen($x) < 128) {
            $maxbits = (128 - strlen($x) < $randmax_bits) ? 128 - strlen($x) : $randmax_bits;
            $x .= str_pad(base_convert(mt_rand(0, pow(2, $maxbits)), 10, 2), $maxbits, "0", STR_PAD_LEFT);
        }

        // break into fields
        $a = array();
        $a['time_low_part'] = substr($x, 0, 32);
        $a['time_mid'] = substr($x, 32, 16);
        $a['time_hi_and_version'] = substr($x, 48, 16);
        $a['clock_seq'] = substr($x, 64, 16);
        $a['node_part'] = substr($x, 80, 48);

        // Apply bit masks for "random or pseudo-random" version per RFC
        $a['time_hi_and_version'] = substr_replace($a['time_hi_and_version'], '0100', 0, 4);
        $a['clock_seq'] = substr_replace($a['clock_seq'], '10', 0, 2);

        // Format output
        return sprintf('%s-%s-%s-%s-%s',
            str_pad(base_convert($a['time_low_part'], 2, 16), 8, "0", STR_PAD_LEFT),
            str_pad(base_convert($a['time_mid'], 2, 16), 4, "0", STR_PAD_LEFT),
            str_pad(base_convert($a['time_hi_and_version'], 2, 16), 4, "0", STR_PAD_LEFT),
            str_pad(base_convert($a['clock_seq'], 2, 16), 4, "0", STR_PAD_LEFT),
            str_pad(base_convert($a['node_part'], 2, 16), 12, "0", STR_PAD_LEFT));
    }

    public function random($length, $numeric = FALSE)
    {
        $seed = base_convert(md5(microtime()), 16, $numeric ? 10 : 35);
        $seed = $numeric ? (str_replace('0', '', $seed) . '012340567890') : ($seed . 'zZ' . strtoupper($seed));
        if ($numeric) {
            $hash = '';
        } else {
            $hash = chr(rand(1, 26) + rand(0, 1) * 32 + 64);
            $length--;
        }
        $max = strlen($seed) - 1;
        for ($i = 0; $i < $length; $i++) {
            $hash .= $seed{mt_rand(0, $max)};
        }
        return $hash;
    }
}
