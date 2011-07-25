<?php

class Buzzimg {
    
    protected $image;
    
    static $jpeg_quality = 90;
    
    protected static function _gd_read($filename) {
        
        $info = getimagesize($filename);
        
        switch($info[2]) {
            case IMAGETYPE_BMP: throw new Exception("Can't read BMP");
            case IMAGETYPE_GIF: return imagecreatefromgif($filename);
            case IMAGETYPE_JPEG: return imagecreatefromjpeg($filename);
            case IMAGETYPE_PNG: return imagecreatefrompng($filename);
            case IMAGETYPE_PSD: throw new Exception("Can't read PSD");
            case IMAGETYPE_WBMP: return imagecreatefromwbmp($filename);
            case IMAGETYPE_XBM: return imagecreatefromxbm($filename);
            default: throw new Exception("Unknown format");
        }
        
    }
    
    protected static function _gd_write($type, $image = null, $filename = null) {
        
        switch($type) {
            case IMAGETYPE_BMP: throw new Exception("Can't write BMP");
            case IMAGETYPE_GIF: $function = 'imagegif'; break;
            case IMAGETYPE_JPEG: $function = 'imagejpeg'; break;
            case IMAGETYPE_PNG: $function = 'imagepng'; break;
            case IMAGETYPE_PSD: throw new Exception("Can't write PSD");
            case IMAGETYPE_WBMP: $function = 'imagewbmp'; break;
            case IMAGETYPE_XBM: $function = 'imagexbm'; break;
            default: throw new Exception("Unknown format");
        }
        
        if($image === null) {
            return $function;
        } elseif($filename === null) {
            return $function($image);
        } elseif($type == IMAGETYPE_JPEG) {
            return $function($image, $filename, self::$jpeg_quality);
        } else {
            return $function($image, $filename);
        }
        
    }
    
    static function create($filename) {
        return new self($filename);
    }
    
    function __construct($filename) {
        $this->image = self::_gd_read($filename);
    }
    
    function __destruct() {
        imagedestroy($this->image);
    }
    
    function save($filename, $type) {
        self::_gd_write($type, $this->image, $filename);
    }
    
    function send($type) {
        
        $function = self::_gd_write($type);
        
        header('Content-Type: ' . image_type_to_mime_type($type));
        
        if($type == IMAGETYPE_JPEG) {
            $function($this->image, null, self::$jpeg_quality);
        } else {
            $function($this->image);
        }
        
        die(0);
    }
    
    function width() { return imagesx($this->image); }
    function height() { return imagesy($this->image); }
    
    function resize($width = null, $height = null) {
        
        if($width === null and $height === null) {
            throw new Exception("Both new width and new height can't be null.");
        }
        
        $w = imagesx($this->image);
        $h = imagesy($this->image);
        $a = $w/$h;
        
        if($width === null) {
            $width = ($height / $h) * $w;
        } elseif($height === null) {
            $height = ($width / $w) * $h;
        } elseif($width > ($height / $h) * $w) {
            $width = ($height / $h) * $w;
        } elseif($height > ($width / $w) * $h) {
            $height = ($width / $w) * $h;
        }
        
        $out = imagecreatetruecolor($width, $height);
        
        imagecopyresampled($out, $this->image, 0, 0, 0, 0, $width, $height, $w, $h);
        
        imagedestroy($this->image);
        
        $this->image = $out;
        
        return $this;
    }
    
    function thumbnail($width, $height) {
        
        $w = imagesx($this->image);
        $h = imagesy($this->image);
        $a = $w/$h;
        
        $out = imagecreatetruecolor($width, $height);
        $aspect = $width / $height;
        
        if($a == $aspect) {
            $sw = $w;
            $sh = $h;
        } elseif($a > $aspect) {
            $sw = round($h * $aspect);
            $sh = $h;
        } elseif($a < $aspect) {
            $sw = $w;
            $sh = round($w / $aspect);
        }
        
        $sx = floor(($w - $sw) / 2);
        $sy = floor(($h - $sh) / 2);
        
        imagecopyresampled($out, $this->image, 0, 0, $sx, $sy, $width, $height, $sw, $sh);
        
        imagedestroy($this->image);
        
        $this->image = $out;
        
        return $this;
    }
    
}
