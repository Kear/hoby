<?php
/*
 * 'Simple Makes Boom'
 * Created on 2012-11-5
 * @author: Kearney
 * @E-mail: kearneyjar@gmail.com
 *
 */

 class Test extends Application{

    function index(){

        //调用Smarty
        $this->assign('test', 'nihao');
        $this->display('login');
        //结束

     }

    function tester(){

        $this->assign('test', 'nihao');
        $this->display('admin');
    }

    function mysql(){


        //使用数据库查询示例
        $mysql = new Mysql();
        $result = $mysql->select('select * from user');
        print_r($result);

        $mysql->close();
        //结束
    }
 }

?>
