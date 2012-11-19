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
        $this->smarty = new Smarty();
    }

    public function assign($key, $val){
        $this->smarty->assign($key, $val);
    }

    public function display($template){
        $this->smarty->display(APP_PATH . '/templates/' . $template. '.html');
    }
}
?>
