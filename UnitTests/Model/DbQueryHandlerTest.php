<?php

include_once(dirname(__FILE__) . '/../../Services/Autoloader.php');
require_once dirname(__FILE__) . '/../Config/phpUnitConfig.php';


class DbQueryHandlerTest extends \PHPUnit_Framework_TestCase
{
    protected $queryHandler;
    protected $db;

    
    protected function setUp()
    {
        $autoloader = new \LwDirectoryObserver\Services\Autoloader();
        $phpUnitConfig = new phpUnitConfig();
        $config = $phpUnitConfig->getConfig();

        require_once $config["libraries"] . 'lw/lw_object.class.php';
        require_once $config["libraries"] . 'lw/lw_db.class.php';
        require_once $config["libraries"] . 'lw/lw_db_mysqli.class.php';
        require_once $config["libraries"] . 'lw/lw_registry.class.php';

        $db = new lw_db_mysqli($config["lwdb"]["user"], $config["lwdb"]["pass"], $config["lwdb"]["host"], $config["lwdb"]["db"]);
        $db->connect();
        $this->db = $db;

        $this->queryHandler = new \LwDirectoryObserver\Model\DbQueryHandler($this->db);
        $this->queryHandler->setObservePath("/ich/bin/der/observe/path/");
        $this->observePath = "/ich/bin/der/observe/path/";

        $this->db->setStatement("CREATE TABLE IF NOT EXISTS lw_directory_observer (
                                                  id int(11) NOT NULL AUTO_INCREMENT,
                                                  date int(8) NOT NULL,
                                                  observed_directory varchar(255) COLLATE utf8_unicode_ci NOT NULL,
                                                  name varchar(255) COLLATE utf8_unicode_ci NOT NULL,
                                                  type varchar(255) COLLATE utf8_unicode_ci NOT NULL,
                                                  operation varchar(2555) COLLATE utf8_unicode_ci NOT NULL,
                                                  size varchar(255) COLLATE utf8_unicode_ci NOT NULL,
                                                  new_size varchar(255) COLLATE utf8_unicode_ci NOT NULL,
                                                  last_change_date bigint(14) NOT NULL,
                                                  PRIMARY KEY (id)
                                                ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1;");
        $this->db->pdbquery();

        $array = array("files" => array(
                "lovelywallpaper8_1.jpg" => array("deleted" => 1),
                "BenutzerhandbuchVorlage.odt" => array("added" => array("size" => "2.2 MB", "date" => 20130423145741)),
                "template.html" => array("added" => array("size" => "5.36 KB", "date" => 20130423145741))
        ));

        $this->addChanges($array);
        $this->saveCompleteSize(2234794);
    }

    
    protected function tearDown()
    {
        $this->db->setStatement("DROP TABLE t:lw_directory_observer ");
        $this->db->pdbquery();
    }

    
    public function testGetLog()
    {
        $assertedArray = array(
            20130424 => array("FILES" => array(
                    "lovelywallpaper8_1.jpg" => array(0 => array("removed" => 1)),
                    "BenutzerhandbuchVorlage.odt" => array(0 => array("added" => array("size" => "2.2 MB"), "lastchange" => "20130423145741")),
                    "template.html" => array(0 => array("added" => array("size" => "5.36 KB"), "lastchange" => "20130423145741"))
                ),
                "COMPLETESIZE" => array(0 => array("size" => "2.13 MB", "timeofscan" => "20130424104305"))
            )
        );
        $result = $this->queryHandler->getLog();
        $this->assertEquals($result, $assertedArray);
        
        $result = $this->queryHandler->getLog(20130424, 20130424);
        $this->assertEquals($result, $assertedArray);
        
        $result = $this->queryHandler->getLog(20130424);
        $this->assertEquals($result, $assertedArray);
        
        $result = $this->queryHandler->getLog(false, 20130424);
        $this->assertEquals($result, $assertedArray);
        
        $result = $this->queryHandler->getLog(20130428);
        $this->assertEquals($result, array());
    }

    
    public function testGetCompleteSize()
    {
        $assertedArray = array ( 20130424 => "2.13 MB" );
        
        $result = $this->queryHandler->getCompleteSize();
        $this->assertEquals($result, $assertedArray);
        
        $result = $this->queryHandler->getCompleteSize(20130424);
        $this->assertEquals($result, $assertedArray);
        
        $result = $this->queryHandler->getCompleteSize(false, 20130424);
        $this->assertEquals($result, $assertedArray);
        
        $result = $this->queryHandler->getCompleteSize(20130424, 20130424);
        $this->assertEquals($result, $assertedArray);
        
        $result = $this->queryHandler->getCompleteSize(20130428);
        $this->assertEquals($result, array());
    }

    private function addChanges($changes)
    {
        if (!empty($changes)) {
            foreach ($changes as $dirOrFile => $entries) {
                foreach ($entries as $name => $infos) {
                    $key = array_keys($infos);
                    $operation = $key[0];
                    $this->db->setStatement("INSERT INTO t:lw_directory_observer (date, observed_directory, name, type, operation, size, new_size, last_change_date) VALUES (:date, :dir, :name, :type, :operation, :size, :new_size, :last_date ) ");
                    $this->db->bindParameter("date", "i", 20130424);
                    $this->db->bindParameter("dir", "s", $this->observePath);
                    $this->db->bindParameter("name", "s", $name);
                    $this->db->bindParameter("type", "s", $dirOrFile);
                    $this->db->bindParameter("operation", "s", $operation);
                    if ($operation == "added") {
                        $this->db->bindParameter("size", "s", $infos[$operation]["size"]);
                        $this->db->bindParameter("last_date", "i", $infos[$operation]["date"]);
                        $this->db->bindParameter("new_size", "s", "");
                    }
                    elseif ($operation == "change") {
                        $this->db->bindParameter("size", "s", $infos[$operation]["size"]["old"]);
                        $this->db->bindParameter("new_size", "s", $infos[$operation]["size"]["new"]);
                        $this->db->bindParameter("last_date", "i", $infos[$operation]["date"]["new"]);
                    }
                    else {
                        $this->db->bindParameter("size", "s", "");
                        $this->db->bindParameter("last_date", "i", date("YmdHis"));
                        $this->db->bindParameter("new_size", "s", "");
                    }
                    $this->db->pdbquery();
                }
            }
        }
    }

    private function saveCompleteSize($completeSize)
    {
        $this->db->setStatement("INSERT INTO t:lw_directory_observer (date, observed_directory,type, size, last_change_date) VALUES (:date, :dir, :type, :size, :timeofscan) ");
        $this->db->bindParameter("date", "i", 20130424);
        $this->db->bindParameter("dir", "s", $this->observePath);
        $this->db->bindParameter("type", "s", "completesize");
        $this->db->bindParameter("timeofscan", "i", 20130424104305);
        $this->db->bindParameter("size", "s", $this->getHumanCompleteSize($completeSize));
        return $this->db->pdbquery();
    }

    private function getHumanCompleteSize($completeSize)
    {
        if ($completeSize == 0) {
            return("0 Bytes");
        }
        $filesizename = array(" Bytes", " KB", " MB", " GB", " TB", " PB", " EB", " ZB", " YB");
        return round($completeSize / pow(1024, ($i = floor(log($completeSize, 1024)))), 2) . $filesizename[$i];
    }
}
