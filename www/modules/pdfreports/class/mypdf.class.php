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

//============================================================+
// classe de la génération de pdf
//============================================================+
global $centreon_path;

//require_once  $centreon_path . "/www/modules/pdfreports/lib/tcpdf/config/lang/eng.php";
require_once  $centreon_path . "/www/modules/pdfreports/lib/tcpdf/tcpdf.php";


// extend TCPF with custom functions
class MYPDF extends TCPDF {


  //ajout de fonctions tcpdf pour la personnalisation du footer à partir de fct de personnalisation header

		/**
	 	 * Set footer data.
		 * @param string $ln footer image logo
		 * @param string $lw foote image logo width in mm
		 * @param string $ht string to print as title on document header
		 * @param string $hs string to print on document header
		 * @access public
		 */
    	public function setFooterData($ln='', $lw=0, $ht='', $hs='') {
			$this->footer_logo = $ln;
			$this->footer_logo_width = $lw;
			$this->footer_title = $ht;
			$this->footer_string = $hs;
		}

    
    // Load table data from file
    public function LoadData($array) {
        $data = $array;
        return $data;
    }


  public  function ColoredTable($header,$data,$piechart_img) {

	// Colors, line width and bold font
        $this->SetFillColor(255, 0, 0);
        $this->SetTextColor(255);
        $this->SetDrawColor(128, 0, 0);
        $this->SetLineWidth(0.1);
	//        $this->SetFont('', 'B');
        // Header

        // Color and font restoration
        $this->SetFillColor(224, 235, 255);
        $this->SetTextColor(0);
	//	$this->SetFont(PDF_FONT_NAME_DATA, 4);
        // Data
        $fill = 0;

/*  2.3
 [14] => Array
        (
            [UP_A] => 2
            [UP_T] => 172800
            [DOWN_A] => 0
            [DOWN_T] => 0
            [UNREACHABLE_A] => 0
            [UNREACHABLE_T] => 0
            [UNDETERMINED_T] => 432000
            [MAINTENANCE_T] => 0
            [TOTAL_TIME] => 604800
            [UP_TP] => 28.57
            [DOWN_TP] => 0
            [UNREACHABLE_TP] => 0
            [UNDETERMINED_TP] => 71.43
            [MAINTENANCE_TP] => 0
            [MEAN_TIME] => 172800
            [UP_MP] => 100
            [DOWN_MP] => 0
            [UNREACHABLE_MP] => 0
            [MEAN_TIME_F] => 2d
            [TOTAL_TIME_F] => 1w
            [UP_TF] => 2d
            [DOWN_TF] =>
            [UNREACHABLE_TF] =>
            [UNDETERMINED_TF] => 5d
            [MAINTENANCE_TF] =>
            [TOTAL_ALERTS] => 2
            [NAME] => ITCHY
            [ID] => 14
        )
*/

	$PIECHART = $piechart_img;

	// Services group state - récuperation des variables sans crochet ni guillemet pour les passer dans le tableau
	$UP_TP_AV = $data["average"]["UP_TP"];
	$UP_MP_AV = $data["average"]["UP_MP"];
	$UP_A_AV = $data["average"]["UP_A"];

	$DOWN_TP_AV = $data["average"]["DOWN_TP"];
	$DOWN_MP_AV = $data["average"]["DOWN_MP"];
	$DOWN_A_AV = $data["average"]["DOWN_A"];

	$UNREACHABLE_TP_AV = $data["average"]["UNREACHABLE_TP"];
	$UNREACHABLE_MP_AV = $data["average"]["UNREACHABLE_MP"];
	$UNREACHABLE_A_AV = $data["average"]["UNREACHABLE_A"];


	$UNDETERMINED_TP_AV = $data["average"]["UNDETERMINED_TP"];
	if (isset($data["average"]["MAINTENANCE_TP"]) ) {
	  $MAINTENANCE_TP_AV =  (isset($data["average"]["MAINTENANCE_TP"]) && $data["average"]["MAINTENANCE_TP"] != NULL ? $data["average"]["MAINTENANCE_TP"] : 0 );
	  $MAINTENANCE_MP_AV = (isset($data["average"]["MAINTENANCE_MP"]) && $data["average"]["MAINTENANCE_MP"] != NULL ? $data["average"]["MAINTENANCE_MP"] : 0 );
	  $MAINTENANCE_A_AV = (isset($data["average"]["MAINTENANCE_A"]) && $data["average"]["MAINTENANCE_A"] != NULL ? $data["average"]["MAINTENANCE_A"] : 0 );
	  
	  $MAINTENANCE_TR = <<<EOD
<tr style="background-color:#EDF4FF;">
  <th style="background-color:#CC99FF;">NoSLA</th>
  <td>$MAINTENANCE_TP_AV %</td> 
  <td>$MAINTENANCE_MP_AV %</td>
  <td>$MAINTENANCE_A_AV</td>
</tr>
EOD;
 $ROWSPAN="8";
	} else {	  
	  $MAINTENANCE_TR = "";
	  $ROWSPAN="7";
	}

	//calcul du total des alertes
	$TOTAL_A_AV = $UP_A_AV + $DOWN_A_AV + $UNREACHABLE_A_AV;

//creation du tableau pour tcpdf, format html
	
	$tbl1 = <<<EOD
                  <table border="0" style="text-align: center;font-size:11">
		  <tr  style="background-color:#F7FAFF;">
			  <td rowspan="$ROWSPAN" border="0" width="125" align="center" valign="center" ><img src="file://$piechart_img" /></td>
			  <td colspan="4" style="background-color:#D7D6DD;" >Hosts group state</td>
		  </tr>
		  <tr style="background-color:#D5DFEB;">
		    <th>State</th>
		    <th>Total Time</th>
		    <th>Mean Time</th>
		    <th>Alerts</th>
		  </tr>
		  
		  <tr style="background-color:#F7FAFF;">
		    <th style="background-color:#19EE11;">UP</th>
		    <td>$UP_TP_AV %</td> 
		    <td>$UP_MP_AV %</td>
		    <td>$UP_A_AV</td>
		  </tr>
		  
		  <tr style="background-color:#EDF4FF;">
		    <th style="background-color:#F91E05;">DOWN</th>
		    <td>$DOWN_TP_AV %</td>
		    <td>$DOWN_MP_AV %</td>
		    <td>$DOWN_A_AV</td>
		  </tr>
		  
		  
		  <tr style="background-color:#F7FAFF;">
		    <th style="background-color:#82CFD8;">UNREACHABLE</th>
		    <td>$UNREACHABLE_TP_AV %</td> 
		    <td>$UNREACHABLE_MP_AV %</td>
		    <td>$UNREACHABLE_A_AV</td>
		  </tr>
		  
		  $MAINTENANCE_TR
		  
		  <tr style="background-color:#EDF4FF;">
		    <th style="background-color:#CCF8FF;">UNDETERMINED</th>
		    <td>$UNDETERMINED_TP_AV %</td> 
		    <td></td>
		    <td></td>
		  
		  </tr>
		  
		  <tr style="background-color:#CED3ED;">
		    <th>Total</th>
		    <td></td>
		    <td></td>
		    <td>$TOTAL_A_AV</td>
		  </tr>
		  
		  </table>

EOD;




// State Breakdowns For Host  

//init du deuxième tableau

if (isset($MAINTENANCE_TR) && $MAINTENANCE_TR != "") {
  $MAINTENANCE_HEADER = '<td  width="10%">NoSLA</td>';
  $MAINTENANCE_HEADER_LABEL = '<td width="10%">%</td>';
  $HEADER_WIDTH = "9";
} else {
   $MAINTENANCE_HEADER = "";
   $MAINTENANCE_HEADER_LABEL = "";
   $HEADER_WIDTH = "8";
}



$tbl2 = <<<EOD

<table border="0" style="text-align: center;font-size:9">
	<tr style="background-color:#D7D6DC;">
	  <td colspan="$HEADER_WIDTH" width="100%">State Breakdowns For Hosts</td>
	</tr>
	<tr style="background-color:#D5DFEB;">
	    <td colspan="1" width="20%"></td>
	    <td colspan="2" width="20%">Up</td>
	    <td colspan="2" width="20%">Down</td>
	    <td colspan="2" width="20%">Unreachable</td>
	    $MAINTENANCE_HEADER 
	    <td width="10%">Undetermined</td>
	</tr>

	<tr style="background-color:#D5DFEB;">
	    <td width="20%">Host</td>
	    <td width="15%">%</td>
	    <td width="5%">Alert</td>
	    <td width="15%">%</td>
	    <td width="5%">Alert</td>
	    <td width="15%">%</td>
	    <td width="5%">Alert</td>
	    $MAINTENANCE_HEADER_LABEL
	    <td width="10%">%</td>
	</tr>
		    

EOD;

unset($data['average']);
$data = array_values($data);

usort($data, function($a, $b) {
    if ($a['UP_MP']==$b['UP_MP']) return 0;
    return ($a['UP_MP']<$b['UP_MP'])?-1:1;
    //    return $a['UP_MP'] - $b['UP_MP'];
  });

//print "<pre>\n After SORT: \n";
//print_r($data);
//print "</pre>\n";



//parsing des hosts du hostgroup et ajout dans tableau
$i =0;
foreach ($data	 as $key => $tab) {
  //  if ($key != "average") {

//bug centreon - hostname et service inverses   
$NAME = $tab["NAME"];

//print_r($tab);

$UP_TP = $tab["UP_TP"];
$UP_MP = $tab["UP_MP"];
$UP_A = $tab["UP_A"];
$DOWN_TP = $tab["DOWN_TP"];
$DOWN_MP = $tab["DOWN_MP"];
$DOWN_A = $tab["DOWN_A"];
$UNREACHABLE_TP = $tab["UNREACHABLE_TP"];
$UNREACHABLE_MP = $tab["UNREACHABLE_MP"];
$UNREACHABLE_A = $tab["UNREACHABLE_A"];
if (isset ($tab["MAINTENANCE_TP"])) {
  $MAINTENANCE_TP =  '<td width="10%" style="background-color:#CC99FF;">'.$tab["MAINTENANCE_TP"]."</td>";
  
} else {
  $MAINTENANCE_TP = "";
}
$UNDETERMINED_TP = $tab["UNDETERMINED_TP"];

$BACKGROUND_COLOR = ( $i % 2 ? "EDF4FF": "F7FAFF"); 

$tbl2 .= <<<EOD

<tr style="background-color:#$BACKGROUND_COLOR;">
<td width="20%" align="left">$NAME</td>
<td width="15%" style="background-color:#13EB3A;">$UP_TP ($UP_MP)</td>
<td width="5%" style="background-color:#13EB3A;">$UP_A</td>
<td width="15%" style="background-color:#F91D05;">$DOWN_TP  ($DOWN_MP)</td>
<td width="5%" style="background-color:#F91D05;">$DOWN_A</td>
<td width="15%" style="background-color:#82CFD8;">$UNREACHABLE_TP ($UNREACHABLE_MP)</td>
<td width="5%" style="background-color:#82CFD8;">$UNREACHABLE_A</td>
$MAINTENANCE_TP
<td width="10%" style="background-color:#CCF8FF;">$UNDETERMINED_TP</td>
</tr>



EOD;
$i++;
//  }
}

//fermeture du tableau
$tbl2 .= <<<EOD
</table>

EOD;

$tbl1 .= "\n <p>Total number of hosts = " . $i . "<p>\n";

//print $tbl1;
//print $tbl2;

//$this->writeHTML($tbl, true, false, false, false, ''); 
$this->writeHTML($tbl1, true, false, false, false, ''); 
$this->writeHTML($tbl2, true, false, false, false, '');

@unlink($piechart_img) ;

}



/* pour les services groupes */
    
