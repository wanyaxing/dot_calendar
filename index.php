<?php 
ini_set("display_errors", "On");
date_default_timezone_set('Asia/Shanghai');
error_reporting(E_ALL);

require __DIR__.'/config.php';

// 此处添加token验证，防止被他人恶意调用
if (!isset($_GET['token']) || $_GET['token']!=DOT_CALENDAR_TOKEN){
    echo '404';
    exit;
}

require __DIR__.'/lib/DotCalendar.php';
require __DIR__.'/lib/DingtalkCalDAVClient.php';

// 可以自定义todolist，格式为字符串数组（空行表示横线）
$todolist = [];

if (isset($_GET['calendar']) && $_GET['calendar']){
    // 此为举例可以通过参数传入日程数据并拼接到todolist中
    // 比如此处传入calendar参数，格式为json字符串每个日程包含time（时间戳，单位毫秒）、timeLabel（时间标签）、title（标题）、location（地点）四个字段
    // 例如：$calendar = '[{"time":1672531200000,"timeLabel":"2023-01-01 00:00","title":"会议","location":"会议室"},{"time":1672534800000,"timeLabel":"2023-01-01 01:00","title":"会议","location":"会议室"}]';
    $calendar = json_decode($_GET['calendar'] ?? '[]', true);
    $isNextDay = false;
    $nextDayTime = strtotime(date('Y-m-d 23:59:59')) * 1000;
    foreach ($calendar as $i => $event) {
        if (!$isNextDay && $event['time'] > $nextDayTime) {
            $todolist[]='';
            $isNextDay = true;
        }
    
        $todo=$event['timeLabel'].' '.$event['title'] . ($event['location'] ? '(' . $event['location'] . ')' : '');
        if (count($todolist)==0 || $todolist[count($todolist)-1] != $todo){
            $todolist[] = $todo;
        }
    }
} else if (defined('DINGTALK_CALDAV_USER') && defined('DINGTALK_CALDAV_PASS') && DINGTALK_CALDAV_USER && DINGTALK_CALDAV_PASS){
    // 如果没有传入calendar参数，则从钉钉日历获取最近两天的日程
    // 此处使用钉钉的caldav接口初始化数据示例，用户名和密码为钉钉下获得的授权码
    // 也可以自行使用其他方式来配置这个todolist，只要是字符串数组即可
    $client = new DingtalkCalDAVClient(DINGTALK_CALDAV_USER,DINGTALK_CALDAV_PASS);
    $events = $client->getAllEvents(
        date('Y-m-d H:i:s',strtotime('-2 hours')), // 开始时间
        date('Y-m-d 00:00:00',strtotime('+2 days'))  // 结束时间
    );
    $index_day=date('d');
    foreach ($events as $event) {
        if (isset($event['SUMMARY']) && isset($event['DTSTART'])) {
            if (date('d',$event['DTSTART'])!=$index_day){
                $todolist[] = "";
                $index_day = date('d',$event['DTSTART']);
            }
            $todolist[] = date('H:i',$event['DTSTART']).' '.$event['SUMMARY'];
        }
    }
    $client->close();
}

// 此处配置和风天气的API获取天气数据，参数为经纬度、和风天气的key，摘录电子墨水屏幕的设备码
$dotCalendar = new DotCalendar(DOT_DEVICE_ID,DOT_APP_KEY,CONFIG_USER_LOCAITON,QWEATHER_HOST,QWEATHER_KEY,$todolist);

// 此处调用和风天气的API获取天气数据，并创建图像，并输出（如果有dotsync参数则输出同时提交到摘录电子墨水屏幕）
$dotCalendar->loadWeatherData()
            ->createImage()
            ->output($_GET['dotsync']??0);


