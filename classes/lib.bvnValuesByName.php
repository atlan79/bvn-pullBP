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
function getFormValues($strHtmlBody, $arOptions=array()) {
    $default_options = array(
		'page'			=> 'team',
    );
    // Sets the default options.
    foreach($default_options as $opt=>$value) {
        if(!isset($arOptions[$opt])) $arOptions[$opt] = $value;
    }
	
	$debug_level = 0;
	
	$response = '';
	
	if ($debug_level == 1) {
		echo "<pre>";
		echo $strHtmlBody;
		echo "</pre>";
		exit;
	}
	
	$haystack = $strHtmlBody;
	if ( $arOptions['page'] == 'location' ) {
		$needle = '<form name="editLocationForm" method="post" action="/saveLocation.do">';
	} elseif ( $arOptions['page'] == 'team' ) {
		$needle = '<form name="editTeamForm" method="post" action="/saveTeam.do">';
	}
	$strpos = strpos ( $haystack, $needle );
	if ($strpos !== false) {
		$stript_head = substr($strHtmlBody, $strpos);
		$haystack = $stript_head;
		
		if ( $arOptions['page'] == 'location' ) {
			$needle = '</form>';
		} elseif ( $arOptions['page'] == 'team' ) {
			$needle = '</form>';
		}
		$strpos = strpos ( $haystack, $needle );
		$strlen = strlen ( $stript_head );
		$stript_foot = substr($stript_head, 0, $strpos);
		
		$clean_tr = preg_replace('/<tr[^>]*>/', '<tr>', $stript_foot);
		$clean_td = preg_replace('/<td[^>]*>/', '<td>', $clean_tr);
		
		# convert to unix new lines
		$convert_unix_new_lines = preg_replace('/\r\n/', "\n", $clean_td);
		# remove extra new lines
		$remove_extra_new_lines = preg_replace('/\n+/', "\n", $convert_unix_new_lines);
	
		# remove extra whitespace in lines
		$remove_extra_whitespace = preg_replace('/\s\s+/', ' ', $remove_extra_new_lines);
		
		if ($debug_level == 2) {
			echo "<pre>";
			echo $remove_extra_whitespace;
			echo "</pre>";
			exit;
		}
		
		$dom = new DOMDocument;
		$dom->loadHTML($remove_extra_whitespace);
		
		$xpath = new DOMXpath($dom);
		
		if ( $arOptions['page'] == 'location' ) { 
			$arNames = array("locationId", "federationId", "shortName", "name", "line1", "zip", "city", "responsableName", "responsablePhoneNumber" );
		} elseif ( $arOptions['page'] == 'team' ) {
			$arNames = array("teamId", "version", "clubId", "federationId", "uniqueTeamCode", "name", "shirtColor1", "shirtColor2", "playerCategory", "sex", "national" );
		}	
		
		foreach ($arNames as $name) {
			$nodes = $xpath->query('//input[@name="'.$name.'"]');
			$arNodes[$name] = trim($nodes->item(0)->getAttribute('value'));
		}

		if ($debug_level == 3) {
			echo "<pre>";
			print_r( $arNodes );
			echo "</pre>";
			exit;
		}


		return $arNodes;
	} else {
		return $arNodes = array();
	}
}
?>
