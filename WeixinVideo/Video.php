<?php

namespace WeixinVideo;

use WeixinVideo\Kernel\BaseApi;

class Video extends BaseApi
{

    /**
     * @title 发布
     */
    public function post_create($params)
    {
        $api_url = self::BASE_API . '/weixin/upload/set_post_create';

        return $this->https_post($api_url, $params);
    }

    /**
     * @title 上传
     */
    public function sns_upload_big($params)
    {
        $api_url = self::BASE_API . '/weixin/upload/sns_upload_big';

        return $this->https_post($api_url, $params);
    }

    /**
     * @title 发布
     */
    public function post_create_local($params)
    {
        $api_url = self::BASE_API . '/weixin/upload/set_post_create_local';

        return $this->https_post($api_url, $params);
    }

    /**
     * @title 凭证
     */
    public function helper_upload($params)
    {
        $api_url = self::BASE_API . '/weixin/upload/helper_upload';

        return $this->https_post($api_url, $params);
    }

    /**
     * @title 本地上传
     */
    public function upload($url, $params)
    {
        // 准备分块上传
        if (!$this->remote_file_exists($url))
            return ['code' => 0, 'info' => '文件不存在，请检查配置'];
        $stream = @file_get_contents($url);
        if (!$stream || !$file_size = strlen($stream))
            return ['code' => 0, 'info' => '文件不能访问'];
        // 缓存临时文件
        $this->mkdirs($params['temp_path']);
        $params['fileuuid'] = $this->getUUID();

        $upload_params = $this->helper_upload([
            'token' => $params['token'], 'raw_key_buff' => $params['raw_key_buff'], 'log_finder_id' => $params['log_finder_id']
        ]);
        if ($upload_params['errCode'] != 0)
            return ['code' => 0, 'info' => '权限不足'];
        $authkey = $upload_params['data']['authKey'];
        $weixinnum = $upload_params['data']['uin'];

        // 准备上传
        if ($file_size > $this->packet && $block_num = ceil($file_size / $this->packet)) {
            // 写入缓存
            $file_temp = $params['temp_path'] . $params['token'];
            @file_put_contents($file_temp, $stream);
            // 文件分块数据流
            $file_packet = [];
            $handle = fopen($file_temp, 'r');
            while (false != ($content = fread($handle, $this->packet))) {
                $file_packet[] = $content;
            }
            fclose($handle);
            // 删除缓存
            unlink($file_temp);

            for ($i = 0; $i < $block_num; $i++) {
                $start = $i * $this->packet;
                $end = $start + $this->packet;
                $end = ($end > $file_size) ? $file_size : $end;
                $md5file_stream = md5($file_packet[$i]);
                $block_stream = [
                    'start' => $start,
                    'end' => $end - 1,
                    'stream' => $file_packet[$i],
                    'md5' => $md5file_stream,
                    'fileuuid' => $params['fileuuid'],
                    'filesize' => $file_size,
                    'filetype' => $params['filetype'],
                    'weixinnum' => $weixinnum
                ];
                $result = $this->send_file($authkey, $block_stream, $params['filename']);
                if ($result['code'] === 0) {
                    $idx = 0;
                    while (true) {
                        if ($idx === 2) return ['code' => 0, 'info' => $i];
                        $result = $this->send_file($authkey, $block_stream, $params['filename']);
                        if ($result['retcode'] === 0) break;
                        $idx++;
                    }
                }
                usleep(800000);
            }
        } else {
            $block_stream = [
                'start' => 0,
                'end' => $file_size - 1,
                'stream' => $stream,
                'md5' => $params['md5file'],
                'fileuuid' => $params['fileuuid'],
                'filesize' => $file_size,
                'filetype' => $params['filetype'],
                'weixinnum' => $weixinnum,
            ];
            $result = $this->send_file($authkey, $block_stream, $params['filename']);
            if ($result['code'] === 0) return $result;
        }
        if ($result['retcode'] === 0 && isset($result['fileurl'])) return ['code' => 1, 'fileurl' => $result['fileurl'], 'filesize' => $file_size];
        else return ['code' => 0, 'info' => '上传文件失败'];
    }

