<?php
/*
 * 'Simple Makes Boom'
 * Created on 2012-11-5
 * @author: Kearney
 * @E-mail: kearneyjar@gmail.com
 *
 */

class Util
{

    /**
     * 请求url
     *
     * @param string $getUrl
     * @param array  $data
     *  @param int	 $timeOut 超时时间
     */
    static public function curlGet( $getUrl, $data = array(),$timeOut = 2)
    {
        if( false == empty($data)  ) {
            if( is_array($data) && count($data) ) {
                $encoded = null;
                while ( list($k,$v) = each($data) ) {
                    $encoded .= ( $encoded ? '&' : '');
                    $encoded .= rawurlencode($k) .'='. rawurlencode($v);
                }
                $getUrl .= '?' . $encoded;
            }
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $getUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeOut);
        $result = curl_exec($ch);
        curl_close($ch);

        return trim($result);
    }

    /**
     * post请求
     * @param string $postUrl 请求的网址
     * @param array	 $data	  发送过去的数据
     * @param int	 $timeOut 超时时间
     */
    static public function curlPost($postUrl, $data=array(), $timeOut=2)
    {
        $encoded = null;
        if( is_array( $data ) ) {
            while (list($k,$v) = each($data)) {
                $encoded .= ($encoded ? "&" : "");
                $encoded .= rawurlencode($k)."=".rawurlencode($v);
            }
        }else if( is_string($data) ){
            $encoded = $data;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $postUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array( 'Expect:' ) );
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeOut);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $encoded);
        $result = curl_exec($ch);
        curl_close($ch);

        return trim($result);
    }

    /**
     * 获得现在时间
     * @param string $pattern　时间格式
     * @return date
     */
    static public function now($pattern='Y-m-d H:i:s')
    {
        return date($pattern, time());
    }

    /**
     * 得到客户端的ip
     *
     * @param string $default
     * @return string
     */
    static public function getRemoteIp($default='127.0.0.1')
    {
        $ip_string = getenv('HTTP_CLIENT_IP').','.getenv('HTTP_X_FORWARDED_FOR').','.getenv('REMOTE_ADDR');
        if ( preg_match ("/\d+\.\d+\.\d+\.\d+/", $ip_string, $matches) )
        {
            return $matches[0];
        }
        return $default;
    }

    /**
     * 根据规则得到用户进行的某项操作的时间差距离现在的时间
     *
     * @param string $time
     * @param int $stress
     * @return string
     */
    static public function getHumanTime($time=null, $stress=1)
    {
        $now = time();
        $time = is_numeric($time) ? $time : strtotime($time);
        $interval = $now - $time;

        switch( $stress )
        {
            case 1:
                if ( $interval > 365*86400 ){
                    return floor($interval/(365*86400))."年前";
                }
                else if ( $interval > 30*86400 ){
                    return floor($interval/(30*86400))."月前";
                }
                else if ( $interval > 7*86400 ){
                    return floor($interval/(7*86400))."周前";
                }
                else if ( $interval > 86400 ){
                    return floor($interval/(86400))."天前";
                }
                else if ( $interval > 3600 ){
                    return floor($interval/(3600))."小时前";
                }
                else if ( $interval > 60 ){
                    return floor($interval/(60))."分钟前";
                }
                else if ( $interval > 0 ) {
                    return "${interval}秒前";
                }else
                return "就在刚才";
            case 2:
                return date("Y-m-d", $time);
            case 3:
                return date("Y-m-d H:i", $time);
        }
    }

    /**
     * 把time转换为Date
     * Y-m-d H:i:s
     * @return string
     */
    static public function getDate($time=0)
    {
        !$time  && $time = time();
        return date('Y-m-d H:i:s', $time);
    }

    /**
     * 获得基本域名
     * @return string
     */
    static public function getBaseDomain()
    {
        $host = explode('.' , $_SERVER['HTTP_HOST']);
        if (count($host) >= 3 ) unset($host[0]);
          return  implode('.', $host);
    }


    /**
     * 更改资源网址
     *
     * @param string $url
     */
    static public function asset($url)
    {
        $realPath = Leb_View::getEnvVar('resourceBase');
        if ($realPath == '/') {
            $realPath = '';
        }
        return  $realPath . '/' . $url;
    }

    /**
     * Convert $data To Json
     *
     * @param mixed $data
     * @return JSON
     */
    static public function convertToJson($data)
    {
        return json_encode($data);
    }

    /**
     * 转为Json并退出
     *
     * @param mixed $data
     */
    static public function jsonExit($data)
    {
        echo self::convertToJson($data);
        exit;
    }

    /**
     * 从数组里获得内容列
     *
     * @param array $array
     * @param strng $field
     */
    static public function getField($array, $field)
    {
        $result = array();
        foreach ($array as $value) {
            array_push($result, $value[$field]);
        }
        return $result;
    }

    /**
     * 获得App的View 插件
     *
     * @param string $name
     */
    static public function incAppViewPlugins($name)
    {
        include_once(_APP_ . '_template/plugins/stand.' . $name . '.php');
    }

    /**
     * 获得命名空间key
     * @param string $key
     * @param string $encrypt
     * @return string
     */
    static public function getNameSpaceKey($key, $encrypt=true)
    {
        $key = 'ks_' . $key;
        if ($encrypt) {
            $key = md5($key);
        }

        return $key;
    }

}

?>
