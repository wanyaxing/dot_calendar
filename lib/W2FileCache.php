<?php
/**
 * 使用文件存储数据
 * @package W2
 * @author axing
 * @since 1.0
 * @version 1.0
 */

class W2FileCache
{
    public static $CACHE_PATH           = NULL; 

    // 从目标文件中取出数据
    public static function getValidDataFromFile($cacheFile)
    {
        if (!file_exists($cacheFile)) {
            return null;
        }
        $content = file_get_contents($cacheFile);
        $item = json_decode($content, true);

        if (is_array($item) && ($item['timeout']==0 || $item['timeout']+$item['update_time']>time())) {
            return $item['data'];
        }
        return null;
    }

    public static function isKeyExist($p_key)
    {
        $cacheFile = static::$CACHE_PATH.'/'.urlencode($p_key).'.cache';
        if (!file_exists($cacheFile)) {
            return false;
        }
        $content = file_get_contents($cacheFile);
        $item = json_decode($content, true);

        if (is_array($item) && ($item['timeout']==0 || $item['timeout']+$item['update_time']>time())) {
            return true;
        }
        return false;
    }

    // 获得用户数据
    public static function getCache($p_key)
    {
        $cacheFile = static::$CACHE_PATH.'/'.urlencode($p_key).'.cache';

        return static::getValidDataFromFile($cacheFile);
    }

    // 存储数据
    public static function setCache($p_key, $data = null, $p_timeout=0)
    {
        $cacheFile = static::$CACHE_PATH.'/'.urlencode($p_key).'.cache';
        // if ($data) {
            $item = [
                'data'=>$data,
                'key'=>$p_key,
                'timeout'=>$p_timeout,
                'update_time'=>time()
            ];
            file_put_contents($cacheFile, json_encode($item, JSON_UNESCAPED_UNICODE));
        // }
    }

    // 移除数据
    public static function removeCache($p_key)
    {
        $cacheFile = static::$CACHE_PATH.'/'.urlencode($p_key).'.cache';
        if (file_exists($cacheFile)){
            unlink($cacheFile);
        }
    }

    // 追加行式数据
    public static function appendCache($p_key, $data = null, $p_timeout=0)
    {
        $cacheFile = static::$CACHE_PATH.'/'.urlencode($p_key).'.list';
        if ($data) {
            file_put_contents($cacheFile, json_encode($data, JSON_UNESCAPED_UNICODE), FILE_APPEND);
            file_put_contents($cacheFile, "\n", FILE_APPEND);
        }
    }

    // 读取行式数据
    // 当$start为负数时，$end只能为空或负数
    // 当$start为正数时，$end可为正数、空或负数
    public static function rangeCahce($p_key, $start=null, $end=null)
    {
        $cacheFile = static::$CACHE_PATH.'/'.urlencode($p_key).'.list';
        $fh = fopen($cacheFile, 'r');
    
        $lines = [];
        if ($start===null || $start>=0){
            // 正向
            $index = 0;
            while (! feof($fh)) {
                $line = fgets($fh);
                if ($line && (is_null($start) || $index>=$start) &&  (is_null($end) || $index<=$end || $end<0) ) {
                    $lines[] = $line;
                }
            }
        }else{
            // 负向
            $filesize = filesize($cacheFile);
            $pos = $filesize - 1; // 从文件末尾开始
            $currentLine = '';

            // 从文件末尾向前逐字符读取
            while ($pos >= 0 && count($lines) < abs($start)) {
                fseek($handle, $pos, SEEK_SET);
                $char = fgetc($handle);

                if ($char === "\n") {
                    // 遇到换行符，表示一行结束
                    if (!empty($currentLine)) {
                        $lines[] = $currentLine;
                        $currentLine = '';
                    }
                } else {
                    $currentLine = $char . $currentLine; // 将字符拼接到当前行
                }

                $pos--; // 向前移动指针
            }

            // 处理文件最后一行（可能没有换行符）
            if (!empty($currentLine)) {
                $lines[] = $currentLine;
            }
        }

        if ($end<0){
            if (count($lines)+$end<0){
                $lines=[];
            }else{
                $lines=array_slice($lines, 0, $end);
            }
        }
        
        fclose($fh);

        $content = '['.implode(',', $lines).']';

        $item = json_decode($content, true);
        return $item;
    }

    // 取出相同前缀的数据
    public static function getCacheList()
    {
        $list = [];
        foreach (glob(static::$CACHE_PATH.'/*.cache') as $_file) {
            $list[] = static::getValidDataFromFile($_file);
        }
        return $list;
    }
}

//静态类的静态变量的初始化不能使用宏，只能用这样的笨办法了。
if (W2FileCache::$CACHE_PATH==null && defined('W2FILECACHE_CACHE_PATH')) {
    W2FileCache::$CACHE_PATH          = W2FILECACHE_CACHE_PATH;
}
