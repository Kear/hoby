<?php
  /**
   * 在本机启动一个新的后台异步进程，执行指定的任务
   *
   * 执行的web环境会被复制过去
   * 运行环境与启动该异步后台任务的环境相同
   * 主执行进程不会等待异步进程的返回值
   * 不通过web server执行，不给web server带来额外压力
   *
   * @author guangzhao1@leju.sina.com.cn
   *
   * @usage: plugin_AsyncTask::run('latest1', 'test6', 'test', array());
   * @usage: new plugin_AysncTask()->run('latest1', 'test6', 'test', array());
   */

class AsyncTask
{
    public function __construct()
    {
    }

    public static function run($action, $controller, $app, $params)
    {
        $server_ip = $_SERVER['SERVER_ADDR'];

        $server_async_task_queue_mckey = 'local_async_task_queue_'.$server_ip; // 由于传递的参数文件在本机，必须区别哪台机器的任务
        $cache_dir = '/tmp';
        if (defined('_CACHE_DIR_')) {
            $cache_dir = _CACHE_DIR_;
        }

        // all php vars
        global $HTTP_RAW_POST_DATA;
        $ekeys = array('_GET', '_POST', '_FILES', '_REQUEST', '_SERVER', '_SESSION', '_ENV', '_COOKIE', 'HTTP_RAW_POST_DATA');
        $evals = array(
                       'GET' => $_GET, 'POST' => $_POST, 'REQUEST' => $_REQUEST,
                       'SERVER' => $_SERVER, 'FILES' => $_FILES,
                       'SESSION' => $_SESSION, 'ENV' => $_ENV,
                       'COOKIE' => $_COOKIE, 'HTTP_RAW_POST_DATA' => $HTTP_RAW_POST_DATA
                 );
        $task = array('app'=> $app, 'controller' => $controller, 'action'=>$action,
                      'params' => $params,
                      );
        $evals['task'] = $task;
        $tapath = $cache_dir . '/local_async_task_' . uniqid() . '.json';
        file_put_contents($tapath, json_encode($evals));
        $mcip = $_SERVER['SINASRV_MEMCACHED_SERVERS'];

        ///// 查找可用的php命令行程序
        $php_exes = array('/usr/local/sinasrv2/bin/php', '/usr/local/php/bin/php');
        $php_exe = '';
        foreach ($php_exes as $idx => $path) {
            if (file_exists($path)) {
                $php_exe = $path;
                break;
            }
        }
        if (empty($php_exe)) {
            return false;
        }

        ///// 清理的日志
        $atlog = "{$cache_dir}/async_task.log";
        if (file_exists($atlog) && filesize($atlog) > 200 * 1024 * 1024) {
            unlink($atlog);
        }

        /////// start async process
        $strret = null;
        $routput = null;
        $rval = null;

        // 后台执行，并返回执行的进程ID
        // 注意，必须使用 > /dev/null 2>&1 & ，否则，不会进入后台
        //$proj_root = _ROOT_;
        $proj_root = FRAMEWORK_PATH . '/aysnyc_task';

        $cmd = "{$php_exe} {$proj_root}/async_task_proxy.php ${tapath} {$server_async_task_queue_mckey} {$mcip} >>{$atlog} 2>&1 & echo $!";
        // $cmd = "{$php_exe} {$proj_root}/cron/async_task_proxy.php ${tapath} {$server_async_task_queue_mckey} {$mcip} >>{$atlog} 2>&1";
        $pid = $strret = exec($cmd, $routput, $rval);

        if (0) {
            print_r($cmd);
            print_r($routput);
            print_r($rval);
        }

        return $strret;
    }

    /**
     * 获取并还原传递过来参数
     */
    public static function get_params()
    {
        $ujparams = $_REQUEST['params'];
        $jparams = urldecode($ujparams);
        $params = json_decode($jparams, true);
        return $params;
    }
};
