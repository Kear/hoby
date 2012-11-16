<?php
/*
 * 'Simple Makes Boom'
 * Created on 2012-11-15
 * @author: Kearney
 * @E-mail: kearneyjar@gmail.com
 *
 */

 return array(

        //all为true，自动加载
        'all'     => false,  // true or false

        //all为false，加载plugins里为true的类，该实例自动加载对象为'security'
        'plugins' => array(
                 'security' => true,
                 'statistics' => false,
                 'util' => false
         )
 )
 ?>
