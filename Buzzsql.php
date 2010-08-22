<?php

class Buzzsql {
    
    protected $info = array();
    protected $update = array();
    
    static $table = null;
    static $primary = null;
    
    protected static function build_sql($where, $order_by, $limit) {
        
        $sql = '';
        
        $first = true;
        
        foreach($where as $key => $val) {
            
            if($first) {
                $first = false;
                $sql .= ' WHERE ';
            } else {
                $sql .= ' AND ';
            }
            
            $sql .= "`$key` = '" . mysql_real_escape_string($val) . "'";
            
        }
        
        $first = true;
        
        foreach($order_by as $key => $val) {
            if(in_array($val,array('ASC','DESC'))) {
                
                if($first) {
                    $first = false;
                    $sql .= ' ORDER BY ';
                } else {
                    $sql .= ', ';
                }
                
                $sql .= "`$key` $val";
                
            }
        }
        
        if($limit !== null) {
            
            $sql .= ' LIMIT ' . $limit;
            
        }
        
        return $sql;
        
    }
    
    static function get_table() {
        
        $self = get_called_class();
        
        if($self::$table === null) {
            return $self;
        } else {
            return $self::$table;
        }
        
    }
    
    static function get_primary() {
        
        $self = get_called_class();
        
        if($self::$primary === null) {
            return 'id';
        } else {
            return $self::$primary;
        }
        
    }
    
    function __construct($primaryOrRow = null) {
        if(is_array($primaryOrRow)) {
            $this->info = $primaryOrRow;
        } elseif($primaryOrRow === null) {
            // Null!
        } else {
            $this->load($primaryOrRow);
        }
    }
    
    function __destruct() {
        $this->save();
    }
    
    function __get($name) {
        if(isset($this->update[$name])) {
            return $this->update[$name];
        } elseif(isset($this->info[$name])) {
            return $this->info[$name];
        } else {
            return null;
        }
    }
    
    function __set($name,$value) {
        if(isset($this->info[$name])) {
            if($this->info[$name] !== $value) {
                $this->update[$name] = $value;
            } else {
                unset($this->update[$name]);
            }
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
    
    function get_one($type) {
        
        return new $type($this->__get($type::get_table()));
        
    }
    
    function get_many($type, $order_by = array(), $limit = null) {
        
        $self = get_called_class();
        
        return $type::select(
            array(
                $self::get_table() => $this->__get($self::get_primary())
            ), $order_by, $limit
        );
        
    }
    
    function save() {
        
        if(count($this->update) == 0) {
            return true;
        }
        
        $self = get_called_class();
        $update = '';
        
        foreach($this->update as $key => $val) {
            
            if($update != '') {
                $update .= ',';
            }
            
            if($val === null) {
                
                $update .= "`$key`=NULL";
                
            } else {
                
                $update .= "`$key`='" . mysql_real_escape_string($val) . "'";
                
            }
            
        }
        
        if(mysql_query(sprintf(
            "UPDATE `%s` SET %s WHERE `%s`='%s'",
            $self::get_table(), $update, $self::get_primary(),
            mysql_real_escape_string($this->__get($self::get_primary()))
        ))) {
            // NOTE: Should check mysql_affected_rows before updating local??
            $this->info = $this->update + $this->info;
            $this->update = array();
            return (mysql_affected_rows() > 0);
        } else {
            return false;
        }
        
    }
    
    function load($primary) {
        
        $self = get_called_class();
        
        $re = mysql_query(sprintf(
            "SELECT * FROM `%s` WHERE `%s`='%s'",
            $self::get_table(), $self::get_primary(),
            mysql_real_escape_string($primary)
        ));
        
        if($re !== false and mysql_numrows($re) > 0) {
            $this->info = mysql_fetch_assoc($re);
            $this->update = array();
            return true;
        } else {
            return false;
        }
        
    }
    
    function get_row() {
        return $this->update + $this->info;
    }
    
    function get_columns() {
        return array_keys($this->info);
    }
    
    static function insert($data) {
        
        $self = get_called_class();
        
        $keys = array();
        $vals = array();
        
        foreach($data as $key => $val) {
            $keys[] = mysql_real_escape_string($key);
            $vals[] = mysql_real_escape_string($val);
        }
        
        $re = mysql_query(sprintf(
            "INSERT INTO `%s` (`%s`) VALUES('%s')",
            $self::get_table(), implode("`,`",$keys),
            implode("','",$vals)
        ));
        
        if($re === false) {
            return false;
        } else {
            return new $self(mysql_insert_id());
        }
        
    }
    
    static function select($where = array(), $order_by = array(), $limit = null) {
        
        $self = get_called_class();
        
        $re = mysql_query(sprintf(
            'SELECT * FROM `%s`%s',
            $self::get_table(),
            $self::build_sql($where, $order_by, $limit)
        ));
        
        if($re === false) {
            return false;
        }
        
        $ret = array();
        
        while($row = mysql_fetch_assoc($re)) {
            $ret[] = new $self($row);
        }
        
        return $ret;
        
    }
    
}
