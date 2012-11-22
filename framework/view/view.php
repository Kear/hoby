<?php
/*
 * 'Simple Makes Boom'
 * Created on 2012-11-19
 * @author: Kearney
 * @E-mail: kearneyjar@gmail.com
 *
 */

class View{

    private $smarty = null;

    public function __construct(){

        include(FRAMEWORK_PATH.'/view/smarty/Smarty.class.php');
      	//define('APP_TEMPLATES_DIR',  APP_PATH . '/' .'templates');
	

        //SAE
        $path = "saemc://templates_c";


        mkdir($path);
        $this->smarty = new Smarty();
        $this->smarty->template_dir = "./templates";
        $this->smarty->compile_dir = $path;
        
        $app_path = explode('/', APP_PATH);
        
        $controller = array_pop($app_path);
        
        $php_self = $_SERVER['PHP_SELF'];
        
        $php_self_arr = explode('/', $php_self);
        $last_param = array_pop($php_self_arr);
      
        //默认控制和action
      	if($last_param == 'index.php'){
            $app_templates_dir = 'app/' . $controller . '/' . 'templates';
        
        //入口index.php/controller/action路由
        }else{
             $app_templates_dir = '../../../app/' . $controller . '/' . 'templates';
        }
        define('APP_TEMPLATES_DIR', $app_templates_dir);
        
    }

    public function assign($key, $val){
        $this->smarty->assign($key, $val);
    }

    public function display($template){
        $this->smarty->display(APP_PATH . '/templates/' . $template. '.html');
    }
}
?>
