<?php
/*
 * 'Simple Makes Boom'
 * Created on 2012-11-5
 * @author: Kearney
 * @E-mail: kearneyjar@gmail.com
 *
 */

 class Helloworld extends Application{


    function Index(){
        echo "hello world!";
        $modelFile = str_replace('\\','/',(dirname(__FILE__))).'/model/memcahce.php';

        if(!file_exists($modelFile)){
            exit("应用model加载失败，找不到该文件:".$modelFile);
        }
        include($modelFile);
        $m = new helloModel();
        $m->add_server();
        $m->set_cache('kearney', 'hehehe');

        $value = $m->get_cache('kearney');
        echo "这是从Memcache里读出来的：".$value;
        $m->close();

        $sec = new Security();

        $this->assign('test', 'nihao');
        $this->display('index');

     }
    function Test(){
        echo 'hahahahah';
    }
 }

?>
