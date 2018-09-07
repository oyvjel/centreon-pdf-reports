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

/*
ini_set('display_errors',1);
error_reporting(E_ALL);
*/
	require_once($centreon_path . "www/class/centreonDB.class.php");
	require_once($centreon_path . "www/include/common/common-Func.php");
        require_once($centreon_path . 'www/class/centreonDB.class.php');
        require_once($centreon_path . 'www/class/centreonAuth.class.php');
        require_once($centreon_path . 'www/class/centreonLog.class.php');
        require_once($centreon_path . 'www/class/centreonUser.class.php');
        require_once($centreon_path . 'www/class/centreonSession.class.php');   
        require_once($centreon_path . 'www/class/centreon.class.php');
        require_once($centreon_path . 'www/class/centreonACL.class.php');  
        include_once $centreon_path . 'www/include/reporting/dashboard/DB-Func.php';  
        include_once $centreon_path . "www/include/common/common-Func.php";
        require_once("Mail-Func.php");
        require_once("PDF-Func.php");
        require_once("DB-Func.php");


#  $debug = true;

	## Get centreon version
	/*
	 * Connector to centreon DB
	 */
	$pearDB = new CentreonDB();
	// Bug #1556
	//$pearDBndo = new CentreonDB("ndo");
	$pearDBO = new CentreonDB("centstorage");

	$DBRESULT =& $pearDB->query("SELECT `value` FROM `informations` WHERE `key` = 'version'");
	$version =& $DBRESULT->fetchRow();  	
	$centreon_version = substr(str_replace(".", "" ,$version["value"]), 0, 3);
	
	if ( $centreon_version >= 220) { 
		require_once($centreon_path . "www/class/centreonDuration.class.php");   
	} else {
		require_once($centreon_path . "www/class/other.class.php");   
	}


function shutdown() 
{ 
  $a=error_get_last(); 
  if($a==null)   
    myDebug( "No errors"); 
  else 
    myDebug( "<pre>\n" . print_r($a, true) . "</pre>\n"); 
  
} 


function RunNowReportInDB ($report_id = null, $report_arr = array()) {
  global $centreon_version;
  //  register_shutdown_function('shutdown'); 
  print "<p>Generating reports....</p>\n";
  print "<p>Centreon version = ". $centreon_version . "</p>\n";
	  
  ini_set('max_execution_time', 900);
  myDebug("Max execution time set to 900 sec");

  ini_set('memory_limit','1G');
  print "<pre>\n";

  GenerateReport($report_id);
  print "</pre>\n";

}

#####
function GenerateReport ($report_id = null) {	  
#  if (!$report_id && !count($report_arr)) return;
  if (!$report_id) return;
  global $pearDB, $oreon, $debug;
  $hosts = array();
  $reportinfo = array();
  $hosts = getHostReport($report_id);
  $reportinfo = getReportInfo($report_id);
  $debug = $reportinfo['bdebug'];
  myDebug("Categories: ". print_r($category, true));

  $services = getServiceGroupReport($report_id);
  $dates = getPeriodToReportFork($reportinfo['period']);
  myDebug("Period = ". print_r($dates, true));
  $start_date = $dates[0] ;
  $end_date = $dates[1];
  myDebug("Start = ". date('r',$start_date));
  myDebug("End = ". date('r',$end_date));
  $category = explode(',',$reportinfo["service_category"]);            
  $hgnr = 0;
  $reportingTimePeriod = getreportingTimePeriod();
  $data = array();
  $periodlist = getPeriodListFork(); 

  $totalhosts = 0;
  $totalservices = 0;
  $okh = 0;
  $oks = 0;

  $csvgen = $reportinfo['bcsv'];
  $tlgen = $reportinfo['btimeline'];
  $sumonly = $reportinfo['bsummary_only'];

# Main Report summary:  
  $templfile = $reportinfo["report_template"];
  if (file_exists($templfile)) {
    $time = $end_date;
#    $time = time();
    $endDay = date("d", $time);
    $endYear = date("Y", $time);
    $endMonth = date("m", $time);
    $tpl_parts = pathinfo($templfile);
    $ReportFile = getGeneralOptInfo("pdfreports_path_gen") . $reportinfo['report_id'] . "/" .$endYear."-".$endMonth."-".$endDay 
      ."_". $periodlist[$reportinfo['period']] . "_Report." . $tpl_parts['extension'];

    myDebug("The SLA template file $templfile will be used to generate $ReportFile \n");
    $Allfiles[] = $ReportFile;
  } else {
    myDebug("The SLA template file $templfile does not exist \n");
  }
  myDebug("Categories: ". print_r($category, true));


############# HostGroups: //////////////////
  if (isset($hosts) && count($hosts) > 0) {
    foreach ( $hosts['report_hgs'] as $hgs_id ) {
      $group_name = getMyHostGroupName($hgs_id);
      
      myDebug("Processing HG ( ". print_r($hgs_id, true) . " ) " . $group_name . " ," . getMyHostGroupField($hgs_id,"hg_alias"));
      $stats = array();
      $stats = getLogInDbForHostGroup($hgs_id , $start_date, $end_date, $reportingTimePeriod);
      $average = $stats["average"];
      myDebug("Summary: ". print_r($stats["average"], true) );

      myDebug("Generate pdf file");

      $pdf = pdfGen( $hgs_id, 'hgs', $start_date, $end_date, $stats, $reportinfo );
      myDebug("Generated file: ". $pdf->FileName);
      $grp_comment = getMyHostGroupField($hgs_id,"hg_comment");
      $pdf->writeHTML("<p><b>Host group comment:</b><br>\n" . $grp_comment . "\n<hr>\n"); 

      myDebug("Generate HOST stats");
      $csv = pdfHosts($pdf, $stats);

# CSV file::::::::
      if ($csvgen){ 
	$csvfile = $pdf->FileName;
	$csv_parts = pathinfo($csvfile);
	$csvfile = $csv_parts['dirname'] ."/" . $csv_parts['filename'] . ".csv"; 
	myDebug("csvfile: ". $csvfile );
# WRITE the file....
	file_put_contents($csvfile, $csv);
	$Allfiles[] = $csvfile; 
      }
      if ($tlgen){ 
	myDebug("Generate Timeline stats");
	pdfHostsTimeline($pdf, $hgs_id, $start_date, $end_date);
      }

      myDebug("Averages: ". print_r($average, true) );
      $up = $average['UP_MP'];
      $sla_data[] = array('hgroup'=> "Hostgroup " . $group_name
			  ,'repfile'=>basename($pdf->FileName)
			  ,'UP'=>$up
			  ,'down'=>$average['DOWN_MP']
			  ,'unknown'=>$average['UNREACHABLE_MP']
			  ,'UP_A'=>$average['UP_A'] 
			  ,'ok'=> ''
			  ,'warn'=> ''
			  ,'crit'=> ''
			  ,'ok_a'=> ''
			  ,'warn_a'=> ''
			  ,'crit_a'=> ''
			  ,'down_a'=>  $average['DOWN_A']
			  ,'unknown_a'=>$average['UNREACHABLE_A']
			  ,'nosla'=>$average['MAINTENANCE_TP']
			  ,'undet'=>$average['UNDETERMINED_TP']
			  ,'hnr'=>$pdf->statlines
			  ,'events'=>$pdf->events
			  ,'comment'=>$grp_comment
			  );

     	// mean = (mean*nold + new*nnew)/(nold+nnew)
	$okh = ($okh * $totalhosts + $up * $pdf->statlines) /($totalhosts + $pdf->statlines);
	$totalhosts += $pdf->statlines;

	if ($sumonly){
	  $i = count($sla_data) -1;
	  $sla_data[$i]['repfile'] = 'na';
	  unset($pdf);
	}else{  
	  myDebug("Write pdf to file");
	  $filename = pdfWriteFile($pdf); # pdf object is destroyed!!!
	  $Allfiles[] = $filename; 
	}
############# Services for hosts in hostgrp: //////////////////
      if (isset($category[$hgnr]) && is_numeric ($category[$hgnr]) ) {
	unset($stats);
	myDebug("Generate SERVICES stats for service category( " .$hgnr . " ) = " . $category[$hgnr] . " ".getMyCategorieField($category[$hgnr],'sc_description') ." in  hostgroup " . $hgs_id );

	$stats = array();
	$stats = getLogInDbForHostgrpServices($hgs_id , $start_date, $end_date, $reportingTimePeriod,$category[$hgnr]);
	myDebug("Summary: ". print_r($stats["average"], true) );
	$average = $stats["average"];
	myDebug("Averages for services in hostgroup: ". print_r($average, true) );

	$pdf = pdfGen( $hgs_id, 'shg', $start_date, $end_date, $stats, $reportinfo );
	//		      		    print_r($stats);
	$grp_comment = getMyHostGroupField($hgs_id,"hg_comment");
	$pdf->writeHTML("<p><b>Host group comment:</b><br>\n" . $grp_comment . "\n<p>\n"); 
	$pdf->writeHTML("<b>Service category(" . $category[$hgnr] ."):</b> ". getMyCategorieField($category[$hgnr],'sc_description') . "\n<hr>\n");

	myDebug("Generate services stats");
	pdfServices($pdf, $stats,"Services in hostggroup overview");

	$up = $average['OK_MP'] + $average['WARNING_MP'];
	$up_a = $average['OK_A'] + $average['WARNING_A'];
	$sla_data[] = array('hgroup'=> $group_name . " services"
			    ,'repfile'=>basename($pdf->FileName)
			    ,'UP'=>$up 
			    ,'ok'=> $average['OK_MP']
			    ,'warn'=> $average['WARNING_MP']
			    ,'crit'=> $average['CRITICAL_MP']
			    ,'down'=> $average['CRITICAL_MP']
			    ,'unknown'=>$average['UNKNOWN_MP']
			    ,'UP_A'=>$up_a 
			    ,'ok_a'=> $average['OK_A']
			    ,'warn_a'=> $average['WARNING_A']
			    ,'crit_a'=> $average['CRITICAL_A']
			    ,'down_a'=> $average['CRITICAL_A']
			    ,'unknown_a'=>$average['UNKNOWN_A']
			    ,'nosla'=>$average['MAINTENANCE_TP']
			    ,'undet'=>$average['UNDETERMINED_TP']
			    ,'hnr'=>$pdf->statlines
			    ,'events'=>$pdf->events
			    ,'comment'=>$grp_comment
			    );
	$oks = ($oks * $totalservices + $up * $pdf->statlines) /($totalservices + $pdf->statlines);
	$totalservices += $pdf->statlines;
	if ($sumonly){
	  $i = count($sla_data) -1;
	  $sla_data[$i]['repfile'] = 'na';
	  unset($pdf);
	}else{  
	  $filename = pdfWriteFile($pdf);
	  $Allfiles[] = $filename; 
	}
      }
      $hgnr++;
    }
  }
################  ServiceGroups ///////////////////////
  if (isset( $services ) && count($services) > 0 ) {
    foreach ( $services['report_sg'] as $sg_id ) {
      $group_name = getMyServiceGroupName($sg_id);
      myDebug("Processing SG ( ". print_r($sg_id, true) . " ) " . $group_name );
      unset($stats);
      $stats = array();
      $stats = getLogInDbForServicesGroup($sg_id , $start_date, $end_date, $reportingTimePeriod);
      $average = $stats["average"];
      myDebug("Averages for servicegroup: ". print_r($average, true) );
      $pdf = pdfGen( $sg_id, 'sgs', $start_date, $end_date, $stats, $reportinfo );
      $grp_comment = getMyServiceGroupField($sg_id,"sg_comment");
      $pdf->writeHTML("<p><b>Service group comment:</b><br>\n" . $grp_comment . "\n<hr>\n"); 

      myDebug("Generate ServiceGroup stats");
      pdfServices($pdf, $stats,"Services group overview");

      $up = $average['OK_MP'] + $average['WARNING_MP'];
      $up_a = $average['OK_A'] + $average['WARNING_A'];
      $sla_data[] = array('hgroup'=> "Servicegrp " . $group_name
			    ,'repfile'=>basename($pdf->FileName)
			    ,'UP'=>$up 
			    ,'ok'=> $average['OK_MP']
			    ,'warn'=> $average['WARNING_MP']
			    ,'crit'=> $average['CRITICAL_MP']
			    ,'down'=>  $average['CRITICAL_MP']
			    ,'unknown'=>$average['UNKNOWN_MP']
			    ,'UP_A'=>$up_a 
			    ,'ok_a'=> $average['OK_A']
			    ,'warn_a'=> $average['WARNING_A']
			    ,'crit_a'=> $average['CRITICAL_A']
			    ,'down_a'=> $average['CRITICAL_A']
			    ,'unknown_a'=>$average['UNKNOWN_A']
			    ,'nosla'=>$average['MAINTENANCE_TP']
			    ,'undet'=>$average['UNDETERMINED_TP']
			    ,'hnr'=>$pdf->statlines
			    ,'events'=>$pdf->events
			    ,'comment'=>$grp_comment
			    );



	$oks = ($oks * $totalservices + $up * $pdf->statlines) /($totalservices + $pdf->statlines);
	$totalservices += $pdf->statlines;
	if ($sumonly){
	  $i = count($sla_data) -1;
	  $sla_data[$i]['repfile'] = 'na';
	  unset($pdf);
	}else{  
	  $filename = pdfWriteFile($pdf);
	  $Allfiles[] = $filename; 
	}
    }
  }

  // Generate summary document from template:
  if (file_exists($templfile)) {
    // Include classes
    global $centreon_path;
    myDebug("Include TBS = ". print_r($centreon_path, true));
    include_once($centreon_path . "/www/modules/pdfreports/lib/tbs/tbs_class.php");
    include_once($centreon_path . "/www/modules/pdfreports/lib/tbs/tbs_plugin_opentbs.php");

    // prevent from a PHP configuration problem when using mktime() and date()
    if (version_compare(PHP_VERSION,'5.1.0')>=0) {
      if (ini_get('date.timezone')=='') {
	date_default_timezone_set('UTC');
      }
    }
    // Initialize the TBS instance
    $TBS = new clsTinyButStrong; // new instance of TBS
    $TBS->Plugin(TBS_INSTALL, OPENTBS_PLUGIN); // load the OpenTBS plugin
    ### Data:
    // define default header data
    $GLOBALS['title'] = $reportinfo["report_title"];
    $GLOBALS['startDate'] = date("d/m/Y", $start_date);
    $GLOBALS['endDate'] = date("d/m/Y", $end_date);
    $GLOBALS['reportdate'] = date("Y-m-d", $time);
    $GLOBALS['description'] = $reportinfo["report_description"];
    $GLOBALS['comment'] = $reportinfo["report_comment"];
    $GLOBALS['period'] =   $periodlist[$reportinfo['period']];
    $TBS->LoadTemplate($templfile, OPENTBS_ALREADY_UTF8); // Also merge some [onload] automatic fields (depends of the type of document).
    $GLOBALS['okh'] = round($okh,2); 
    $GLOBALS['oks'] = round($oks,2);
    $GLOBALS['sla'] = 99.5;

    $GLOBALS['kam'] = ''; 
    $GLOBALS['author'] = "Centreon"; 
 
    
    //  $TBS->PlugIn(OPENTBS_DEBUG_INFO);
  // TODO: Merge data in $templfile, see http://www.tinybutstrong.com/opentbs.php
    myDebug("TBS merge array data: ". print_r($sla_data, true));

    $TBS->MergeBlock('a,b,c', $sla_data);
    
    myDebug("Write report file ". $ReportFile);

    $TBS->Show(OPENTBS_FILE, $ReportFile);

  }

#### Summary & mail
  $summary = "\n<hr>\nUP hosts = ". $GLOBALS['okh'] ."%";
  $summary .= "<br>\nUP services = ". $GLOBALS['oks'] ."%";

  $summary .= '
    <table border="1">
	<tr style="background-color:#D5DFEB;" >
		<th>Nr</th>
		<th>Group</th>
		<th>UP%</th>
		<th>Nunber</th>
		<th>Events</th>
		<th>File</th>
	</tr>';

  foreach ($sla_data as $key => $tab) {
    $summary .= "\n<tr><td>". $key . "</td><td>" 
    . $tab['hgroup'] . "</td><td>" 
    . $tab['UP'] . "</td><td>"
    . $tab['hnr'] . "</td><td>"
    . $tab['events'] . "</td><td>"
    . $tab['repfile'] . "</td>"
    . "</tr>\n"; 
  }
  $summary .= "</table>\n";

  $files = array();
  $b = getGeneralOptInfo("pdfreports_path_gen");
  // FQDN for host when called from cli uses gethostbyaddr("127.0.1.1"). Define fqdn for 127.0.1.1 in /etc/hosts. Ref Debian man hostname
  $h = empty($_SERVER['HTTP_HOST']) ? 'https://'. gethostbyaddr("127.0.1.1") : 'https://'. $_SERVER['HTTP_HOST'];
  $summary .= "\n<p>Generated files:<ul>\n";
  foreach ( $Allfiles as $file) {
    $files[basename($file)]["url"] = $file;
    $u = str_replace($b,"/reports/",$file);
    $a = str_replace($b,"",$file);
    // $u = rawurlencode($u); # Chrome does not decode %2F (/)in url (except for the error message ???). Firefox is OK.
    // https://<server>/reports%2F1%2F2018-08-01_Last%20Month_Report.docx produces the message:
    // The requested URL /reports/1/2018-08-01_Last Month_Report.docx was not found on this server.
    // https://<server>/reports/1/2018-08-01_Last%20Month_Report.docx is OK
    // translate only space:
    $u = str_replace(" ","%20",$u);
    $a = str_replace(" ","%20",$a);
    $summary .= '<li><a href="'. $h . $u . "\">". $file . "</a>. ";
    $summary .= 'Download: <a href="' . $h. '/centreon/modules/pdfreports/viewreport.php?file='. $a . '">'.basename($file)."</a></li>\n ";

#    $summary .= "<li><a href=\"$a\">". $file . "</a> </li>\n";
  }
  $summary .= "</ul>\n";


  print $summary;

  if ($reportinfo['activate'] > 0 ) {
    $emails = getReportContactEmail($report_id);

    mailer(getGeneralOptInfo("pdfreports_report_author"),
	   getGeneralOptInfo("pdfreports_email_sender"),$emails,$reportinfo['subject'],$reportinfo['mail_body'] . $summary,
	   getGeneralOptInfo("pdfreports_smtp_server_address"),$files,$reportinfo['name'] );
  } else {
    print "<p>Generated, but NO mail sent, report is not active <p>\n";
  }

}


