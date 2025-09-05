<?php
/**
 * 简易版钉钉的CalDAV客户端，仅测试了钉钉日程的兼容性
 */

class DingtalkCalDAVClient {
    private $baseUrl;
    private $username;
    private $password;
    private $curl;
    private $calendarHomeSet;
    private $calendarPaths=[];
    
    public function __construct($username, $password) {
        $this->baseUrl = 'https://calendar.dingtalk.com';
        $this->calendarHomeSet = '/dav/'.$username.'/'; // 钉钉固定的calendar-home-set路径
        $this->username = $username;
        $this->password = $password;
        $this->initCurl();
        $this->discoverCalendars(); // 初始化时发现日历
    }
    
    /**
     * 初始化cURL会话
     */
    private function initCurl() {
        $this->curl = curl_init();
        curl_setopt_array($this->curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_USERPWD => $this->username . ':' . $this->password,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'PHP Enhanced CalDAV Client/1.0'
        ]);
    }

    /**
     * 发现可用日历
     */
    public function discoverCalendars() {
        if (empty($this->calendarHomeSet)) {
            throw new Exception("未找到用户日历信息，请先调用discoverUserPrincipal方法");
        }
        if (!empty($this->calendarPaths)) {
            return $this->calendarPaths;
        }
        $url = $this->baseUrl . $this->calendarHomeSet;
        curl_setopt_array($this->curl, [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => 'PROPFIND',
            CURLOPT_POSTFIELDS => '<?xml version="1.0" encoding="utf-8" ?>
<d:propfind xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav" xmlns:cs="http://calendarserver.org/ns/">
  <d:prop>
    <d:resourcetype />
    <d:displayname />
    <c:supported-calendar-component-set />
    <cs:getctag />
  </d:prop>
</d:propfind>',CURLOPT_HTTPHEADER => [
                'Content-Type: application/xml; charset=utf-8',
                'Depth: 1'
            ]
        ]);
        
        $response = curl_exec($this->curl);
        $httpCode = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
        
        if ($httpCode !== 207) {
            echo '<textarea>',$response,'</textarea>';
            throw new Exception("获取用户日历列表失败，HTTP状态码: " . $httpCode);
        }
        
        $this->calendarPaths = $this->parseCalendars($response);

        return $this->calendarPaths;
    }
    
    /**
     * 解析日历发现响应
     */
    private function parseCalendars($response) {
        $xml = simplexml_load_string($response);
        $xml->registerXPathNamespace('d', 'DAV:');
        
        $calendars = [];
        $responses = $xml->xpath('//d:response');
        
        foreach ($responses as $response) {
            $response->registerXPathNamespace('d', 'DAV:');
            
            $href = (string)$response->xpath('./d:href')[0];
            
            // 跳过日历home set本身
            if ($href === $this->calendarHomeSet || $href === $this->calendarHomeSet . '/') {
                continue;
            }
            
            $response->registerXPathNamespace('cs', 'http://calendarserver.org/ns/');
            
            $displayName = $response->xpath('./d:propstat/d:prop/d:displayname');
            $ctag = $response->xpath('//cs:getctag');
            
            // 跳过Outbox等非日历资源
            if (!empty($displayName) && in_array($displayName[0], ['Outbox', 'Inbox', 'Notifications'])) {
                continue;
            }
            $calendars[] = [
                'href' => $href,
                'displayname' => !empty($displayName) ? (string)$displayName[0] : '未命名日历',
                'ctag' => !empty($ctag) ? (string)$ctag[0] : null
            ];
        }
        
        return $calendars;
    }

    public function getAllEvents($start = null, $end = null) {
        $start = date('Ymd\THis\Z', ($start ? strtotime($start) : strtotime())-date('Z'));
        $end = date('Ymd\THis\Z', ($end ? strtotime($end) : strtotime('+7 days'))-date('Z'));
        $events = [];
        foreach ($this->calendarPaths as $calendar) {
            # code...
            $events = array_merge($events,$this->getEvents($calendar['href'], $start,$end));
        }
        // 此处按事件的开始时间排序
        usort($events, function($a, $b) {
            return $a['DTSTART']>$b['DTSTART']?1:-1;
        });
        return $events;
    }
    
    /**
     * 获取指定日历中的事件
     */
    public function getEvents($calendarPath, $start = null, $end = null) {
        $url = $this->baseUrl . $calendarPath;
        
        // 设置时间范围过滤器
        $timeFilter = '';
        if ($start && $end) {
            $timeFilter = '<C:time-range start="' . $start . '" end="' . $end . '"/>';
        }
        $caldavbody='<?xml version="1.0" encoding="utf-8"?>
<C:calendar-query xmlns:D="DAV:" xmlns:C="urn:ietf:params:xml:ns:caldav">
  <D:prop>
    <D:getetag/>
    <C:calendar-data/>
  </D:prop>
  <C:filter>
    <C:comp-filter name="VCALENDAR">
      <C:comp-filter name="VEVENT">
        ' . $timeFilter . '
      </C:comp-filter>
    </C:comp-filter>
  </C:filter>
</C:calendar-query>';
        curl_setopt_array($this->curl, [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => 'REPORT',
            CURLOPT_POSTFIELDS => $caldavbody,CURLOPT_HTTPHEADER => [
                'Content-Type: application/xml; charset=utf-8',
                'Depth: 1'
            ]
        ]);
        
        $response = curl_exec($this->curl);
        $httpCode = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
        
        if ($httpCode !== 207) {
            echo '$url:c',$url,'';
            echo '$caldavbody<textarea>',$caldavbody,'</textarea>';
            echo '$response<textarea>',$response,'</textarea>';
            return [["SUMMARY"=>"REPORT请求失败，HTTP状态码: " . $httpCode]];
            // throw new Exception("REPORT请求失败，HTTP状态码: " . $httpCode);
        }
        
        return $this->parseEvents($response);
    }
    
    /**
     * 解析事件响应
     */
    private function parseEvents($response) {
        $xml = simplexml_load_string($response);
        $xml->registerXPathNamespace('d', 'DAV:');
        
        $events = [];
        $responses = $xml->xpath('//d:response');
        
        foreach ($responses as $response) {
            $response->registerXPathNamespace('c', 'urn:ietf:params:xml:ns:caldav');
            $calendarData = $response->xpath('.//c:calendar-data');
            if (!empty($calendarData)) {
                $icalData = (string)$calendarData[0];
                $events = array_merge($events,$this->parseIcal($icalData));
            }
        }
        return $events;
    }
    
    /**
     * 解析iCalendar数据
     */
    private function parseIcal($icalData) {
        $events = [];
        
        // print_r($icalData);
        // echo '<br/>';   
        // 使用更健壮的iCalendar解析方法
        $lines = explode("\n", $icalData);
        $event = [];
        $inEvent = false;
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (strpos($line, 'BEGIN:VEVENT') === 0) {
                $inEvent = true;
                $event = [];
            } elseif (strpos($line, 'END:VEVENT') === 0) {
                $inEvent = false;
                if (!empty($event)) {
                    $events[] = $event;
                }
            } elseif ($inEvent) {
                // 处理可能的多行内容
                if (preg_match('/^(.+?):(.*)$/', $line, $matches)) {
                    $key = $matches[1];
                    $value = $matches[2];
                    if (strpos($key, ';') !== false) {
                        // 去掉属性参数，只保留主键
                        $parmstr = substr($key, strpos($key, ';')+1);
                        parse_str($parmstr,$parms);
                        if (isset($parms['TZID'])){
                            $value = strtotime($value . ' ' . $parms['TZID']);
                        }
                        $key = substr($key, 0, strpos($key, ';'));
                    }
                    $event[$key] = $value;
                } elseif (!empty($key) && !empty($line)) {
                    // 续行内容
                    $event[$key] .= $line;
                }
            }
        }
        
        return $events;
    }
    
    /**
     * 关闭cURL资源
     */
    public function close() {
        if ($this->curl) {
            curl_close($this->curl);
            $this->curl = null;
        }
    }
    
    public function __destruct() {
        $this->close();
    }
}
