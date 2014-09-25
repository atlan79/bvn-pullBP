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
$table = "bpCrawlTeam as bpCt";
$arSelect = array(
	teamId => "bpCt.teamId",
);
$whereClause = "bpCt.requestStamp > bpCt.successStamp OR bpCt.successStamp IS NULL ORDER BY bpCt.teamId";
$arCrawlTeams = $utfMysql->mysqlMultiSELECT($table, $arSelect, $whereClause);

//echo "<pre>";
//print_r($arCrawlTeams);
//echo "</pre>";


if ( date("md") >= 800 ) {
	// Herbst
	$bpSeason = date("y");
} else {
	//Frühling
	$bpSeason = date("y")-1;
}
$urlTeam       = "https://www.basketplan.ch/findTeamById.do?teamId=%s&perspective=de_default";

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
 * STEP 6 - PARSE ALL TEAMS OF GAMES WITH CLUBS OF THE BVN REGIO INVOLVED
 * Link: https://www.basketplan.ch/findTeamById.do?teamId=%s&perspective=de_default
 * Result: All Info of Teams
 */
$arContainerTeam = array();

// Beschreibungen der Teams
foreach ($arCrawlTeams as $crawlTeam) {
	$teamId = $crawlTeam['teamId'];
	// Seiten laden
	//echo sprintf ($urlTeam, $crawlTeam);
	$arLoad = load(sprintf ($urlTeam, $teamId), $arLoadOptions);
	$htmlBody = $arLoad['body'];
	
	// Verbindungen zu Ligen und Hallen auslesen
	$arCrawlReturn = crawlPage($htmlBody, "team");
	if(is_array($arCrawlReturn)) {
		$arConnectLeagues =  $arCrawlReturn['leagueHoldingId'];
		$arConnectLocations =  $arCrawlReturn['location'];
	}
	
	if(is_array($arConnectLeagues) && count($arConnectLeagues) != 0) {
		foreach ($arConnectLeagues as $league) {
			echo $bpSeason.":".$teamId."/ ".$league."<br>";
			$relTable = "bpRelTeamLeague";
			$arSelect = array(
				season => "season",
				teamId => "teamId",
				leagueHoldingId => "leagueHoldingId"
			);
			$whereClause = " season = '".$bpSeason."'";
			$whereClause.= " AND teamId = '".$teamId."'";
			$whereClause.= " AND leagueHoldingId = '".$league."'";
			$arCheckTeam = $utfMysql->mysqlSELECT($relTable, $arSelect, $whereClause);
			$arInsert = array(
				season => $bpSeason,
				teamId => $teamId,
				leagueHoldingId => $league
			);
			if(is_array($arCheckTeam)) {
				//$myTeamId = $utfMysql->mysqlUPDATE($relTable, $arInsert, $whereClause);
			} else {
				$myTeamId = $utfMysql->mysqlINSERT($relTable, $arInsert);
			}
		}
	}
	/* Auswärtsspiele rausnehmen
	if(is_array($arConnectLocations) && count($arConnectLocations) != 0) {
		foreach ($arConnectLocations as $location) {
			echo $bpSeason.":".$teamId."/ ".$location."<br>";
			$relTable = "bpRelTeamLocation";
			$arSelect = array(
				season => "season",
				teamId => "teamId",
				location => "location"
			);
			$whereClause = " season = '".$bpSeason."'";
			$whereClause.= " AND teamId = '".$teamId."'";
			$whereClause.= " AND location = '".$location."'";
			$arCheckTeam = $utfMysql->mysqlSELECT($relTable, $arSelect, $whereClause);
			if(is_array($arCheckTeam)) {
				//$myTeamId = $utfMysql->mysqlUPDATE($relTable, $arInsert, $whereClause);
			} else {
				$myTeamId = $utfMysql->mysqlINSERT($relTable, $arInsert);
			}
		}
	}
	*/
	
	
	
	// Inhalt der Seiten auswerten
	$arFormValueOptions = array(
		'page'	=> "team",
	);
	
	$arFormData = getFormValues($htmlBody, $arFormValueOptions);
	//echo "<pre>";
	//print_r($arFormData);
	//echo "</pre>";
	
	if(is_array($arFormData) && count($arFormData) != 0) {
		// Sind bereits Team Infos in der BVN Tabelle vorhanden?
		$table = "bpTeam";
		$arSelect = array(
			teamId => "teamId",
			version => "version"
		);
		$whereClause = " teamId = '".$teamId."'";
		$arCheckTeam = $utfMysql->mysqlSELECT($table, $arSelect, $whereClause);
		
		$arInsert = array ( 
			teamId => $arFormData["teamId"], 
			version => $arFormData["version"],
			clubId => $arFormData["clubId"],
			federationId => $arFormData["federationId"],
			uniqueTeamCode => $arFormData["uniqueTeamCode"],
			name => $arFormData["name"],
			shirtColor1 => $arFormData["shirtColor1"],
			shirtColor2 => $arFormData["shirtColor2"],
			playerCategory => $arFormData["playerCategory"],
			sex => $arFormData["sex"],
			national => $arFormData["national"],
		);
		
		if(is_array($arCheckTeam)) {
			$whereClause = "teamId = ".$teamId;
			$myTeamId = $utfMysql->mysqlUPDATE($table, $arInsert, $whereClause);
		} else {
			$myTeamId = $utfMysql->mysqlINSERT($table, $arInsert);
		}
		
		// Team als erfolgreich updated markieren
		$table = "bpCrawlTeam";
		$arUpdate = array(
			successStamp => "NOW()"
		);
		$whereClause = " teamId = '".$teamId."'";
		$myTeamId = $utfMysql->mysqlUPDATE($table, $arUpdate, $whereClause);
		
		// Team dem Array bearbeitet hinzufügen
		$arContainerTeam[] =  $arFormData ;
	}
}


$output = 0;

if( $output == 1 ) {
	echo "<table>\n";
	echo "  <tr><td colspan=\"".count($arContainerTeam['0'])."\">".count($arContainerTeam)." einzulesende Zeilen</td></tr>\n";
	foreach ($arContainerTeam as $arRow){
	  echo "  <tr>\n";
	  foreach ($arRow as $col){
		echo "    <td>".$col."</td>\n";
	  }
	  echo "  </tr>\n";
	}
	echo "</table>\n";
	
//	echo "<pre>";
//	print_r($arContainerTeam);
//	echo "</pre>";
}

$time_end = microtime(true);
$time = $time_end - $time_start;

echo "Basketplan Teams to BVN bpTeam Import in ".$time." seconds<br>\n";

?>
