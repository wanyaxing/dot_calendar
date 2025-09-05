<?php
class DotCalendar {
    // 地图宽度，固定296
    const BG_WIDTH = 296;
    // 地图高度，固定152
    const BG_HEIGHT = 152;
    // 日历区域起始位置，用于微调
    const CALENDAR_START_LEFT = 2;
    const CALENDAR_START_TOP = -3;
    // 日历区域网格宽度
    const GRID_WIDTH = 18;
    // 日历区域网格高度
    const GRID_HEIGHT = 27;
    // 日历区域标题高度
    const HEADER_HEIGHT = 17;
    // 日历区域标题字体大小
    const HEADER_FONT_SIZE = 9;
    // 日历区域日期字体大小
    const DAY_FONT_SIZE = 9;
    // 日历区域日期图标与日期的间距
    const DAY_ICON_MARGIN = 2;
    // 日历区域日期图标字体大小
    const ICON_FONT_SIZE = 10;
    // 待办事项字体大小
    const TODO_FONT_SIZE = 9;
    // 待办事项最大行数
    const TODO_MAX_LINE = 5;
    
    // 定位经纬度
    public $location;
    public $qweatherKey;
    public $dotDeviceId;
    public $dotAppkey;
    
    // 代办提醒
    public $calendar = [];

    // 字体位置（公共属性，允许外界直接改）
    public $textFont;
    public $iconFont;
    public $numberFont;
    

    private $params = [];
    private $data = [];
    private $image;
    private $blackColor;
    private $whiteColor;
    
    public function __construct($dotDeviceId='',$dotAppkey='',$location = '',$qweatherHost='',$qweatherKey='',$todolist=[]) {
        $this->location = $location;
        $this->qweatherHost = $qweatherHost;
        $this->qweatherKey = $qweatherKey;
        $this->dotDeviceId = $dotDeviceId;
        $this->dotAppkey = $dotAppkey;
        $this->textFont = __DIR__.'/../fonts/fusion-pixel-12px-monospaced-zh_hans.ttf';
        $this->numberFont = __DIR__.'/../fonts/fusion-pixel-12px-monospaced-zh_hans.ttf';
        $this->iconFont = __DIR__.'/../fonts/qweather-icons.ttf';
        
        // 初始化日历数据
        $this->todolist = $todolist;
    }

    public function get_warning($location) {
        // https://dev.qweather.com/docs/api/warning/weather-warning/
        $result= json_decode(static::curl_ungzip('https://'.$this->qweatherHost.'/v7/warning/now?location='.$location.'&key='.$this->qweatherKey),true);
        $extraType = [];
        $firstWarning='';
        $secondWarning='';
        $moreWarning=[];
        if (isset($result['warning']) && count($result['warning'])>0){
            foreach ($result['warning'] as $warning) {
                if ($warning['typeName']=='高温'){
                    $firstWarning=$warning['typeName'];
                    continue;
                }else if ($secondWarning=='' && mb_strlen($warning['typeName'])==2){
                    $secondWarning=$warning['typeName'];
                    continue;
                }else{
                    $moreWarning[] = '['.$warning['typeName'].']';
                }
            }
        }
        return [$firstWarning,$secondWarning,implode('',$moreWarning)];
    }
    public function get_recent($location) {
        $recent= json_decode(static::curl_ungzip('https://'.$this->qweatherHost.'/v7/minutely/5m?location='.$location.'&key='.$this->qweatherKey),true);
        $extraInfo = $recent['summary'] ?? '信息获取失败';

        if ($extraInfo!='未来两小时无降水'){
            $extraInfo = preg_replace_callback('/(\d+)分钟后开始/',function($matches){
                    return '将';
                },$extraInfo);
            $extraInfo = preg_replace_callback('/^(\d+)分钟后/',function($matches){
                    return '';
                },$extraInfo);
            $extraInfo = preg_replace_callback('/(\d+)分钟后/',function($matches){
                    return date('H:i',time()+($matches[1]*60)) ;
                },$extraInfo);
            $extraInfo = preg_replace_callback('/持续(\d+)分钟/',function($matches){
                    return '持续到'.date('H:i',time()+($matches[1]*60)) ;
                },$extraInfo);
            $extraInfo = preg_replace_callback('/明天/',function($matches){
                    return date('明天d日',time()+60*60*24) ;
                },$extraInfo);
            $extraInfo = preg_replace_callback('/未来(一|二|两|三)小时/',function($matches){
                    return date('H:i',time()+(['一'=>1,'两'=>2,'二'=>2,'三'=>3][$matches[1]]*60*60)).'前' ;
                },$extraInfo);
        }
        return $extraInfo;
    }

