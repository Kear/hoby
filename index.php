<?php
/*
 * 'Simple Makes Boom'
 * Created on 2012-11-5
 * @author: Kearney
 * @E-mail: kearneyjar@gmail.com
 *
 */
define('ISEXIST',true);

require 'framework/config/config.php';
require FRAMEWORK_PATH.'/Bootstrap.php';


$bootstrap = new Bootstrap();
$bootstrap->Run();

?>