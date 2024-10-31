<?php

function ads_autoloader($class){
    $file = array();
    $path = explode("\\", $class);
    
    if(count($path) < 2) {
        return;
    }
    $file += array(
        ADS_PATH . '/inc/'.strtolower($path[1]).'/' . $path[2] . '.php',
    );
    
    
    foreach($file as $fname){
        if(file_exists($fname)){
            require_once($fname);
            return;
        }
    }
    
}

spl_autoload_register('ads_autoloader');