function getVersionNagios() {
  global $pearDB;
  $DBRESULT = $pearDB->query("SELECT `value` FROM `options` WHERE `key` = 'nagios_version' LIMIT 1");
  $row = $DBRESULT->fetchRow();
  $DBRESULT->free();
  return $row["value"];
}

function getCentreonVersionPdf(){	

	## Get centreon version
	global $pearDB;

	$DBRESULT =& $pearDB->query("SELECT `value` FROM `informations` WHERE `key` = 'version'");
	$version =& $DBRESULT->fetchRow();  	
	$centreon_version = substr(str_replace(".", "" ,$version["value"]), 0, 3);

	return $centreon_version;
}
	
function getAdminUserAlias(){	

	## Get centreon version
	global $pearDB;

	$DBRESULT =& $pearDB->query("SELECT `contact_alias` FROM `contact` WHERE `contact_admin` = '1' AND `contact_activate` = '1' LIMIT 1");
	$contact =& $DBRESULT->fetchRow();  	
	$admin_alias = $contact["contact_alias"];

	return $admin_alias;
}

	
function getGeneralOptInfo($option_name)	{

	global $pearDB;

    $DBRESULT =& $pearDB->query("SELECT value FROM options WHERE options.key like '".$option_name."'");
    if (PEAR::isError($DBRESULT))
        print "DB Error : SELECT value FROM options WHERE options.key like '".$option_name."' : ".$DBRESULT->getMessage()."<br />";
        $gopt = $DBRESULT->fetchRow();
	
	return  $gopt['value'] ;		
}		
	
	//reprise de la fonction getPeriodToReport de www/include/reporting/dashboard/common-Func.php pour retourner un timestamp sans $_POST
function getPeriodToReportFork($arg) {	
<<<<<<< HEAD
                $interval = getDateSelectPredefined($arg);
		//		$interval = getDateSelect_predefined($arg);
=======
		$interval = getDateSelect_predefined_Fork($arg);
>>>>>>> 2.7
		$start_date = $interval[0];
		$end_date = $interval[1];
		return(array($start_date,$end_date));
}

