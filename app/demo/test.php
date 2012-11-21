<?php
/*
 * 'Simple Makes Boom'
 * Created on 2012-11-5
 * @author: Kearney
 * @E-mail: kearneyjar@gmail.com
 *
 */

 class Test extends Application{

    function Index(){

        //加载数据model
        $modelFile = str_replace('\\','/',(dirname(__FILE__))).'/model/mysql.php';
        if(!file_exists($modelFile)){
            exit("应用model加载失败，找不到该文件:".$modelFile);
        }
        include($modelFile);
        //结束


        //使用数据库查询示例
        $mysql = new Mysql();
        $result = $mysql->select('select * from news');
        print_r($result);
        $mysql->close();
        //结束


        //调用Smarty
        $this->assign('test', 'nihao');
        $this->display('login');
        //结束

     }
    function Tester(){
        $this->assign('test', 'nihao');
        $this->display('admin');
    }
 }

?>
