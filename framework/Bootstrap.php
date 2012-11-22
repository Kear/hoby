<?php
class Bootstrap {
    public function Run() {

        if(!defined('ISEXIST')){
            exit("请从入口文件运行程序");
        }

        //处理URL路由
        $this->AnalysisURL();

        //加载Plugin类
        $this->launchPlugin();

        //引入View类
        $this->launchView();

        //加载Model类
        $this->launchModel();

        //加载Application类
        $this->launchApplication();

        //加载创建Controller实例
        $this->makeController();

    }

    protected function AnalysisURL() {
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

        define('APP_PATH', APP_ROOT_PATH . '/' . $_GET['app']);
        define('CONTOLLER_PATH', APP_PATH. '/' . $_GET['con']);
        define('ACTION_PATH', CONTOLLER_PATH . '/' . $_GET['act']);
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


    //加载数据Model类
    public function launchModel(){

        $dbFile = FRAMEWORK_PATH . '/db/persistent/db.php';
        //引入DB类
        if(!file_exists($dbFile)){
            exit("db类不存在。");
        }
        include($dbFile);

        //加载数据mysql_model，由于app/model是集成db类，因此后加载app/model
        $modelFile = APP_PATH . '/model/mysql.php';

        if(!file_exists($modelFile)){
            exit("应用model加载失败，找不到该文件:".$modelFile);
        }
        include($modelFile);
        //结束
    }


    //加载Application类
    public function launchApplication(){
        //引入Application类
        $applicationFile = FRAMEWORK_PATH . '/Application.php';

        if(!file_exists($applicationFile)){
            exit("Application类不存在。");
        }
        include($applicationFile);
    }


    //加载View类
    public function launchView(){
        include($viewfile = FRAMEWORK_PATH . '/view/view.php');
    }


    //创建Controller实例
    public function makeController(){

        //开始解析URL获得请求的控制器和方法
        $app = $_GET['app'];
        $control = $_GET['con'];
        $action = $_GET['act'];
        $action = ucfirst($action);

        //这里构造出控制器文件的路径
        $controlFile = CONTOLLER_PATH. '.php';

        //如果文件不存在提示错误, 否则引入
        if (!file_exists($controlFile)){
            exit("{$control}控制器不存在<br>" . "请检查: " . $controlFile . "是否存在<br>");
        }
        include($controlFile);


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
}
?>