function getDateSelect_predefined_Fork($period){
  $time = time();
  $day = date("d", $time);
  $year = date("Y", $time);
  $month = date("m", $time);

  if (!is_null($period)){
    if($period == "yesterday"){
      $start_date = mktime(0, 0, 0, $month, $day - 1, $year);
      $end_date = mktime(24, 0, 0, $month, $day - 1, $year);
    } else if($period == "thisweek"){
      $dd = (date("D",mktime(0, 0, 0, $month, $day, $year))); // today@ 00:00:00
      for($ct = 1; $dd != "Mon" ;$ct++)
	$dd = (date("D",mktime(0, 0, 0, $month, ($day - $ct), $year)));
      $start_date = mktime(24, 0, 0, $month, $day - $ct, $year);
      $end_date = mktime(0, 0, 0, $month, ($day), $year);
    } else if($period == "lastweek"){
      $dd = (date("D",mktime(0, 0, 0, $month, $day - 7, $year)));
      for($ct = 8; $dd != "Mon" ;$ct++) {
	$dd = (date("D",mktime(0, 0, 0, $month, ($day - $ct), $year)));
	//	print_r( $ct . " ".  $dd . " start_date = " . mktime(0, 0, 0, $month, $day - $ct, $year) . "\n");
      }
      
      $start_date = mktime(24, 0, 0, $month, $day - $ct, $year);
      $end_date = mktime(24, 0, 0, $month, ($day - $ct + 7), $year);
    } else if($period == "last7days"){
      $start_date = mktime(0, 0, 0, $month, $day - 7, $year);
      $end_date = mktime(24, 0, 0, $month, $day - 1, $year);
    } else if($period == "last30days"){
      $start_date = mktime(0, 0, 0, $month, $day - 30, $year);
      $end_date = mktime(24, 0, 0, $month, $day - 1, $year);
    } else if($period == "lastyear"){
      $start_date = mktime(0, 0, 0, 1, 1, $year-1);
      $end_date = mktime(0, 0, 0, 1, 1, $year);
    } else if($period == "thismonth") {
      $start_date = mktime(0, 0, 0, $month, 1, $year);
      $end_date = mktime(24, 0, 0, $month, $day - 1, $year);

    } else if($period == "last3months") {
      $start_date = mktime(0, 0, 0, $month - 3, 1, $year);
      $end_date = mktime(0, 0, 0, $month, 1, $year);
    } else if($period == "lastquarter") {
      $start_date = strtotime('3 months ago');
      $start_quarter = ceil(date('m', $start_date) / 3);
      $start_month = ($start_quarter * 3) - 2;
      $start_year = date('Y', $start_date);
      $start_date = mktime(0, 0, 0, $start_month, 1, $start_year);
      $end_date = mktime(0, 0, 0, $start_month + 3, 1, $start_year);

    } else if($period == "thisyear"){
      $start_date = mktime(0, 0, 0, 1, 1, $year);
      $end_date = mktime(24, 0, 0, $month, $day - 1, $year);
    } else { /* last month */
      $start_date = mktime(0, 0, 0, $month - 1, 1, $year);
      $end_date = mktime(0, 0, 0, $month, 1, $year);
    }
  } else {
    $start_date = mktime(0, 0, 0, $month, $day - 1, $year);
    $end_date = mktime(24, 0, 0, $month, $day - 1, $year);
  }

  if ($start_date > $end_date) {
    $start_date = $end_date;
  }
  return (array($start_date, $end_date));
}



function getPeriodListFork(){

  $periodList = array();
  $periodList[""] = "";
  $periodList["yesterday"] = _("Yesterday");
  $periodList["thisweek"] = _("This Week");
  $periodList["lastweek"] = _("Last Week");
  $periodList["last7days"] = _("Last 7 Days");
  $periodList["thismonth"] = _("This Month");
  $periodList["last30days"] = _("Last 30 Days");
  $periodList["lastmonth"] = _("Last Month");
  $periodList["last3months"] = _("Last 3 Months");
  $periodList["lastquarter"] = _("Last Quarter");
  $periodList["thisyear"] = _("This Year");
  $periodList["lastyear"] = _("Last Year");

  return $periodList;
}

//recuperation de tous les groupes de services
function getAllServiceGroups(){

		global $pearDB;
		$req = "SELECT * FROM `servicegroup`";
		$DBRESULT =& $pearDB->query($req);
		unset($req);
		if (PEAR::isError($DBRESULT))
			print "DB Error : ".$DBRESULT->getDebugInfo()."<br />";
		while ($row =& $DBRESULT->fetchRow())			    
			$sdata[] = $row;  
		$DBRESULT->free();
		return $sdata; 

}


//recuperation de tous les groupes d'hosts
function getAllHostGroup(){

		global $pearDB;
		$req = "SELECT * FROM `hostgroup`";
		$DBRESULT =& $pearDB->query($req);
		unset($req);
		if (PEAR::isError($DBRESULT))
			print "DB Error : ".$DBRESULT->getDebugInfo()."<br />";
		while ($row =& $DBRESULT->fetchRow())			    
			$hdata[] = $row;  
		$DBRESULT->free();
		return $hdata; 

}


# Report Logos
function return_logos_list($mode = 0, $rep = NULL, $full = true, $origin_path = NULL) {
	global $oreon;
	$elems = array();
	$images = array();
	if ($full)
		$images = array(NULL=>NULL);

	$is_not_an_image = array(".","..","README","readme","LICENCE","licence");
	$is_a_valid_image = array(
		0 => array('png'),
		1 => array('gif', 'png', 'jpg'),
		2 => array('gif', 'png', 'jpg', 'gd2')
	);

	if ( ! $rep )
		if ($oreon->optGen["nagios_path_img"] && is_dir($oreon->optGen["nagios_path_img"]))
			$rep=$oreon->optGen["nagios_path_img"];
		else
			return ($images);
	$rep .= "/"; // XXX not clean

	if ( ! $origin_path)
		$origin_path = $rep;
	$path_len = strlen($origin_path);

	if (! ($dh = @opendir($rep)) ) {
		// error_log("WARNING: can't open directory '".$rep."'",0);
		return ($images);
	}

	while (false !== ($filename = readdir($dh))) {
		if ( $filename == "." || $filename == "..")
			continue;

		# WARNING: recursive call
		if (is_dir($rep.$filename)) {
			$tmp_images = return_image_list($mode, $rep.$filename, $full, $origin_path);
			$images = array_merge($images,$tmp_images);
			continue;
		}

		if (in_array($filename, $is_not_an_image))
			continue;

		$pinfo = pathinfo($filename);
		if (isset($pinfo["extension"]) && isset($is_a_valid_image[$mode][$pinfo["extension"]]))
			continue;

		$key = substr($rep.$filename, $path_len);
		$images[$key] = $key;
	}

	closedir($dh);
	ksort($images);
	return ($images);
}	

function myDecodeReport($arg)	{
	$arg = html_entity_decode($arg, ENT_QUOTES);
	return($arg);
}


function getActiveReports($period = NULL) {
	global $pearDB; 
	
	$period_filter = "";
	if (isset($period))
		$period_filter = "AND period = '".  $period ."'";
	
	$reports = array();
	$DBRESULT =& $pearDB->query("SELECT report_id, name FROM pdfreports_reports WHERE activate = '1' $period_filter");
	while ($notifCg =& $DBRESULT->fetchRow())
		$reports[$notifCg["report_id"]] = $notifCg["name"];
	$DBRESULT->free();	
	
	return $reports;
	
	
}

function getReportInfo($report_id = NULL) {
	if (!$report_id ) return;	
	global $pearDB; 
	
	$DBRESULT =& $pearDB->query("SELECT * FROM pdfreports_reports WHERE report_id = '".$report_id."' LIMIT 1");
	# Set base value
	$report_info = array_map("myDecodeReport", $DBRESULT->fetchRow());
	$DBRESULT->free();	
	//	print_r($report_info);
	return $report_info;
	
	
}


function getReportContactEmail($report_id = NULL) {
	if (!$report_id ) return;	
	global $pearDB; 
	
	$DBRESULT =& $pearDB->query("SELECT contact_contact_id FROM pdfreports_reports_contactgroup_relation rrcr, contactgroup_contact_relation ccr WHERE reports_rp_id = '".$report_id."' AND rrcr.contactgroup_cg_id = ccr.contactgroup_cg_id");
	for ($i = 0; $Cg =& $DBRESULT->fetchRow(); $i++)
		$contacts[$i] = $Cg["contact_contact_id"];
	$DBRESULT->free();


	$DBRESULT =& $pearDB->query("SELECT contact_c_id FROM pdfreports_reports_contact_relation  WHERE reports_rp_id = '".$report_id."'");
	for ($j = $i; $C =& $DBRESULT->fetchRow(); $j++)
		$contacts[$j] = $C["contact_c_id"];	
	

	$contacts_email = array();
	foreach ( $contacts as $key => $contact_id ) {
		$contacts_email[$key] = getContactEmail($contact_id);
		}
	
	
	//print_r($contacts_email);
	return $contacts_email;
	
	
}


function getContactEmail($contact_id = NULL) {
	if (!$contact_id ) return;	
	global $pearDB; 
	
	$DBRESULT =& $pearDB->query("SELECT contact_email FROM contact WHERE contact_id = '".$contact_id."' LIMIT 1");
	# Set base value
	$email =  $DBRESULT->fetchRow();
	$DBRESULT->free();	
	return $email['contact_email'];	
}


function getHostReport($report_id) {
	if (!$report_id ) return;	
	global $pearDB;
	$hosts = array();
	/*
	 * Grab hostgroup || host
	 */
	$DBRESULT =& $pearDB->query("SELECT * FROM pdfreports_host_report_relation hrr WHERE hrr.reports_rp_id = '".$report_id."'");
	while ($parent =& $DBRESULT->fetchRow())	{
		if ($parent["host_host_id"])
			$hosts["report_hs"][$parent["host_host_id"]] = $parent["host_host_id"];
		else if ($parent["hostgroup_hg_id"])
			$hosts["report_hgs"][$parent["hostgroup_hg_id"]] = $parent["hostgroup_hg_id"];
	}	
	return $hosts;
	
}

