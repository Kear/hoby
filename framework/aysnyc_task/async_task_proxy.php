<?php

  /**
   * 后台异步执行代理程序，可加载并使用框架，
   * 模拟一个相对真实的web请求执行环境
   * 
   * usage: 
   *       php /path/to/async_task_proxy.php <json_params_mckey> <queue_mckey> <mcip>
   */

if (!function_exists('apache_get_version')) {
    function apache_get_version()
    {
        return 'SLAE framework console emulator httpd V1.0';
    }
}

/**
 * 主要的类。
 *
 */
class ConsoleApplication
{
    private $_output = true;
    public function __construct($argc, $argv, $output = true)
    {
        $this->_output = $output;

        $this->_initVirtualConsole();

        set_error_handler(array($this, '_phpErrorHandler'), E_NOTICE);
    }

    protected function _initVirtualConsole()
    {
        /*
        if (isset($_SERVER['REQUEST_URI'])) {
            echo ('You are not running console mode.' . "\n");
            exit(-1);
            }
        */
        // 设置$_SERVER模块请求环境变量
        
        // $_SERVER = array();
        
        $_SERVER['HTTP_HOST'] = 'photo.house.sina.com.cn';
        // $_SERVER['REQUEST_URI'] = '/test/test6/phpinfo?abcd=123';
        //  $_SERVER['SINASRV_CACHE_DIR'] = '/tmp/CACHE/cache';
        // $_SERVER['SINASRV_DATA_DIR'] = '/tmp/CACHE/data';
        // $_SERVER['SINASRV_RSYNC_SERVER'] = '';
        //        $_SERVER['SINASRV_RSYNC_MODULES'] = '';
        //        $_SERVER['SINASRV_RESOURCE_URL'] = '';
        // $_SERVER['SINASRV_DIST_URL'] = '';
        //     $_SERVER['SERVER_ADDR'] = '127.0.0.1';
        
    }

    protected function _initFramework()
    {
    }

    public function _phpErrorHandler($errno, $errstr, $errfile, $errline, $errcontext)
    {
        // echo "$errno $errstr";
        // quite notice
    }

    protected function _cleanupVirtualConsole()
    {
        unset($_SERVER);
        // print_r($_SESSION);
        unset($_SESSION);
        // session_destroy();
    }

    public function get($request_uri, $host = '')
    {
        // $_SERVER['REQUEST_URI'] = '/test/test6/phpinfo?abcd=123';
        $_SERVER['SCRIPT_URI'] = $_SERVER['REQUEST_URI'] = $request_uri;
        $_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'] = $_SERVER['SCRIPT_URL']
            = substr($request_uri, 0, strpos($request_uri, '?'));
        $_SERVER['QUERY_STRING'] = substr($request_uri, strpos($request_uri, '?') + 1);
        $_POST['params'] = $_GET['params'] = $_REQUEST['params'] 
            = substr($request_uri, strpos($request_uri, '=') + 1);

        if (!empty($host)) {
            $_SERVER['HTTP_HOST'] = $host;
        }

        $btime = microtime(true);

        // require_once('index.php');
        require_once(__DIR__ . '/../config/init.php');
        require_once(_FRAMEWORK_.'/loader.php');
        Leb_Loader::setAutoLoad();

        // print_r(get_include_path());        
        
        ob_start();

        // 中心控制 请求->路由->过滤->分发->响应
        $controller = Leb_Controller::getInstance();
        $controller->run();

        $output = ob_get_clean();

        $this->_cleanupVirtualConsole();
        
        if ($this->_output) {
            echo $output;
        }

        $etime = microtime(true);
        $used = $etime - $btime;
        $ntime = date('Y-m-d H:i:s');
        echo "[$ntime] GET $request_uri Done. Used time: $used.\n";

        return $this;
    }

    public function post($request_uri, $host)
    {

        $this->_cleanupVirtualConsole();
        return $this;        
    }
};

/////检测任务队列数，防止系统超载
/*
  描述：
 */
