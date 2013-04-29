<?php
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);

$config = parse_ini_file("/var/www/c38/lw_configs/conf.inc.php", true);

include_once($config["path"]["package"] . "LwDirectoryObserver/Model/DirectoryObserver.php");
include_once($config["path"]["package"] . "LwDirectoryObserver/Model/ChangeLogDirectoryNotWritableException.php");
include_once($config["path"]["package"] . "LwDirectoryObserver/Model/ChangeLogDirectoryNotExistingException.php");
include_once($config["path"]["package"] . "LwDirectoryObserver/Model/DirectoryObserverLogDirectoryNotExistingException.php");
include_once($config["path"]["package"] . "LwDirectoryObserver/Model/ObserveDirectoryNotExistingException.php");
include_once($config["path"]["package"] . "LwDirectoryObserver/Model/JqPlotDirectoryNotExistingException.php");

class lwBaseAutoloader
{
    public function __construct($config)
    {
         $this->config = $config;
         spl_autoload_register(array($this, 'loader'));
    }
 
    private function loader($className)
    {
        if (strtolower(substr($className, 0, 6)) == "build_") {
           if (is_file($this->config['path']['builder'] . $className . ".class.php")) {
               include_once($this->config['path']['builder'] . $className . ".class.php");
           }
        }
    }
}
 
$autoloader = new lwBaseAutoloader($config);
$builder    = new build_autoloadregistry($config);

if ($config["lwdb"]["type"] == "mysql" || $config["lwdb"]["type"] == "mysqli") {
    include_once($config["path"]["framework"] . "lw/lw_db_mysqli.class.php");
    $db = new lw_db_mysqli($config["lwdb"]["user"], $config["lwdb"]["pass"], $config["lwdb"]["host"], $config["lwdb"]["name"]);
    $db->connect();
}
elseif ($config["lwdb"]["type"] == "oracle") {
    include_once($config["path"]["framework"] . "lw/lw_db_oracle.class.php");
    $db = new lw_db_oracle($config["lwdb"]["user"], $config["lwdb"]["pass"], $config["lwdb"]["host"], $config["lwdb"]["name"]);
    $db->connect();
}

$db->setStatement("SELECT opt1text FROM t:lw_master WHERE lw_object = :lw_object ");
$db->bindParameter("lw_object", "s", "agent_direcotryobserver");
$observeDirectories = $db->pselect();

foreach($observeDirectories as $observePath){
    try {
        include_once($config["path"]["package"] . "LwDirectoryObserver/Model/DirectoryObserver.php");
        $directoryObserver = new \LwDirectoryObserver\Model\DirectoryObserver($config, $observePath["opt1text"]);
    } catch (\LwDirectoryObserver\Model\ObserveDirectoryNotExistingException $exc) {
        die("Das zu beobachtene Verzeichnis ist nicht vorhanden.");
    } catch (\LwDirectoryObserver\Model\DirectoryObserverLogDirectoryNotExistingException $exc) {
        die("'lw_direcotryobserver' Verzeichnis zum Speichern der Verzeichnisstruktur existiert nicht.");
    } catch (\LwDirectoryObserver\Model\ChangeLogDirectoryNotExistingException $exc) {
        die("Das Change-Log Verzeichnis existiert nicht.");
    } catch (\LwDirectoryObserver\Model\ChangeLogDirectoryNotWritableException $exc) {
        die("Das Change-Log Verzeichnis ist nicht beschreibbar.");
    } catch (\LwDirectoryObserver\Model\JqPlotDirectoryNotWritableException $exc) {
        die("Das Verzeichnis f&uuml;r die JqPlot-Library existiert nicht.");
    }

    if ($config["directoryobserver"]["logtype"] == "db") {
        include_once($config["path"]["package"] . "LwDirectoryObserver/Model/DbCommandHandler.php");
        include_once($config["path"]["package"] . "LwDirectoryObserver/Model/DbQueryHandler.php");
        $directoryObserver->setCommandHandler(new \LwDirectoryObserver\Model\DbCommandHandler($db, $config["path"]["resource"] . "lw_logs/lw_directoryobserver/"));
        $directoryObserver->setQueryHandler(new \LwDirectoryObserver\Model\DbQueryHandler($db));
    }
    else {
        include_once($config["path"]["package"] . "LwDirectoryObserver/Model/TextCommandHandler.php");
        include_once($config["path"]["package"] . "LwDirectoryObserver/Model/TextQueryHandler.php");
        $directoryObserver->setCommandHandler(new \LwDirectoryObserver\Model\TextCommandHandler($config["directoryobserver"]["changelog_path"], $config["path"]["resource"] . "lw_logs/lw_directoryobserver/"));
        $directoryObserver->setQueryHandler(new \LwDirectoryObserver\Model\TextQueryHandler($config["directoryobserver"]["changelog_path"]));
    }
    $directoryObserver->scan();
}