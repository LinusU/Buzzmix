<?php

spl_autoload_register(function ($className) {
    
    if(substr($className, 0, 4) != 'Buzz') {
        return false;
    }
    
    $file = dirname(__FILE__) . '/' . $className . '.php';
    
    if(!file_exists($file)) {
        return false;
    }
    
    include $file;
    
    return true;
    
});
