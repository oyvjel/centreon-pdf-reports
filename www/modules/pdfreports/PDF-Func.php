<?php
/*
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * GPL License: http://www.gnu.org/licenses/gpl-2.0.txt
 * 
 * Developped by : 
 *   - Christophe Coraboeuf
 *   - Charles Judith 
 *   - Olivier LI KIANG CHEONG
 *   - Linagora
 *   - Øyvind Jelstad
 */

require_once("class/mypdf.class.php");
require_once("DB-Func.php");

/* pChart library inclusions */
include($centreon_path . "/www/modules/pdfreports/lib/pChart/class/pData.class.php");
include($centreon_path . "/www/modules/pdfreports/lib/pChart/class/pDraw.class.php");
include($centreon_path . "/www/modules/pdfreports/lib/pChart/class/pPie.class.php");
include($centreon_path . "/www/modules/pdfreports/lib/pChart/class/pImage.class.php");


function init_pdf_header() {
  
// First, we define K_TCPDF_EXTERNAL_CONFIG 
//define ('K_TCPDF_EXTERNAL_CONFIG', true);

//define ('PDF_HEADER_LOGO', "../../../img/header/centreon.gif");    
define ('PDF_HEADER_LOGO_WIDTH', 30);
  
}

//function pdfGen($group_name, $start_date, $end_date,$stats,$l,$logo_header, $chart_img){
function pdfGen($gid, $mode = NULL, $start_date, $end_date,$stats,$reportinfo){
		global $centreon_path;
		$subtitle = "";
#		$pdfDirName = getGeneralOptInfo("pdfreports_path_gen") . $endYear.$endMonth.$endDay . "/";
		
		if ($mode == "hgs") { // Hostgroup
		  $group_name = getMyHostGroupName($gid);
		  $subtitle = "Hosts in hostgroup " . $group_name;
		  $filetag = $group_name;
		} else if ($mode == "sgs") { // Servicegroup
		  $group_name = getMyServiceGroupName($gid);
		  $filetag = $group_name;
		  $subtitle = "Services in servicegroup " . $group_name;
		} else if ($mode == "shg") { // SLA-services on hosts in hostgroup
		  $mode = "sgs";
		  $group_name = getMyHostGroupName($gid);
		  $subtitle = "Services with SLA on hosts in hostgroup " .  $group_name;
		  $filetag = $group_name . "_sla";
		}
		//		define ('PDF_PAGE_ORIENTATION', 'P');
		
		// create new PDF document
#		$pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
		$pdf = new MYPDF('P', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

		//génération d'un nom de pdf
		$time = time();
		$pdf->DirName = getGeneralOptInfo("pdfreports_path_gen") . $reportinfo['report_id'] . "/";
		if (!is_dir($pdf->DirName))
		  mkdir($pdf->DirName,0775,true);
		$endDay = date("d", $time);
		$endYear = date("Y", $time);
		$endMonth = date("m", $time);
		$pdf->FileName =  $pdf->DirName .$endYear."-".$endMonth."-".$endDay."_".$filetag.".pdf";

		// set document information
		$pdf->SetCreator("PDF Reports Module");
		$pdf->SetAuthor(getGeneralOptInfo("pdfreports_report_author"));
		//$pdf->SetAuthor('Fully Automated Nagios');

		$pdfTitle = $reportinfo["report_title"] . " " . $filetag;
		//$pdfTitle = "Rapport de supervision du hostgroup ".$group_name; 
		$pdf->SetTitle($pdfTitle);
		//$pdf->SetSubject('TCPDF Tutorial');
		//$pdf->SetKeywords('TCPDF, PDF, example, test, guide');

		// define default header data
		$header = $reportinfo["report_title"];
		//$header = "Rapport de supervision du hostgroup ".$group_name;
		//$ip = $_SERVER['HOSTNAME'];
		$startDate = date("d/m/Y", $start_date);
		$endDate = date("d/m/Y", $end_date);
		$string = _("From") ." ".strftime("%A",$start_date). " ".$startDate." "._("to") ." ".strftime("%A",$time)." ".$endDate."\n".$subtitle;

		// set header and footer fonts
		$pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
		$pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

		// set default monospaced font
		$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

		//set margins
		$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
		$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
		$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

		//set auto page breaks
		$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

		//set image scale factor
		$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO); 

		//set some language-dependent strings
		$lg = Array();
		$lg['a_meta_charset'] = 'UTF-8';
		$lg['a_meta_language'] = 'no';
		$pdf->setLanguageArray($lg);

		// ---------------------------------------------------------

		// set font
		$pdf->SetFont('helvetica', '', 12);

		// set default header data
		
		//       	$pdf->SetHeaderData('../../img/headers/' . getGeneralOptInfo("pdfreports_report_header_logo") , PDF_HEADER_LOGO_WIDTH, $header,$string);
		$pdf->SetHeaderData('../../img/headers/' . getGeneralOptInfo("pdfreports_report_header_logo") ,60, $header,$string);

		// add a page
		$pdf->AddPage();
		$pdf->writeHTML($reportinfo["report_comment"] ); 

		// ---------------------------------------------------------


		return $pdf;

		//Close and output PDF document
		//		$pdf->Output($pdfFileName, 'F'); 

		//		return $pdfFileName;
}

