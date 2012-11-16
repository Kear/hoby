<?php
/*
 * 'Simple Makes Boom'
 * Created on 2012-11-13
 * @author: Kearney
 * @E-mail: kearneyjar@gmail.com
 *
 */
 isset($_GET['url']) ? $url = $_GET['url'] : $url = "http://localhost/demo/helloworld/index";

 $cron_content = "* * * * * curl ".$url;
 $crontab_file = 'cron';
 $exec_srt = "crontab -e ".$crontab_file;

 exec($exec_srt);
?>
