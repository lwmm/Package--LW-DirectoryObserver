<?php
/**
 * Main Controller of lw_directoryobserver. This plugin save
 * changes which were done to a certain directory.
 * 
 * @author Michael Mandt <michael.mandt@logic-works.de>
 * @package lw_directoryobserver
 */


namespace LwDirectoryObserver\Model;

class DirectoryObserver
{

    protected $commandHandler;
    protected $queryHandler;
    protected $observePath;
    protected $config;
    protected $completeSize = 0;
    protected $logFile;


    /* Instance of text(command and query)handler or db(command and query)handler
     * and the directory path which this plugin have to observe are required.
     * 
     * @param object $commandHandler
     * @param object $queryHandler
     * @param string $observePath
     */
    public function __construct($config, $observePath)
    {   
        $this->config = $config;
        
        if (substr($observePath, strlen($observePath) - 1, strlen($observePath)) != "/") {
            $observePath = $observePath . "/";
        }
        $observePath = str_replace("//", "/", $observePath);

        if(!is_dir($observePath)) {
            throw new \LwDirectoryObserver\Model\ObserveDirectoryNotExistingException();
        }
        
        if(!is_dir($this->config["path"]["media"]."jquery/jqplot/")){
            throw new \LwDirectoryObserver\Model\JqPlotDirectoryNotExistingException();
        }
        
        if(!is_dir($this->config["path"]["resource"] . "lw_logs/lw_directoryobserver/")) {
            throw new \LwDirectoryObserver\Model\DirectoryObserverLogDirectoryNotExistingException();
        }
        
        $this->observePath = $observePath;
        $this->logFile = $this->config["directoryobserver"]["changelog_path"].md5($observePath).".log";
        if($this->config["directoryobserver"]["logtype"] == "file") {
            $this->checkLogPath();
        }
    }
    
    public function setCommandHandler($commandHandler)
    {
        $this->commandHandler = $commandHandler;
        $this->commandHandler->setObservePath($this->observePath);
    }
    
    public function setQueryHandler($queryHandler)
    {
        $this->queryHandler = $queryHandler;
        $this->queryHandler->setObservePath($this->observePath);
    }
    
    public function getObservePath()
    {
        return $this->observePath;
    }
    
    private function checkLogPath()
    {
        if(!is_dir($this->config["directoryobserver"]["changelog_path"]) || $this->config["directoryobserver"]["changelog_path"] == ""){
            throw new \LwDirectoryObserver\Model\ChangeLogDirectoryNotExistingException();
        }
        elseif(!is_writable($this->config["directoryobserver"]["changelog_path"])) {
            throw new \LwDirectoryObserver\Model\ChangeLogDirectoryNotWritableException();
        }
        else{
            $logPath = \lw_directory::getInstance($this->config["directoryobserver"]["changelog_path"]);
            $files = $logPath->getDirectoryContents("file");
            
            $htaccessExisting = false;
            foreach($files as $file) {
                if($file->getName() == ".htaccess") {
                    $htaccessExisting = true;
                    $content = file_get_contents($file->getPath().$file->getName());
                    $content = str_replace(PHP_EOL, "", strtolower($content));
                    if(!$content == "deny from all"){
                        $htacessfile = fopen($file->getPath().$file->getName(), "w");
                        fwrite($htacessfile, "deny from all");
                        fclose($htacessfile);
                    }
                }
            }
            if(!$htaccessExisting) {
                $htacessfile = fopen($this->config["directoryobserver"]["changelog_path"].".htaccess", "w");
                fwrite($htacessfile, "deny from all");
                fclose($htacessfile);
            }
        }
    }
    
    /**
     * Executes the directory scanning and logging.
     */
    public function scan()
    {
        if(array_key_exists("month_of_saving", $this->config["directoryobserver"])) {
            $days = 30 * intval($this->config["directoryobserver"]["month_of_saving"]);
        }
        else{
            $days = 30;
        }
        $expiringDate = date("Ymd", strtotime("-".$days." days"));
        $this->commandHandler->autoDeleteOfExpiredEntries($expiringDate);
        
        $observedDir = \lw_directory::getInstance($this->observePath);

        $files = $observedDir->getDirectoryContents("file");
        $directories = $observedDir->getDirectoryContents("dir");

        $array = $this->prepareArray($files, $directories);

        $result = $this->compare($array);

        if (is_array($result)) {
            $this->commandHandler->addChanges($result);
        }
        $this->commandHandler->saveCompleteSize($this->completeSize);
        $this->saveStructure($this->observePath, $array, $this->config["path"]["resource"] . "lw_logs/lw_directoryobserver/");
    }

    /**
     * Existing direcotries and files are collected in an array.
     * 
     * @param objects $files
     * @param objects $directories
     * @return array
     */
    private function prepareArray($files, $directories)
    {
        $array = array();

        if (!empty($directories)) {
            foreach ($directories as $directory) {
                $day = substr($directory->getDate(), 0, 2);
                $month = substr($directory->getDate(), 3, 2);
                $year = substr($directory->getDate(), 6, 4);
                $hour = substr($directory->getDate(), 11, 2);
                $min = substr($directory->getDate(), 14, 2);
                $array["directories"][$directory->getName()]["size"] = $directory->getSize();
                $array["directories"][$directory->getName()]["date"] = $year . $month . $day . $hour . $min . "00";
            }
        }
        
        if (!empty($files)) {
            foreach ($files as $file) {
                $file->setDateFormat("YmdHis");
                $this->completeSize += $file->getSize(true);
                $array["files"][$file->getName()]["size"] = $file->getSize();
                $array["files"][$file->getName()]["date"] = $file->getDate();
            }
        }

        return $array;
    }

