<?php
/*
 * 'Simple Makes Boom'
 * Created on 2012-11-5
 * @author: Kearney
 * @E-mail: kearneyjar@gmail.com
 *
 */

 class user extends Application{

    function index(){

         $this->login();
     }


    function login(){

        if($_POST){

            $username = $_POST['username'];
            $password = $_POST['password'];

            //使用数据库查询示例
            $mysql = new Mysql();
            $result = $mysql->select("select * from user where username='".$username."' and password='".$password."'");

            if($result[0]){
                $this->display('admin');
            }else{
                $this->display('login');
            }

            $mysql->close();
            //结束

        }else{
            //调用Smarty
            $this->display('login');
            //结束
        }
    }
 }

?>
