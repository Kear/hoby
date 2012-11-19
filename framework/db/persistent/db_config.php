<?php
/*
 * 'Simple Makes Boom'
 * Created on 2012-11-19
 * @author: Kearney
 * @E-mail: kearneyjar@gmail.com
 *
 */

return array(
    'dbms' => 'pdomysql',  //默认为pdo
    'tablePrefix' => '',   //可选
    'tableSuffix' => '',   //可选
    'dbFieldtypeCheck' => true,

    /**
     * 如果不分read和write ，将会自动合并成array('read'=>array(),'write'=>array())
     */


    'read' => array(
        'host'     => 'localhost',
        'port'     => '3306',
        'dbname'   => 'faxing',
        'username' => 'root',
        'password' => '',
        'charset'  => 'utf8',
        'persist'  => '0',
    ),
    'write' => array(
        'host'     => 'localhost',
        'port'     => '3306',
        'dbname'   => 'faxing',
        'username' => 'root',
        'password' => '',
        'charset'  => 'utf8',
        'persist'  => '0',
    )
);

?>
