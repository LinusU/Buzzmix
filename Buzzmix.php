<?php

if(!class_exists('Smarty')) {
    require "smarty/Smarty.class.php";
}

class Buzzmix extends Smarty {
    
    public $header_tpl = null;
    public $footer_tpl = null;
    
    public $css_dir = null;
    public $css_suffix = ".css";
    
    public $pages_dir = null;
    public $pages_suffix = ".php";
    
    public $images_dir = null;
    public $images_suffix = "";
    
    public $classes_dir = null;
    public $classes_suffix = ".class.php";
    
    public $templates_suffix = ".tpl";
    
    public $separator = ",";
    
    private $mysql = null;
    private $current_page = null;
    
    private $content_type_override = null;
    
    function __construct($base_dir = null) {
        parent::__construct();
        
        if($this->_contenttype() === null) {
            if(headers_sent()) {
                $this->content_type_override = 'text/html; charset=utf-8';
            } else {
                header('Content-Type: text/html; charset=utf-8');
            }
        }
        
        if($base_dir !== null) {
            
            while(substr($base_dir, -1) == "/") {
                $base_dir = substr($base_dir, 0, -1);
            }
            
            /* Set the directories */
            $this->css_dir      = $base_dir . '/css/';
            $this->pages_dir    = $base_dir . '/pages/';
            $this->images_dir   = $base_dir . '/images/';
            $this->classes_dir  = $base_dir . '/classes/';
            $this->compile_dir  = $base_dir . '/compiled/';
            $this->template_dir = $base_dir . '/templates/';
            
        }
        
        spl_autoload_register(array($this, '_autoload'));
        
    }
    
    function __destruct() {
        
        $this->display_header();
        
        if($this->footer_tpl !== null) {
            
            $type = $this->_contenttype();
            
            if(substr($type, 0, 9) == "text/html") {
                parent::display($this->footer_tpl);
            }
            
            $this->footer_tpl = null;
            
        }
        
        if(method_exists(get_parent_class(), '__destruct')) {
            parent::__destruct();
        }
        
    }
    
    function _autoload($class) {
        
        if($this->classes_dir === null) {
            return false;
        }
        
        $file = $this->classes_dir . $class . $this->classes_suffix;
        
        if(!file_exists($file)) {
            return false;
        }
        
        include $file;
        
        return true;
        
    }
    
    function _onlycontent() {
        
        $this->header_tpl = null;
        $this->footer_tpl = null;
        
    }
    
    function _contenttype() {
        
        if($this->content_type_override !== null) {
            return $this->content_type_override;
        }
        
        $headers = headers_list();
        
        foreach($headers as $cur) {
            
            list($key, $val) = explode(':', $cur, 2);
            
            if(trim($key) == 'Content-Type') {
                return trim($val);
            }
            
        }
        
        return null;
        
    }
    
    function mysql_setup($hostname, $username, $password, $database) {
        
        $this->mysql = array(
            'connected' => false,
            'hostname' => $hostname,
            'username' => $username,
            'password' => $password,
            'database' => $database
        );
        
    }
    
    function mysql_connect() {
        
        if($this->mysql === null) {
            return false;
        }
        
        if($this->mysql['connected']) {
            return true;
        }
        
        mysql_connect($this->mysql['hostname'], $this->mysql['username'], $this->mysql['password']);
        mysql_selectdb($this->mysql['database']);
        mysql_set_charset("UTF8");
        
        $this->mysql['connected'] = true;
        
        return true;
        
    }
    
    function display_header() {
        
        if($this->header_tpl !== null) {
            
            $type = $this->_contenttype();
            
            if(substr($type, 0, 9) == "text/html") {
                
                $content = ob_get_clean();
                
                parent::display($this->header_tpl);
                
                if($content !== false) {
                    echo $content;
                }
                
            }
            
            $this->header_tpl = null;
            
        }
        
    }
    
    function display($template = null, $cache_id = null, $compile_id = null, $parent = null) {
        
        $this->display_header();
        
        if($template === null) {
            $template = "pages/" . $this->current_page . $this->templates_suffix;
        }
        
        return parent::display($template, $cache_id, $compile_id, $parent);
        
    }
    