function getServiceGroupReport($report_id) {
	if (!$report_id ) return;	
	global $pearDB;
	$services = array();
	/*
	 * Grab servicegroup
	 */
	$DBRESULT =& $pearDB->query("SELECT * FROM pdfreports_reports_servicegroup_relation WHERE reports_rp_id = '".$report_id."'");
	for ($i = 0; $notifSg =& $DBRESULT->fetchRow(); $i++)
		$services["report_sg"][$i] = $notifSg["servicegroup_sg_id"];
	$DBRESULT->free();
	
	return $services;
	
}
///////////////////////////////////////////////////////////////////////
function getSqlForHostgrpServices($hostgrp_id){
  // $hostgrp_id identifies the hostgroup of the hosts to include. 
  $sql = "
-- Services assigned via hostgroup memberships (hg2):
  SELECT host.host_name, host.host_id,
    service.service_description, service.service_id, 
    hg.hg_name AS Parent, hg2.hg_name
  FROM  hostgroup AS hg
    JOIN hostgroup_relation AS hgr ON  hg.hg_id = hgr.hostgroup_hg_id
    JOIN host ON hgr.host_host_id = host.host_id
    JOIN hostgroup_relation AS hgr2 ON host.host_id = hgr2.host_host_id
    JOIN hostgroup AS hg2 ON hgr2.hostgroup_hg_id = hg2.hg_id
    JOIN host_service_relation as hsr ON hsr.hostgroup_hg_id = hg2.hg_id 
    JOIN service ON service.service_id = hsr.service_service_id 
    WHERE service.service_activate='1' AND host.host_activate='1'  
    AND hg.hg_id ='" . CentreonDB::escape($hostgrp_id) . "' 

-- and we need the host assigned services:
  UNION
  SELECT host.host_name, host.host_id,
    service.service_description, service.service_id, 
    hg.hg_name AS Parent, ''
  FROM  hostgroup AS hg
    JOIN hostgroup_relation AS hgr ON  hg.hg_id = hgr.hostgroup_hg_id
    JOIN host ON hgr.host_host_id = host.host_id
    JOIN host_service_relation as hsr ON hsr.host_host_id = host.host_id 
    JOIN service ON service.service_id = hsr.service_service_id 
    WHERE service.service_activate='1' AND host.host_activate='1'
    AND hg.hg_id ='" . CentreonDB::escape($hostgrp_id) . "'"; 
  return $sql;
}

function service_category($id,$category){
  global $pearDB;
  $name = NULL;
   $query = "select s.service_id,s.service_template_model_stm_id,s.service_description, cat.sc_id
     from service as s
     LEFT JOIN service_categories_relation as cat ON cat.service_service_id = s.service_id
     WHERE s.service_id = ? and (cat.sc_id = ? or cat.sc_id IS NULL)";

   $DBRESULT = $pearDB->query($query,array($id,$category));
  while ($row = $DBRESULT->fetchRow('MDB2_FETCHMODE_ORDERED')) {
    $id = $row[0];
    $template = $row[1];
    $name = $row[2];
    $value = $row[3];
    myDebug("Service/tpl = ". $name. ", template = " . $template . ", value = " .$value); 
    if ( $value != NULL ) { # implies value = category 
	return array($name ."(" . $id . ")",$value);
# if no value check parent template
    }
    if ($template != NULL) return service_category($template,$category);
  }
  $DBRESULT->free();
  return array($name,NULL);
}

/*
 * Return a table ($serviceGroupStats) that contains availability (average with availability of all services from servicegroup)
 * and alerts (the sum of alerts of all services from servicegroup) for given servicegroup defined by $servicegroup_id
 */
function getLogInDbForHostgrpServices($hostgrp_id, $start_date, $end_date, $reportTimePeriod,$category=NULL){
  global $pearDB;
  $serviceStatsLabels = array();
  $serviceStatsLabels = getServicesStatsValueName();
  $status = array("OK", "WARNING", "CRITICAL", "UNKNOWN", "UNDETERMINED", "MAINTENANCE");
  /* Initialising hostgroup stats to 0 */
  foreach ($serviceStatsLabels as $name)
    $serviceStats["average"][$name] = 0;
  
  // 
  
  
  /* $count count the number of services in servicegroup */
  $count = 0;
  //	$services = getServiceGroupActivateServices($servicegroup_id);
  
// Get all services for hosts in hostgroup where servvice match some criteria. Category is not present in Centreon_storage. 
// In Centreon: service.host_id, service.service_id where hosts_hostgroups.hostgroup_id=HG and service->category=??? 

  $query = "
SELECT service_description, service_id, host_id, host_name
FROM (" 
  . getSqlForHostgrpServices($hostgrp_id) . "
) as hgs";
#  if ( isset($category) and $category > 0){
#    // Should inherit category. TODO!
#    $query .= "
#  JOIN service_categories_relation as cat ON cat.service_service_id = hgs.service_id
#  WHERE cat.sc_id = '". $category ."'";
#  }

  $query .= "
ORDER BY host_name, service_description;";

  //    print $query;

  $DBRESULT = $pearDB->query($query);

  while ($row = $DBRESULT->fetchRow()) {
  // TODO: category is NOT inherited from parent templates! MUST be set on service.
    // Could check 'service_template_model_stm_id' for $row['service_id'] here.
    // ... or check for match in service-name?

    // next if not inherited_category($row['host_id'], $category);
    if ( isset($category) and $category > 0){
      list($servtpl, $cat) = service_category($row['service_id'],$category);
      myDebug("Template = " . $servtpl . ", value = " . $cat); 

      if ($cat != $category) continue;
    }

    foreach ($serviceStatsLabels as $name) {
      $serviceStats[$count][$name] = 0;
    }
    $Stats = array();
    $Stats = getLogInDbForOneSVC($row['host_id'], $row['service_id'], $start_date, $end_date, $reportTimePeriod);
    
    if (isset($Stats)) {
      $serviceStats[$count] = $Stats;
      $serviceStats[$count]["HOST_ID"] = $row['host_id'];
      $serviceStats[$count]["SERVICE_ID"] = $row['service_id'];
      $serviceStats[$count]["HOST_NAME"] = $row['host_name'];
      $serviceStats[$count]["SERVICE_DESC"] = $row['service_description'];
      foreach ($serviceStatsLabels as $name){
	$serviceStats["average"][$name] += $Stats[$name];
	//	$svStats[$row['service_id']][$name] += $Stats[$name];
	//$hostStats[$row['host_id']][$name] += $Stats[$name];
      }
      //$svStats[$row['service_id']]["SERVICE_DESC"] =  $row['service_description'];
      // $svStats[$row['service_id']]["COUNT"] ++;
      //$hostStats[$row['host_id']]["HOST_NAME"] = $row['host_name'];
      //$hostStats[$row['host_id']]["COUNT"] ++;
    }
    $count++;
  }
  $DBRESULT->free();

 
  /*
   * Average time for all status (OK, Critical, Warning, Unknown)
   */
  foreach ($serviceStatsLabels as $name) {
    if ($name == "OK_T" || $name == "WARNING_T" || $name == "CRITICAL_T"
	|| $name == "UNKNOWN_T" || $name == "UNDETERMINED_T" || $name == "MAINTENANCE_T")
      if ($count)
	$serviceStats["average"][$name] /= $count;
      else
	$serviceStats["average"][$name] = 0;

  }
  
  /*
   * Calculate percentage of time (_TP => Total time percentage) for each status
   */
  $serviceStats["average"]["TOTAL_TIME"] = $serviceStats["average"]["OK_T"] +  $serviceStats["average"]["WARNING_T"]
    +  $serviceStats["average"]["CRITICAL_T"] +  $serviceStats["average"]["UNKNOWN_T"]
    +  $serviceStats["average"]["UNDETERMINED_T"] + $serviceStats["average"]["MAINTENANCE_T"];
  
  $time = $serviceStats["average"]["TOTAL_TIME"];
  foreach ($status as $key => $value) {
    if ($time)
      $serviceStats["average"][$value."_TP"] = round($serviceStats["average"][$value."_T"] / $time * 100, 2);
    else
      $serviceStats["average"][$value."_TP"] = 0;
  }
  
  /*
   * Calculate percentage of time (_MP => Mean Time percentage) for each status ignoring undetermined time
   */
  $serviceStats["average"]["MEAN_TIME"] = $serviceStats["average"]["OK_T"] +  $serviceStats["average"]["WARNING_T"]
    + $serviceStats["average"]["CRITICAL_T"]+ $serviceStats["average"]["UNKNOWN_T"];
  
  /*
   * Calculate total of alerts
   */
  $serviceStats["average"]["TOTAL_ALERTS"] = $serviceStats["average"]["OK_A"] +  $serviceStats["average"]["WARNING_A"]
    + $serviceStats["average"]["CRITICAL_A"]+ $serviceStats["average"]["UNKNOWN_A"];
  $time = $serviceStats["average"]["MEAN_TIME"];
  if ($time <= 0) {
    foreach ($status as $key => $value)
      if ($value != "UNDETERMINED" && $value != "MAINTENANCE")
	$serviceStats["average"][$value."_MP"] = 0;
  } else {
    foreach ($status as $key => $value)
      if ($value != "UNDETERMINED" && $value != "MAINTENANCE")
	$serviceStats["average"][$value."_MP"] = round($serviceStats["average"][$value."_T"] / $time * 100, 2);
  }

  return $serviceStats;
}

///////////////////////////////

function getHGDayStat($id, $start_date, $end_date) {
  global $pearDB;
  global $pearDBO;
  global $centreon_version;
  if ( $centreon_version >= 280) { 
    global $centreon;
  } else {
    global $oreon;
  }

  $i = 0;
  
  /*
   * getting all hosts from hostgroup
   */
  
  $hosts_id = $oreon->user->access->getHostHostGroupAclConf($id, $oreon->broker->getBroker());
  if (count($hosts_id) == 0) {
    return 'No hosts in group';
  }
  $str = "";
  foreach ($hosts_id as $hostId => $host_name) {
    //  $host_stats = getLogInDbForHost($hostId, $start_date, $end_date, $reportTimePeriod);
    if ($str != "") {
      $str .= ", ";
    }
    $str .= "'". $hostId ."'";
    $i++;
<<<<<<< HEAD
}
if ($str == "") {
    $str = "''";
}
unset($hg);
unset($DBRESULT);
*/
$hosts_id = $centreon->user->access->getHostHostGroupAclConf($id, 'broker');
if (count($hosts_id) == 0) {
  return 'No hosts in group';
}
$str = "";
foreach ($hosts_id as $hostId => $host_name) {
  //  $host_stats = getLogInDbForHost($hostId, $start_date, $end_date, $reportTimePeriod);
  if ($str != "") {
    $str .= ", ";
=======
>>>>>>> 2.7
  }

  myDebug("Hosts in group: " . $str);

  //echo "Number of hosts in group = $i";
  //echo "Hostlist: $str";
  //echo "Start date $start_date";
  /*
   * Getting hostgroup stats evolution
   */
  #### TODO: $days_of_week = getReportDaysStr($reportTimePeriod);
# To be compatible with Centreon getLogInDbForHost()

  $rq = "SELECT `date_start`, `date_end`, sum(`UPnbEvent`) as UP_A, sum(`DOWNnbEvent`) as DOWN_A, "
    . "sum(`UNREACHABLEnbEvent`) as UNREACHABLE_A, "
    . "avg( `UPTimeScheduled` ) as UP_T, "
    . "avg( `DOWNTimeScheduled` ) as DOWN_T, "
    . "avg( `UNREACHABLETimeScheduled` ) as UNREACHABLE_T, "
    . "avg(`UNDETERMINEDTimeScheduled`) as UNDETERMINED_T, "
    . "avg(`MaintenanceTime`) as MAINTENANCE_T "
    . "FROM `log_archive_host` WHERE `host_id` IN (".$str.") "
    . "AND `date_start` >= '".$start_date."' "
    . "AND `date_end` <= '".$end_date."' "
    . "GROUP BY `date_end`, `date_start` ORDER BY `date_start` desc";

  ###    . "AND DATE_FORMAT( FROM_UNIXTIME( `date_start`), '%W') IN (".$days_of_week.") ".

  // echo "rq = $rq"; 

  myDebug("Query: " . $rq);

  $DBRESULT = $pearDBO->query($rq);

  $tbl = <<<EOD
<style>
table, td  {
  border-collapse: collapse;
  font-size:9;
  border-spacing:10px;
}

td {
  padding:1
  text-align: left;
}

th {  
  padding: 1;
  text-align: left;
  font-size: 15px;
  font-weight: bold;
}

tr.even {background-color: #EDF4FF;}
tr.odd {background-color: #F7FAFF;}

tr.day {
  border-top: 3px solid black;
  background-color:#D7D6DD;
}

td#green {
  background-image: linear-gradient(to right, rgba(0, 150, 0, 1) 0%, rgba(0, 175, 0, 1) 17%, rgba(0, 190, 0, 1) 33%, rgba(82, 210, 82, 1) 67%, rgba(131, 230, 131, 1) 83%, rgba(180, 221, 180, 1) 100%);  /* your gradient */
  background-color: #00ff00;
  background-repeat: no-repeat;  /* don't remove */
}
td#red {
  background-image: linear-gradient(to right, rgba(255, 0, 0, 0.1) 0%, rgba(255, 255,0, 1) );  /* your gradient */
  background-color: #ff0000;
  background-repeat: no-repeat;  /* don't remove */
}  
td#up {
  background-image: linear-gradient(to right, rgba(255, 0, 0, 0.1) 0%, rgba(255, 255,0, 1) );  /* your gradient */
/*  background-color: #ff0000; */
  background-repeat: no-repeat;  /* don't remove */
}  

tr#red {
  background-image: linear-gradient(to right, rgba(255, 0, 0, 0.1) 0%, rgba(255, 0, 255, 1) );  /* your gradient */
  background-repeat: no-repeat;  /* don't remove */
}  

