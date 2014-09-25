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
function crawlPage ($strHtmlBody, $page) {
	
	$regexp = "<a\s[^>]*href=(\"??)([^\" >]*?)\\1[^>]*>(.*)<\/a>"; 
	if(preg_match_all("/$regexp/siU", $strHtmlBody, $arMatches)) { 
		// $matches[2] = array of link addresses 
		// $matches[3] = array of link text - including HTML code 
		//echo "<pre>";
		//print_r($arMatches[2]);
		//echo "</pre>";
		$needleLeague = "leagueHoldingId=";
		$needleGym    = "findLocationById.do"; //locationId=
		$needleTeam   = "findTeamById.do";     //teamId=
		foreach($arMatches[2] as $match) {
			
			if($page == "base") {
				if(strpos($match, $needleLeague) !== FALSE) {
					// möglicher Treffer mit showLeagueSchedule.do?leagueHoldingId=4507&federationId=1
					//echo $match;
					parse_str( substr($match, 22), $arArgs);
					//echo "<pre>";
					//print_r($arArgs);
					//echo "</pre>";
					$arResult['league'][] = $arArgs['leagueHoldingId'];
				}
			}
			
			if($page == "games") {
				if(strpos($match, $needleGym) !== FALSE) {
					// möglicher Treffer mit findLocationById.do?locationId=958
					//echo $match;
					//parse_str( substr($match, 20), $arArgs);
					parse_str( substr($match, strpos($match,"?")+1), $arArgs);
					//echo "<pre>";
					//print_r($arArgs);
					//echo "</pre>";
					$arResult['gym'][] = $arArgs['locationId'];
				}
				
				if(strpos($match, $needleTeam) !== FALSE) {
					// möglicher Treffer mit findTeamById.do?teamId=4290
					//echo $match;
					//parse_str( substr($match, 16), $arArgs);
					parse_str( substr($match, strpos($match,"?")+1), $arArgs);
					//echo "<pre>";
					//print_r($arArgs);
					//echo "</pre>";
					$arResult['team'][] = $arArgs['teamId'];
				}
			}
			
			if($page == "team") {
				if(strpos($match, $needleLeague) !== FALSE) {
					// möglicher Treffer mit showTeamOverview.do?teamId=4303&leagueHoldingId=4534
					//echo $match;
					parse_str( substr($match, strpos($match,"?")+1), $arArgs);
					//echo "<pre>";
					//print_r($arArgs);
					//echo "</pre>";
					if (isset($arArgs['amp;leagueHoldingId'])) {
						$arArgs['leagueHoldingId'] = $arArgs['amp;leagueHoldingId'];
						unset($arArgs['amp;leagueHoldingId']);
					}
					$arResult['teamId'][] = $arArgs['teamId'];
					$arResult['leagueHoldingId'][] = $arArgs['leagueHoldingId'];
				}
				if(strpos($match, $needleGym) !== FALSE) {
					// möglicher Treffer mit findLocationById.do?locationId=958
					//echo $match;
					//parse_str( substr($match, 20), $arArgs);
					parse_str( substr($match, strpos($match,"?")+1), $arArgs);
					//echo "<pre>";
					//print_r($arArgs);
					//echo "</pre>";
					$arResult['location'][] = $arArgs['locationId'];
				}
			}
    	}
	}
	//echo "<pre>";
	//print_r($arResult);
	//echo "</pre>";
	return $arResult;
}
?>
