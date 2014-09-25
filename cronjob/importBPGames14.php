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
 *  Reading Club Information from BVN DB
 *
 *  Data received in ANSI and stored in UTF-8
 * 
 */
$table = "bpCrawlClub as bpCc";
$arSelect = array(
	clubId => "bpCc.clubId",
);
$whereClause = "bpCc.requestStamp > bpCc.successStamp OR bpCc.successStamp IS NULL ORDER BY bpCc.clubId";
$arCrawlClubs = $utfMysql->mysqlMultiSELECT($table, $arSelect, $whereClause);

//echo "<pre>";
//print_r($arCrawlClubs);
//echo "</pre>";


if ( date("md") >= 800 ) {
	// Herbst
	$bpSeason = date("y");
} else {
	//Frühling
	$bpSeason = date("y")-1;
}
$urlClubGames  = "https://www.basketplan.ch/searchGames.do?federationId=1&clubId=%s&from=01.08.%s&sortDirection=u&sortedColum=date&command=sort&sortProperty=getDateTime&sortProperty=getLeagueHolding&sortProperty=getLeague&sortProperty=getShortName&perspective=de_default";

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
 * STEP 4 - PARSE ALL GAMES OF CLUB IN THE BVN REGIO BY CLUB-ID
 * Link: https://www.basketplan.ch/searchGames.do?federationId=1&clubId=CCCC&from=01.07.14&sortDirection=u&sortedColum=date&command=sort&sortProperty=getDateTime&sortProperty=getLeagueHolding&sortProperty=getLeague&sortProperty=getShortName&perspective=de_default
 * Result: Full amount of Gyms links, full amount of Team links, full amount of game schedules
 */
$arCrawlLocations = array();
$arCrawlTeams = array();
$arContainerGame = array();

 
// Spiele der Vereine, um auch Inter und National zu crawlen
foreach ($arCrawlClubs as $crawlClub) {
	// Seiten laden
	//echo sprintf ($urlClubGames, $crawlClub["clubId"], $bpSeason);
	$arLoad = load(sprintf ($urlClubGames, $crawlClub["clubId"], $bpSeason), $arLoadOptions);
	$htmlBody = $arLoad['body'];
	//echo $htmlBody;
	
	// Links der Seiten auslesen
	$arCrawlReturn = crawlPage($htmlBody, "games");
	if(is_array($arCrawlReturn)) {
		$arCrawlLocations =  array_merge( $arCrawlLocations,  $arCrawlReturn['gym'] );
		$arCrawlTeams = array_merge( $arCrawlTeams, $arCrawlReturn['team']);
	}
	
	// Inhalt der Seiten auswerten
	$arStripOptions = array(
		'page'	=> "showSearchGames",
	);
	
	$arStripData = stripHTML($htmlBody, $arStripOptions);
	if(is_array($arStripData)) {
		$arContainerGame = array_merge( $arContainerGame, $arStripData );
	}
}
$arCrawlLocations  = array_values( array_filter( array_unique ( $arCrawlLocations ) ) );
$arCrawlTeams = array_values( array_filter( array_unique ( $arCrawlTeams) ) );
//echo "<pre>";
//print_r($arCrawlLocations);
//print_r($arCrawlTeams);
//print_r($arContainerGame);
//echo "</pre>";


foreach ($arCrawlLocations as $location) {
	$locationId = 0;
	$job = "";
	
	// Ist die Location in der BVN Tabelle vorhanden?
	$table = "bpCrawlLocation";
	$arSelect = array(
		locationId => "locationId"
	);
	$whereClause = " locationId = '".$location."'";
	$arCheckLocation = $utfMysql->mysqlSELECT($table, $arSelect, $whereClause);
	
	if(is_array($arCheckLocation)) {
		$job = "update";
		$locationId = $location;
	}
	$arInsert = array(
		locationId => $location,
		requestStamp => "NOW()"
	);
	if($job == "update") {
		$whereClause = "locationId = ".$location;
		$locationId = $utfMysql->mysqlUPDATE($table, $arInsert, $whereClause);
	} else {
		$locationId = $utfMysql->mysqlINSERT($table, $arInsert);
	}
}

foreach ($arCrawlTeams as $team) {
	$teamId = 0;
	$job = "";
	
	// Ist das Team in der BVN Tabelle vorhanden?
	$table = "bpCrawlTeam";
	$arSelect = array(
		teamId => "teamId"
	);
	$whereClause = " teamId = '".$team."'";
	$arCheckTeam = $utfMysql->mysqlSELECT($table, $arSelect, $whereClause);
	
	if(is_array($arCheckTeam)) {
		$arInsert = array(
			requestStamp => "NOW()"
		);
		$whereClause = "teamId = ".$team;
		$teamId = $utfMysql->mysqlUPDATE($table, $arInsert, $whereClause);
	} else {
		$arInsert = array(
			teamId => $team,
			requestStamp => "NOW()"
		);
		$teamId = $utfMysql->mysqlINSERT($table, $arInsert);
	}
}


$output = 0;

if( $output == 1 ) {
	echo "<table border='1'>\n";
	echo "  <tr><td colspan=\"".count($arContainerGame['0'])."\">".count($arContainerGame)." einzulesende Zeilen</td></tr>\n";
	foreach ($arContainerGame as $arRow){
	  echo "  <tr>\n";
	  foreach ($arRow as $col){
		echo "    <td>".$col."</td>\n";
	  }
	  echo "  </tr>\n";
	}
	echo "</table>\n";
	
//	echo "<pre>";
//	print_r($arContainerGame);
//	echo "</pre>";

}