</style>

EOD;



  $tbl .= "<table border=cellspacing=\"0\" cellpadding=\"1\" border=\"0\">\n".
    "<tr> "  
    ."<th > " . _("Day"). "<br>". _("Duration") ."</th>"
    ."<th width=\"20%\"> " . _("State")."</th>"
    ."<th width=\"100\"> " . _("Graph")."</th>"
    //  ."<th> " . _("Duration") . "</th>"
    ."<th> " . _("Total")."</th>"
    ."<th>" . _("Total")."%  </th>"
    ."<th> " . _("Mean")."% </th>"
    ."<th>  " . _("Alerts")." </th>"
    ."</tr>\n";

  //$img ='../../../../img';
  $img ='file:///usr/share/centreon/www/modules/pdfreports/img';

  while ($row = $DBRESULT->fetchRow()) {
    myDebug("Processing Date: " . date("Y-m-d", $row["date_start"]));
		  
    $duration = $row["UP_T"] + $row["DOWN_T"] + $row["UNREACHABLE_T"];
    $totaltime = $duration + $row["UNDETERMINED_T"] + $row["MAINTENANCE_T"];

    //    echo "Duration = $duration";
    //    echo "Totaltime  = $totaltime";

    /* Percentage by status */
    $row["UP_MP"] = round($row["UP_T"] * 100 / $duration, 2);
    $row["DOWN_MP"] = round($row["DOWN_T"] * 100 / $duration, 2);
    $row["UNREACHABLE_MP"] = round($row["UNREACHABLE_T"] * 100 / $duration, 2);

    $row["UP_TP"] = round($row["UP_T"] * 100 / $totaltime, 2);
    $row["DOWN_TP"] = round($row["DOWN_T"] * 100 / $totaltime, 2);
    $row["UNREACHABLE_TP"] = round($row["UNREACHABLE_T"] * 100 / $totaltime, 2);
    $row["MAINTENANCE_TP"] = round($row["MAINTENANCE_T"] * 100 / $totaltime, 2);
    $row["UNDETERMINED_TP"] = round($row["UNDETERMINED_T"] * 100 / $totaltime, 2);

    $tbl .= 
     "<tr class=\"day\"> "
      . "<td rowspan=\"5\" >" . date("Y-m-d", $row["date_start"])
      ."  <br>" . $duration."s;". "</td>\n"
      //      ."  <td  id=\"green\" style='background-size: 90% 100%' > UP
      ."  <td> UP </td><td>" . '<img src="'.$img.'/1x1-19ee11ff.png" width="'.round($row["UP_TP"]+0.001,3).'" height="10"></td><td>'
      .  round($row["UP_T"],0)."</td><td>" .  $row["UP_TP"]. "%</td><td>" .  $row["UP_MP"]. "%</td><td>" .$row["UP_A"]."</td></tr>\n"
      ."  <tr class=\"even\"><td>DOWN </td><td>". '<img src="'.$img.'/1x1-f91e05ff.png" width="'.round($row["DOWN_TP"]+0.001,3).'" height="10"></td><td>' 
      .  round($row["DOWN_T"],0)."</td><td>" .  $row["DOWN_TP"]. "%</td><td>" .  $row["DOWN_MP"]. "%</td><td>" .$row["DOWN_A"]."</td></tr>\n"
      ."  <tr class=\"odd\"><td>UNREACHABLE </td><td>" . '<img src="'.$img.'/1x1-82cfd8ff.png" width="'.round($row["UNREACHABLE_TP"]+0.001,3).'" height="10"></td><td>' 
      .  round($row["UNREACHABLE_T"],0)."</td><td>" .  $row["UNREACHABLE_TP"]. "%</td><td>" .  $row["UNREACHABLE_MP"]. "%</td><td>" .$row["UNREACHABLE_A"]."</td></tr>\n"
      ."  <tr class=\"even\"><td>MAINTENANCE </td><td>". '<img src="'.$img.'/1x1-cc99ffff.png" width="'.round($row["MAINTENANCE_TP"]+0.001,3).'" height="10"></td><td>' 
      .  round($row["MAINTENANCE_T"],0)."</td><td>" .  $row["MAINTENANCE_TP"]. "%</td><td> " . "</td><td>" ."</td></tr>\n"
      ."  <tr class=\"odd\"><td>UNDETERMINED </td><td>" . '<img src="'.$img.'/1x1-ccf8ffff.png" width="'.round($row["UNDETERMINED_TP"]+0.001,3).'" height="10"></td><td>' 
      .  round($row["UNDETERMINED_T"],0)."</td><td>" .  $row["UNDETERMINED_TP"]. "%</td><td>" . "</td><td>" ."</td></tr>\n"
     ."\n";
  }
  $tbl .= "</table>\n";
  $DBRESULT->free();
  return $tbl;
}

function testReportExistence ($name = NULL)	{
  global $pearDB;
  global $form;
  $id = NULL;
  if (isset($form))
    $id = $form->getSubmitValue('report_id');
  $DBRESULT =& $pearDB->query("SELECT name, report_id FROM pdfreports_reports WHERE name = '".htmlentities($name, ENT_QUOTES)."'");
  $report =& $DBRESULT->fetchRow();
#Modif case
  if ($DBRESULT->numRows() >= 1 && $report["report_id"] == $id)	
    return true;
#Duplicate entry
  else if ($DBRESULT->numRows() >= 1 && $report["report_id"] != $id)	
    return false;
  else
    return true;
}


