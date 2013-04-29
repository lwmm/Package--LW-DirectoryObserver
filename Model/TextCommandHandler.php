<?php
/**
 *CommandHandler for a textfile log.
 * 
 * @author Michael Mandt <michael.mandt@logic-works.de> 
 * @package lw_directoryobserver
 */

namespace LwDirectoryObserver\Model;

class TextCommandHandler
{
    protected $observePath;
    protected $changeLogPath;
    protected $structureLogPath;
    
    
    public function __construct($changeLogPath, $structureLogPath)
    {
        $this->changeLogPath = $changeLogPath;
        $this->structureLogPath = $structureLogPath;
    }

    public function setObservePath($observePath){
        $this->observePath = $observePath;
    }
    
    /**
     * Changes will be added to the logfile.
     * 
     * @param array $changes
     * @return boolean
     */
    public function addChanges($changes, $override = false)
    {
        if(!$override) {
            $param = "a";
        }else{
            $param = "w";
        }
        $name = md5($this->observePath) . ".log";
        $logfile = fopen($this->changeLogPath.$name, $param);

        foreach(array("directories", "files") as $type){
            if (array_key_exists($type, $changes)) {
                foreach ($changes[$type] as $filename => $info) {
                    if (array_key_exists("change", $info)) {
                        // description log entry change 
                        // <--DATE-->##FILE##<--FILENAME-->##changed##<--OLDSIZE-->##<--NEWSIZE-->##<--CHANGEDATE->##
                        fwrite($logfile, date("Ymd")."##".strtoupper($type)."##".$filename."##changed##".$info["change"]["size"]["old"]."##".$info["change"]["size"]["new"]."##".$info["change"]["date"]["new"]."##".PHP_EOL);
                    }
                    elseif (array_key_exists("deleted", $info)) {
                        fwrite($logfile, date("Ymd")."##".strtoupper($type)."##".$filename."##removed##" . PHP_EOL);
                    }
                    elseif (array_key_exists("added", $info)) {
                        fwrite($logfile, date("Ymd")."##".strtoupper($type)."##".$filename."##added##".$info["added"]["size"]."##".$info["added"]["date"]."##" . PHP_EOL);
                    }
                }
            }
        }
        fclose($logfile);

        return true;
    }
    
    /**
     * The complete size of the observed directory will be logged.
     * 
     * @return boolean
     */
    public function saveCompleteSize($completeSize)
    {
        $name = md5($this->observePath) . ".log";
        $logfile = fopen($this->changeLogPath.$name, "a");
        fwrite($logfile, date("Ymd")."##COMPLETESIZE##".$this->getHumanCompleteSize($completeSize)."##". date("YmdHis") ."##" . PHP_EOL);
        fclose($logfile);

        return true;
    }
    
    /**
     * Size will be converted into a "normal" format.
     * 
     * @return string
     */
    private function getHumanCompleteSize($completeSize)
    {
        if ($completeSize == 0) {
            return("0 Bytes");
        }
        $filesizename = array(" Bytes", " KB", " MB", " GB", " TB", " PB", " EB", " ZB", " YB");
        return round($completeSize / pow(1024, ($i = floor(log($completeSize, 1024)))), 2) . $filesizename[$i];
    }
    
    public function deleteAllObserveData()
    {
        $changeLogDir = \lw_directory::getInstance($this->changeLogPath);
        $files = $changeLogDir->getDirectoryContents("file");
        
        foreach($files as $file) {
            if($file->getName() == md5($this->observePath).".log") {
                $changeLogDir->deleteFile($file->getName());
            }
        }
        
        $logDir = \lw_directory::getInstance($this->structureLogPath);
        $files = $logDir->getDirectoryContents("file");
        foreach($files as $file) {
            if($file->getName() == md5($this->observePath).".log") {
                $logDir->deleteFile($file->getName());
            }
        }
        
        return true;
    }
    
    public function autoDeleteOfExpiredEntries($expiringDate)
    {
        $queryHandler = new \LwDirectoryObserver\Model\TextQueryHandler($this->changeLogPath);
        $logs = $queryHandler->getLog();
        foreach($logs as $date => $entries){
            if($date < $expiringDate) {
                unset($logs[$date]);
            }
        }
        
        return $this->addChanges($logs, true);
    }
}