$data_dir = '/data1/CACHE/sina-cache/photo.house.sina.com.cn';
if (!file_exists($data_dir)) {
    // 线上目录
    $data_dir = '/data1/www/cache/photo.house.sina.com.cn';
}

$task_queue_lock_file = $data_dir . '/local_async_task_queue.lock';
$task_proc_lock_file_tpl = $data_dir . '/local_async_task_proc_SEQ.lock';
$max_task_queue = 16; // 最大异步进程数，1-100
$task_queue_mckey = $argv[2];// 'local_async_task_queue_<ip>';
$gmch = null;
$gmcip = $argv[3];

// 查找可用的proc
$free_task_proc_seq = 0; // 无
$free_task_proc_fp = null;
for ($i = 1; $i < $max_task_queue; ++ $i) {
    $task_proc_lock_file = str_replace('SEQ', $i, $task_proc_lock_file_tpl);
    if (!file_exists($task_proc_lock_file)) {
    }
    $fp = fopen($task_proc_lock_file, 'w+');
    if (!$fp) {
        echo "can not open lock file: {$task_proc_lock_file}\n";
        exit(3);
    }
    if (!flock($fp, LOCK_EX | LOCK_NB)) {
        fclose($fp);
        continue;
    }
    $free_task_proc_seq = $i;
    $free_task_proc_fp = $fp;
    break;
}

// 无可用的proc，入队，退出，不可超过限定的异步进程数    
if ($free_task_proc_seq == 0) {
    enqueue_task($argv[1]);
    echo "exceed max task queue.\n";
    exit(4); // 
}

// 有可用的proc
// Using task proc {$free_task_proc_seq} run task
// main loop
{
    $bret = false;

    // 处理该任务
    $bret = run_task($argc, $argv);
    
    // 循环查找队列中的任务并处理
    while (true) {
        $next_task_param_file = find_next_task();
        if (!$next_task_param_file) {
            qlog($free_task_proc_seq, "No other task left,done.");
            break;
        } else {
            qlog($free_task_proc_seq, "run queued task: {$next_task_param_file}");
            $argv[1] = $next_task_param_file;
            $bret = run_task($argc, $argv);
        }
    }

    // 清理资源
    flock($free_task_proc_fp, LOCK_UN);
    fclose($free_task_proc_fp);
}

///// 辅助函数
function qlog($seq, $msg)
{
    $time = date('Y-m-d H:i:s');
    echo "[{$time} P{$seq}] {$msg}\n";
}

function run_task($pargc, $pargv) {
    $argc = $pargc;
    $argv = $pargv;

    // restore php envs
    $ptenvs = file_get_contents($argv[1]);
    if (!$ptenvs) {
        // maybe file not exists;
        return false;
    }
    /*
    $mch = connect_memcache();
    $ptenvs = $mch->get($argv[1]);
    if ($ptenvs === false) {
        // maybe file not exists;
        return false;
    }
    */
    $evals = json_decode($ptenvs, true);

    // print_r($evals);
    $ekeys = array('_GET', '_POST', '_FILES', '_REQUEST', '_SERVER', '_SESSION', '_ENV', '_COOKIE', 'HTTP_RAW_POST_DATA');
    $_GET = $evals['GET'];
    $_POST = $evals['POST'];
    $_FILES = $evals['FILES'];
    $_REQUEST = $evals['REQUEST'];
    $_SERVER = $evals['SERVER'];
    $_SESSION = $evals['SESSION'];
    $_ENV = $evals['ENV'];
    $_COOKIE = $evals['COOKIE'];
    $HTTP_RAW_POST_DATA = $evals['HTTP_RAW_POST_DATA'];

    //////// runit
    $task = $evals['task'];
    $app = $task['app'];
    $controller = $task['controller'];
    $action = $task['action'];
    $params = '';
    if (isset($task['params'])) {
        $params = urlencode(json_encode($task['params']));
    }

    $capp = new ConsoleApplication($argc, $argv,  true);
    $turi = "/{$app}/{$controller}/{$action}?params={$params}";
    $capp->get($turi, '');

    unlink($argv[1]);
    
    return true;
}

