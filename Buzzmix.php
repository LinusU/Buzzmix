<?php

if(!class_exists('Smarty')) {
    require (dirname(__FILE__) . "/smarty3/libs/Smarty.class.php");
}

if(!function_exists('http_response_code')) {
    function http_response_code($response_code = null) {
        
        static $current_code = 200;
        
        if($response_code !== null) {
            
            switch($response_code) {
                case 100: $text = 'Continue'; break;
                case 101: $text = 'Switching Protocols'; break;
                case 200: $text = 'OK'; break;
                case 201: $text = 'Created'; break;
                case 202: $text = 'Accepted'; break;
                case 203: $text = 'Non-Authoritative Information'; break;
                case 204: $text = 'No Content'; break;
                case 205: $text = 'Reset Content'; break;
                case 206: $text = 'Partial Content'; break;
                case 300: $text = 'Multiple Choices'; break;
                case 301: $text = 'Moved Permanently'; break;
                case 302: $text = 'Moved Temporarily'; break;
                case 303: $text = 'See Other'; break;
                case 304: $text = 'Not Modified'; break;
                case 305: $text = 'Use Proxy'; break;
                case 400: $text = 'Bad Request'; break;
                case 401: $text = 'Unauthorized'; break;
                case 402: $text = 'Payment Required'; break;
                case 403: $text = 'Forbidden'; break;
                case 404: $text = 'Not Found'; break;
                case 405: $text = 'Method Not Allowed'; break;
                case 406: $text = 'Not Acceptable'; break;
                case 407: $text = 'Proxy Authentication Required'; break;
                case 408: $text = 'Request Time-out'; break;
                case 409: $text = 'Conflict'; break;
                case 410: $text = 'Gone'; break;
                case 411: $text = 'Length Required'; break;
                case 412: $text = 'Precondition Failed'; break;
                case 413: $text = 'Request Entity Too Large'; break;
                case 414: $text = 'Request-URI Too Large'; break;
                case 415: $text = 'Unsupported Media Type'; break;
                case 500: $text = 'Internal Server Error'; break;
                case 501: $text = 'Not Implemented'; break;
                case 502: $text = 'Bad Gateway'; break;
                case 503: $text = 'Service Unavailable'; break;
                case 504: $text = 'Gateway Time-out'; break;
                case 505: $text = 'HTTP Version not supported'; break;
                default: throw new Exception('Unknown http status code "' . $code . '"');
            }
            
            $current_code = $code;
            $protocol = (isset($_SERVER['SERVER_PROTOCOL'])?$_SERVER['SERVER_PROTOCOL']:'HTTP/1.0');
            
            header($protocol . ' ' . $code . ' ' . $text, true, $current_code);
            
        }
        
        return $current_code;
    }
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
        
        if($type == "text" && $subtype == "json") {
            $type = "application";
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
            throw new Buzzexcp("No MySQL host specified");
        }
        
        if($this->mysql['connected']) {
            return true;
        }
        
        try {
            mysql_connect($this->mysql['hostname'], $this->mysql['username'], $this->mysql['password']);
            mysql_select_db($this->mysql['database']);
            mysql_set_charset("UTF8");
        } catch(Exception $e) {
            throw new Buzzexcp("Couldn't connect to database", 0, $e);
        }
        
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
        
        try {
            parent::display($template, $cache_id, $compile_id, $parent);
        } catch(SmartyException $e) {
            return ( $this->templateExists($template) ? 500 : 404 );
        }
        
        return 200;
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
            
            if($this->mysql !== null) {
                $this->mysqlConnect();
            }
            
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
            http_response_code($status);
        }
        
        $l = ob_get_length();
        if($l !== false) { ( $l > 0 ? ob_end_flush() : ob_end_clean() ); }
        
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
        
        http_response_code($status);
        header('Location: ' . $url);
        
        $this->onlyContent();
        
        switch($status) {
            case 301: $title = 'Moved Permanently'; break;
            case 302: $title = 'Moved Temporarily'; break;
            case 303: $title = 'See Other'; break;
            default: $title = '';
        }
        
        printf(
            '<!DOCTYPE html>' . PHP_EOL .
            '<html>' . PHP_EOL .
            '<head><title>%1$u %2$s</title></head>' . PHP_EOL .
            '<body>' .
            '<h1>%1$u %2$s</h1>' .
            '<p>%3$s <a href="%4$s">%4$s</a></p>' .
            '</body>' . PHP_EOL .
            '</html>',
            $status,
            $title,
            "Please see:",
            $url
        );
        
        return $status;
    }
    
}
