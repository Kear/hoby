<?php

/**
 *
 * 'Simple Makes Boom'
 * Created on 2012-11-5
 * @author: Kearney
 * @E-mail: kearneyjar@gmail.com
 *
 */

class Plugin_statistics {

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
        $this->mch = Leb_Dao_Memcache::getInstance();

    }

    public function run($params){

        $this->amode = $params['ACCESS_MODE'];

        //echo $access_state = $this->getAccessState('UNFROMAT');

        $this->doStatistics();

    }


    public function doStatistics() {

        //统计访问IP
        $this->collectAccessIP();

        //统计总访问次数
        $this->countSystemAccess();

        //统计访问/上传次数
        $this->countUpOrAccess($this->amode);

        //统计方法调用次数
        //countFunctionAccess($params['FUNCTION_NAME']);
        //统计流量
        //fluxStatistics($params['FLUX_AMOUNT']);

    }

    //获取系统访问概况
    public function getSysemAccessState(){
        //上传次数
        $system_access_state['access_upload_count'] = $this->mch->get('statistics_access_upload_count');
        //访问次数
        $system_access_state['access_view_count'] = $this->mch->get('statistics_access_view_count');
        //总访问次数
        $system_access_state['access_all_count'] = $this->mch->get('statistics_access_all_count');

        //方法调用概况
        //$system_access_state['function_count'] = $this->mch->get('statistics_function_count');

        return $system_access_state;
    }

    //统计上传或者访问次数
    public function countUpOrAccess($access_mode){
        if($access_mode == $this->ACCESS_MODE_UPLOAD){
            $access_upload_count = $this->mch->get('statistics_access_upload_count');
            $access_upload_count = is_numeric($access_upload_count) && !empty($access_upload_count) ?  $access_upload_count + 1 : 1;
            $this->mch->set('statistics_access_upload_count', $access_upload_count, 0);

        }else{
            $access_view_count = $this->mch->get('statistics_access_view_count');
            $access_view_count = is_numeric($access_view_count) && !empty($access_view_count) ?  $access_view_count + 1 : 1;
            $this->mch->set('statistics_access_view_count', $access_view_count, 0);
        }

    }

    //统计系统总访问次数
    public function countSystemAccess(){
        $access_all_count = $this->mch->get('statistics_access_all_count');
        $access_all_count = is_numeric($access_all_count) && !empty($access_all_count) ?  $access_all_count + 1 : 1;
        $this->mch->set('statistics_access_all_count', $access_all_count, 0);

    }

    //统计方法调用次数
    public function countFunctionAccess($function_name){
        $function_access_count = $this->mch->get('statistics_function_'. $function_name .'_access_count');
        $function_access_count = is_numeric($function_access_count) && !empty($function_access_count) ?  $function_access_count + 1 : 1;
        $this->mch->set('statistics_function_'. $function_name .'_access_count', $function_access_count, 0);

    }

    //统计系统错误信息
    public function errorInfo($params){

        $error_info = Util::jsonDecode($this->mch->get('statistics_error_infomation'));
        $error_info[$params['code']] = is_numeric($error_info[$params['code']]) && !empty($error_info[$params['code']]) ?  $error_info[$params['code']] + 1 : 1;
        $this->mch->set('statistics_error_infomation', Util::jsonEncode($error_info), 0);
    }

    //统计访问IP
    public function collectAccessIP(){

        $ip_addr = Util::getRealIp();
        $access_ip_list = array();
        $access_ip_list = Util::jsonDecode($this->mch->get('statistics_ip_list'));


        if(!is_array($access_ip_list)){
            $new_ip_list[$ip_addr]['count'] = 1;
            $new_ip_list[$ip_addr]['last_access_time'] = time();
            $this->mch->set('statistics_ip_list', Util::jsonEncode($new_ip_list), 0);
        }else{

            $access_ip_list[$ip_addr]['count'] = is_numeric($access_ip_list[$ip_addr]['count']) && !empty($access_ip_list[$ip_addr]['count']) ?  $access_ip_list[$ip_addr]['count'] + 1 : 1;
            $access_ip_list[$ip_addr]['last_access_time'] = time();
            $this->mch->set('statistics_ip_list', Util::jsonEncode($access_ip_list), 0);
        }
    }

    //统计系统对外流量
    public function outFluxStatistics($view_flux_amount){
        $flux_in_amount_all = $this->mch->get('statistics_flux_in_amount');
        $flux_in_amount_all = is_numeric($flux_in_amount_all) && !empty($flux_in_amount_all) ?  $flux_in_amount_all + $view_flux_amount : $view_flux_amount;
        $this->mch->set('statistics_flux_in_amount', $flux_in_amount_all);

    }
    //统计系统上传流量
    public function inFluxStatistics($upload_flux_amount){
        $flux_out_amount_all = $this->mch->get('statistics_flux_out_amount');
        $flux_out_amount_all = is_numeric($flux_out_amount_all) && !empty($flux_out_amount_all) ?  $flux_out_amount_all + $upload_flux_amount : $upload_flux_amount;
        $this->mch->set('statistics_flux_out_amount', $flux_out_amount_all);
    }

}
