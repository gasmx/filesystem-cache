<?php

/**
 * This is a pure class for caching using filesystem with zero dependencies.
 * The usage interface is very simple and intuitive, however you can find docs and usage examples in github repo.
 * https://github.com/gasmx/filesystem-cache 
 * Feel free to contribute 🚀
 * 
 * @author Gabriel Angelus
 */

namespace Gasmx;

class Cache
{
    // Extension used in cache files
    const EXT = '.tmp';
    // Extension used in option files
    const OPTIONS_EXT = '.opt';

    // Used to locate an item
    private $key;
    // Path to cache dir
    private $dir;
    // Options used in an item
    private $options;
    // Instance created to store options
    private $optionsInstance;

    // Singleton used to isolate items by domain
    static $preffix;
    // Singleton instance created to store the item
    static $instance;
    // Singleton for cache dir
    static $staticDir = 'tmp';
    // Single used to store in human readable format
    static $prettyPrint = true;    

    private function __construct($key, $createOptionsFile = true)
    {
        $this->key = $key;
        $this->dir = self::$staticDir . '/';

        // Create default options for the item
        $this->options = [
            'expiry' => -1,
            'lock' => false
        ];

        if ($createOptionsFile) {
            // Create options instance
            $this->optionsInstance = new self($this->key . self::OPTIONS_EXT, false);

            // Load options for the item
            if ($this->optionsInstance->fileExists()) {
                $this->options = $this->optionsInstance->get();
            }
        }
    }

    private function saveOptions()
    {
        if ($this->optionsInstance) {
            $this->optionsInstance->set($this->options);
        }
    }

    public static function key($key, $usePreffix = true)
    {
        if ($usePreffix && self::$preffix) {
            $key = self::$preffix . "__{$key}";
        }

        // Singleton pattern
        if (!isset(self::$instance[$key])) {
            self::$instance[$key] = new self($key);
        }
        
        return self::$instance[$key];
    }

    public static function setDirectory($dirname)
    {
        self::$staticDir = $dirname;
    }

    public static function setPreffix($preffix)
    {
        self::$preffix = $preffix;
    }

    public static function setPretty($val)
    {
        self::$prettyPrint = (boolean) $val;
    }

    // Store/Update a value for the item
    public function set($val, $secondsToExpire = null, $lock = null)
    {
        $key = $this->key;

        // Do nothing if item is locked
        if ($this->options['lock'] === true) { 
            return $this;
        }

        // Retrieve cached item
        $val = var_export($val, true);

        // Checking if storage will be done in a single line to optmize storage
        if (!self::$prettyPrint) {
            $val = str_replace(["\n", ",  '", " => "], ["", ",'", "=>"], $val);
        }

        // Create temporary file first to ensure atomicity
        $tmp = $this->dir . "$key." . uniqid('', true) . self::EXT;

        // Create and write to cache file
        $file = fopen($tmp, 'x');
        fwrite($file, '<?php $val=' . $val . ';');
        fclose($file);
        rename($tmp, $this->dir . $key);

        // Set expiry time
        if ($secondsToExpire) {
            $this->options['expiry'] = time() + $secondsToExpire;
        }

        // Set lock state
        if (is_bool($lock)) {
            $this->options['lock'] = $lock;
        }

        $this->saveOptions();
 
        return $this;
    }

    public function get()
    {
        // Return null if item is expired or not found
        if (!$this->isValid()) {
            return;
        }

        // Retrieve item and return it
        include $this->dir . "$this->key";
        return isset($val) ? $val : false;
    }

    public function getOptions()
    {
        return $this->options;
    }

    // Checks if item exists in filesystem and if it is expired
    public function isValid()
    {
        if ($this->options['expiry'] != -1 && $this->options['expiry'] < time()) {
            return false;
        }

        if (!$this->fileExists()) {
            return false;
        }

        return true;
    }

    // Checks if file for item exists in the filesystem
    private function fileExists()
    {
        return file_exists($this->dir . $this->key);
    }

    // Delete all files created in cache dir
    public static function clearAll()
    {
        $files = glob(self::$staticDir . '/*');

        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    // Enable lock option for the item
    public function lock()
    {
        $this->options['lock'] = true;
        $this->saveOptions();
        return $this;
    }

    // Disable lock option for the item
    public function unlock()
    {
        $this->options['lock'] = false;
        $this->saveOptions();
        return $this;
    }

    // Update options for the item
    public function options($options)
    {
        $this->options = array_merge($this->options, $options);
        $this->saveOptions();
        return $this;
    }

    // Remove item from the cache system
    public function destroy()
    {
        unlink($this->dir . $this->key);
        unlink($this->dir . $this->key . self::OPTIONS_EXT);
    }
}