function pdfWriteFile($pdf) {
  $file = $pdf->FileName;
  $pdf->Output($file, 'F');  #This destroys the pdf object!
  return $file;
}


function pdfHosts($pdf, $stats) {
	
  // Pie chart Generation
  $piechart_img = pieGen($stats,"hgs",$pdf->DirName);
  
  //Data loading
  $data = $pdf->LoadData($stats);
  
  //Column titles
  $header = array('Status', 'Time', 'Total Time', 'Mean Time', 'Alert');
  
  // print colored table
  $pdf->ColoredTable($header, $data,$piechart_img );
}
function pdfHostsTimeline($pdf, $hgs_id, $start_date, $end_date){

  $daytable = getHGDayStat($hgs_id, $start_date, $end_date);
  
  $pdf->writeHTML("<H1>Tidslinje</H1", true, false, false, false, ''); 
  $pdf->writeHTML($daytable, true, false, false, false, ''); 
}

function myDebug($message) {
  global $debug;
  $debug = false;
  if (! $debug ) return;

  echo "Debug: " . $message . "\t Memory usage: " .memory_get_usage() . "\n";
  //  print_r(debug_backtrace(false,1),true);

}
function pdfServices($pdf, $data, $header ) {
		// Pie chart Generation
  $piechart_img = pieGen($data,"sgs",$pdf->DirName);
  
  myDebug("ServiceSummary");
  $pdf->ServicesSummary($header, $data,$piechart_img);
  
  // Remove average 
  unset($data['average']);
  $data = array_values($data);
  // 		print_r($data);
  
  $serviceStatsLabels = array();
  $serviceStatsLabels = getServicesStatsValueName();
  $status = array("OK", "WARNING", "CRITICAL", "UNKNOWN", "UNDETERMINED", "MAINTENANCE");
  $zero["COUNT"] = 0;
  foreach ($serviceStatsLabels as $name){
    $zero[$name] = 0.0;
  }
     

  myDebug("Service and host collapse");
  $svStats = array();
  $hostStats = array();
  // Collapse hosts and services
  foreach ($data as $rid => $row){
    $h_id = $row['HOST_ID'];
    //    $s_id = $row['SERVICE_ID'];
    $s_id = $row['SERVICE_DESC'];
    if (!isset($svStats[$s_id])) {
      $svStats[$s_id] = $zero;
      $svStats[$s_id]["SERVICE_DESC"] =  $row['SERVICE_DESC'];
    }
    if (!isset($hostStats[$h_id])) {
      $hostStats[$h_id] = $zero;
      $hostStats[$h_id]["HOST_NAME"] = $row['HOST_NAME'];
    }
    $svStats[$s_id]["COUNT"] ++;
    $hostStats[$h_id]["COUNT"] ++;
    foreach ($serviceStatsLabels as $name){
      $svStats[$s_id][$name] += $row[$name];
      $hostStats[$h_id][$name] += $row[$name];
    }
  } 


   foreach ($hostStats as $h_id => $hs){
    $duration = $hs["OK_T"] +  $hs["CRITICAL_T"] +  $hs["UNKNOWN_T"] + $hs["WARNING_T"];
    $time = $duration + $hs["UNDETERMINED_T"] + $hs["MAINTENANCE_T"];
    $hostStats[$h_id]["SERVICE_DESC"] =  $hs["COUNT"] . " services";  
    // We recalculate percents
    foreach ($status as $key => $value) {
      if ($time)
	$hostStats[$h_id][$value."_TP"] = round($hs[$value."_T"] / $time * 100, 2);
      else
	$hostStats[$h_id][$value."_TP"] = 0;
 
     if ($duration)
	$hostStats[$h_id][$value."_MP"] = round($hs[$value."_T"] / $duration * 100, 2);
      else
	$hostStats[$h_id][$value."_MP"] = 0;
 
    } 
  }

  myDebug("Service stats calc");

   foreach ($svStats as $s_id => $ss){
    $duration = $ss["OK_T"] +  $ss["CRITICAL_T"] +  $ss["UNKNOWN_T"]  +  $ss["WARNING_T"];
    $time = $duration + $ss["UNDETERMINED_T"] + $ss["MAINTENANCE_T"];
    $svStats[$s_id]["HOST_NAME"] =  $ss["COUNT"] . " hosts";  
    // We recalculate percents
    foreach ($status as $key => $value) {
      if ($time)
	$svStats[$s_id][$value."_TP"] = round($ss[$value."_T"] / $time * 100, 2);
      else
	$svStats[$s_id][$value."_TP"] = 0;
 
     if ($duration)
	$svStats[$s_id][$value."_MP"] = round($ss[$value."_T"] / $duration * 100, 2);
      else
	$svStats[$s_id][$value."_MP"] = 0;
 
    } 
  }

   myDebug("HostTable");
   usort($hostStats, "mpOKSort");
   $pdf->ServicesColoredTable($hostStats,"Summary for Hosts (Ordered by OK % of SLA time)");

   myDebug("ServiceTable");
   usort($svStats, "mpOKSort");
   $pdf->ServicesColoredTable($svStats,"Summary for Services (Ordered by OK % of SLA time)");

   myDebug("Sort Table");

   /* 
//Sort new dataset.
  usort($data, function($a, $b) {
      if ($a['OK_MP']==$b['OK_MP']) return 0;
      return ($a['OK_MP']<$b['OK_MP'])?-1:1;
    });
   */
   //   usort($data, "mpOKSort");
   //   array_sort_by_column($data, 'OK_MP');
   myDebug("Service-Host Table");
  
  $pdf->ServicesColoredTable($data,"State Breakdowns For Host Services (ordered by host,service)");
  
}
function mpOKSort($a, $b) {
      if ($a['OK_MP']==$b['OK_MP']) return 0;
      return ($a['OK_MP']<$b['OK_MP'])?-1:1;
}