    public function qweatherGetdaily($location,$days = '30d') {
        return json_decode(static::curl_ungzip('https://'.$this->qweatherHost.'/v7/weather/'.$days.'?location='.$location.'&key='.$this->qweatherKey),true);
    }
    
    public function loadWeatherData($days = '30d') {
        $this->data = $this->qweatherGetdaily($this->location, $days);
        $this->processWeatherData();
        return $this;
    }

    public static function get_qweather_font($code){
        // https://cdn.jsdelivr.net/npm/qweather-icons@1.3.0/font/qweather-icons.css
        $weatherCode=[
             '100'=>"\u{f101}"
            ,'101'=>"\u{f102}"
            ,'102'=>"\u{f103}"
            ,'103'=>"\u{f104}"
            ,'104'=>"\u{f105}"
            ,'150'=>"\u{f106}"
            ,'151'=>"\u{f107}"
            ,'152'=>"\u{f108}"
            ,'153'=>"\u{f109}"
            ,'300'=>"\u{f1d5}"
            ,'301'=>"\u{f1d6}"
            ,'302'=>"\u{f1d7}"
            ,'303'=>"\u{f1d8}"
            ,'304'=>"\u{f1d9}"
            ,'305'=>"\u{f1da}"
            ,'306'=>"\u{f1db}"
            ,'307'=>"\u{f1dc}"
            ,'308'=>"\u{f1dd}"
            ,'309'=>"\u{f1de}"
            ,'310'=>"\u{f1df}"
            ,'311'=>"\u{f1e0}"
            ,'312'=>"\u{f1e1}"
            ,'313'=>"\u{f1e2}"
            ,'314'=>"\u{f1e3}"
            ,'315'=>"\u{f1e4}"
            ,'316'=>"\u{f1e5}"
            ,'317'=>"\u{f1e6}"
            ,'318'=>"\u{f1e7}"
            ,'350'=>"\u{f1e8}"
            ,'351'=>"\u{f1e9}"
            ,'399'=>"\u{f1ea}"
            ,'400'=>"\u{f1eb}"
            ,'401'=>"\u{f1ec}"
            ,'402'=>"\u{f1ed}"
            ,'403'=>"\u{f1ee}"
            ,'404'=>"\u{f1ef}"
            ,'405'=>"\u{f1f0}"
            ,'406'=>"\u{f1f1}"
            ,'407'=>"\u{f1f2}"
            ,'408'=>"\u{f1f3}"
            ,'409'=>"\u{f1f4}"
            ,'410'=>"\u{f1f5}"
            ,'456'=>"\u{f1f6}"
            ,'457'=>"\u{f1f7}"
            ,'499'=>"\u{f1f8}"
            ,'500'=>"\u{f1f9}"
            ,'501'=>"\u{f1fa}"
            ,'502'=>"\u{f1fb}"
            ,'503'=>"\u{f1fc}"
            ,'504'=>"\u{f1fd}"
            ,'507'=>"\u{f1fe}"
            ,'508'=>"\u{f1ff}"
            ,'509'=>"\u{f200}"
            ,'510'=>"\u{f201}"
            ,'511'=>"\u{f202}"
            ,'512'=>"\u{f203}"
            ,'513'=>"\u{f204}"
            ,'514'=>"\u{f205}"
            ,'515'=>"\u{f206}"
            ,'900'=>"\u{f207}"
            ,'901'=>"\u{f208}"
            ,'999'=>"\u{f146}"
        ];
        if (isset($weatherCode[$code])){
            return $weatherCode[$code];
        }
        return "\u{f1cc}";
    }
    
