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
            $self::$auth = $self::select_one(array(
                $self::get_primary() => $_SESSION[$self::get_table()]
            ));
        }
        
        return $self::$auth;
    }
    
    static function logged_in() {
        $self = get_called_class();
        return ($self::get_auth() !== false);
    }
    
    static function login($username, $password) {
        
        $self = get_called_class();
        
        $self::$auth = $self::select_one(array(
            $self::$username => $username,
            $self::$password => $self::hash($password)
        ));
        
        if($self::$auth !== false) {
            $_SESSION[$self::get_table()] = $self::$auth->__get($self::get_primary());
        }
        
        return ($self::$auth !== false);
    }
    
    static function logout() {
        $self = get_called_class();
        $_SESSION[$self::get_table()] = null;
        unset($_SESSION[$self::get_table()]);
        return true;
    }
    
    static function hash($string) {
        return md5($string, true);
    }
    
}
