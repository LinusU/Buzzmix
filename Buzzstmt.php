<?php

class Buzzstmt {
    
    protected $sql = '';
    protected $type = null;
    protected $tables = array();
    
    protected static function escape_column($column) {
        return ('`' . str_replace('.', '`.`', $column) . '`');
    }
    
    protected static function escape_columns($columns) {
        
        $r = '';
        
        foreach($columns as $c) {
            $r .= (strlen($r)?',':'') . self::escape_column($c);
        }
        
        return $r;
    }
    
    protected static function escape_columns_order($columns) {
        
        $r = '';
        
        foreach($columns as $key => $val) {
            if(is_numeric($key)) { $key = $val; $val = ''; }
            $r .= (strlen($r)?',':'') . self::escape_column($key) . ($val != 'DESC'?'':' DESC');
        }
        
        return $r;
    }
    
    protected function where_condition($condition, $substitutes) {
        
        $r = '';
        
        if($condition instanceof Buzzsql) {
            $condition = array_merge(array($condition), $substitutes);
        }
        
        if(is_object($condition)) {
            $condition = (array) $condition;
        }
        
        if(is_array($condition)) {
            foreach($condition as $key => $val) {
                
                if(is_numeric($key) && is_string($val)) {
                    $r .= ($r?' AND ':'') . $val; continue;
                }
                
                $r .= ($r?' AND ':'') . self::escape_column(mysql_real_escape_string(
                    is_numeric($key)?(
                        (count($this->tables) == 1 && isset($this->tables[0]) && $this->tables[0] == $val::get_table())?
                        $val::get_primary() : sprintf($val::$link, $val::get_table(), $val::get_primary())
                    ):$key
                )) . '=';
                
                if(is_null($val)) {
                    $r .= 'NULL';
                } else {
                    $r .= ('\'' . mysql_real_escape_string(
                        ($val instanceof Buzzsql)?$val->__get($val::get_primary()):$val
                    ) . '\'');
                }
                
            }
        } else {
            for($i=0; $i<strlen($condition); $i++) {
                if($condition[$i] == '?') {
                    $r .= mysql_real_escape_string(array_shift($substitutes));
                } else {
                    $r .= $condition[$i];
                }
            }
        }
        
        return $r;
    }
    
    static function construct($type = null, $sql = '') {
        $params = func_get_args();
        if($type === null || strpos($type, ' ') === false) {
            $stmt = new self(array_shift($params));
        } else {
            $stmt = new self();
            $sql = $type;
        }
        return ($sql===''?$stmt:call_user_func_array(array($stmt, 'append'), $params));
    }
    
    function __construct($type = null, $sql = '') {
        $params = func_get_args();
        if($type === null || strpos($type, ' ') === false) {
            $this->type = array_shift($params);
        } else {
            $sql = $type;
        }
        if($sql !== '') { call_user_func_array(array($this, 'append'), $params); }
    }
    
    protected function append($sql) {
        
        if(func_num_args() == 1) {
            $this->sql .= $sql;
            return $this;
        }
        
        $k = 0;
        
        for($i=0; $i<strlen($sql); $i++) {
            if($sql[$i] == '?') {
                $this->sql .= mysql_real_escape_string(func_get_arg(++$k));
            } else {
                $this->sql .= $sql[$i];
            }
        }
        
        return $this;
    }
    
    function select() {
        switch(func_num_args()) {
            case 0: return $this->append('SELECT *');
            case 1:
                if(is_array(func_get_arg(0))) {
                    return $this->append('SELECT ' . self::escape_columns(func_get_arg(0)));
                } elseif(
                    strpos(func_get_arg(0), ' ') !== false or
                    strpos(func_get_arg(0), '(') !== false
                ) {
                    return $this->append('SELECT ' . func_get_arg(0));
                } else {
                    return $this->append('SELECT ' . self::escape_column(func_get_arg(0)));
                }
            default: return $this->append('SELECT ' . self::escape_columns(func_get_args()));
        }
    }
    
    function from($from) {
        if(is_array($from)) {
            
            $first = true;
            $this->tables = $from;
            
            foreach($from as $key => $val) {
                $this->append($first?' FROM ':','); $first = false;
                $this->append((is_numeric($key)?'':self::escape_column($key) . ' AS ') . self::escape_column($val));
            }
            
            return $this;
        } else {
            $this->tables = func_get_args();
            return $this->append(' FROM ' . self::escape_columns(func_get_args()));
        }
    }
    
    function update($table) {
        $this->tables = func_get_args();
        return $this->append('UPDATE ' . self::escape_columns(func_get_args()));
    }
    
    function set($values) {
        
        if(is_string($values)) {
            return $this->append(' SET ' . $values);
        }
        
        if(is_object($values)) {
            $values = (array) $values;
        }
        
        $first = true;
        
        foreach($values as $key => $val) {
            
            $this->append($first?' SET ':','); $first = false;
            
            $this->append(self::escape_column(mysql_real_escape_string(
                is_numeric($key)?sprintf($val::$link, $val::get_table(), $val::get_primary()):$key
            )));
            
            $this->append('=');
            
            if(is_null($val)) {
                $this->append('NULL');
            } else {
                $this->append('\'' . mysql_real_escape_string(
                    ($val instanceof Buzzsql)?$val->__get($val::get_primary()):$val
                ) . '\'');
            }
            
        }
        
        return $this;
    }
    
    function insert() {
        return $this->append('INSERT ');
    }
    
    function into($table) {
        $this->tables = array($table);
        return $this->append(' INTO ' . self::escape_column($table));
    }
    
    function values($values) {
        return $this->set($values);
    }
    
    function where($where) {
        
        $args = func_get_args();
        $where = array_shift($args);
        
        return $this->append(' WHERE ' . $this->where_condition($where, $args));
    }
    
    function group_by() {
        return $this->append(' GROUP BY ' . self::escape_columns(func_get_args()));
    }
    
    function having($having) {
        
        $args = func_get_args();
        $having = array_shift($args);
        
        return $this->append(' HAVING ' . $this->where_condition($having, $args));
    }
    
    function order_by($order) {
        if(is_array($order)) {
            return $this->append(' ORDER BY ' . self::escape_columns_order($order));
        } else {
            return $this->append(' ORDER BY ' . $order);
        }
    }
    
    function limit($limit = 1, $offset = 0) {
        return $this->append(' LIMIT ' . ((int) $offset) . ',' . ((int) $limit));
    }
    
    function run() {
        
        $result = mysql_query($this->sql);
        
        if($result === false) {
            throw new Buzzexcp("MySQL error: " . mysql_error());
        }
        
        return $result;
    }
    
    function many() {
        
        $result = $this->run();
        
        $ret = array();
        
        while($row = mysql_fetch_object($result)) {
            $ret[] = (is_null($this->type)?$row:new $this->type($row));
        }
        
        return $ret;
    }
    
    function one() {
        
        $result = $this->limit(1)->run();
        
        if(mysql_num_rows($result) == 0) {
            return false;
        }
        
        $row = mysql_fetch_object($result);
        
        return (is_null($this->type)?$row:new $this->type($row));
    }
    
}