/**
 *
 * @return json_param_file_path 或 false
 */
function find_next_task() {
    global $task_queue_lock_file;
    global $task_queue_mckey;
    $param_file = false;

    $fp = fopen($task_queue_lock_file, "w+");
    if (!$fp) {
        qlog(0, "Can not open file: {$task_queue_lock_file}");
        return false;
    }
    if (flock($fp, LOCK_EX)) {
        $mch = connect_memcache();

        $tqval = $mch->get($task_queue_mckey);
        if ($tqval === false || empty($tqval)) {
            // no task left
        } else {
            $dpos = strpos($tqval, ',');
            if ($dpos === false) {
                $param_file = $tqval;
                $mch->set($task_queue_mckey, '');
            } else {
                $param_file = substr($tqval, 0, $dpos);
                $queue_value = substr($tqval, $dpos + 1);
                $mch->set($task_queue_mckey, $queue_value);
            }
        }

        flock($fp, LOCK_UN);
    }
    fclose($fp);
    return $param_file;
}

/**
 *
 *
 */
function enqueue_task($param_file) {
    global $task_queue_lock_file;
    global $task_queue_mckey;
    
    $fp = fopen($task_queue_lock_file, "w+");
    if (!$fp) {
        qlog(0, "Can not open file: {$task_queue_lock_file}");
        return false;
    }
    if (flock($fp, LOCK_EX)) {
        $mch = connect_memcache();

        $tqval = $mch->get($task_queue_mckey);
        $bret = false;
        if (!$tqval) {
            $bret = $mch->set($task_queue_mckey, $param_file);
        } else {
            $bret = $mch->set($task_queue_mckey, $tqval . ',' . $param_file);
        }
        $queue_value = $mch->get($task_queue_mckey);
        qlog(0, "enqued({$bret}): {$task_queue_mckey}={$param_file}");
        qlog(0, "now queue: {$queue_value}");
        flock($fp, LOCK_UN);
    }
    fclose($fp);
    return true;
}

/**
 *
 * @return resouce
 */
function connect_memcache()
{
    global $gmch;
    global $gmcip;

    $mch = $gmch;
    if ($gmch == null) {
        $gmch = $mch = new Memcache();
        // $mch->addServer('10.207.0.202:8091');
        // $mch->addServer('10.207.16.251:11211');
        $mch->addServer($gmcip);
    }
    return $mch;
}


//// test it
echo "sleeping le...\n";
// sleep(6);
exit;

// restore php envs
$ptenvs = file_get_contents($argv[1]);
if (!$ptenvs) {
    // maybe file not exists;
    exit;
}

$evals = json_decode($ptenvs, true);

// print_r($evals);
$ekeys = array('_GET', '_POST', '_FILES', '_REQUEST', '_SERVER', '_SESSION', '_ENV', '_COOKIE', 'HTTP_RAW_POST_DATA');
$_GET = $evals['GET'];
$_POST = $evals['POST'];
$_FILES = $evals['FILES'];
$_REQUEST = $evals['REQUEST'];
$_SERVER = $evals['SERVER'];
$_SESSION = $evals['SESSION'];
$_ENV = $evals['ENV'];
$_COOKIE = $evals['COOKIE'];
$HTTP_RAW_POST_DATA = $evals['HTTP_RAW_POST_DATA'];

//////// runit
$task = $evals['task'];
$app = $task['app'];
$controller = $task['controller'];
$action = $task['action'];
$params = '';
if (isset($task['params'])) {
    $params = urlencode(json_encode($task['params']));
}

$capp = new ConsoleApplication($argc, $argv,  true);
$turi = "/{$app}/{$controller}/{$action}?params={$params}";
$capp->get($turi, '');

sleep(5);

exit;
///// test
for ($i = 0; $i < 1; $i++) {
    $capp = new ConsoleApplication($argc, $argv,  false);
    $capp->get('/test/test6/phpinfo?abcd=' . $i, '');
}



// unlink($argv[1]);