    function handle_css() {
        
        if($this->css_dir === null) {
            throw new Exception("The css directory is not set.");
        }
        
        header('Content-Type: text/css');
        
        $files = glob($this->css_dir . '*' . $this->css_suffix);
        
        foreach($files as $file) {
            
            echo PHP_EOL . '/* ' . basename($file) . ' */' . PHP_EOL;
            
            readfile($file);
            
            echo PHP_EOL;
            
        }
        
        $this->_onlycontent();
        die(0);
        
    }
    
    function handle_image($image) {
        
        if($this->images_dir === null) {
            throw new Exception("The images directory is not set.");
        }
        
        $file = $this->images_dir . str_replace($this->separator, '/', $image) . $this->images_suffix;
        
        if(!file_exists($file)) {
            return false;
        }
        
        $size = filesize($file);
        $etag = md5($file . ":" . $size);
        
        if(isset($_SERVER['HTTP_IF_NONE_MATCH']) and $_SERVER['HTTP_IF_NONE_MATCH'] == $etag) {
            
            header('HTTP/1.1 304 Not Modified');
            
            $this->_onlycontent();
            die(0);
            
        }
        
        if(substr($file, -4) == ".png") {
            header('Content-Type: image/png');
        } elseif(substr($file, -4) == ".jpg") {
            header('Content-Type: image/jpeg');
        } elseif(substr($file, -4) == ".gif") {
            header('Content-Type: image/gif');
        } else {
            header('Content-Type: image/png');
        }
        
        header('Content-Length: ' . $size);
        header('ETag: ' . $etag);
        
        readfile($file);
        
        $this->_onlycontent();
        die(0);
        
    }
    
    function handle_request($uri) {
        
        while(substr($uri, 0, 1) == "/") {
            $uri = substr($uri, 1);
        }
        
        $pos = strpos($uri, '?');
        
        if($pos !== false) {
            $uri = substr($uri, 0, $pos);
        }
        
        while(substr($uri, -1) == "/") {
            $uri = substr($uri, 0, -1);
        }
        
        if(preg_match('/^css$/', $uri) == 1) {
            return $this->handle_css();
        }
        
        if(preg_match('/^img' . preg_quote($this->separator, "/") . '(([a-zA-Z0-9_+-]+' . preg_quote($this->separator, "/") . ')*[a-zA-Z0-9_+-]+(\.[a-z]+)?)/', $uri, $matches) == 1) {
            return $this->handle_image($matches[1]);
        }
        
        if($this->pages_dir === null) {
            throw new Exception("The pages directory is not set.");
        }
        
        if($this->separator != "/") {
            if(strpos($uri, "/") !== false) {
                return false;
            }
        }
        
        if(strpos($uri, ".") !== false) {
            return false;
        }
        
        ob_start();
        
        $this->mysql_connect();
        
        $parts = explode($this->separator, $uri);
        
        for($i = count($parts); $i > 0; $i--) {
            
            $file = (
                $this->pages_dir .
                implode("/", array_slice($parts, 0, $i)) . 
                $this->pages_suffix
            );
            
            if(file_exists($file)) {
                
                $this->current_page = implode('/', array_slice($parts, 0, $i));
                $this->output_page($file, $parts, $uri);
                
                return true;
                
            }
            
        }
        
        ob_end_flush();
        
        return false;
        
    }
    
    function output_page($file, $parts, $uri) {
        
        $smarty = $this;
        
        include $file;
        
        return true;
        
    }
    
    function craft_url($to = '', $keep_query_string = false) {
        
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
        
        $url = $this->craft_url($to, $keep_query_string);
        
        header('x', true, $status);
        header('Location: ' . $url);
        
        printf('<h1>%u %s</h1>%s', $status, ($status == 301?"Moved Permanently":"See Other"), PHP_EOL);
        printf('<p>%s <a href="%2$s">%2$s</a></p>', "Please see:", $url);
        
        die(0);
        
    }
    
}
