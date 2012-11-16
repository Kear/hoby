<?php
class Bootstrap {
    public function Run() {

        if(!defined('ISEXIST')){
            exit("请从入口文件运行程序");
        }

        $this->Analysis();
        $this->launchPlugin();
        //引入Application类
        $applicationFile = FRAMEWORK_PATH . '/Application.php';

        if(!file_exists($applicationFile)){
            exit("Application类不存在。");
        }
        include($applicationFile);




        //开始解析URL获得请求的控制器和方法
        $app = $_GET['app'];
        $control = $_GET['con'];
        $action = $_GET['act'];
        $action = ucfirst($action);

        //这里构造出控制器文件的路径
        $controlFile = APP_PATH  .$app. '/' . $control . '.php';

        //如果文件不存在提示错误, 否则引入
        if (!file_exists($controlFile)){
            exit("{$control}控制器不存在<br>" . "请检查: " . $controlFile . "是否存在<br>");
        }
        include($controlFile);

        $dbFile = FRAMEWORK_PATH . '/db/memory/memcache.php';
        //引入DB类
        if(!file_exists($dbFile)){
            exit("db类不存在。");
        }
        include($dbFile);



        $class = ucfirst($control); //将控制器名称中的每个单词首字母大写,来当作控制器的类名

        if (!class_exists($class)) //判断类是否存在, 如果不存在提示错
        {
            exit ("{$control}.php中未定义的控制器类" . $class);
        }
        $instance = new $class(); //否则创建实例

        if (!method_exists($instance, $action)) //判断实例$instance中是否存在$action方法, 不存在则提示错误
        {
            exit ($class."类中不存在方法:" . $action);
        }
        $instance->$action();
    }

    protected function Analysis() {
        //$GLOBALS['C']['URL_MODE'];
        global $Config; //包含全局配置数组, 这个数组是在Config.ph文件中定义的,global声明$C是调用外部的

        if ($Config['URL_MODE'] == 1)
            //如果URL模式为1 那么就在GET中获取控制器, 也就是说url地址是这种的 [url=http://localhost/index.php?c]http://localhost /index.php?c[/url]=控制器&a=方法
            {
            $app = !empty ($_GET['app']) ? trim($_GET['app']) : '';
            $control = !empty ($_GET['con']) ? trim($_GET['con']) : '';
            $action = !empty ($_GET['act']) ? trim($_GET['act']) : '';
        } else
            if ($Config['URL_MODE'] == 2) //如果为2 那么就是使用PATH_INFO模式, 也就是url地址是这样的    [url=http://localhost/index.php/]http://localhost/index.php/[/url]控制器/方法 /其他参数
                {
                if (isset ($_SERVER['PATH_INFO'])) {
                    //$_SERVER['PATH_INFO']URL地址中文件名后的路径信息, 不好理解, 来看看例子
                    //比如你现在的URL是 [url=http://www.php100.com/index.php]http://www.php100.com/index.php[/url] 那么你的$_SERVER['PATH_INFO']就是空的
                    //但是如果URL是 [url=http://www.php100.com/index.php/abc/123]http://www.php100.com/index.php/abc/123[/url]
                    //现在的$_SERVER['PATH_INFO']的值将会是 index.php文件名称后的内容 /abc/123/
                    $path = trim($_SERVER['PATH_INFO'], '/');
                    $paths = explode('/', $path);
                    $app = array_shift($paths);
                    $control = array_shift($paths);
                    $action = array_shift($paths);
                }
            }
        //应用默认
        $_GET['app'] = !empty ($app) ? $app : $Config['DEAFAULT_APP'];
        //这里判断控制器的值是否为空, 如果是空的使用默认的
        $_GET['con'] = !empty ($control) ? $control : $Config['DEFAULT_CONTROLLER'];
        //和上面一样
        $_GET['act'] = !empty ($action) ? $action : $Config['DEFAULT_ACTION'];

    }

    //读取插件类
    public function launchPlugin(){

        $config = include(ROOT_PATH. '/plugin/plug_config.php');

        if($config['all'] === true){
            foreach(glob( ROOT_PATH. '/plugin/*') as $plugin){
                if(file_exists($plugin)){
                    include($plugin);
                }
            }
        }else{
            if(is_array($config['plugins'])){
                foreach($config['plugins'] as $cfg=>$cfg_signal){
                    $cfg_signal === true ? include(ROOT_PATH. '/plugin/'.$cfg.'.php') : null;
                }
            }
        }
    }
}
?>