$arContainerSpiel = array();
foreach ($arContainerGame as $arGame) {
	if ($arGame[1] == "Datum") {
		// Neue Titelzeile, kann neue Spaltenreihenfolge zur Folge haben
		//echo "reconfigurating headers";
		$arRowConf['gametime'] = array_search('Datum', $arGame );
		$arRowConf['fed'] = array_search('Ver.', $arGame );
		$arRowConf['league'] = array_search('Meist.', $arGame );
		$arRowConf['round'] = array_search('R', $arGame );
		$arRowConf['matchNr'] = array_search('SpielNr.', $arGame );
		$arRowConf['location'] = array_search('Halle', $arGame );
		$arRowConf['home'] = array_search('Heimmannschaft', $arGame );
		$arRowConf['away'] = array_search('Gastmannschaft', $arGame );
		$arRowConf['score'] = array_search('Ergebnis', $arGame );
		$arRowConf['FF'] = array_search('FF', $arGame );
		//echo "<pre>";
		//print_r($arRowConf);
		//echo "</pre>";
	} else {
		//echo "Data Row";
		$arBeginn = explode (" ", $arGame[$arRowConf['gametime']]);
		// "So 19.09.10 16:00"
		// "31.12.99 23:59"
		// "31.12.1999 17h30"
		array_shift($arBeginn);
		$arBegDate = explode (".", $arBeginn[0]);
		$arBeginn[0] = implode( "-", array_reverse($arBegDate));
		$arBeginn[1] = str_replace( "h", ":", $arBeginn[1]);
		$arLocation = explode("&nbsp;", $arGame[$arRowConf['location']]);
		$location = $arLocation[0];
		if($arGame[$arRowConf['score']] == "nicht gespielt") {
			$score = $arGame[$arRowConf['score']];
			$homescore = 0;
			$awayscore = 0;
		} else {
			$scoreIndex = $arRowConf['score'];
			$scoreIndexHome = $arRowConf['score'];
			$scoreIndexDouble = $arRowConf['score']+1;
			$scoreIndexAway = $arRowConf['score']+2;
			$score = $arGame[$scoreIndexHome].$arGame[$scoreIndexDouble].$arGame[$scoreIndexAway];
			$homescore = $arGame[$scoreIndexHome];
			$awayscore = $arGame[$scoreIndexAway];
		}
		$arMatchNr = explode ("-", $arGame[$arRowConf['matchNr']]);
		$matchNr = $arMatchNr[1];
				
		$arSpiel['gameDate'] = "20".$arBeginn[0];
		$arSpiel['gameTime'] = $arBeginn[1];
		$arSpiel['fed']      = $arGame[$arRowConf['fed']];
		$arSpiel['league']   = $arGame[$arRowConf['league']];
		$arSpiel['round']    = $arGame[$arRowConf['round']];
		$arSpiel['matchNr']  = $matchNr;
		$arSpiel['location'] = utf8_encode($arGame[$arRowConf['location']]);
		$arSpiel['gym']      = utf8_encode($location);
		$arSpiel['home']     = utf8_encode($arGame[$arRowConf['home']]);
		$arSpiel['away']     = utf8_encode($arGame[$arRowConf['away']]);
		$arSpiel['score']    = $score;
		$arSpiel['homeScore']= $homescore;
		$arSpiel['awayScore']= $awayscore;
		$arSpiel['FF']       = $arGame[$arRowConf['FF']];
		$arSpiel['season']   = $bpSeason;
		
		$arContainerSpiel[] = $arSpiel;
		//echo "<pre>";
		//print_r($arSpiel);
		//echo "</pre>";

 
	
		$bpSchedule = 0;
		$job = "";
		
		// Ist das Spiel in der BVN Tabelle vorhanden?
		$table = "bpSchedule";
		$arSelect = array(
			matchNr => "matchNr"
		);
		$whereClause = " matchNr = '".$arSpiel['matchNr']."'";
		$arCheckMatchNr = $utfMysql->mysqlSELECT($table, $arSelect, $whereClause);
		
		if(is_array($arCheckMatchNr)) {
			$bpSchedule = $utfMysql->mysqlUPDATE($table, $arSpiel, $whereClause);
		} else {
			$bpSchedule = $utfMysql->mysqlINSERT($table, $arSpiel);
		}
	}
}


if( $output == 2 ) {
	echo "<table border='1'>\n";
	echo "  <tr><td colspan=\"".count($arContainerSpiel['0'])."\">".count($arContainerSpiel)." einzulesende Zeilen</td></tr>\n";
	foreach ($arContainerSpiel as $arRow){
	  echo "  <tr>\n";
	  foreach ($arRow as $col){
		echo "    <td>".$col."</td>\n";
	  }
	  echo "  </tr>\n";
	}
	echo "</table>\n";
	
//	echo "<pre>";
//	print_r($arContainerSpiel);
//	echo "</pre>";
}



$time_end = microtime(true);
$time = $time_end - $time_start;

echo "BVN Games imported in ".$time." seconds<br>\n";

?>
