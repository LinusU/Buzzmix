<?php

class Buzzsql {
    
    protected $info = array();
    protected $update = array();
    protected $primary_id = null;
    
    static $table = null;
    static $primary = null;
    
    static $link = '%s_%s';
    
    static function get_table() {
        $self = get_called_class();
        return (is_null($self::$table)?$self:$self::$table);
    }
    
    static function get_primary() {
        $self = get_called_class();
        return (is_null($self::$primary)?'id':$self::$primary);
    }
    
    function __construct($primaryOrRow = null) {
        if(is_a($primaryOrRow, __CLASS__)) {
            $self = get_called_class();
            $this->primary_id = $primaryOrRow->__get(sprintf($self::$link, $self::get_table(), $self::get_primary()));
        } elseif(is_array($primaryOrRow)) {
            $this->info = $primaryOrRow;
        } elseif(is_object($primaryOrRow)) {
            $this->info = (array) $primaryOrRow;
        } elseif(!is_null($primaryOrRow)) {
            $this->primary_id = $primaryOrRow;
        }
    }
    
    function __destruct() {
        $this->save();
    }
    
    function __get($name) {
        
        $self = get_called_class();
        
        if(!is_null($this->primary_id)) {
            if($name == $self::get_primary()) {
                return $this->primary_id;
            } else {
                $this->load();
            }
        }
        
        if(isset($this->update[$name])) {
            return $this->update[$name];
        } elseif(isset($this->info[$name])) {
            return $this->info[$name];
        } else {
            return null;
        }
        
    }
    
    function __set($name,$value) {
        
        if(is_a($value, __CLASS__)) {
            $value = $value->__get($value::get_primary());
        }
        
        if(isset($this->info[$name])) {
            if($this->info[$name] != $value) {
                $this->update[$name] = $value;
            } else {
                unset($this->update[$name]);
            }
        } elseif(!is_null($this->primary_id)) {
            $this->update[$name] = $value;
        }
        
    }
    
    function __isset($name) {
        return isset($this->info[$name]);
    }
    
    function __unset($name) {
        if(isset($this->update[$name])) {
            unset($this->update[$name]);
        }
    }
    
    function changed() {
        return (count($this->update) > 0);
    }
    
    function save() {
        
        if(count($this->update) == 0) {
            return true;
        }
        
        $self = get_called_class();
        
        $stmt = $self::update()->set($this->update)->where(
            "`?` = '?'", $self::get_primary(),
            $this->__get($self::get_primary())
        );
        
        if($stmt->run()) {
            $this->info = $this->update + $this->info;
            $this->update = array();
            return (mysql_affected_rows() > 0);
        } else {
            return false;
        }
        
    }
    
    function load($primary = null) {
        
        $self = get_called_class();
        
        $re = Buzzstmt::construct()->select()->from($self::get_table())->where(
            "`?` = '?'", $self::get_primary(),
            (is_null($primary)?$this->primary_id:$primary)
        )->one();
        
        if($re === false) {
            return false;
        }
        
        $this->info = (array) $re;
        
        if(is_null($this->primary_id)) {
            $this->update = array();
        } else {
            $this->primary_id = null;
        }
        
        return true;
    }
    
    function get_row() {
        if($this->primary_id !== null) { $this->load(); }
        return $this->update + $this->info;
    }
    
    function get_columns() {
        if($this->primary_id !== null) { $this->load(); }
        return array_keys($this->info);
    }
    
    static function insert($data) {
        $self = get_called_class();
        $stmt = Buzzstmt::construct($self)->insert()->into($self::get_table())->values($data);
        return ($stmt->run()?new $self(mysql_insert_id()):false);
    }
    
    static function select() {
        $self = get_called_class();
        $stmt = call_user_func_array(array(new Buzzstmt($self), 'select'), func_get_args());
        return $stmt->from($self::get_table());
    }
    
    static function update() {
        $self = get_called_class();
        return Buzzstmt::construct($self)->update($self::get_table());
    }
    
}
