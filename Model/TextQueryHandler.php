<?php
/**
 * QueryHandler for a textfile log.
 * 
 * @author Michael Mandt <michael.mandt@logic-works.de>
 * @package lw_directoryobserver
 */

namespace LwDirectoryObserver\Model;

class TextQueryHandler
{
    protected $observePath;
    protected $changeLogPath;
    
    
    public function __construct($changeLogPath)
    {
        $this->changeLogPath = $changeLogPath;
    }
    
    public function setObservePath($observePath){
        $this->observePath = $observePath;
    }
    
    /**
     * Log entries will be returned for a certain date oder date range.
     * If empty params then the complete log will be returned.
     * 
     * @param int $startDate
     * @param int $endDate
     * @return array
     */
    public function getLog($startDate = false, $endDate = false)
    {
        $preparedArray = $this->prepareLogArray($this->changeLogPath.md5($this->observePath).".log");
        
        if(!$startDate && !$endDate) {
            return $preparedArray;
        }
        
        if(!$endDate){
            $endDate = $startDate;
        }
        
        $filteredArray = array();
        
        foreach($preparedArray as $date => $entries) {
            if($date >= $startDate && $date <= $endDate) {
                $filteredArray[$date] = $entries; 
            }
        }
        
        return $filteredArray;
    }
    
    /**
     * The directory size will be returned for a certain date or date range.
     * If empty params then the complete size-logs will be returned.
     * 
     * @param int $startDate
     * @param int $endDate
     * @return array
     */
    public function getCompleteSize($startDate = false, $endDate = false)
    {
        $completeSizeArray = array();
        $logArray = $this->getLog($startDate, $endDate);
        foreach($logArray as $date => $entries){
            $completeSizeArray[$date] = $entries["COMPLETESIZE"][count($entries["COMPLETESIZE"]) - 1]["size"];
        }
        return $completeSizeArray;
    }

    /**
     * Logfile content will be converted to an array.
     * 
     * @param string $logFile
     * @return array
     */
    private function prepareLogArray($logFile)
    {
        $preparedArray = array();

        if (is_file($logFile)) {
            $str = file_get_contents($logFile);

            $temp_arr = explode(PHP_EOL, $str);
            unset($temp_arr[count($temp_arr) - 1]);

            foreach ($temp_arr as $entry) {
                $explode_entry = explode("##", $entry);
                unset($explode_entry[count($explode_entry) - 1]);

                switch ($explode_entry[1]) {
                    case "DIRECTORIES":
                        if (count($explode_entry) == 4) {
                            //____________(-----DATE-------)_(------TYPE-------)_(-----NAME------)____(----DELETED-------------) 
                            $preparedArray[$explode_entry[0]][$explode_entry[1]][$explode_entry[2]][] = array($explode_entry[3] => 1);
                        }
                        else {
                            //____________(-----DATE-------)_(------TYPE-------)_(-----NAME------)____(----ADDED-------------------/------LASTCHANGEDATE-------------)
                            $preparedArray[$explode_entry[0]][$explode_entry[1]][$explode_entry[2]][] = array($explode_entry[3] => 1, "lastchange" => $explode_entry[5]);
                        }
                        break;
                    case "FILES":
                        if (count($explode_entry) == 4) {
                            //____________(-----DATE-------)_(------TYPE-------)_(-----NAME------)____(----DELETED----------------)
                            $preparedArray[$explode_entry[0]][$explode_entry[1]][$explode_entry[2]][] = array($explode_entry[3] => 1);
                        }
                        elseif (count($explode_entry) == 6) {
                            //____________(-----DATE-------)_(------TYPE-------)_(-----NAME------)____(----ADDDED---------------------(-----SIZE-------------------/------LASTCHANGE-----------------))
                            $preparedArray[$explode_entry[0]][$explode_entry[1]][$explode_entry[2]][] = array($explode_entry[3] => array("size" => $explode_entry[4]), "lastchange" => $explode_entry[5]);
                        }
                        else {
                            //____________(-----DATE-------)_(------TYPE-------)_(-----NAME------)____(----CHANGE--------------------(-------OLDSIZE------------------/-------NEWSSIZE---------------)------LASTCHANGE------------------)
                            $preparedArray[$explode_entry[0]][$explode_entry[1]][$explode_entry[2]][] = array($explode_entry[3] => array("oldsize" => $explode_entry[4], "newsize" => $explode_entry[5]), "lastchange" => $explode_entry[6]);
                        }
                        break;
                    case "COMPLETESIZE":
                        //____________(-----DATE-------)_(------TYPE-------)___(-----COMPLETESIZE---------------)
                        $preparedArray[$explode_entry[0]][$explode_entry[1]][] = array("size" => $explode_entry[2], "timeofscan" => $explode_entry[3]);
                        break;
                }
            }
        }
        return $preparedArray;
    }
}