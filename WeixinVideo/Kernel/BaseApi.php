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

    public function __construct($config)
    {
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

    public function https_post($url, $data = [])
    {
        $data['client_authcode'] = $this->client_authcode;
        if (!isset($data['token'])) $data['token'] = !empty($_SESSION['video_token']) ? $_SESSION['video_token'] : '';
        $header = [
            'Accept:application/json', 'Content-Type:application/json'
        ];
        $this->response = $this->https_request($url, json_encode($data), $header);
        return json_decode($this->response, true);
    }

    public function https_request($url, $data = null, $headers = null)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        if (!empty($data)) {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_TIMEOUT, 60);
        if (!empty($headers)) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        }
        if (defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V4')) {
            curl_setopt($curl, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        }
        $output = curl_exec($curl);
        curl_close($curl);
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
            'seq' => $this->getMillisecond() . '.' . random(4, true),
            'weixinnum' => $block_stream['weixinnum'], 'apptype' => 251, 'filetype' => $block_stream['filetype'], 'authkey' => $authkey,
            'filekey' => $filename, 'totalsize' => $block_stream['filesize'], 'fileuuid' => $block_stream['fileuuid'],
            'rangestart' => $block_stream['start'], 'rangeend' => $block_stream['end'], 'blockmd5' => $block_stream['md5'],
        ];

        $api_url = self::BASE_API_VIDEO . '/snsuploadbig';
        return $this->https_byte($api_url, $params, $block_stream['stream'], $index);
    }

    public function https_byte($url, $options, $video_stream, $index = null)
    {
        $boundary = random(16);
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

        if (!is_null($index)) {
            $this->curl[$index] = $this->doCurl($url, $params, $request_headers);
            return true;
        } else {
            $curl = $this->doCurl($url, $params, $request_headers);
            $output = curl_exec($curl);
            if (curl_errno($curl)) $error = curl_error($curl);
            curl_close($curl);
            if (!empty($error)) return ['code' => 0, 'info' => $error];

            return $output ? json_decode($output, true) : ['code' => 0, 'info' => '文件上传失败'];
        }
    }

    public function doCurl($url, $params, $request_headers)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST, 1);
//        curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $request_headers);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_TIMEOUT, 60);
//        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 30);
//        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
//        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
//        curl_setopt($curl, CURLOPT_SSLVERSION, 4);
//        curl_setopt($curl, CURLOPT_SSL_CIPHER_LIST, 'TLSv1');
//         curl_setopt($curl, CURLOPT_NOSIGNAL, 1);
//         curl_setopt($curl, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

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
}
