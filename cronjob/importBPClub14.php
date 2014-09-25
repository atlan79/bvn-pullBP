<?php
    /* 
    Plugin Name: BVN Basketplan Import
    Plugin URI: http://www.bvn.ch 
    Description: Imports clubs, team and respective games from Basketplan to the BVN WebControl database 
    Author: Thomas Winter 
    Version: 0.5
    Author URI: http://www.houseofwinter.ch
	Encoding: UTF-8 for proper display of äöüéèà
    */
//header('Content-type: text/html; charset=utf-8');
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

echo "BP Import to BVN DB<br>\n";

require_once($root_path."/classes/lib.mysql.utf8.php");
$utfMysql = new utfMysql();

/*
 *  Importing data from WebCrawler to BVN Database
 *
 *  Data received in UTF-8 and stored in UTF-8
 * 
 *  Data send in $arContainerTeam;
 */

$output = 1;

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
	exit;
}

// Tabelle leeren und alle Spiele einfügen
$utfMysql->mysqlTRUNCATE("bpTeam");

$table = "bpTeam";

foreach ($arContainerTeam as $team) {
	list($day, $month, $year) = split('[/.-]', $team["11"]);
    $date = "$year-$month-$day";
	if ($date == '--') {
	   $date = 'NULL';
	}
    
	//$arSearch  = array ('Ä','Ö','Ü','É','È','À','Â');
	//$arReplace = array ('ä','ö','ü','é','è','à','â');
	//$Zeile1 = ucfirst(strtolower( str_replace ( $arSearch , $arReplace , $address["5"]) ));
	// Für Grenzach-Wyhlen 
	//$OrtNorm = str_replace( '- ', '-', ucwords( strtolower( str_replace('-', '- ', str_replace ( $arSearch , $arReplace , $address["8"] ) ) ) ) );
	
	$arInsert = array ( 
		teamId => $team["teamId"], 
		version => $team["version"],
		clubId => $team["clubId"],
		federationId => $team["federationId"],
		uniqueTeamCode => $team["uniqueTeamCode"],
		name => $team["name"],
		shirtColor1 => $team["shirtColor1"],
		shirtColor2 => $team["shirtColor2"],
		playerCategory => $team["playerCategory"],
		sex => $team["sex"],
		national => $team["national"],
	);
	$idTeam = $utfMysql->mysqlINSERT($table, $arInsert);
}

$time_end = microtime(true);
$time = $time_end - $time_start;

echo "Basketplan Teams to BVN bpTeam Import in ".$time." seconds<br>\n";


?>
