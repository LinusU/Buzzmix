<?php

spl_autoload_register(function ($className) {
    if(
        $className == "Buzzmix" ||
        $className == "Buzzsql" ||
        $className == "Buzzauth" ||
        $className == "Buzzimg"
    ) {
        require (dirname(__FILE__) . '/' . $className . '.php');
    }
});