    // Colored table
public function ServicesColoredTable($header,$data,$piechart_img) {
        // Colors, line width and bold font
        $this->SetFillColor(255, 0, 0);
        $this->SetTextColor(255);
        $this->SetDrawColor(128, 0, 0);
        $this->SetLineWidth(0.1);
        $this->SetFont('', 'B');
        // Header

        // Color and font restoration
        $this->SetFillColor(224, 235, 255);
        $this->SetTextColor(0);
        $this->SetFont('');
        // Data
        $fill = 0;

	//print_r($data);
/*
    [average] => Array
        (
            [OK_T] => 103680
            [OK_A] => 6
            [WARNING_T] => 0
            [WARNING_A] => 0
            [CRITICAL_T] => 0
            [CRITICAL_A] => 0
            [UNKNOWN_T] => 0
            [UNKNOWN_A] => 0
            [UNDETERMINED_T] => 155520
            [MAINTENANCE_T] => 0
            [OK_TP] => 40
            [WARNING_TP] => 0
            [CRITICAL_TP] => 0
            [UNKNOWN_TP] => 0
            [UNDETERMINED_TP] => 60
            [MAINTENANCE_TP] => 0
            [TOTAL_TIME] => 259200
            [MEAN_TIME] => 103680
            [TOTAL_ALERTS] => 6
            [OK_MP] => 100
            [WARNING_MP] => 0
            [CRITICAL_MP] => 0
            [UNKNOWN_MP] => 0
        )
*/	
	
	
/*
    [14_26] => Array
        (
            [service_id] => 26
            [OK_T] => 172800
            [OK_A] => 2
            [WARNING_T] => 0
            [WARNING_A] => 0
            [UNKNOWN_T] => 0
            [UNKNOWN_A] => 0
            [CRITICAL_T] => 0
            [CRITICAL_A] => 0
            [UNDETERMINED_T] => 86400
            [MAINTENANCE_T] => 0
            [TOTAL_TIME] => 259200
            [OK_TP] => 66.67
            [WARNING_TP] => 0
            [CRITICAL_TP] => 0
            [UNKNOWN_TP] => 0
            [UNDETERMINED_TP] => 33.33
            [MAINTENANCE_TP] => 0
            [MEAN_TIME] => 172800
            [OK_MP] =>  100
            [WARNING_MP] => 0
            [CRITICAL_MP] => 0
            [UNKNOWN_MP] => 0
            [MEAN_TIME_F] => 2d
            [TOTAL_TIME_F] => 3d
            [OK_TF] => 2d
            [WARNING_TF] =>
            [CRITICAL_TF] =>
            [UNKNOWN_TF] =>
            [UNDETERMINED_TF] => 1d
            [MAINTENANCE_TF] =>
            [TOTAL_ALERTS] => 2
            [HOST_ID] => 14
            [SERVICE_ID] => 26
            [HOST_NAME] => ITCHY
            [SERVICE_DESC] => Ping
        )

*/		

	// Services group state - récuperation des variables sans crochet ni guillemet pour les passer dans le tableau
	$OK_TP_AV = $data["average"]["OK_TP"];
	$OK_MP_AV = $data["average"]["OK_MP"];
	$OK_A_AV = $data["average"]["OK_A"];

	$WARNING_TP_AV = $data["average"]["WARNING_TP"];
	$WARNING_MP_AV = $data["average"]["WARNING_MP"];
	$WARNING_A_AV = $data["average"]["WARNING_A"];

	$CRITICAL_TP_AV = $data["average"]["CRITICAL_TP"];
	$CRITICAL_MP_AV = $data["average"]["CRITICAL_MP"];
	$CRITICAL_A_AV = $data["average"]["CRITICAL_A"];

	$UNKNOWN_TP_AV = $data["average"]["UNKNOWN_TP"];
	$UNKNOWN_MP_AV = $data["average"]["UNKNOWN_MP"];
	$UNKNOWN_A_AV = $data["average"]["UNKNOWN_A"];

	$UNDETERMINED_TP_AV = $data["average"]["UNDETERMINED_TP"];

	if (isset($data["average"]["MAINTENANCE_TP"]) ) {
	  $MAINTENANCE_TP_AV =  (isset($data["average"]["MAINTENANCE_TP"]) && $data["average"]["MAINTENANCE_TP"] != NULL ? $data["average"]["MAINTENANCE_TP"] : 0 );
	  $MAINTENANCE_MP_AV = "" ;//(isset($data["average"]["MAINTENANCE_MP"]) && $data["average"]["MAINTENANCE_MP"] != NULL ? $data["average"]["MAINTENANCE_MP"] : 0 );
	  $MAINTENANCE_A_AV = "" ;// (isset($data["average"]["MAINTENANCE_A"]) && $data["average"]["MAINTENANCE_A"] != NULL ? $data["average"]["MAINTENANCE_A"] : 0 );
	  
	  $MAINTENANCE_TR = <<<EOD
<tr style="background-color:#EDF4FF;">
  <th style="background-color:#CC99FF;">NoSLA</th>
  <td>$MAINTENANCE_TP_AV %</td> 
  <td>$MAINTENANCE_MP_AV</td>
  <td>$MAINTENANCE_A_AV</td>
</tr>
EOD;
 $ROWSPAN="9";
	} else {	  
	  $MAINTENANCE_TR = "";
	  $ROWSPAN="8";
	}
	
	//calcul du total des alertes
	$TOTAL_A_AV = $OK_A_AV + $WARNING_A_AV + $UNKNOWN_A_AV;

//creation du tableau pour tcpdf, format html
	
	$tbl1 = <<<EOD
<table border="0" align="center">
	<tr   border="0" >
	<td rowspan="$ROWSPAN" border="0" width="125" align="center" valign="center" ><img src="file://$piechart_img" /></td>
	<td colspan="4"  style="background-color:#D7D7DD;" >Services group state</td>
	</tr>
<tr>
  <th style="background-color:#D5DFEB;">State</th>
  <th style="background-color:#D5DFEB;">Total Time</th>
  <th style="background-color:#D5DFEB;">Mean Time</th>
  <th style="background-color:#D5DFEB;">Alerts</th>
</tr>

<tr>
  <th style="background-color:#13EB3A;">Ok</th>
  <td style="background-color:#F7FAFF;">$OK_TP_AV %</td> 
  <td style="background-color:#F7FAFF;">$OK_MP_AV %</td>
  <td style="background-color:#F7FAFF;">$OK_A_AV</td>
</tr>

<tr>
  <th style="background-color:#F8C706;">Warning</th>
  <td style="background-color:#EDF4FF;">$WARNING_TP_AV %</td> 
  <td style="background-color:#EDF4FF;">$WARNING_MP_AV %</td>
  <td style="background-color:#EDF4FF;">$WARNING_A_AV</td>
</tr>


<tr>
  <th style="background-color:#F91E05;">Critical</th>
  <td style="background-color:#F7FAFF;">$CRITICAL_TP_AV %</td> 
  <td style="background-color:#F7FAFF;">$CRITICAL_MP_AV %</td>
  <td style="background-color:#F7FAFF;">$CRITICAL_A_AV</td>
</tr>


<tr>
  <th style="background-color:#DCDADA;">Unknown</th>
  <td style="background-color:#EDF4FF;">$UNKNOWN_TP_AV %</td> 
  <td style="background-color:#EDF4FF;">$UNKNOWN_MP_AV %</td>
  <td style="background-color:#EDF4FF;">$UNKNOWN_A_AV</td>
</tr>

$MAINTENANCE_TR

<tr>
  <th style="background-color:#CCF8FF;">Undertermined</th>
  <td style="background-color:#F7FAFF;">$UNDETERMINED_TP_AV %</td> 
  <td style="background-color:#F7FAFF;"></td>
  <td style="background-color:#F7FAFF;"></td>
</tr>

<tr>
  <th style="background-color:#CED3ED;">Total</th>
  <td style="background-color:#CED3ED;"></td>
  <td style="background-color:#CED3ED;"></td>
  <td style="background-color:#CED3ED;">$TOTAL_A_AV</td>
</tr>

</table>
EOD;

// State Breakdowns For Host Services 

//init du deuxième tableau

if (isset($MAINTENANCE_TR) && $MAINTENANCE_TR != "") {
  $MAINTENANCE_HEADER = '<th width="6%" >NoSLA</th>';
  $MAINTENANCE_HEADER_LABEL = '<th width="6%">%</th>';
  $HEADER_WIDTH = "12";
} else {
   $MAINTENANCE_HEADER = "";
   $MAINTENANCE_HEADER_LABEL = "";
   $HEADER_WIDTH = "11";
}

$tbl2 = <<<EOD
<style>
table, td  {
  border-collapse: collapse;
  font-size:7;
  border-spacing:0px;

}

td {
  padding:1
  text-align: left;
}

th {  
  padding: 0;
  text-align: left;
  font-size: 9px;
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

<table>
	<tr>
	<th width="100%" colspan="$HEADER_WIDTH" style="background-color:#D7D6DD;" >State Breakdowns For Host Services</th>
	</tr>
	<tr style="background-color:#D5DFEB;" >
		<th colspan="2" width="36%"  ></th>
		<th colspan="2" width="13%" >OK</th>
		<th colspan="2" width="13%" >Warning</th>
		<th colspan="2" width="13%" >Critical</th>
		<th colspan="2" width="13%" >Unknown</th>
		$MAINTENANCE_HEADER
		<th width="6%" >Undef</th>
	</tr>

	<tr style="background-color:#D5DFEB;">
		<th width="16%" >Host Name</th>
		<th width="20%">Service</th>
		<th width="10%">%</th>
		<th width="3%">A</th>
		<th width="10%">%</th>
		<th width="3%">A</th>
		<th width="10%">%</th>
		<th width="3%">A</th>
		<th width="10%">%</th>
		<th width="3%">A</th>
		$MAINTENANCE_HEADER_LABEL
		<th width="6%">%</th>
	</tr>
EOD;


unset($data['average']);
$data = array_values($data);

usort($data, function($a, $b) {
    if ($a['OK_MP']==$b['OK_MP']) return 0;
    return ($a['OK_MP']<$b['OK_MP'])?-1:1;
  });


//parsing des services du service group et ajout dans tableau
$i =0;
foreach ($data	 as $key => $tab) {
  //  if ($key != "average") {

//bug centreon - hostname et service inverses   
$HOST_NAME = $tab["HOST_NAME"];
$SERVICE_DESC = $tab["SERVICE_DESC"];

$OK_TP = $tab["OK_TP"];
$OK_MP = $tab["OK_MP"];
$OK_A = $tab["OK_A"];
$WARNING_TP = $tab["WARNING_TP"];
$WARNING_MP = $tab["WARNING_MP"];
$WARNING_A = $tab["WARNING_A"];
$CRITICAL_TP = $tab["CRITICAL_TP"];
$CRITICAL_MP = $tab["CRITICAL_MP"];
$CRITICAL_A = $tab["CRITICAL_A"];
$UNKNOWN_TP = $tab["UNKNOWN_TP"];
$UNKNOWN_MP = $tab["UNKNOWN_MP"];
$UNKNOWN_A = $tab["UNKNOWN_A"];
$UNDETERMINED_TP = $tab["UNDETERMINED_TP"];
if (isset ($tab["MAINTENANCE_TP"])) {
  $MAINTENANCE_TP =  '<td style="background-color:#CC99FF;">'.$tab["MAINTENANCE_TP"]."</td>";
  
} else {
  $MAINTENANCE_TP = "";
}

$BACKGROUND_COLOR = ( $i % 2 ? "EDF4FF": "F7FAFF"); 

$tbl2 .= <<<EOD

<tr style="background-color:#$BACKGROUND_COLOR;" >
<td align="left">$HOST_NAME</td>
<td align="left">$SERVICE_DESC</td>
  <td style="background-color:#13EB3A;" >$OK_TP ($OK_MP)</td>
<td style="background-color:#13EB3A;">$OK_A</td>
<td style="background-color:#F8C706;">$WARNING_TP ($WARNING_MP)</td>
<td style="background-color:#F8C706;">$WARNING_A</td>
<td style="background-color:#F91D05;">$CRITICAL_TP ($CRITICAL_MP)</td>
<td style="background-color:#F91D05;">$CRITICAL_A</td>
<td style="background-color:#DCDADA;">$UNKNOWN_TP ($UNKNOWN_MP)</td>
<td style="background-color:#DCDADA;">$UNKNOWN_A</td>
$MAINTENANCE_TP
<td style="background-color:#CCF8FF;">$UNDETERMINED_TP</td>
</tr>



EOD;
$i++;
//  }
}

//fermeture du tableau
$tbl2 .= <<<EOD
</table>

EOD;

$tbl1 .= "\n <p>Total number of services = " . $i . "<p>\n";

//écriture des tableaux
$this->writeHTML($tbl1, true, false, false, false, ''); 
$this->writeHTML($tbl2, true, false, false, false, ''); 

//
@unlink($piechart_img) ;


    } 






}


