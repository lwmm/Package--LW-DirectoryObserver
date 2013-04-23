<?php

/**
 * CommandHandler for a db log.
 * 
 * @author Michael Mandt <michael.mandt@logic-works.de> 
 * @package lw_directoryobserver
 */

namespace LwDirectoryObserver\Model;

class DbCommandHandler
{

    protected $observePath;
    protected $db;
    protected $structureLogPath;

    /**
     * @param array $config
     */
    public function __construct($db, $structureLogPath)
    {
        $this->db = $db;
        $this->structureLogPath = $structureLogPath;
    }

    public function setObservePath($observePath)
    {
        $this->observePath = $observePath;
    }

    /**
     * Changes will be added to the log table.
     * 
     * @param array $changes
     * @return boolean
     */
    public function addChanges($changes)
    {
        if (!empty($changes)) {
            foreach ($changes as $dirOrFile => $entries) {
                foreach ($entries as $name => $infos) {
                    $key = array_keys($infos);
                    $operation = $key[0];
                    $this->db->setStatement("INSERT INTO t:lw_directory_observer (date, observed_directory, name, type, operation, size, new_size, last_change_date) VALUES (:date, :dir, :name, :type, :operation, :size, :new_size, :last_date ) ");
                    $this->db->bindParameter("date", "i", date("Ymd"));
                    $this->db->bindParameter("dir", "s", $this->observePath);
                    $this->db->bindParameter("name", "s", $name);
                    $this->db->bindParameter("type", "s", $dirOrFile);
                    $this->db->bindParameter("operation", "s", $operation);
                    if($operation == "added") {
                        $this->db->bindParameter("size", "s", $infos[$operation]["size"]);
                        $this->db->bindParameter("last_date", "i", $infos[$operation]["date"]);
                        $this->db->bindParameter("new_size", "s", "");
                    }
                    elseif($operation == "change"){
                        $this->db->bindParameter("size", "s", $infos[$operation]["size"]["old"]);
                        $this->db->bindParameter("new_size", "s", $infos[$operation]["size"]["new"]);
                        $this->db->bindParameter("last_date", "i", $infos[$operation]["date"]["new"]);
                    }
                    else{
                        $this->db->bindParameter("size", "s", "");
                        $this->db->bindParameter("last_date", "i", date("YmdHis"));
                        $this->db->bindParameter("new_size", "s", "");
                    }
                    #die($this->db->prepare());
                    $this->db->pdbquery();
                }
            }
        }
    }

    /**
     * The complete size of the observed directory will be logged.
     * 
     * @return boolean
     */
    public function saveCompleteSize($completeSize)
    {
        $this->db->setStatement("INSERT INTO t:lw_directory_observer (date, observed_directory,type, size, last_change_date) VALUES (:date, :dir, :type, :size, :timeofscan) ");
        $this->db->bindParameter("date", "i", date("Ymd"));
        $this->db->bindParameter("dir", "s", $this->observePath);
        $this->db->bindParameter("type","s", "completesize");
        $this->db->bindParameter("timeofscan","i", date("YmdHis"));
        $this->db->bindParameter("size","s", $this->getHumanCompleteSize($completeSize));
        return $this->db->pdbquery();
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

    public function deleteAllObserveData($observePath)
    {
        $logDir = \lw_directory::getInstance($this->structureLogPath);
        $files = $logDir->getDirectoryContents("file");
        foreach($files as $file) {
            if($file->getName() == md5($observePath).".log") {
                $logDir->deleteFile($file->getName());
            }
        }
        
        $this->db->setStatement("DELETE FROM t:lw_directory_observer WHERE observed_directory = :dir ");
        $this->db->bindParameter("dir", "s", $observePath);
        return $this->db->pdbquery();
    }
}