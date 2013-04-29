<?php
class phpUnitConfig
{
    public function __construct() 
    {
    }
    
    public function getConfig()
    {
        return array(
            "lwdb"              => array("user" => "root",
                                        "pass" => "tischtennis",
                                        "host" => "localhost",
                                        "db"   => "phpUnit"),
            
            "libraries"         => "/var/www/c38/contentory/c_libraries/",
            
            "pathForDirTesting" => "/var/www/c38/lw_resource/"
        );
    }
}