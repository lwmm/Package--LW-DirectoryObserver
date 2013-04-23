<?php

namespace LwDirectoryObserver\Views;

class DirSizeTable
{
    protected  $config;
    
    public function __construct($config)
    {
        $this->config = $config;
    }
    public function render($entries, $observePath, $startDate, $endDate)
    {
        $sortArray = $this->sortSizeArray($entries);
        $sizeDivisorArray = $this->getDivisor($this->getMaxSize($sortArray));
        $view = new \lw_view(dirname(__FILE__) . '/Templates/DirSizeTable.phtml');
        $view->entries = $sortArray;
        $view->observePath = $observePath;
        $view->divisor = $sizeDivisorArray["divisor"];
        $view->size = $sizeDivisorArray["size"];
        $view->jqPlotMin = $this->config["url"]["media"]."jquery/jqplot/jquery.jqplot.min.js";
        $view->jqPlotDateRender = $this->config["url"]["media"]."jquery/jqplot/plugins/jqplot.dateAxisRenderer.min.js";
        $view->jqPlotTextRender = $this->config["url"]["media"]."jquery/jqplot/plugins/jqplot.canvasTextRenderer.min.js";
        $view->jqPlotAxisTickRender = $this->config["url"]["media"]."jquery/jqplot/plugins/jqplot.canvasAxisTickRenderer.min.js";
        $view->startDate = $this->changeDateFormat($startDate);
        $view->endDate = $this->changeDateFormat($endDate);
        
        return $view->render();
    }
    
    private function sortSizeArray($array)
    {
        $temp = array();
        foreach($array as $key => $value){
            $temp[$key] = array($key => $value);
        }
        
        foreach($temp as $key => $value){
            $temp2[] = $key;
        }
        
        array_multisort($temp2, SORT_ASC, $temp);
        
        foreach($temp as $value){
            foreach($value as $k => $v){
                $array2[$k] = $this->sizeInBytes($v);
            }
        }
        return $array2;
    }
    
    private function sizeInBytes($size)
    {
        if(strpos($size, "Bytes")) {
            $size = str_replace("ytes", "", $size);
        }
        $size = str_replace(" ", "", $size);
        $stringEnd = strtolower(substr($size, strlen($size)-2, 1));
        $sizeOhneStringEnd =substr($size, 0, strlen($size)-2);
        switch($stringEnd) {
            case 'g':
                $sizeOhneStringEnd *= 1024;
            case 'm':
                $sizeOhneStringEnd *= 1024;
            case 'k':
                $sizeOhneStringEnd *= 1024;
        }
        return $sizeOhneStringEnd;
    }
    
    private function getMaxSize($array)
    {
        $maxsize = 0;
        foreach($array as $size){
            if($size > $maxsize){
                $maxsize = $size;
            }
        }
        return $maxsize;
    }
    
    private function getDivisor($maxsize)
    {        
        if(floatval($maxsize) > 1024.00 && floatval($maxsize) < 1048576.00){
            $array = array("size" => "KB", "divisor" => 1024);
        }
        elseif(floatval($maxsize) > 1048576.00 && floatval($maxsize) < 1073741824.00){
            $array = array("size" => "MB", "divisor" => (1024*1024));
        }
        elseif(floatval($maxsize) > 1073741824.00){
            $array = array("size" => "GB", "divisor" => (1024*1024*1024));
        }
        else{
            $array = array("size" => "Bytes", "divisor" => 0);
        }
        
        return $array;
    }
    
    private function changeDateFormat($date)
    {
        $year = substr($date, 0, 4);
        $month = substr($date, 4, 2);
        $day = substr($date, 6, 2);
        
        return $day.".".$month.".".$year;
    }
}