function array_sort_by_column(&$arr, $col, $dir = SORT_ASC) {
  $sort_col = array();
  foreach ($arr as $key=> $row) {
    $sort_col[$key] = $row[$col];
  }

  array_multisort($sort_col, $dir, $arr);
}


//array_sort_by_column($array, 'order');

function pdfServicesList($pdf, $data) {
	
		// Remove aveerage 
		unset($data['average']);
		$data = array_values($data);
		//Sort new dataset.
		usort($data, function($a, $b) {
		    if ($a['OK_MP']==$b['OK_MP']) return 0;
		    return ($a['OK_MP']<$b['OK_MP'])?-1:1;
		  });
		//		print_r($data);

		$pdf->ServicesColoredTable($data);

}


/*
* Including pie chart in report (like dashboard)
*/
function pieGen($stats, $mode,$Dir) {
	
	global $centreon_path;
	// Create and populate the pData object 
	 $MyData = new pData();

	// print_r($stats["average"]);
	
	$arrPoints = array();
	$i=0;
	// Host groups
	if ($mode == "hgs" ) { 
	 
	 $MyData->addPoints(array($stats["average"]["UP_TP"],
				  $stats["average"]["DOWN_TP"],
				  $stats["average"]["UNDETERMINED_TP"],
				  $stats["average"]["UNREACHABLE_TP"],
				  $stats["average"]["MAINTENANCE_TP"]),
				"Hostgroups");  
	 $MyData->setSerieDescription("Hostgroups","Hostgroups");
	

	$arrPoints = array("Up",
				  "Down",
				  "Undeterminded",
				  "Unreachable",
				  "Schedule Downtime");		

	// Define the absissa serie 
	 $MyData->addPoints($arrPoints,	"Labels");
	 $MyData->setAbscissa("Labels");					  
				  
				  
	 // Create the pChart object 
	 $myPicture = new pImage(120,120,$MyData,TRUE);				  
	 // Create the pPie object  
	 $PieChart = new pPie($myPicture,$MyData);			  
				  
	$i = 0;
	/* Define the slice color */ 
	if ($stats["average"]["UP_TP"] > 0 ) { 
		$PieChart->setSliceColor($i,array("R"=>25,"G"=>238,"B"=>17));  // UP
		$i++;
		}
	if ($stats["average"]["DOWN_TP"] > 0 ) {
		$PieChart->setSliceColor($i,array("R"=>249,"G"=>30,"B"=>5));   // DOWN
		$i++;
		}
	if ($stats["average"]["UNDETERMINED_TP"] > 0 ) 	{
		$PieChart->setSliceColor($i,array("R"=>204,"G"=>248,"B"=>255));// UNDETERMINED
		//$PieChart->setSliceColor($i,array("R"=>240,"G"=>240,"B"=>240)); 
		$i++;
		}
	if ($stats["average"]["UNREACHABLE_TP"] > 0 ) 	{
		$PieChart->setSliceColor($i,array("R"=>130,"G"=>207,"B"=>216)); // UNREACHABLE
		$i++;
		}
	if ($stats["average"]["MAINTENANCE_TP"] > 0 ) 	{
		$PieChart->setSliceColor($i,array("R"=>204,"G"=>153,"B"=>255));	// MAINTENANCE
		$i++;
		}
	  
	}

	// Service Groups
	if ($mode == "sgs" ) { 
	 
	 $MyData->addPoints(array($stats["average"]["OK_TP"],
				  $stats["average"]["WARNING_TP"],
				  $stats["average"]["CRITICAL_TP"],
				  $stats["average"]["UNKNOWN_TP"],
				  $stats["average"]["MAINTENANCE_TP"],
				  $stats["average"]["UNDETERMINED_TP"]),
				"Servicegroups");  
	 $MyData->setSerieDescription("Servicegroups","Servicegroups");
	

	$arrPoints = array("Ok",
				  "Warning",
				  "Critical",
				  "Unknown",
				  "Schedule Downtime",
				  "Undeterminded");

		// Define the absissa serie 
	 $MyData->addPoints($arrPoints,	"Labels");
	 $MyData->setAbscissa("Labels");			  
				  
	 // Create the pChart object 
	 $myPicture = new pImage(120,120,$MyData,TRUE);	
	 // Create the pPie object  
	 $PieChart = new pPie($myPicture,$MyData);
	
	
	
	/* Define the slice color */ 
	if ($stats["average"]["OK_TP"] > 0 ) {
		$PieChart->setSliceColor($i,array("R"=>13,"G"=>235,"B"=>58));  // OK
		$i++;
		}
	if ($stats["average"]["WARNING_TP"] > 0 ) { 	
		$PieChart->setSliceColor($i,array("R"=>248,"G"=>199,"B"=>6));   // DOWN 
		$i++;
		}
	if ($stats["average"]["CRITICAL_TP"] > 0 ) 	{ 	
		$PieChart->setSliceColor($i,array("R"=>249,"G"=>30,"B"=>5));   // DOWN
		$i++;
		}
	if ($stats["average"]["UNKNOWN_TP"] > 0 ) {		
		$PieChart->setSliceColor($i,array("R"=>220,"G"=>218,"B"=>218)); // UNKNOWN
		$i++;
		}
	if ($stats["average"]["MAINTENANCE_TP"] > 0 ) { 	
		$PieChart->setSliceColor($i,array("R"=>204,"G"=>153,"B"=>255));	// MAINTENANCE
		$i++;
		}
	if ($stats["average"]["UNDETERMINED_TP"] > 0 ) 	{ 
		$PieChart->setSliceColor($i,array("R"=>204,"G"=>248,"B"=>255)); // UNDETERMINED
		//$PieChart->setSliceColor($i,array("R"=>240,"G"=>240,"B"=>240)); // UNDETERMINED		
		$i++;
		}	  
	}		 
	
	
	$Settings = array("R"=>255, "G"=>255, "B"=>255);
	$myPicture->drawFilledRectangle(0,0,120,120,$Settings);
	
	//  Enable shadow computing  
	// $myPicture->setShadow(TRUE,array("X"=>3,"Y"=>3,"R"=>0,"G"=>0,"B"=>0,"Alpha"=>10));
	
	 // Draw a splitted pie chart 
	//	 $PieChart->draw3DPie(60,70,array("Radius"=>50,"DataGapAngle"=>8,"DataGapRadius"=>6,"Border"=>TRUE,"BorderR"=>0,"BorderG"=>0,"BorderB"=>0));
	$PieChart->draw2DPie(60,60,array("Radius"=>50,"DrawLabels"=>FALSE,"LabelStacked"=>TRUE,"Border"=>FALSE,"BorderR"=>0,"BorderG"=>0,"BorderB"=>0));

	 /* Render the picture  */
	 

	//	$pie_file = tempnam( "/tmp" , "reportreon_pie_" );
	$pie_file = tempnam($Dir, "reportreon_pie_" );
//	$pie_file = tempnam( $centreon_path . "/www/modules/pdfreports/generatedFiles/tmp" , "reportreon_pie_" );

	$myPicture->render($pie_file . ".png");
	       	@unlink($pie_file );

	return  $pie_file  . ".png" ;
}




?>