    /**
     * Structure of the observed directory will be saved.
     * 
     * @param string $observePath
     * @param array $array
     * @param string $structureLogPath
     * @return boolean
     */
    public function saveStructure($observePath, $array, $structureLogPath)
    {
        $name = md5($observePath) . ".log";

        $logfile = fopen($structureLogPath . $name, "w");

        if (array_key_exists("directories", $array)) {
            fwrite($logfile, "!!Directories!!" . PHP_EOL);
            foreach ($array["directories"] as $dirname => $infos) {
                fwrite($logfile, $dirname . "##" . $infos["size"] . "##" . $infos["date"] . "##" . PHP_EOL);
            }
        }
        if (array_key_exists("files", $array)) {
            fwrite($logfile, "!!Files!!" . PHP_EOL);
            foreach ($array["files"] as $filename => $infos) {
                fwrite($logfile, $filename . "##" . $infos["size"] . "##" . $infos["date"] . "##" . PHP_EOL);
            }
        }

        fclose($logfile);

        return true;
    }

    
    /**
     * The actual scanned structure will be compared with the saved structure of the
     * observed directory.
     * 
     * @param array $scannedStructure
     * @return boolean
     */
    private function compare($scannedStructure)
    {
        $savedStructure = $this->savedStructureToArray();
        if ($scannedStructure === $savedStructure) {
            return true;
        }
        else {

            foreach (array("directories", "files") as $type) {
                foreach ($savedStructure[$type] as $name => $info) {
                    if (array_key_exists($name, $scannedStructure[$type])) {
                        if ($info["size"] != $scannedStructure[$type][$name]["size"]) {
                            $diffArray[$type][$name]["change"]["size"] = array("old" => $info["size"], "new" => $scannedStructure[$type][$name]["size"]);
                        }
                        if ($info["date"] != $scannedStructure[$type][$name]["date"]) {
                            $diffArray[$type][$name]["change"]["date"] = array("old" => $info["date"], "new" => $scannedStructure[$type][$name]["date"]);
                        }
                    }
                    else {
                        $diffArray[$type][$name]["deleted"] = 1;
                    }
                }
                foreach ($scannedStructure[$type] as $name => $info) {
                    if (!array_key_exists($name, $savedStructure[$type])) {
                        $diffArray[$type][$name]["added"] = $info;
                    }
                }
            }
            return $diffArray;
        }
    }

    /**
     * Saved structure will be converted into an array.
     * 
     * @return array
     */
    private function savedStructureToArray()
    {
        $name = md5($this->observePath) . ".log";

        if (is_file($this->config["path"]["resource"] . "lw_logs/lw_directoryobserver/" . $name)) {
            $str = str_replace(PHP_EOL, "", file_get_contents($this->config["path"]["resource"] . "lw_logs/lw_directoryobserver/" . $name));
            if (strstr($str, "!!Directories!!")) {
                if (strstr($str, "!!Directories!!")) {
                    $directories_str = str_replace("!!Directories!!", "", substr($str, strpos($str, "!!Directories!!"), strpos($str, "!!Files!!")));
                }
                else {
                    $directories_str = str_replace("!!Directories!!", "", substr($str, strpos($str, "!!Directories!!")));
                }

                $dir_array = $this->stringToArray($directories_str);
            }
            if (strstr($str, "!!Files!!")) {
                $files_str = str_replace("!!Files!!", "", substr($str, strpos($str, "!!Files!!")));

                $file_array = $this->stringToArray($files_str);
            }
        }

        $array = array("directories" => $dir_array, "files" => $file_array);
        return $array;
    }

    private function stringToArray($str)
    {
        $temp_array = explode("##", $str);
        $i = 0;
        foreach ($temp_array as $info) {
            if ($i == 0) {
                $name = $info;
            }
            elseif ($i == 1) {
                $array[$name]["size"] = $info;
            }
            elseif ($i == 2) {
                $array[$name]["date"] = $info;
            }
            $i++;
            if ($i == 3) {
                $i = 0;
            }
        }
        return $array;
    }

    /**
     * Returns the Log entries for a specific date or date range.
     * Switch "type":
     *      file:   entries from the log file will be returned.
     *      db:     entries from the log table will be returned.
     * 
     * @param string $type
     * @param int $startDate
     * @param int $endDate
     * @return array
     */
    public function getLog($startDate = false, $endDate = false)
    {
        return $this->queryHandler->getLog($startDate, $endDate);
    }
    
    public function getCompleteSizeArrayByDateRange($startDate = false, $endDate = false)
    {
        return $this->queryHandler->getCompleteSize($startDate, $endDate);
    }
    
    public function deleteAllObserveData()
    {
        return $this->commandHandler->deleteAllObserveData();
    }

}