<?php
/*
 * 'Simple Makes Boom'
 * Created on 2012-11-5
 * @author: Kearney
 * @E-mail: kearneyjar@gmail.com
 *
 */

class DB{

    public function __construct(){
        $this->init();
    }

    public function init(){

         //SAE连接方式
         $conn = mysql_connect(SAE_MYSQL_HOST_M.":".SAE_MYSQL_PORT,SAE_MYSQL_USER,SAE_MYSQL_PASS) or die("数据库服务器连接错误".mysql_error());
         mysql_select_db(SAE_MYSQL_DB,$conn) or die("数据库访问错误".mysql_error());
         mysql_query("set character set utf-8");
         mysql_query("set names utf8");
    }

    public function insert($sql){
        return mysql_query($sql) or die("Invalid query: " . mysql_error());
    }

    public function delete($sql){
        return mysql_query($sql) or die("Invalid query: " . mysql_error());
    }

    public function update($sql){
        return mysql_query($sql) or die("Invalid query: " . mysql_error());
    }

    public function select($sql){

        $resource =  mysql_query($sql) or die("Invalid query: " . mysql_error());

        $result = null;
        while($result[] = mysql_fetch_array($resource, MYSQL_BOTH)){}

        return $result;
    }

    public function close(){
        mysql_close();
    }
}
?>
