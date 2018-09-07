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

//ini_set('display_errors',1);
//error_reporting(E_ALL);
	/**
	 * Configuration file
	 */
	$centreonConf = "@CENTREON_ETC@/centreon.conf.php";


	/* ***********************************************
	 * Test if Centreon configuration file exists
	 */
	if (false === file_exists($centreonConf)) {
		file_put_contents('php://stderr', "The configuration file does not exists.");
		exit(1);
	}



	function programExit($msg) {
	    echo "[".date("Y-m-d H:i:s")."] ".$msg."\n";
	    exit;
	}

#	(int)$nbProc = exec("ps -edf | grep cron_pdfreports_purge.php | grep -v grep | wc -l");
#	if ($nbProc > 2) {
#		programExit("More than one cron_pdfreports_purge.php process currently running. Going to exit...");
#	}

	ini_set('max_execution_time', 0);

	try {
		
		require_once $centreonConf;

		$centreonClasspath = $centreon_path . 'www/class';

		/* Include class */
		require_once $centreonClasspath . '/centreonDB.class.php';
	
		
		require_once $centreonClasspath . '/centreonAuth.class.php';
		require_once $centreonClasspath . '/centreonLog.class.php';
	
	
		include_once $centreon_path . "www/include/common/common-Func.php";
	
		require_once("DB-Func.php");
		
		$centreon_version =getCentreonVersionPdf();
		$admin_alias  = getAdminUserAlias();
		
		if ( $centreon_version >= 220) {
			require_once $centreonClasspath . '/centreonUser.class.php';
			require_once $centreonClasspath . '/centreonSession.class.php';   
			require_once $centreonClasspath . '/centreon.class.php';
			
			$CentreonLog = new CentreonUserLog(-1, $pearDB);
			$centreonAuth = new CentreonAuth($admin_alias, "", "", $pearDB, $CentreonLog, 1);

			$centreon = new Centreon($centreonAuth->userInfos,getVersionNagios());
			$oreon = $centreon;
	
		} else {
			require_once $centreonClasspath . '/other.class.php';	
			require_once $centreonClasspath . '/User.class.php';
			require_once $centreonClasspath . '/Session.class.php';  
			require_once $centreonClasspath . '/Oreon.class.php';
	
			$CentreonLog = new CentreonUserLog(-1, $pearDB);
			$centreonAuth = new CentreonAuth($admin_alias, "", "", $pearDB, $CentreonLog, 1);
	
			$user =& new User($centreonAuth->userInfos, getVersion());
	
			$oreon = new Oreon($user);

			
		}
		require_once $centreonClasspath . '/centreonACL.class.php';  
	
	/*
	*	Main
	*/
	
	global $pearDB, $pearDBndo, $pearDBO, $oreon ;	
	
	$period_arg = NULL;
    
	if (count($argv) > 1)
	  $period_arg = $argv[1];
	
	$reports = array();
	$reports = getActiveReports($period_arg);
	$reportsFiles = array();
	
	
		foreach ( $reports as $report_id => $name ) {
			$reportinfo = array();
			$reportinfo = getReportInfo($report_id);

			$is_not_an_report = array(".","..","README","readme","LICENCE","licence");
			$is_a_valid_report = array(
						   'docx',
						   'odt'
						   );

#			print_r ($is_a_valid_report);

			$pdfDirName = getGeneralOptInfo("pdfreports_path_gen") . $reportinfo['report_id'] . "/";
			if (! ($dh = @opendir($pdfDirName)) ) {
				// error_log("WARNING: can't open directory '".$rep."'",0);
				return ;
				//break;
			}

			while (false !== ($filename = readdir($dh))) {
#			  print $filename . PHP_EOL;
				if ( $filename == "." || $filename == "..")
					continue;

				if (in_array($filename, $is_not_an_report))
					continue;

				$pinfo = pathinfo($filename);
#				print_r($pinfo);
				if (isset($pinfo["extension"]) && in_array($pinfo["extension"],$is_a_valid_report)) {
#				  print  $pinfo["extension"] . " is a valid extention. Continuing".PHP_EOL;
					continue;
				}

				$key = $filename;
				$reportsFiles[] = $key;
			}

			closedir($dh);
			rsort($reportsFiles);
#			print_r($reportsFiles);
			$start_i = intval($reportinfo['retention']) ;
			for ($i = $reportinfo['retention']; $i < count($reportsFiles) ; $i++) {
				$reportFilename = $pdfDirName . $reportsFiles[$i] ;
				if (@unlink($reportFilename ) ) 
					print " Report ". $reportFilename  . " deleted !  ". PHP_EOL ;
				else 
					print " ERROR : Unable to delete Report ". $reportFilename  .  PHP_EOL ;
			}
		
			$reportinfo = null ;
			$reportsFiles = null;
			$reportFilename = null ;
		}

	} catch (Exception $e) {
		programExit($e->getMessage());
	}









