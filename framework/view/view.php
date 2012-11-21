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

        //SAE
        $path = "saemc://templates_c";
        mkdir($path);
        $this->smarty = new Smarty();
        $this->smarty->template_dir = "./templates";
        $this->smarty->compile_dir = $path;

    }

    public function assign($key, $val){
        $this->smarty->assign($key, $val);
    }

    public function display($template){
        $this->smarty->display(APP_PATH . '/templates/' . $template. '.html');
    }
}
?>
