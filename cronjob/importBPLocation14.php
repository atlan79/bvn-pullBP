<?php
    /* 
    Plugin Name: BVN Basketplan Import
    Plugin URI: http://www.bvn.ch 
    Description: Imports clubs, team and respective games from Basketplan to the BVN WebControl database 
    Author: Thomas Winter 
    Version: 1.0
    Author URI: http://www.houseofwinter.ch
	Encoding: UTF-8 for proper display of äöüéèà
    */
header('Content-type: text/html; charset=utf-8');
$time_start = microtime(true);

if (stristr(dirname(__FILE__), "homepages_work")) {
// local
	//echo "local<br>\n";
	$root_path = "D:\\homepages_work\\bvn";
} else {
// online
	//echo "online<br>\n";
	$root_path = "/home/bvnch/public_html";
}
$_SESSION['root_path'] = $root_path;


require_once($root_path."/classes/lib.mysql.utf8.php");
$utfMysql = new utfMysql();

/*
 *  Importing data from WebCrawler to BVN Database
 *
 *  Data received in ANSI and stored in UTF-8
 * 
 */
$table = "bpCrawlLocation as bpCl";
$arSelect = array(
	locationId => "bpCl.locationId",
);
$whereClause = "bpCl.requestStamp > bpCl.successStamp OR bpCl.successStamp IS NULL ORDER BY bpCl.locationId";
$arCrawlLocations = $utfMysql->mysqlMultiSELECT($table, $arSelect, $whereClause);

//echo "<pre>";
//print_r($arCrawlLocations);
//echo "</pre>";

$urlLocation        = "https://www.basketplan.ch/findLocationById.do?locationId=%s&perspective=de_default";

// code and options to load the page 
include_once($root_path."/classes/lib.loadUrl_3.0.A.php");
include_once($root_path."/classes/lib.bvnPlanCrawlPage.php");
include_once($root_path."/classes/lib.bvnPlanStripHTML.php");
include_once($root_path."/classes/lib.bvnValuesByName.php");

$arLoadOptions = array(
	'return_info'	=> true,
	'method'		=> 'post'
);

/*
 * STEP 6 - PARSE ALL LOCATIONS OF GAMES WITH CLUBS OF THE BVN REGIO INVOLVED
 * Link: https://www.basketplan.ch/findLocationById.do?locationId=%s&perspective=de_default
 * Result: All Info of Locations
 */
$arContainerLocation = array();

// Beschreibungen der Locations
foreach ($arCrawlLocations as $crawlLocation) {
	$locationId = $crawlLocation['locationId'];
	// Seiten laden
	//echo sprintf ($urlLocation, $crawlLocation);
	$arLoad = load(sprintf ($urlLocation, $locationId), $arLoadOptions);
	$htmlBody = $arLoad['body'];
	
	// Links der Seiten auslesen
	// Keine Links auszulesen
	
	// Inhalt der Seiten auswerten
	$arFormValueOptions = array(
		'page'	=> "location",
	);
	
	$arFormData = getFormValues($htmlBody, $arFormValueOptions);
	//echo "<pre>";
	//print_r($arFormData);
	//echo "</pre>";
	
	if(is_array($arFormData) && count($arFormData) != 0) {
		// Sind bereits Location Infos in der BVN Tabelle vorhanden?
		$table = "bpLocation";
		$arSelect = array(
			locationId => "locationId"
		);
		$whereClause = " locationId = '".$locationId."'";
		$arCheckLocation = $utfMysql->mysqlSELECT($table, $arSelect, $whereClause);
		
		$arInsert = array ( 
			locationId => $arFormData["locationId"], 
			federationId => $arFormData["federationId"],
			shortName => $arFormData["shortName"],
			name => $arFormData["name"],
			line1 => $arFormData["line1"],
			zip => $arFormData["zip"],
			city => $arFormData["city"],
			responsableName => $arFormData["responsableName"],
			responsablePhoneNumber => $arFormData["responsablePhoneNumber"]
		);
		
		if(is_array($arCheckLocation)) {
			$whereClause = "locationId = '".$locationId."'";
			$myLocationId = $utfMysql->mysqlUPDATE($table, $arInsert, $whereClause);
		} else {
			$myLocationId = $utfMysql->mysqlINSERT($table, $arInsert);
		}
		
		// Location als erfolgreich updated markieren
		$table = "bpCrawlLocation";
		$arUpdate = array(
			successStamp => "NOW()"
		);
		$whereClause = " locationId = '".$locationId."'";
		$myLocationId = $utfMysql->mysqlUPDATE($table, $arUpdate, $whereClause);

		
		// Location dem Array bearbeitet hinzufügen
		$arContainerLocation[] =  $arFormData ;
	}
}


$output = 0;

if( $output == 1 ) {
	echo "<table>\n";
	echo "  <tr><td colspan=\"".count($arContainerLocation['0'])."\">".count($arContainerLocation)." einzulesende Zeilen</td></tr>\n";
	foreach ($arContainerLocation as $arRow){
	  echo "  <tr>\n";
	  foreach ($arRow as $col){
		echo "    <td>".$col."</td>\n";
	  }
	  echo "  </tr>\n";
	}
	echo "</table>\n";
	
//	echo "<pre>";
//	print_r($arContainerLocation);
//	echo "</pre>";
}

$time_end = microtime(true);
$time = $time_end - $time_start;

echo "Basketplan Locations to BVN bpLocation Import in ".$time." seconds<br>\n";

?>
