<?php

spl_autoload_register(function ($className) {
    if(
        $className == "Buzzmix" ||
        $className == "Buzzsql" ||
        $className == "Buzzstmt" ||
        $className == "Buzzauth" ||
        $className == "Buzzimg"
    ) {
        require (dirname(__FILE__) . '/' . $className . '.php');
    }
});
