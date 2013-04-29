<?php

include_once(dirname(__FILE__) . '/../../Services/Autoloader.php');
require_once dirname(__FILE__) . '/../Config/phpUnitConfig.php';

class TextCommandHandlerTest extends \PHPUnit_Framework_TestCase
{

    protected $commandHandler;
    protected $baseTestDir;
    protected $phpUnitTestDir;
    protected $structureLogDir;
    protected $changeLogDir;

    protected function setUp()
    {
        $autoloader = new \LwDirectoryObserver\Services\Autoloader();
        $phpUnitConfig = new phpUnitConfig();
        $config = $phpUnitConfig->getConfig();

        require_once $config["libraries"] . 'lw/lw_object.class.php';
        require_once $config["libraries"] . 'lw/lw_registry.class.php';
        require_once $config["libraries"] . 'lw/lw_file.class.php';
        require_once $config["libraries"] . 'lw/lw_fileop.class.php';
        require_once $config["libraries"] . 'lw/lw_fileop_fs.class.php';
        require_once $config["libraries"] . 'lw/lw_directory.class.php';

        $this->baseTestDir = \lw_directory::getInstance($config["pathForDirTesting"]);
        $dirs = $this->baseTestDir->getDirectoryContents("dir");

        foreach ($dirs as $dir) {
            if ($dir->getName() == "phpUnitTest/") {
                $this->phpUnitTestDir = \lw_directory::getInstance($this->baseTestDir->getPath() . "phpUnitTest");
                $this->structureLogDir = \lw_directory::getInstance($this->phpUnitTestDir->getPath() . "structureLog");
                $this->changeLogDir = \lw_directory::getInstance($this->phpUnitTestDir->getPath() . "changeLog");
                $this->tearDown();
            }
        }

        $this->baseTestDir->add("phpUnitTest");
        $this->phpUnitTestDir = \lw_directory::getInstance($this->baseTestDir->getPath() . "phpUnitTest");
        $this->phpUnitTestDir->add("structureLog");
        $this->phpUnitTestDir->add("changeLog");
        $this->structureLogDir = \lw_directory::getInstance($this->phpUnitTestDir->getPath() . "structureLog");
        $this->changeLogDir = \lw_directory::getInstance($this->phpUnitTestDir->getPath() . "changeLog");

        $this->commandHandler = new \LwDirectoryObserver\Model\TextCommandHandler($this->changeLogDir->getPath(), $this->structureLogDir->getPath());
        $this->commandHandler->setObservePath("/ich/bin/der/observe/path/");
    }

    protected function tearDown()
    {
        $this->structureLogDir->delete(true);
        $this->changeLogDir->delete(true);
        $this->phpUnitTestDir->delete(true);
    }

    public function testAddChanges()
    {
        $array = array("files" => array(
                "lovelywallpaper8_1.jpg" => array("deleted" => 1),
                "BenutzerhandbuchVorlage.odt" => array("added" => array("size" => "2.2 MB", "date" => 20130423145741)),
                "template.html" => array("added" => array("size" => "5.36 KB", "date" => 20130423145741))
        ));
        $this->commandHandler->addChanges($array);
        $assertedContent = date("Ymd") . "##FILES##lovelywallpaper8_1.jpg##removed##" . date("Ymd") . "##FILES##BenutzerhandbuchVorlage.odt##added##2.2 MB##20130423145741##" . date("Ymd") . "##FILES##template.html##added##5.36 KB##20130423145741##";
        $content = str_replace(PHP_EOL, "", file_get_contents($this->changeLogDir->getPath() . md5("/ich/bin/der/observe/path/") . ".log"));
        $this->assertEquals($content, $assertedContent);
    }

    public function testSaveCompleteSize()
    {
        $completeSize = 2234794;
        $this->commandHandler->saveCompleteSize($completeSize);
        $assertedContent = date("Ymd") . "##COMPLETESIZE##2.13 MB##" . date("Ymd");
        $content = str_replace(PHP_EOL, "", file_get_contents($this->changeLogDir->getPath() . md5("/ich/bin/der/observe/path/") . ".log"));
        $content = substr($content, 0, -8);
        $this->assertEquals($content, $assertedContent);
    }

    /**
     * @covers LwDirectoryObserver\Model\TextCommandHandler::deleteAllObserveData
     * @todo   Implement testDeleteAllObserveData().
     */
    public function testDeleteAllObserveData()
    {
        $logfile = fopen($this->changeLogDir->getPath() . md5("/ich/bin/der/observe/path/") . ".log", "a");
        fwrite($logfile, "test");
        fclose($logfile);
        $logfile2 = fopen($this->structureLogDir->getPath() . md5("/ich/bin/der/observe/path/") . ".log", "a");
        fwrite($logfile2, "test");
        fclose($logfile2);

        $files = $this->structureLogDir->getDirectoryContents("dir");
        print_r($files);
        die("HIER");


        #$this->assertTrue(is_file($this->changeLogDir->getPath().md5("/ich/bin/der/observe/path/").".log"));        
        #$this->assertTrue($this->checkIfStructureLogFileExists());        
        #$this->commandHandler->deleteAllObserveData("/ich/bin/der/observe/path/");
        #$this->assertFalse($this->checkIfChangeLogFileExists());        
        #$this->assertFalse($this->checkIfStructureLogFileExists());        
    }

    private function checkIfStructureLogFileExists()
    {
        $files = $this->structureLogDir->getDirectoryContents("file");
        foreach ($files as $file) {
            if ($file->getName() == md5("/ich/bin/der/observe/path/") . ".log") {
                return true;
            }
        }
        return false;
    }

    private function checkIfChangeLogFileExists()
    {
        $files = $this->changeLogDir->getDirectoryContents("file");
        foreach ($files as $file) {
            if ($file->getName() == md5("/ich/bin/der/observe/path/") . ".log") {
                return true;
            }
        }
        return false;
    }

}