    private function processWeatherData() {
        $line = 0;
        foreach ($this->data['daily'] as $i => $forecast) {
            $param = [];
            $param['date'] = $forecast['fxDate'];
            $param['week'] = date('w', strtotime($forecast['fxDate']));
            $param['day'] = intval(date('d', strtotime($forecast['fxDate'])));
            
            if ($param['week'] == 1 && $i > 0) {
                if ($line == 4) break; // 最多显示5行
                $line++;
            }
            
            if ($param['week'] == 0) {
                $param['week'] = 7;
            }
            
            $param['line'] = $line;
            $param['font'] = static::get_qweather_font($forecast['iconDay']);
            // 如果晚上天气有雨雪雷雾等恶劣天气，则使用晚上的天气图标
            if (preg_match('/[雨雪雷雾]/u', $forecast['textNight'])) {
                $param['font'] = static::get_qweather_font($forecast['iconNight']);
            }
            $param['dx'] = ($param['week'] - 1) * self::GRID_WIDTH + 1;
            $param['dy'] = ($param['line'] + 1) * self::GRID_HEIGHT;
            
            $this->params[] = $param;
        }
        $lastweek = $this->params[count($this->params)-1]['week'];
        if ($lastweek!=7){
            $lastdate =$this->params[count($this->params)-1]['date'];
            // 补齐到周日
            for ($i=$lastweek+1; $i <= 7; $i++) { 
                $param = [];
                $param['date'] = date('Y-m-d',strtotime($lastdate.' +'.($i-$lastweek).' days'));
                $param['week'] = $i;
                $param['day'] = intval(date('d',strtotime($param['date'])));
                $param['line'] = $line;
                $param['font'] = static::get_qweather_font(999);
                $param['dx'] = ($param['week'] - 1) * self::GRID_WIDTH + 1;
                $param['dy'] = ($param['line'] + 1) * self::GRID_HEIGHT;
                $this->params[] = $param;
            }
        }
    }