    /**
     * @title 多线程本地上传
     */
    public function upload_multi($url, $params)
    {
        $result = null;
        // 准备分块上传
        if (!$this->remote_file_exists($url))
            return ['code' => 0, 'info' => '文件不存在，请检查配置'];
        $stream = @file_get_contents($url);
        if (!$stream || !$file_size = strlen($stream))
            return ['code' => 0, 'info' => '文件不能访问'];
        // 缓存临时文件
        $this->mkdirs($params['temp_path']);
        $params['fileuuid'] = $this->getUUID();

        $upload_params = $this->helper_upload([
            'token' => $params['token'], 'raw_key_buff' => $params['raw_key_buff'], 'log_finder_id' => $params['log_finder_id']
        ]);
        if ($upload_params['errCode'] != 0)
            return ['code' => 0, 'info' => '权限不足'];
        $authkey = $upload_params['data']['authKey'];
        $weixinnum = $upload_params['data']['uin'];

        if ($file_size > $this->packet && $block_num = intval(ceil($file_size / $this->packet))) {
            // 写入缓存
            $file_temp = $params['temp_path'] . $params['token'];
            @file_put_contents($file_temp, $stream);
            // 文件分块数据流
            $file_packet = [];
            $handle = fopen($file_temp, 'r');
            while (false != ($content = fread($handle, $this->packet))) {
                $file_packet[] = $content;
            }
            fclose($handle);
            // 删除缓存
            unlink($file_temp);

            $mh = curl_multi_init();
            $this->curl = null;
            $index = 0;
            $index_key = 0;
            $end_num = intval($block_num - 1);
            for ($i = 0; $i < $block_num; $i += $this->task_count) {
                $index_key = $i;
                for ($i2 = 0; $i2 < $this->task_count; $i2++) {
                    $start = $index * $this->packet;
                    $end = $start + $this->packet;
                    $end = ($end > $file_size) ? $file_size : $end;
                    $md5file_stream = md5($file_packet[$index]);
                    $block_stream = [
                        'start' => $start,
                        'end' => $end - 1,
                        'stream' => $file_packet[$index],
                        'md5' => $md5file_stream,
                        'fileuuid' => $params['fileuuid'],
                        'filesize' => $file_size,
                        'filetype' => $params['filetype'],
                        'weixinnum' => $weixinnum,
                    ];

                    $this->send_file($authkey, $block_stream, $params['filename'], $index);
                    curl_multi_add_handle($mh, $this->curl[$index]);
                    $index++;
                    if ($index === $block_num) break;
                }

                retry:
                $active = null;
                do {
                    $mrc = curl_multi_exec($mh, $active);
                } while ($mrc == CURLM_CALL_MULTI_PERFORM);

                while ($active && $mrc == CURLM_OK) {
                    if (curl_multi_select($mh) != -1) {
                        do {
                            $mrc = curl_multi_exec($mh, $active);
                        } while ($mrc == CURLM_CALL_MULTI_PERFORM);
                    }
                }

                $index = ($index >= $block_num) ? $block_num : $index;

                for ($index_key; $index_key < $index; $index_key++) {
                    if (!isset($this->curl[$index_key])) continue;
                    $output = curl_multi_getcontent($this->curl[$index_key]);
                    if (empty($output)) goto retry;
                    curl_close($this->curl[$index_key]);
                    curl_multi_remove_handle($mh, $this->curl[$index_key]);
                    if ($end_num === $index_key) $result = json_decode($output, true);
                }
            }

            curl_multi_close($mh);
        } else {
            $block_stream = [
                'start' => 0,
                'end' => $file_size - 1,
                'stream' => $stream,
                'md5' => $params['md5file'],
                'fileuuid' => $params['fileuuid'],
                'filesize' => $file_size,
                'filetype' => $params['filetype'],
                'weixinnum' => $weixinnum,
            ];
            $result = $this->send_file($authkey, $block_stream, $params['filename']);
            if ($result['code'] === 0) return $result;
        }

        if ($result['retcode'] === 0 && isset($result['fileurl'])) return ['code' => 1, 'fileurl' => $result['fileurl'], 'filesize' => $file_size];
        else return ['code' => 0, 'info' => '上传文件失败'];
    }

}
