<?php

/**
 * This class is an autoloader which load the required file,
 * depending on namespace and classname.
 * The combination of namespace and classname have to be the 
 * same as in your project directory.
 * 
 * @author Andreas Eckhoff <andreas.eckhoff@logic-works.de>
 * @package lw_directoryobserver
 */

namespace LwDirectoryObserver\Services;

class Autoloader
{

    /**
     * A new autoloader will be registered in the system.
     */
    public function __construct()
    {
        spl_autoload_register(array($this, 'loader'));
    }

    /**
     * Depending on the class name will be the php file loaded.
     * 
     * @param string $className
     */
    private function loader($className)
    {
        $path = dirname(__FILE__) . '/..';
        $filename = str_replace('LwDirectoryObserver', $path, $className);

        $filename = str_replace('\\', '/', $filename) . '.php';
        if (is_file($filename)) {
            include_once($filename);
        }
    }

}