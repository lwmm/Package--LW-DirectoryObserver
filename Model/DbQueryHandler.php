<?php
/**
 * QueryHandler for a textfile log.
 * 
 * @author Michael Mandt <michael.mandt@logic-works.de>
 * @package lw_directoryobserver
 */

namespace LwDirectoryObserver\Model;

class DbQueryHandler
{
    protected $observePath;
    protected $db;
    
    /**
     * @param array $config
     */
    public function __construct($db)
    {
        $this->db = $db;
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
        return $this->prepareLogArray($startDate, $endDate);
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
        
        if(!$startDate && !$endDate){
            $this->db->setStatement("SELECT date,size FROM t:lw_directory_observer WHERE observed_directory = :dir AND type = :type ");
        }
        else {
            if(!$endDate && $startDate) {
                $endDate = $startDate;
            }
            elseif(!$startDate && $endDate){
                $startDate = $endDate;
            }
            $this->db->setStatement("SELECT date,size FROM t:lw_directory_observer WHERE observed_directory = :dir AND type = :type AND date >= :startdate AND date <= :enddate  ");
            $this->db->bindParameter("startdate", "i", $startDate);
            $this->db->bindParameter("enddate", "i", $endDate);
        }
        $this->db->bindParameter("dir", "s", $this->observePath);
        $this->db->bindParameter("type", "s", "completesize");
        
        $result = $this->db->pselect(); 
        
        foreach($result as $entry) {
            $completeSizeArray[$entry["date"]] = $entry["size"];
        }
        
        return $completeSizeArray;
    }

    /**
     * Logtable content will be converted to an array, which is similar to the textlog array.
     * 
     * @return array
     */
    private function prepareLogArray($startDate = false, $endDate = false)
    {
       if(!$startDate && !$endDate){
            $this->db->setStatement("SELECT * FROM t:lw_directory_observer WHERE observed_directory = :dir ");
        }
        else {
            if(!$endDate && $startDate) {
                $endDate = $startDate;
            }
            elseif(!$startDate && $endDate){
                $startDate = $endDate;
            }
            $this->db->setStatement("SELECT * FROM t:lw_directory_observer WHERE observed_directory = :dir AND date >= :startdate AND date <= :enddate  ");
            $this->db->bindParameter("startdate", "i", $startDate);
            $this->db->bindParameter("enddate", "i", $endDate);
        }
        $this->db->bindParameter("dir", "s", $this->observePath);
        $result = $this->db->pselect(); 
        
        $logArray = array();
        foreach($result as $entry){
            $temp = array();
            if($entry["type"] != "completesize") {
                switch ($entry["operation"]) {
                    case "deleted":
                        $temp = array("removed" => 1);
                        break;
                    case "added":
                        if($entry["type"] == "directories"){
                            $temp = array("added" => 1, "lastchange" => $entry["last_change_date"]);
                        }
                        else{
                            $temp = array("added" => array("size" => $entry["size"]), "lastchange" => $entry["last_change_date"]);
                        }
                        break;
                    case "change":
                        if($entry["type"] == "files"){
                            $temp = array("chenged" => array("oldsize" => $entry["size"], "newsize" => $entry["new_size"]), "lastchange" => $entry["last_change_date"]);
                        }
                        break;
                }

                $logArray[$entry["date"]][strtoupper($entry["type"])][$entry["name"]][] = $temp;
            }
            else{
                $logArray[$entry["date"]][strtoupper($entry["type"])][] = array("size" => $entry["size"], "timeofscan" => $entry["last_change_date"]);
            }
        }       
        return $logArray;
    }
}