function multipleReportInDB ($reports = array(), $nbrDup = array(), $host = NULL, $descKey = 1, $hostgroup = NULL, $hPars = array(), $hgPars = array())	{
  global $pearDB, $oreon;

  /*
   * $descKey param is a flag.
   * 	If 1, we know we have to rename description because it's a traditionnal duplication.
   * 	If 0, we don't have to, beacause we duplicate services for an Host duplication
   *	Foreach Service
   */
  $maxId["MAX(report_id)"] = NULL;
  foreach ($reports as $key=>$value)	{
    /*
     *  Get all information about it
     */
    $DBRESULT =& $pearDB->query("SELECT * FROM pdfreports_reports WHERE report_id = '".$key."' LIMIT 1");
    $row = $DBRESULT->fetchRow();
    $row["report_id"] = '';
    /*
     * Loop on the number of Service we want to duplicate
     */
    for ($i = 1; $i <= $nbrDup[$key]; $i++)	{
      $val = NULL;
      /*
       * Create a sentence which contains all the value
       */
      foreach ($row as $key2=>$value2)	{
	if ($key2 == "name" && $descKey) {
	  $name = $value2 = $value2."_".$i;
	}
	else if ($key2 == "name")
	  $report_description = NULL;
	$val ? $val .= ($value2!=NULL?(", '".$value2."'"):", NULL") : $val .= ($value2!=NULL?("'".$value2."'"):"NULL");
	if ($key2 != "report_id")
	  $fields[$key2] = $value2;
	/*if (isset($service_description))
	  $fields["report_description"] = $report_description;*/
      }
      if (!count($hPars))
	$hPars = getMyServiceHosts($key); // todo
      if (!count($hgPars))
	$hgPars = getMyServiceHostGroups($key);  // todo
      if ( testReportExistence($name)) 	{
	$hPars = array();
	$hgPars = array();
	(isset($val) && $val != "NULL" && $val) ? $rq = "INSERT INTO pdfreports_reports VALUES (".$val.")" : $rq = NULL;
	if (isset($rq)) {
	  $DBRESULT =& $pearDB->query($rq);
	  $DBRESULT =& $pearDB->query("SELECT MAX(report_id) FROM pdfreports_reports");
	  $maxId =& $DBRESULT->fetchRow();
	  if (isset($maxId["MAX(report_id)"]))	{
	    /*
	     * Host duplication case -> Duplicate the Service for the Host we create
	     */
	    if ($host)
	      $pearDB->query("INSERT INTO pdfreports_host_report_relation VALUES ('', NULL, '".$host."',  '".$maxId["MAX(report_id)"]."')");
	    else if ($hostgroup)
	      $pearDB->query("INSERT INTO pdfreports_host_report_relation VALUES ('', '".$hostgroup."', NULL, '".$maxId["MAX(report_id)"]."')");
	    else	{
# Service duplication case -> Duplicate the Service for each relation the base Service have
	      $DBRESULT =& $pearDB->query("SELECT DISTINCT host_host_id, hostgroup_hg_id FROM pdfreports_host_report_relation WHERE reports_rp_id = '".$key."'");
	      //$fields["service_hPars"] = "";
	      //$fields["service_hgPars"] = "";
	      while($report =& $DBRESULT->fetchRow())	{
		if ($report["host_host_id"]) {
		  $DBRESULT2 =& $pearDB->query("INSERT INTO pdfreports_host_report_relation VALUES ('', NULL, '".$report["host_host_id"]."',  '".$maxId["MAX(report_id)"]."')");
		  //$fields["service_hPars"] .= $service["host_host_id"] . ",";
		}
		else if ($report["hostgroup_hg_id"]) {
		  $DBRESULT2 =& $pearDB->query("INSERT INTO pdfreports_host_report_relation VALUES ('', '".$report["hostgroup_hg_id"]."', NULL, '".$maxId["MAX(report_id)"]."')");
		  //$fields["service_hgPars"] .= $service["hostgroup_hg_id"] . ",";
		}
	      }
	      //$fields["service_hPars"] = trim($fields["service_hPars"], ",");
	      //$fields["service_hgPars"] = trim($fields["service_hgPars"], ",");
	    }

	    /*
	     * ServiceGroup duplication
	     */
	    $DBRESULT =& $pearDB->query("SELECT DISTINCT servicegroup_sg_id FROM pdfreports_reports_servicegroup_relation WHERE reports_rp_id = '".$key."'");
	    //$fields["service_cgs"] = "";
	    while($Sg =& $DBRESULT->fetchRow()){
	      $DBRESULT2 =& $pearDB->query("INSERT INTO pdfreports_reports_servicegroup_relation VALUES ('', '".$maxId["MAX(report_id)"]."', '".$Sg["servicegroup_sg_id"]."')");
	      //$fields["service_cgs"] .= $Cg["contactgroup_cg_id"] . ",";
	    }
	    //$fields["service_cgs"] = trim($fields["service_cgs"], ",");


	    /*
	     * Contact duplication
	     */
	    $DBRESULT =& $pearDB->query("SELECT DISTINCT contact_c_id FROM pdfreports_reports_contact_relation WHERE reports_rp_id = '".$key."'");
	    //$fields["service_cs"] = "";
	    while ($C =& $DBRESULT->fetchRow()){
	      $DBRESULT2 =& $pearDB->query("INSERT INTO pdfreports_reports_contact_relation VALUES ('', '".$maxId["MAX(report_id)"]."', '".$C["contact_c_id"]."')");
	      //$fields["service_cs"] .= $C["contact_c_id"] . ",";
	    }
	    //$fields["service_cs"] = trim($fields["service_cs"], ",");

	    /*
	     * ContactGroup duplication
	     */
	    $DBRESULT =& $pearDB->query("SELECT DISTINCT contactgroup_cg_id FROM pdfreports_reports_contactgroup_relation WHERE reports_rp_id = '".$key."'");
	    //$fields["service_cgs"] = "";
	    while($Cg =& $DBRESULT->fetchRow()){
	      $DBRESULT2 =& $pearDB->query("INSERT INTO pdfreports_reports_contactgroup_relation VALUES ('', '".$maxId["MAX(report_id)"]."', '".$Cg["contactgroup_cg_id"]."')");
	      //$fields["service_cgs"] .= $Cg["contactgroup_cg_id"] . ",";
	    }
	    //$fields["service_cgs"] = trim($fields["service_cgs"], ",");



	    /*
	     *  get svc desc
	     */
	    $query = "SELECT report_description FROM pdfreports_reports WHERE report_id = '".$maxId["MAX(report_id)"]."' LIMIT 1";
	    $DBRES =& $pearDB->query($query);
	    if ($DBRES->numRows()) {
	      $row2 =& $DBRES->fetchRow();
	      $description = $row2['report_description'];
	      $description = str_replace("#S#", "/", $description);
	      $description = str_replace("#BS#", "\\", $description);
	      //$oreon->CentreonLogAction->insertLog("service", $maxId["MAX(service_id)"], getHostServiceCombo($maxId["MAX(service_id)"], $description), "a", $fields);
	    }
	  }
	}
      }
    }
  }
  return ($maxId["MAX(report_id)"]);
}




function enableReportInDB ($report_id = null, $report_arr = array())	{
  if (!$report_id && !count($report_arr)) return;
  global $pearDB, $oreon;
  if ($report_id)
    $report_arr = array($report_id=>"1");
  foreach($report_arr as $key=>$value)	{
    $DBRESULT =& $pearDB->query("UPDATE pdfreports_reports SET activate = '1' WHERE report_id = '".$key."'");
    $DBRESULT2 =& $pearDB->query("SELECT report_description FROM `pdfreports_reports` WHERE report_id = '".$key."' LIMIT 1");
    $row = $DBRESULT2->fetchRow();
  }
}

function disableReportInDB ($report_id = null, $report_arr = array())	{
  if (!$report_id && !count($report_arr)) return;
  global $pearDB, $oreon;
  if ($report_id)
    $report_arr = array($report_id=>"1");
  foreach($report_arr as $key=>$value)	{
    $DBRESULT =& $pearDB->query("UPDATE pdfreports_reports SET activate = '0' WHERE report_id = '".$key."'");

    $DBRESULT2 =& $pearDB->query("SELECT report_description FROM `pdfreports_reports` WHERE report_id = '".$key."' LIMIT 1");
    $row = $DBRESULT2->fetchRow();
    //	$oreon->CentreonLogAction->insertLog("service", $key, getHostServiceCombo($key, $row['service_description']), "disable");
  }
}

function getMyHostGroupField($hg_id = NULL, $field) 
{
  if (!$hg_id)
    return;
  global $pearDB;
    
  $DBRESULT = $pearDB->query("SELECT $field FROM hostgroup WHERE hg_id = '" . CentreonDB::escape($hg_id) . "' LIMIT 1");
  $row = $DBRESULT->fetchRow();
  if ($row[$field])
    return html_entity_decode($row[$field], ENT_QUOTES, "UTF-8");
  return NULL;
}

function getMyServiceGroupField($sg_id = NULL, $field) {
  if (!$sg_id)
    return;
  global $pearDB;

  $DBRESULT = $pearDB->query("SELECT $field FROM servicegroup WHERE sg_id = '" . CentreonDB::escape($sg_id) . "'");
  $row = $DBRESULT->fetchRow();
  return $row[$field];
}

