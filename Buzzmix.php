<?php

if(!class_exists('Smarty')) {
    require (dirname(__FILE__) . "/smarty3/Smarty.class.php");
}

class Buzzmix extends Smarty {
    
    public $page_dir = null;
    public $page_suffix = ".php";
    
    public $class_dir = null;
    public $class_suffix = ".class.php";
    
    public $template_suffix = ".tpl";
    
    protected $headers = array();
    protected $footers = array();
    
    protected $displayed = array(false, false);
    
    protected $mysql = null;
    protected $is_cloned = false;
    protected $current_page = null;
    protected $content_type = "text/html";
    
    function __construct($base_dir = null) {
        parent::__construct();
        
        if(!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
        }
        
        if($base_dir !== null) {
            
            $base_dir = rtrim($base_dir, "/");
            
            $this->page_dir  = $base_dir . '/pages/';
            $this->class_dir = $base_dir . '/classes/';
            $this->setCompileDir($base_dir . '/compiled/');
            $this->setTemplateDir($base_dir . '/templates/');
            $this->addPluginsDir($base_dir . '/plugins/');
            
        }
        
        spl_autoload_register(array($this, '_autoload'));
    }
    
    function __destruct() {
        $this->displayHeader();
        $this->displayFooter();
        parent::__destruct();
    }
    
    function _autoload($class) {
        
        if($this->class_dir === null) {
            return false;
        }
        
        $file = $this->class_dir . $class . $this->class_suffix;
        
        if(!file_exists($file)) {
            return false;
        }
        
        include $file;
        
        return true;
    }
    
    function __clone() { $this->is_cloned = true; }
    
    function setHeader($tpl, $type = 'text/html') { $this->headers[$type] = $tpl; }
    function setFooter($tpl, $type = 'text/html') { $this->footers[$type] = $tpl; }
    
    function onlyContent() { $this->displayed = array(true, true); }
    
    function contentType($type, $subtype = null, $parameters = array()) {
        
        if($subtype === null) {
            $subtype = $type;
            $type = "text";
        }
        
        if($type == "text" && !isset($parameters['charset'])) {
            $parameters['charset'] = 'utf-8';
        }
        
        $p = '';
        
        foreach($parameters as $key => $val) {
            $p .= "; $key=$val";
        }
        
        header("Content-Type: $type/$subtype$p");
        
        $this->content_type = ($type . "/" . $subtype);
    }
    
    function mysqlSetup($hostname, $username, $password, $database) {
        $this->mysql = array(
            'connected' => false,
            'hostname' => $hostname,
            'username' => $username,
            'password' => $password,
            'database' => $database
        );
    }
    
    function mysqlConnect() {
        
        if($this->mysql === null) {
            return false;
        }
        
        if($this->mysql['connected']) {
            return true;
        }
        
        mysql_connect($this->mysql['hostname'], $this->mysql['username'], $this->mysql['password']);
        mysql_select_db($this->mysql['database']);
        mysql_set_charset("UTF8");
        
        return ($this->mysql['connected'] = true);
    }
    
    protected function displayHeader() {
        
        if($this->displayed[0] || $this->is_cloned) { return ; }
        if(empty($this->headers[$this->content_type])) { return ; }
        
        $content = ob_get_clean();
        
        parent::display($this->headers[$this->content_type]);
        $this->displayed[0] = true;
        
        if($content !== false) { echo $content; }
        
    }
    
    protected function displayFooter() {
        
        if($this->displayed[1] || $this->is_cloned) { return ; }
        if(empty($this->footers[$this->content_type])) { return ; }
        
        parent::display($this->footers[$this->content_type]);
        $this->displayed[1] = true;
        
    }
    
    function display($template = null, $cache_id = null, $compile_id = null, $parent = null) {
        
        $this->displayHeader();
        
        if($template === null) {
            $template = "pages/" . $this->current_page . $this->template_suffix;
        }
        
        return parent::display($template, $cache_id, $compile_id, $parent);
    }
    
    function handleRequest($uri) {
        
        if($this->page_dir === null) {
            throw new Exception("The pages directory is not set.");
        }
        
        $pos = strpos($uri, '?');
        
        if($pos !== false) {
            $uri = substr($uri, 0, $pos);
        }
        
        ob_start();
        
        if(strpos($uri, "..") !== false) {
            $status = 403;
        } else {
            
            $this->mysqlConnect();
            
            $parts = explode("/", trim($uri, "/"));
            $status = 404;
            
            for($i = count($parts); $i > 0; $i--) {
                
                $name = implode("/", array_slice($parts, 0, $i));
                $name .= (is_dir($this->page_dir . $name)?"/index":"");
                $file = $this->page_dir . $name . $this->page_suffix;
                
                if(file_exists($file)) {
                    $this->current_page = $name;
                    $status = $this->outputPage($file, $parts, $uri);
                    break;
                }
                
            }
            
        }
        
        if(!headers_sent()) {
            header('x', true, $status);
        }
        
        ob_end_flush();
        
        return $status;
    }
    
    function outputPage($file, $parts, $uri) {
        
        $smarty = $this;
        
        $r = include $file;
        
        return (($r === 1 || $r === true)?200:(($r === false)?500:$r));
    }
    
    function craftUrl($to = '', $keep_query_string = false) {
        
        if(preg_match('/^https?\:\/\//', $to)) {
            
            $url = $to;
            
        } else {
            
            if(empty($_SERVER['HTTPS']) or $_SERVER['HTTPS'] == "off") {
                $url = "http://";
            } else {
                $url = "https://";
            }
            
            $url .= $_SERVER['HTTP_HOST'];
            
            if(substr($to, 0, 1) != '/') {
                if(preg_match('/^(.+)\/([^\/]*)\?' . (empty($_SERVER['QUERY_STRING'])?'?':'') . preg_quote($_SERVER['QUERY_STRING'], '/') . '$/', $_SERVER['REQUEST_URI'], $matches)) {
                    $url .= $matches[1] . '/';
                } else {
                    $url .= '/';
                }
            }
            
            $url .= $to;
            
        }
        
        if($keep_query_string and strlen($_SERVER['QUERY_STRING']) > 0) {
            if(strpos($url, "?") === false) {
                $url .= "?" . $_SERVER['QUERY_STRING'];
            } else {
                $url .= "&" . $_SERVER['QUERY_STRING'];
            }
        }
        
        return $url;
        
    }
    
    function redirect($to, $keep_query_string = false, $status = 303) {
        
        $url = $this->craftUrl($to, $keep_query_string);
        
        header('x', true, $status);
        header('Location: ' . $url);
        
        $this->onlyContent();
        
        $title = sprintf("%u %s", $status, ($status == 301?"Moved Permanently":"See Other"));
        
        printf('<html><head><title>%s</title></head><body>%s', $title, PHP_EOL);
        printf('<h1>%s</h1>%s', $title, PHP_EOL);
        printf('<p>%s <a href="%2$s">%2$s</a></p>%s', "Please see:", $url, PHP_EOL);
        printf('</body></html>');
        
        die(0);
    }
    
}
