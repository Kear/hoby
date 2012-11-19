<?php
/*
 * 'Simple Makes Boom'
 * Created on 2012-11-8
 * @author: Kearney
 * @E-mail: kearneyjar@gmail.com
 *
 */
 class Application extends View{

     public function __construct(){
         parent::__construct();
         $this->_init();
     }

     public function _init(){

         echo 'Autoload start...<br>';
         $this->load_privilege();
         $this->load_config();
         $this->load_functions();
         $this->load_model();
         $this->generate_log();
         echo 'Autoload end!<br>';
         echo '-----------------------<br>';
     }

     public function load_privilege(){
        echo "Here is loading privilege.<br>";
     }
     public function load_config(){
         echo "Here is loading config.<br>";

     }
     public function load_functions(){
         echo "Here is loading functions.<br>";
     }
     public function load_model(){

         echo "Here is loading model.<br>";
     }
     public function generate_log(){
         echo "Here is generating logs.<br>";
     }
}



?>