function getMyCategorieField($sc_id = NULL, $field) {
  if (!$sc_id)
    return;
  global $pearDB;

  $DBRESULT = $pearDB->query("SELECT $field FROM service_categories WHERE sc_id = '" . CentreonDB::escape($sc_id) . "'");
  $row = $DBRESULT->fetchRow();
  return $row[$field];
}
	function deleteReportInDB ($reports = array())	{
		global $pearDB, $oreon;

		foreach ($reports as $key => $value)	{

		/*	$DBRESULT =& $pearDB->query("SELECT id FROM service WHERE service_template_model_stm_id = '".$key."'");
			while ($row =& $DBRESULT->fetchRow())	{
				$DBRESULT2 =& $pearDB->query("UPDATE service SET service_template_model_stm_id = NULL WHERE service_id = '".$row["service_id"]."'");
			}*/

			$DBRESULT3 =& $pearDB->query("SELECT report_description FROM `pdfreports_reports` WHERE `report_id` = '".$key."' LIMIT 1");
			$svcname = $DBRESULT3->fetchRow();
			//$oreon->CentreonLogAction->insertLog("service", $key, getHostServiceCombo($key, $svcname['service_description']), "d");
			$DBRESULT =& $pearDB->query("DELETE FROM pdfreports_reports WHERE report_id = '".$key."'");

			if ($oreon->user->get_version() >= 3) {
				//$DBRESULT =& $pearDB->query("DELETE FROM on_demand_macro_service WHERE svc_svc_id = '".$key."'");
				$DBRESULT =& $pearDB->query("DELETE FROM pdfreports_host_report_relation WHERE reports_rp_id = '".$key."'");
				$DBRESULT =& $pearDB->query("DELETE FROM pdfreports_reports_contact_relation WHERE reports_rp_id = '".$key."'");
				$DBRESULT =& $pearDB->query("DELETE FROM pdfreports_reports_contactgroup_relation WHERE reports_rp_id = '".$key."'");								
			}
		}
	}

	function updateReportInDB ($report_id = NULL, $from_MC = false)	{
		if (!$report_id) return;
		global $form;
		$ret = $form->getSubmitValues();
		if ($from_MC)
			updateReport_MC($report_id);
		else
			updateReport($report_id, $from_MC);
		# Function for updating cg
		# 1 - MC with deletion of existing cg
		# 2 - MC with addition of new cg
		# 3 - Normal update
		if (isset($ret["mc_mod_cgs"]["mc_mod_cgs"]) && $ret["mc_mod_cgs"]["mc_mod_cgs"]) {
			updateReportContactGroup($report_id);
			updateReportContact($report_id);
		}
		else if (isset($ret["mc_mod_cgs"]["mc_mod_cgs"]) && !$ret["mc_mod_cgs"]["mc_mod_cgs"]) {
			updateReportContactGroup_MC($report_id);
			updateReportContact_MC($report_id);
		}
		else {
			updateReportContactGroup($report_id);
			updateReportContact($report_id);
		}

		# Function for updating host/hg
		# 1 - MC with deletion of existing host/hg
		# 2 - MC with addition of new host/hg
		# 3 - Normal update
		if (isset($ret["mc_mod_Pars"]["mc_mod_Pars"]) && $ret["mc_mod_Pars"]["mc_mod_Pars"])
			updateReportHost($report_id);
		else if (isset($ret["mc_mod_Pars"]["mc_mod_Pars"]) && !$ret["mc_mod_Pars"]["mc_mod_Pars"])
			updateReportHost_MC($report_id);
		else
			updateReportHost($report_id);
			
			
		# Function for updating sg
		# 1 - MC with deletion of existing host/hg parent
		# 2 - MC with addition of new host/hg parent
		# 3 - Normal update
		if (isset($ret["mc_mod_sg"]["mc_mod_sg"]) && $ret["mc_mod_sg"]["mc_mod_sg"])
			updateReportServiceGroup($report_id);
		else if (isset($ret["mc_mod_sg"]["mc_mod_sg"]) && !$ret["mc_mod_sg"]["mc_mod_sg"])
			updateReportServiceGroup_MC($report_id);
		else
			updateReportServiceGroup($report_id);			


	}

	function insertReportInDB ($ret = array())	{
		global $oreon;

		$tmp_fields = array();
		$tmp_fields = insertReport($ret);
		$report_id = $tmp_fields['report_id'];
		updateReportContactGroup($report_id, $ret);
		updateReportContact($report_id, $ret);
		updateReportHost($report_id, $ret);	
		updateReportServiceGroup($report_id, $ret);
		$oreon->user->access->updateACL();
		$fields = $tmp_fields['fields'];
		//$oreon->CentreonLogAction->insertLog("service", $service_id, getHostServiceCombo($service_id, htmlentities($fields["service_description"], ENT_QUOTES)), "a", $fields);
		return ($report_id);
	}

	function insertReport($ret = array())	{
		global $form, $pearDB, $oreon;

		if (!count($ret))
			$ret = $form->getSubmitValues();
		/*if (isset($ret["command_command_id_arg"]) && $ret["command_command_id_arg"] != NULL)		{
			$ret["command_command_id_arg"] = str_replace("\n", "#BR#", $ret["command_command_id_arg"]);
			$ret["command_command_id_arg"] = str_replace("\t", "#T#", $ret["command_command_id_arg"]);
			$ret["command_command_id_arg"] = str_replace("\r", "#R#", $ret["command_command_id_arg"]);
			$ret["command_command_id_arg"] = str_replace('/', "#S#", $ret["command_command_id_arg"]);
			$ret["command_command_id_arg"] = str_replace('\\', "#BS#", $ret["command_command_id_arg"]);
		}*/
		/*if (isset($ret["command_command_id_arg2"]) && $ret["command_command_id_arg2"] != NULL)		{
			$ret["command_command_id_arg2"] = str_replace("\n", "#BR#", $ret["command_command_id_arg2"]);
			$ret["command_command_id_arg2"] = str_replace("\t", "#T#", $ret["command_command_id_arg2"]);
			$ret["command_command_id_arg2"] = str_replace("\r", "#R#", $ret["command_command_id_arg2"]);
			$ret["command_command_id_arg2"] = str_replace('/', "#S#", $ret["command_command_id_arg2"]);
			$ret["command_command_id_arg2"] = str_replace('\\', "#BS#", $ret["command_command_id_arg2"]);
		}*/
		if (isset($ret["report_description"]) && $ret["report_description"] != NULL)		{
			$ret["report_descriptionv"] = str_replace('/', "#S#", $ret["report_description"]);
			$ret["report_description"] = str_replace('\\', "#BS#", $ret["report_description"]);
		}
		/*if (isset($ret["service_alias"]) && $ret["service_alias"] != NULL)		{
			$ret["service_alias"] = str_replace('/', "#S#", $ret["service_alias"]);
			$ret["service_alias"] = str_replace('\\', "#BS#", $ret["service_alias"]);
		}*/
		$rq = "INSERT INTO pdfreports_reports " .
				"(name, report_description, period, report_title, subject, mail_body, retention,
                                    service_category, report_template, report_comment, activate,
                                    bcsv, btimeline, bsummary_only, bdebug  ) " .
				"VALUES ( ";
				isset($ret["name"]) && $ret["name"] != NULL ? $rq .= "'".$ret["name"]."', ": $rq .= "NULL, ";
				isset($ret["report_description"]) && $ret["report_description"] != NULL ? $rq .= "'".addslashes(htmlentities($ret["report_description"], ENT_QUOTES))."', ": $rq .= "NULL, ";
				isset($ret["period"]) && $ret["period"] != NULL ? $rq .= "'".$ret["period"]."', ": $rq .= "NULL, ";
				isset($ret["report_title"]) && $ret["report_title"] != NULL ? $rq .= "'".$ret["report_title"]."', ": $rq .= "NULL, ";				
				isset($ret["subject"]) && $ret["subject"] != NULL ? $rq .= "'".addslashes(htmlentities($ret["subject"], ENT_QUOTES))."', ": $rq .= "NULL, ";
				isset($ret["mail_body"]) && $ret["mail_body"] != NULL ? $rq .= "'".addslashes(htmlentities($ret["mail_body"], ENT_QUOTES))."', ": $rq .= "NULL, ";
				isset($ret["retention"]) && $ret["retention"] != NULL ? $rq .= "'".addslashes(htmlentities($ret["retention"], ENT_QUOTES))."', ": $rq .= "NULL, ";
				isset($ret["service_category"]) && $ret["service_category"] != NULL ? $rq .= "'".addslashes(htmlentities($ret["service_category"], ENT_QUOTES))."', ": $rq .= "NULL, ";
				isset($ret["report_template"]) && $ret["report_template"] != NULL ? $rq .= "'".addslashes(htmlentities($ret["report_template"], ENT_QUOTES))."', ": $rq .= "NULL, ";


				if (isset($ret["report_comment"]) && $ret["report_comment"])	{
					$ret["report_comment"] = str_replace('/', "#S#", $ret["report_comment"]);
					$ret["report_comment"] = str_replace('\\', "#BS#", $ret["report_comment"]);
				}
				isset($ret["report_comment"]) && $ret["report_comment"] != NULL ? $rq .= "'".htmlentities($ret["report_comment"], ENT_QUOTES)."', " : $rq .= "NULL, ";

				isset($ret["activate"]["activate"]) && $ret["activate"]["activate"] != NULL ? $rq .= "'".$ret["activate"]["activate"]."', " : $rq .= "NULL, ";


				isset($ret["reportOpts"]["bcsv"]) ? $rq .= "'".$ret["reportOpts"]["bcsv"]."', " : $rq .= "NULL, ";
				isset($ret["reportOpts"]["btimeline"]) ? $rq .= "'".$ret["reportOpts"]["btimeline"]."', " : $rq .= "NULL, ";
				isset($ret["reportOpts"]["bsummary_only"]) ? $rq .= "'".$ret["reportOpts"]["bsummary_only"]."', " : $rq .= "NULL, ";
				isset($ret["reportOpts"]["bdebug"]) ? $rq .= "'".$ret["reportOpts"]["bdebug"]."' " : $rq .= "NULL ";

				$rq .= ")";
		$DBRESULT =& $pearDB->query($rq);
		$DBRESULT =& $pearDB->query("SELECT MAX(report_id) FROM pdfreports_reports");
		$report_id = $DBRESULT->fetchRow();


		$fields["name"] = $ret["name"];
		$fields["report_description"] = htmlentities($ret["report_description"], ENT_QUOTES);
		$fields["period"] = $ret["period"];
		$fields["report_title"] = $ret["report_title"];
		$fields["subject"] = htmlentities($ret["subject"], ENT_QUOTES);	
		$fields["mail_body"] = htmlentities($ret["mail_body"], ENT_QUOTES);	
		$fields["retention"] = htmlentities($ret["retention"], ENT_QUOTES);		
		$fields["report_comment"] = htmlentities($ret["report_comment"], ENT_QUOTES);

		return (array("report_id" => $report_id["MAX(report_id)"], "fields" => $fields));
	}
	
	function updateReport($report_id = null, $from_MC = false)	{
		global $form, $pearDB, $oreon;
		if (!$report_id)
			return;

		$ret = array();
		$ret = $form->getSubmitValues();
		if (isset($ret["report_description"]) && $ret["report_description"] != NULL)		{
			$ret["report_descriptionv"] = str_replace('/', "#S#", $ret["report_description"]);
			$ret["report_description"] = str_replace('\\', "#BS#", $ret["report_description"]);
		}
		$rq = "UPDATE pdfreports_reports SET " ;
		$rq .= "name = ";
		isset($ret["name"]) && $ret["name"] != NULL ? $rq .= "'".$ret["name"]."', ": $rq .= "NULL, ";
		# If we are doing a MC, we don't have to set name and alias field
		if (!$from_MC)	{
			$rq .= "report_description = ";
			isset($ret["report_description"]) && $ret["report_description"] != NULL ? $rq .= "'".addslashes(htmlentities($ret["report_description"], ENT_QUOTES))."', ": $rq .= "NULL, ";
		}
		$rq .= "period = ";
		isset($ret["period"]) && $ret["period"] != NULL ? $rq .= "'".$ret["period"]."', ": $rq .= "NULL, ";
		$rq .= "report_title = ";
		isset($ret["report_title"]) && $ret["report_title"] != NULL ? $rq .= "'".$ret["report_title"]."', ": $rq .= "NULL, ";		
		$rq .= "subject = ";
		isset($ret["subject"]) && $ret["subject"] != NULL ? $rq .= "'".addslashes(htmlentities($ret["subject"], ENT_QUOTES))."', ": $rq .= "NULL, ";		
		$rq .= "mail_body = ";
		isset($ret["mail_body"]) && $ret["mail_body"] != NULL ? $rq .= "'".addslashes(htmlentities($ret["mail_body"], ENT_QUOTES))."', ": $rq .= "NULL, ";
		$rq .= "retention = ";
		isset($ret["retention"]) && $ret["retention"] != NULL ? $rq .= "'".addslashes(htmlentities($ret["retention"], ENT_QUOTES))."', ": $rq .= "4, ";

		$rq .= "service_category = ";
		isset($ret["service_category"]) && $ret["service_category"] != NULL ? $rq .= "'".addslashes(htmlentities($ret["service_category"], ENT_QUOTES))."', ": $rq .= "NULL, ";
		$rq .= "report_template = ";
		isset($ret["report_template"]) && $ret["report_template"] != NULL ? $rq .= "'".addslashes(htmlentities($ret["report_template"], ENT_QUOTES))."', ": $rq .= "NULL, ";

		$rq .= "report_comment = ";
		$ret["report_comment"] = str_replace("/", '#S#', $ret["report_comment"]);
		$ret["report_comment"] = str_replace("\\", '#BS#', $ret["report_comment"]);
		isset($ret["report_comment"]) && $ret["report_comment"] != NULL ? $rq .= "'".htmlentities($ret["report_comment"], ENT_QUOTES)."', " : $rq .= "NULL, ";

		$rq .= "activate = ";
		isset($ret["activate"]["activate"]) && $ret["activate"]["activate"] != NULL ? $rq .= "'".$ret["activate"]["activate"]."', " : $rq .= "NULL, ";

		$rq .= "bcsv = ";
		isset($ret["reportOpts"]["bcsv"]) ? $rq .= "'".$ret["reportOpts"]["bcsv"]."', " : $rq .= "NULL,";
		$rq .= "btimeline = ";
		isset($ret["reportOpts"]["btimeline"]) ? $rq .= "'".$ret["reportOpts"]["btimeline"]."', " : $rq .= "NULL, ";
		$rq .= "bsummary_only = ";
		isset($ret["reportOpts"]["bsummary_only"]) ? $rq .= "'".$ret["reportOpts"]["bsummary_only"]."', " : $rq .= "NULL, ";
		$rq .= "bdebug = ";
		isset($ret["reportOpts"]["bdebug"]) ? $rq .= "'".$ret["reportOpts"]["bdebug"]."' " : $rq .= "NULL ";


		$rq .= "WHERE report_id = '".$report_id."'";

		$DBRESULT =& $pearDB->query($rq);


		$fields["name"] = $ret["name"];
		$fields["report_description"] = htmlentities($ret["report_description"], ENT_QUOTES);
		$fields["period"] = $ret["period"];
		$fields["report_title"] = $ret["report_title"];		
		$fields["subject"] = htmlentities($ret["subject"], ENT_QUOTES);
		$fields["mail_body"] = htmlentities($ret["mail_body"], ENT_QUOTES);
		$fields["retention"] = htmlentities($ret["retention"], ENT_QUOTES);			
		$fields["service_category"] = htmlentities($ret["service_category"], ENT_QUOTES);			
		$fields["report_template"] = htmlentities($ret["report_template"], ENT_QUOTES);			
		$fields["report_comment"] = htmlentities($ret["report_comment"], ENT_QUOTES);
		//$oreon->CentreonLogAction->insertLog("service", $service_id["MAX(service_id)"], getHostServiceCombo($service_id, htmlentities($ret["service_description"], ENT_QUOTES)), "c", $fields);
		//$oreon->user->access->updateACL();
	}

	function updateReport_MC($report_id = null)	{
		if (!$report_id)
			return;
		global $form;
		global $pearDB, $oreon;
		$ret = array();
		$ret = $form->getSubmitValues();


		$rq = "UPDATE pdfreports_reports SET ";
		if (isset($ret["name"]) && $ret["name"] != NULL) {
			$rq .= "name = '".$ret["name"]."', ";
			$fields["name"] = $ret["name"];
		}
		if (isset($ret["period"]) && $ret["period"] != NULL) {
			$rq .= "period = '".$ret["period"]."', ";
			$fields["period"] = $ret["period"];
		}
		if (isset($ret["report_title"]) && $ret["report_title"] != NULL) {
			$rq .= "report_title = '".$ret["report_title"]."', ";
			$fields["report_title"] = $ret["report_title"];
		}
		if (isset($ret["subject"]) && $ret["subject"] != NULL) {
			$rq .= "subject = '".htmlentities($ret["subject"], ENT_QUOTES)."', ";
			$fields["subject"] = htmlentities($ret["subject"], ENT_QUOTES);
		}

		if (isset($ret["mail_body"]) && $ret["mail_body"] != NULL) {
			$rq .= "mail_body = '".htmlentities($ret["mail_body"], ENT_QUOTES)."', ";
			$fields["mail_body"] = htmlentities($ret["mail_body"], ENT_QUOTES);
		}

		if (isset($ret["retention"]) && $ret["retention"] != NULL) {
			$rq .= "retention = '".htmlentities($ret["retention"], ENT_QUOTES)."', ";
			$fields["retention"] = htmlentities($ret["retention"], ENT_QUOTES);
		}
		if (isset($ret["service_category"]) && $ret["service_category"] != NULL) {
			$rq .= "service_category = '".htmlentities($ret["service_category"], ENT_QUOTES)."', ";
			$fields["service_category"] = htmlentities($ret["service_category"], ENT_QUOTES);
		}
		if (isset($ret["report_template"]) && $ret["report_template"] != NULL) {
			$rq .= "report_template = '".htmlentities($ret["report_template"], ENT_QUOTES)."', ";
			$fields["report_template"] = htmlentities($ret["report_template"], ENT_QUOTES);
		}

		if (isset($ret["report_activate"]["report_activate"]) && $ret["report_activate"]["report_activate"] != NULL) {
			$rq .= "report_activate = '".$ret["report_activate"]["report_activate"]."', ";
			$fields["report_activate"] = $ret["report_activate"]["report_activate"];
		}



		if (strcmp("UPDATE pdfreports_reports SET ", $rq))	{
			# Delete last ',' in request
			$rq[strlen($rq)-2] = " ";
			$rq .= "WHERE report_id = '".$report_id."'";
			$DBRESULT =& $pearDB->query($rq);
		}

		//$oreon->CentreonLogAction->insertLog("service", $service_id, getHostServiceCombo($service_id, getMyServiceName($service_id), ENT_QUOTES), "mc", $fields);
	}
	
	
	function updateReportContact($report_id = null, $ret = array())	{
		if (!$report_id) return;
		global $form;
		global $pearDB;
		$rq = "DELETE FROM pdfreports_reports_contact_relation ";
		$rq .= "WHERE reports_rp_id = '".$report_id."'";
		$DBRESULT =& $pearDB->query($rq);
		if (isset($ret["report_cs"]))
			$ret = $ret["report_cs"];
		else
			$ret = $form->getSubmitValue("report_cs");
		for($i = 0; $i < count($ret); $i++)	{
			$rq = "INSERT INTO pdfreports_reports_contact_relation ";
			$rq .= "(contact_c_id, reports_rp_id) ";
			$rq .= "VALUES ";
			$rq .= "('".$ret[$i]."', '".$report_id."')";
			$DBRESULT =& $pearDB->query($rq);
		}
	}

	function updateReportContactGroup($report_id = null, $ret = array())	{
		if (!$report_id) return;
		global $form;
		global $pearDB;
		$rq = "DELETE FROM pdfreports_reports_contactgroup_relation ";
		$rq .= "WHERE reports_rp_id = '".$report_id."'";
		$DBRESULT =& $pearDB->query($rq);
		if (isset($ret["report_cgs"]))
			$ret = $ret["report_cgs"];
		else
			$ret = $form->getSubmitValue("report_cgs");
		for($i = 0; $i < count($ret); $i++)	{
			$rq = "INSERT INTO pdfreports_reports_contactgroup_relation ";
			$rq .= "(contactgroup_cg_id, reports_rp_id) ";
			$rq .= "VALUES ";
			$rq .= "('".$ret[$i]."', '".$report_id."')";
			$DBRESULT =& $pearDB->query($rq);
		}
	}

	function updateReportServiceGroup($report_id = null, $ret = array())	{
		if (!$report_id) return;
		global $form;
		global $pearDB;
		$rq = "DELETE FROM pdfreports_reports_servicegroup_relation ";
		$rq .= "WHERE reports_rp_id = '".$report_id."'";
		$DBRESULT =& $pearDB->query($rq);

		if (isset($ret["report_sg"]))
			$ret = $ret["report_sg"];
		else
			$ret = $form->getSubmitValue("report_sg");
		for($i = 0; $i < count($ret); $i++)	{
			$rq = "INSERT INTO pdfreports_reports_servicegroup_relation ";
			$rq .= "(servicegroup_sg_id, reports_rp_id) ";
			$rq .= "VALUES ";
			$rq .= "('".$ret[$i]."', '".$report_id."')";
			$DBRESULT =& $pearDB->query($rq);

		}
	}
	
	function updateReportHost($report_id = null, $ret = array())	{
		if (!$report_id) return;
		global $form;
		global $pearDB;
		$rq = "DELETE FROM pdfreports_host_report_relation ";
		$rq .= "WHERE reports_rp_id = '".$report_id."'";
		$DBRESULT =& $pearDB->query($rq);
		$ret1 = array();
		$ret2 = array();
		if (isset($ret["report_hs"]))
			$ret1 = $ret["report_hs"];
		else
			$ret1 = $form->getSubmitValue("report_hs");
		if (isset($ret["report_hgs"]))
			$ret2 = $ret["report_hgs"];
		else
			$ret2 = $form->getSubmitValue("report_hgs");
		 if (count($ret2))
			for($i = 0; $i < count($ret2); $i++)	{
				$rq = "INSERT INTO pdfreports_host_report_relation ";
				$rq .= "(hostgroup_hg_id, host_host_id, reports_rp_id) ";
				$rq .= "VALUES ";
				$rq .= "('".$ret2[$i]."', NULL, '".$report_id."')";
				//print "$rq<br/>";
				$DBRESULT =& $pearDB->query($rq);
			}
		else if (count($ret1))
			for($i = 0; $i < count($ret1); $i++)	{
				$rq = "INSERT INTO pdfreports_host_report_relation ";
				$rq .= "(hostgroup_hg_id, host_host_id,  reports_rp_id) ";
				$rq .= "VALUES ";
				$rq .= "(NULL, '".$ret1[$i]."', '".$report_id."')";
				//print "$rq<br/>";
				$DBRESULT =& $pearDB->query($rq);
			}
	}

	# For massive change. We just add the new list if the elem doesn't exist yet
	function updateReportHost_MC($report_id = null)	{
		if (!$report_id) return;
		global $form, $pearDB;
		$rq = "SELECT * FROM pdfreports_host_report_relation ";
		$rq .= "WHERE reports_rp_id = '".$report_id."'";
		$DBRESULT =& $pearDB->query($rq);
		$hsvs = array();
		$hgsvs = array();
		while($arr =& $DBRESULT->fetchRow())	{
			if ($arr["host_host_id"])
				$hsvs[$arr["host_host_id"]] = $arr["host_host_id"];
			if ($arr["hostgroup_hg_id"])
				$hgsvs[$arr["hostgroup_hg_id"]] = $arr["hostgroup_hg_id"];
		}
		$ret1 = array();
		$ret2 = array();
		$ret1 = $form->getSubmitValue("report_hs");
		$ret2 = $form->getSubmitValue("report_hgs");
		 if (count($ret2))
			for($i = 0; $i < count($ret2); $i++)	{
				if (!isset($hgsvs[$ret2[$i]]))	{
					$rq = "DELETE FROM pdfreports_host_report_relation ";
					$rq .= "WHERE reports_rp_id = '".$report_id."' AND host_host_id IS NOT NULL";
					$DBRESULT =& $pearDB->query($rq);
					$rq = "INSERT INTO pdfreports_host_report_relation ";
					$rq .= "(hostgroup_hg_id, host_host_id,  reports_rp_id) ";
					$rq .= "VALUES ";
					$rq .= "('".$ret2[$i]."', NULL,  '".$report_id."')";
					$DBRESULT =& $pearDB->query($rq);
				}
			}
		else if (count($ret1))
			for($i = 0; $i < count($ret1); $i++)	{
				if (!isset($hsvs[$ret1[$i]]))	{
					$rq = "DELETE FROM pdfreports_host_report_relation ";
					$rq .= "WHERE reports_rp_id = '".$report_id."' AND hostgroup_hg_id IS NOT NULL";
					$DBRESULT =& $pearDB->query($rq);
					$rq = "INSERT INTO pdfreports_host_report_relation ";
					$rq .= "(hostgroup_hg_id, host_host_id,  reports_rp_id) ";
					$rq .= "VALUES ";
					$rq .= "(NULL, '".$ret1[$i]."',  '".$report_id."')";
					$DBRESULT =& $pearDB->query($rq);
				}
			}
	}
	


	
?>