    public static function curl_post($url,$data,$header=[])
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1); 
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); 
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);  
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);  
        // 线下环境不用开启curl证书验证, 未调通情况可尝试添加该代码
        curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, 0); 
        curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $contents = curl_exec($ch);
        if (curl_errno($ch)) {
            echo 'Curl error: ' . curl_error($ch);
        }
        curl_close($ch);
        return $contents;
    }

    public static function curl_ungzip($url,$header=null)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, 0); 
        curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_ENCODING,'gzip');
        if (isset($header)){
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        }else{
            curl_setopt($ch, CURLOPT_HEADER, false);
        }
        $contents = curl_exec($ch);
        if (curl_errno($ch)) {
            echo 'Curl error: ' . curl_error($ch);
        }
        curl_close($ch);
        return $contents;
    }
    // 计算文字的长度
    public static function calculateTextBox($fontSize,$fontAngle,$fontFile,$text) 
    {
        $rect = imagettfbbox($fontSize,$fontAngle,$fontFile,$text);
        $minX = min(array($rect[0],$rect[2],$rect[4],$rect[6]));
        $maxX = max(array($rect[0],$rect[2],$rect[4],$rect[6]));
        $minY = min(array($rect[1],$rect[3],$rect[5],$rect[7]));
        $maxY = max(array($rect[1],$rect[3],$rect[5],$rect[7]));

        return array(
        "left"   => $minX,
        "top"    => $minY,
        "width"  => $maxX - $minX,
        "height" => $maxY - $minY,
        "box"    => $rect
        );
    }
    // 创建一个透明图
    public static function getTransPng($image_width,$image_height)
    {
        $image = @imagecreatetruecolor($image_width, $image_height);
        imagealphablending($image,true);
        imagesavealpha($image, true);
        $trans_colour = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefill($image, 0, 0, $trans_colour);
        return $image;
    }


    // 自适应宽度文字转图片
    public static function textToImage($code,$image_width=100,$font_size=26,$pendding=5,$rgb=[255,255,255],$font='',$is_single_line=false)
    {
        if ($is_single_line && mb_strlen($code)>14){
            // $code="字符内容太长，无法转化图片，请检查。";
            $code = mb_substr($code,0,14);
        }

        if (!$font){
            $font = __dir__.'/Alibaba-PuHuiTi-Light.ttf';
        }

        $textbox = static::calculateTextBox($font_size, 0, $font, '中');

        $fontHeight = $textbox['height'];

        /* 生成一个文本框，然后在里面写字符 */
        $x = 0;
        $xMax = 0;
        $y = 0;
        $letters = [];
        for ($i=0; $i < mb_strlen($code); $i++) { 
            $s = mb_substr($code,$i,1);
            $textbox        = static::calculateTextBox($font_size, 0, $font, $s);
            if ($s=="\n" || $x + $pendding + $textbox['width'] + $pendding > $image_width)
            {
                $letters[]=["\n",0];
                $x = 0;
                $y += $fontHeight + $pendding;
            }
            if ($s!="\n")
            {
                $letters[]=[$s,$textbox];
                $x += $pendding + $textbox['width'];
            }
            $xMax = max($xMax,$x);
        }

        if ($xMax+$pendding<$image_width)
        {
            $image_width = $xMax+$pendding;
        }

        $image_height = $y + $fontHeight + $pendding;

        /*画布：初始化背景图*/
        $image = static::getTransPng($image_width,$is_single_line?$fontHeight + $pendding:$image_height);

        /** 文本色 */
        $text_color = imagecolorallocate($image, ...$rgb);

        $x = 0;
        $y = 0;
        for ($i=0; $i < count($letters); $i++) { 
            $s = $letters[$i][0];
            if ($s=="\n") {
                if ($is_single_line) {
                    break; // 如果是单行模式，遇到换行符直接跳出循环
                }
                $x = 0;
                $y += $fontHeight + $pendding;
            }
            if ($s!="\n") {
                $textbox = $letters[$i][1];
                imagettftext($image, $font_size,0 , $x + $pendding - $textbox['left'] , $y + $fontHeight , $text_color, $font , $s);
                $x += $pendding + $textbox['width'];
            }
        }

        return $image;
    }

    // 图片转黑白
    public static function blackwhite_image($image) {
        // 加载源图像
        // $image = imagecreatefromstring(file_get_contents($sourcePath));
        if (!$image) {
            throw new Exception("无法加载图像");
        }

        $width = imagesx($image);
        $height = imagesy($image);
        
        // 创建新图像（真彩色，无alpha通道）
        $newImage = imagecreatetruecolor($width, $height);

        // 分配颜色
        $white = imagecolorallocate($newImage, 255, 255, 255);
        $black = imagecolorallocate($newImage, 0, 0, 0);

        // 用白色填充背景
        imagefill($newImage, 0, 0, $white);

        // 像素处理阈值
        $whitenessThreshold = 200; // RGB值高于此视为白色
        $transparencyThreshold = 85; // Alpha值高于此视为透明（0-127范围）

        // 遍历所有像素
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                // 获取像素颜色索引
                $colorIndex = imagecolorat($image, $x, $y);
                
                // 解析RGBA值
                $rgba = imagecolorsforindex($image, $colorIndex);
                $red = $rgba['red'];
                $green = $rgba['green'];
                $blue = $rgba['blue'];
                $alpha = $rgba['alpha']; // 0（不透明）到127（全透明）
                
                // 判断像素类型
                $isWhite = ($red >= $whitenessThreshold && 
                        $green >= $whitenessThreshold && 
                        $blue >= $whitenessThreshold);
                
                $isTransparent = ($alpha > $transparencyThreshold);
                
                // 二值化处理：透明或白色->白色，其他->黑色
                if ($isTransparent || $isWhite) {
                    // 保持背景白色（无需操作）
                } else {
                    imagesetpixel($newImage, $x, $y, $black);
                }
            }
        }

        return $newImage;
    }
    public function createImage() {
        // 计算日历尺寸
        $calendarWidth = (7 * self::GRID_WIDTH);
        $calendarHeight = self::HEADER_HEIGHT + ((count($this->params) > 0 ? max(array_column($this->params, 'line')) + 1 : 0) * self::GRID_HEIGHT);
        
        // 初始化背景图
        $this->image = static::getTransPng(self::BG_WIDTH, self::BG_HEIGHT);
        $this->blackColor = imagecolorallocate($this->image, 0, 0, 0);
        $this->whiteColor = imagecolorallocate($this->image, 255, 255, 255);
        
        // 绘制日历头部
        $this->drawCalendarHeader($calendarWidth);
        
        // 绘制日期和图标
        $this->drawDaysAndIcons($calendarWidth);
        
        // 绘制日程
        $this->drawTodos($calendarWidth);
        
        // 添加额外天气信息
        $this->addWeatherInfo($calendarWidth);
        
        return $this;
    }
    
    private function drawCalendarHeader($calendarWidth) {
        $header = ['一', '二', '三', '四', '五', '六', '日'];
        
        for ($i = 0; $i < 7; $i++) {
            imagettftext($this->image, self::HEADER_FONT_SIZE, 0, self::BG_WIDTH - $calendarWidth + self::CALENDAR_START_LEFT + $i * self::GRID_WIDTH + 1, self::CALENDAR_START_TOP + self::HEADER_HEIGHT - 3, $this->blackColor, $this->textFont, $header[$i]);
        }
    }
    
    private function drawDaysAndIcons($calendarWidth) {
        foreach ($this->params as $param) {
            // 绘制日期
            $dayX = self::BG_WIDTH - $calendarWidth + self::CALENDAR_START_LEFT + $param['dx'] + 
                   ($param['day'] < 10 ? 4 : 0) + substr_count($param['day'], '1') * 2;
            $dayY = self::CALENDAR_START_TOP + self::HEADER_HEIGHT + $param['dy'];
            imagettftext($this->image, self::DAY_FONT_SIZE, 0, $dayX, $dayY, $this->blackColor, $this->numberFont, $param['day']);
            
            // 绘制天气图标
            $iconX = self::BG_WIDTH - $calendarWidth + self::CALENDAR_START_LEFT + $param['dx'];
            $iconY = self::CALENDAR_START_TOP + self::HEADER_HEIGHT + $param['dy'] - self::DAY_FONT_SIZE - self::DAY_ICON_MARGIN;
            imagettftext($this->image, self::ICON_FONT_SIZE, 0, $iconX, $iconY, $this->blackColor, $this->iconFont, $param['font']);
        }
    }
    
    private function drawTodos($calendarWidth) {
        $processHeight = 3;
        
        if (count($this->todolist) == 0) {
            imagettftext($this->image, self::TODO_FONT_SIZE, 0, 30, 10 + self::TODO_FONT_SIZE, $this->blackColor, $this->textFont, '近日无日程');
            $processHeight = 10 + self::TODO_FONT_SIZE + 10;
        } else {
            $isNextDay = false;
            $nextDayTime = strtotime(date('Y-m-d 23:59:59')) * 1000;
            
            foreach ($this->todolist as $i => $lineText) {
                if ($i >= self::TODO_MAX_LINE) {
                    break;
                }
                if ($lineText=='') {
                    imageline($this->image, 10, $processHeight + 3, self::BG_WIDTH - $calendarWidth - 10, $processHeight + 3, $this->blackColor);
                    $processHeight += 6;
                    continue;
                }
                
                $lineImage = static::textToImage($lineText, self::BG_WIDTH - $calendarWidth - 3, self::TODO_FONT_SIZE, 3, [0, 0, 0], $this->textFont,true);
                
                imagecopy($this->image, $lineImage, 3 , $processHeight, 0, 0, imagesx($lineImage), imagesy($lineImage));
                
                $processHeight += imagesy($lineImage);
            }
        }
        
        return $processHeight;
    }
    
    private function addWeatherInfo($calendarWidth) {
        $processHeight = $this->drawTodos($calendarWidth);
        
        if ($processHeight < self::BG_HEIGHT / 2 && isset($this->data['daily'])) {
            $extraInfo = $this->getPrecipitationInfo();
            $warningTypes = $this->addTemperatureInfo();
            if (isset($warningTypes[2]) && $warningTypes[2]!=''){
                $extraInfo = $warningTypes[2].$extraInfo;
            }
            
            if ($extraInfo) {
                $extraImage = static::textToImage($extraInfo, self::BG_WIDTH - $calendarWidth - 53 , self::TODO_FONT_SIZE, 3, [0, 0, 0], $this->textFont,false);
                imagecopy($this->image, $extraImage, self::BG_WIDTH - $calendarWidth - imagesx($extraImage) - 5, self::BG_HEIGHT - 10 - 15 - 15 - imagesy($extraImage), 0, 0, imagesx($extraImage), imagesy($extraImage));
            }
        }
    }
    
    private function getPrecipitationInfo() {
        $extraInfo = '';
        
        if (isset($this->data['daily'][0])) {
            $forecast = $this->data['daily'][0];
            
            if (preg_match('/[雨雪雷雾]/u', $forecast['textDay']) || 
                preg_match('/[雨雪雷雾]/u', $forecast['textNight'])) {
                $extraInfo = static::get_recent($this->location);
                if ($extraInfo == '未来两小时无降水') {
                    $extraInfo = '';
                }
            }
        }
        
        if (!$extraInfo) {
            $unsunnyTip = '';
            $week = date('w', time());
            $weekLabel = ['周日', '周一', '周二', '周三', '周四', '周五', '周六'];
            $dayLabel = [];
            $isNextWeek= false;
            for($i=0;$i<14;$i++){
                if (($week + $i)%7 == 1){
                    if (!$isNextWeek){
                        $isNextWeek = true;
                    }else{
                        break;
                    }
                }
                if ($i==0){
                    $dayLabel[]='今天';
                }elseif ($i==1){
                    $dayLabel[]='明天';
                }elseif ($i==2){
                    $dayLabel[]='后天';
                }else{
                    $dayLabel[]=($isNextWeek?'下':'').$weekLabel[($week + $i) % 7];
                }
            }

            foreach ($this->data['daily'] as $index => $forecast) {
                $dayStr = $dayLabel[$index] ?? ($index - 1) . '天后';
                
                if (preg_match('/[雨雪雷雾]/u', $forecast['textDay'])) {
                    if (!$extraInfo) $extraInfo = $dayStr . '有' . $forecast['textDay'];
                    if ($index <= 2) break;
                    $unsunnyTip = $dayStr . $forecast['textDay'];
                    break;
                } else if (preg_match('/[雨雪雷雾]/u', $forecast['textNight'])) {
                    if (!$extraInfo) $extraInfo = $dayStr . '夜里' . $forecast['textNight'];
                    if ($index <= 2) break;
                    $unsunnyTip = $dayStr . $forecast['textNight'];
                    break;
                }
            }
            // print_r($extraInfo);
            // print_r($dayLabel);exit;

            
            if (!$extraInfo) {
                $extraInfo = $unsunnyTip;
            }
        }
        
        return $extraInfo;
    }
    
    private function addTemperatureInfo() {
        if (!isset($this->data['daily'][0])) return;
        
        $forecast = $this->data['daily'][0];

        $warningTypes = [];
        if (($forecast['tempMax'] && $forecast['tempMax']>30) || preg_match('/[雨雪雷雾]/u', $forecast['textDay']) || preg_match('/[雨雪雷雾]/u', $forecast['textNight'])){
            $warningTypes = $this->get_warning($this->location);
        }
        
        // 添加今日天气大图标
        if (preg_match('/[雨雪雷雾]/u', $forecast['textNight']) || date('H') >= 17) {
            $iconToday = static::get_qweather_font($forecast['iconNight']);
        }else{
            $iconToday = static::get_qweather_font($forecast['iconDay']);
        }
        imagettftext($this->image, 35, 0, 3, self::BG_HEIGHT - 10, $this->blackColor, $this->iconFont, $iconToday);
        
        // 添加最低温度
        if (isset($warningTypes[1]) && $warningTypes[1]!=''){
            imagerectangle($this->image, 3 + 50 - 3, self::BG_HEIGHT - 10 - 15 - 12, 3 + 50 + 13, self::BG_HEIGHT - 6, $this->blackColor);
            imagettftext($this->image, 10, 0, 3 + 50, self::BG_HEIGHT - 10 - 15, $this->blackColor, $this->textFont, mb_substr($warningTypes[1],0,1));
            imagettftext($this->image, 10, 0, 3 + 50, self::BG_HEIGHT - 10, $this->blackColor, $this->textFont, mb_substr($warningTypes[1],1));
        }else{
            imagettftext($this->image, 10, 0, 3 + 50, self::BG_HEIGHT - 10 - 15, $this->blackColor, $this->textFont, '最');
            imagettftext($this->image, 10, 0, 3 + 50, self::BG_HEIGHT - 10, $this->blackColor, $this->textFont, '低');
        }
        imagettftext($this->image, 20, 0, 3 + 50 + 15, self::BG_HEIGHT - 10, $this->blackColor, $this->textFont, $forecast['tempMin'] . '°');
        
        // 添加最高温度
        if (isset($warningTypes[0]) && $warningTypes[0]!=''){
            imagerectangle($this->image, 3 + 50 + 15 + 45 - 3, self::BG_HEIGHT - 10 - 15 - 12, 3 + 50 + 15 + 45 + 13, self::BG_HEIGHT - 6, $this->blackColor);
            imagettftext($this->image, 10, 0, 3 + 50 + 15 + 45, self::BG_HEIGHT - 10 - 15, $this->blackColor, $this->textFont, '高');
            imagettftext($this->image, 10, 0, 3 + 50 + 15 + 45, self::BG_HEIGHT - 10, $this->blackColor, $this->textFont, '温');
        }else{
            imagettftext($this->image, 10, 0, 3 + 50 + 15 + 45, self::BG_HEIGHT - 10 - 15, $this->blackColor, $this->textFont, '最');
            imagettftext($this->image, 10, 0, 3 + 50 + 15 + 45, self::BG_HEIGHT - 10, $this->blackColor, $this->textFont, '高');
        }
        imagettftext($this->image, 20, 0, 3 + 50 + 15 + 45 + 15, self::BG_HEIGHT - 10, $this->blackColor, $this->textFont, $forecast['tempMax'] . '°');
        return $warningTypes;
    }
    
    public function output($dotsync = false) {
        $image2 = static::blackwhite_image($this->image);
        if ($dotsync && $this->dotDeviceId && $this->dotAppkey) {
            ob_start();
            imagepng($image2);
            $imageContent = ob_get_clean();
            
            foreach (explode(',',$this->dotDeviceId)  as $deviceId) {
                static::curl_post(
                    'https://dot.mindreset.tech/api/open/image',
                    json_encode([
                        "deviceId"=> $deviceId,
                        "image"=> base64_encode($imageContent),
                        "refreshNow"=> true,
                        "border"=> 0,
                        "ditherType"=> "NONE",
                        // "ditherKernel"=> "FLOYD_STEINBERG",
                        "link"=> "https://dot.mindreset.tech"
                    ], JSON_UNESCAPED_UNICODE),
                    [
                        'Authorization: Bearer '.$this->dotAppkey,
                        'Content-Type: application/json'
                    ]
                );
            }
        } else {
            header('Content-type: image/png');
            imagepng($image2);
        }
        
        imagedestroy($this->image);
        imagedestroy($image2);
        
        return $this;
    }
}
