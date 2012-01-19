<?php

class Buzzauth extends Buzzsql {
    
    static $username = 'username';
    static $password = 'password';
    
    protected static $auth = false;
    
    static function get_auth() {
        $self = get_called_class();
        
        if($self::$auth !== false) {
            return $self::$auth;
        }
        
        if(isset($_SESSION[$self::get_table()])) {
            $self::$auth = $self::select()->where(array(
                $self::get_primary() => $_SESSION[$self::get_table()]
            ))->one();
        }
        
        return $self::$auth;
    }
    
    static function logged_in() {
        $self = get_called_class();
        return ($self::get_auth() !== false);
    }
    
    static function login($username, $password) {
        
        $self = get_called_class();
        
        $self::$auth = $self::select()->where(array(
            $self::$username => $username,
            $self::$password => $self::hash($password)
        ))->one();
        
        if($self::$auth !== false) {
            $_SESSION[$self::get_table()] = $self::$auth->__get($self::get_primary());
        }
        
        return $self::$auth;
    }
    
    static function logout() {
        $self = get_called_class();
        $self::$auth = false;
        $_SESSION[$self::get_table()] = null;
        unset($_SESSION[$self::get_table()]);
        return true;
    }
    
    static function hash($string) {
        return md5($string, true);
    }
    
    static function randomPassword($length = 6) {
        
        $ret = '';
        $chr = "abcdefghkmnpqrstuvwxyzABCDEFGHKLMNPRST23456789";
        
        while(strlen($ret) < $length) {
            $ret .= $chr[rand(0, strlen($chr) - 1)];
        }
        
        return $ret;
    }
    
}
