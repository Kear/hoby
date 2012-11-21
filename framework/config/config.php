<?php
/*
 * 'Simple Makes Boom'
 * Created on 2012-11-7
 * @author: Kearney
 * @E-mail: kearneyjar@gmail.com
 *
 */
header('Content-Type:text/html; charset=utf-8');


define('ROOT_PATH',str_replace('\\','/',dirname(dirname(dirname(__FILE__)))));
define('FRAMEWORK_PATH', ROOT_PATH . '/framework');
define('APP_ROOT_PATH', ROOT_PATH . '/app');


  if(!defined('ISEXIST')) exit("请从入口文件运行程序");

  $Config = array(
      'URL_MODE' => 2,//url模式,1为普通模式,2为path_info模式

      'DEAFAULT_APP' => 'demo',//默认的应用
      'DEFAULT_CONTROLLER' => 'test',//默认的控制器
      'DEFAULT_ACTION' => 'index'//默认的方法
      );
?>