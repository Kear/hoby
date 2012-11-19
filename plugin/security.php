<?php
/**
 *
 * 'Simple Makes Boom'
 * Created on 2012-11-5
 * @author: Kearney
 * @E-mail: kearneyjar@gmail.com
 *
 */

class Security
{

    private $mch = null;
    private $amode = 0; //模式接口
    private $expire_time = 3600; // 过期时间，秒
    private $refresh_time = 60;  // 重新计时阈值，秒
    private $max_upload_per_minite = 10;
    private $max_access_per_minite = 100;
    private $ACCESS_MODE_UPLOAD = 1;
    private $ACCESS_MODE_ACCESS = 2;
    private $statval = null;
    private $access_state_type = null; // 默认为null，返回字符串；UNFORMAT返回数组


    public function __construct() {

        if ($_SERVER['REQUEST_METHOD'] == 'POST' || $_SERVER['REQUEST_METHOD'] == 'PUT') {
            $this->amode = $this->ACCESS_MODE_UPLOAD;
        } else {
            $this->amode = $this->ACCESS_MODE_ACCESS;
        }
        echo "Launch plugins success! " .
                "Here is security plugin!<br>";
        //$this->mch = Leb_Dao_Memcache::getInstance();
    }


    //过滤访问，验证访问频率，判定是否列入黑名单
    public function validateAccess($params) {


        //限制访问次数，60秒内，从后台数据库读取；默认upload为10次每分钟，access为100次每分钟

        if(!empty($params['max_upload_per_minite'])){
            $this->max_upload_per_minite = $params['max_upload_per_minite'];
        }
        if(!empty($params['max_access_per_minite'])){
            $this->max_access_per_minite = $params['max_access_per_minite'];
        }
        $ntime = time();

        $curkey = $this->getAccessState();
        $prev_val = $this->mch->get($curkey);

        $mcopt = array('expire' => $this->expire_time);

        if (!$prev_val) {

            $curval = array('ctime'=>$ntime,
                            'last_access' => $ntime,
                            'upload_count'=> $this->amode == $this->ACCESS_MODE_UPLOAD ? 1 : 0,
                            'access_count'=> $this->amode == $this->ACCESS_MODE_UPLOAD ? 0 : 1,
                            'last_forbidden' => 0,
                            'forbidden_count' => 0,
                            'uniqkey' => $curkey,
                            );
            $this->mch->set($curkey, Util::jsonEncode($curval), $mcopt);
            $this->statval = $curval;
            return false;
        } else {
            $pval = Util::jsonDecode($prev_val);

            $pval['uniqkey'] = $curkey;
            $pval['upload_count'] += $this->amode == $this->ACCESS_MODE_UPLOAD ? 1 : 0;
            $pval['access_count'] += $this->amode == $this->ACCESS_MODE_UPLOAD ? 0 : 1;
            $second_after_last_access = $ntime - $pval['last_access'];
            $pval['last_access'] = $ntime;

            if ($second_after_last_access > $this->refresh_time) {
                /// 间隔超过计时区间，重新计时
                $pval['upload_count'] = $this->amode == $this->ACCESS_MODE_UPLOAD ? 1 : 0;
                $pval['access_count'] = $this->amode == $this->ACCESS_MODE_UPLOAD ? 0 : 1;
                $pval['last_forbidden'] = 0;
                $pval['forbidden_count'] = 0;

                $this->mch->set($curkey, Util::jsonEncode($pval), $mcopt);
                $this->statval = $pval;
                return false;
            } else {
                /// 累加，检测是否超过计时区间内的次数限制

                $forbid = false;

                if ($this->amode == $this->ACCESS_MODE_UPLOAD) {
                    $forbid = $pval['upload_count'] > $this->max_upload_per_minite;

                } else {
                    $forbid = $pval['access_count'] > $this->max_access_per_minite;
                }

                if ($forbid) {
                    $pval['last_forbidden'] = $ntime;
                    $pval['forbidden_count'] += 1;
                }
                // var_dump($forbid);
                // echo "here\n";
                $this->mch->set($curkey, Util::jsonEncode($pval), $mcopt);
                $this->statval = $pval;

                return $forbid;
            }
        }
    }

    //获取用户访问状态码
    public function getAccessState($state_type = '') {

        $statkey = '';
        $ip = Util::getRealIp();
        $cookie = array();
        $sina_cookie_keys = array('SUR', 'SUS', 'SUE', 'SUP','LUP');

        foreach ($sina_cookie_keys as $key) {
            if (isset($_COOKIE[$key])) {
                $cv = $_COOKIE[$key];
                $cookie[] = "{$key}={$cv}";
            }
        }
        $cookie_data = implode('&', $cookie);
        $cookie_md5 = md5($cookie_data);
        $cookie_len = strlen($cookie_data);

        $statkey = "security_stat_{$ip}_{$cookie_len}_{$cookie_md5}";

        if($state_type == 'UNFROMAT'){

            $unformat_state = array();
            $unformat_state['ip'] = $ip;
            $unformat_state['cookie_data'] = $cookie_data;
            $unformat_state['cookie_len'] = $cookie_len;
            $unformat_state['cookie_md5'] = $cookie_md5;

            return Util::jsonEncode($unformat_state);

        }else{
            return $statkey;
        }
    }

};
