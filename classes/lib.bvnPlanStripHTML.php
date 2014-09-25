<?php
    /* 
    Plugin Name: BVN Basketplan Import
    Plugin URI: http://www.bvn.ch 
    Description: Imports clubs, team and respective games from Basketplan to the BVN WebControl database 
    Author: Thomas Winter 
    Version: 0.9
    Author URI: http://www.houseofwinter.ch
	Encoding: UTF-8 for proper display of äöüéèà 
    */
function stripHTML($strHtmlBody, $arOptions=array()) {
    $default_options = array(
		'page'			=> 'games'
    );
    // Sets the default options.
    foreach($default_options as $opt=>$value) {
        if(!isset($arOptions[$opt])) $arOptions[$opt] = $value;
    }
	
	$debug_level = 0;
	$debug_site  = 'games'; // 'games', 'rank'

    $response = '';
	
	if ($debug_level == 1) {
		echo "<pre>";
		echo $strHtmlBody;
		echo "</pre>";
		exit;
	}
	
	$haystack = $strHtmlBody;
	if ( $arOptions['page'] == 'games' ) {
		$needle = '<!-- Space -->';
	} elseif ( $arOptions['page'] == 'showSearchGames' ) {
		$needle = '<table border="0" cellspacing="0" cellpadding="2" width="840" class="forms">';
	} elseif ( $arOptions['page'] == 'rank' ) {
		$needle = '<td style="padding-top: 1px; padding-left: 1px;">';
	}
	$strpos = strpos ( $haystack, $needle );
	if ($strpos !== false) {
		$stript_head = substr($strHtmlBody, $strpos);
				
		$haystack = $stript_head;
		if ( $arOptions['page'] == 'games' ) {
			$needle = '<!-- Errors -->';
		} elseif ( $arOptions['page'] == 'showSearchGames' ) {
			$needle = '</table>';
		}  elseif ( $arOptions['page'] == 'rank' ) {
			$needle = '<!-- Errors -->';
		}
		$strpos = strpos ( $haystack, $needle );
		$strlen = strlen ( $stript_head );
		$stript_foot = substr($stript_head, 0, $strpos);
	
		$stript_tags = strip_tags($stript_foot, '<table><tr><td><a>');
		
		if( $debug_site == 'games' && $debug_level == 3 ) {
			echo $stript_tags;
		}
		
		$clean_tr = preg_replace('/<tr[^>]*>/', '<tr>', $stript_tags);
		$clean_td = preg_replace('/<td[^>]*>/', '<td>', $clean_tr);
		
		# convert to unix new lines
		$convert_unix_new_lines = preg_replace('/\r\n/', "\n", $clean_td);
		# remove extra new lines
		$remove_extra_new_lines = preg_replace('/\n+/', "\n", $convert_unix_new_lines);
	
		# remove extra whitespace in lines
		$remove_extra_whitespace = preg_replace('/\s\s+/', ' ', $remove_extra_new_lines);
	
		//debug
		if ( $arOptions['page'] == 'games' && $arOptions['tour'] == 1 && $debug_site == 'games' && $debug_level == 4 ) {
			echo $remove_extra_whitespace;
			echo "<br>\n<br>\n";
		} elseif ( $arOptions['page'] == 'rank' && $debug_site == 'rank' && $debug_level == 4 ) {
			echo $remove_extra_whitespace;
			echo "<br>\n<br>\n";
		}
	
		/*
		http://www.php.net/preg_replace
		http://www.regular-expressions.info/tutorial.html
		
		$string = 'This is some [random] data. [some] should be removed.';
		echo preg_replace('/\\[[^\\]]*\\]/', '', $string);
		
		// /[[^]]*]/ 
		// Ein [ gefolgt von irgend etwas, nur keinem ] bis zum einem ]
		
		What this does is to search for square brackets in a string, and replaces them (along with anything between them) with an empty string (the next parameter).
		*/
		
		$string_prepare_1 = preg_replace('/<table[^>]*>/', '', $remove_extra_whitespace);
		$string_prepare_2 = str_replace('</tr>', '',$string_prepare_1);
		// leave the end tags to get clean data
		//$string_prepare_3 = str_replace('</td>', '',$string_prepare_2);
		$string_prepare_3 = str_replace('</a>', '',$string_prepare_2);
		$string_prepare_4 = str_replace('</table>', '',$string_prepare_3);
		
		
		$arTable = 0;
		$arTable = array();
		$row=0;
		foreach (explode("<tr>", $string_prepare_4) as $dataset) {
			$id=0;
			foreach (explode("<td>", $dataset) as $data) {
				// erster Teil immer leer
				if ($id > 0) {
					// <td> und </td> umschliessen die gewünschten Werte. Gröl, der nach </td> steht nicht in der Tabelle: daher nur ar[0] importieren
					$arValue = explode("</td>", $data);
					//echo "[".$row."]:[".$id."]".$arValue[0]."<br>\n";
					//if(preg_match_all("/$regexp/siU", $strHtmlBody, $arMatches)) {
					// Falls ein Link im <td> steht
					$haystack = $arValue[0];
					if(strpos($haystack, "<a") !== FALSE) {
						// Wir haben einen Link im Heuhaufen
						if(strpos($haystack, "<a") == 1) {
							$needleGym    = "locationId";
							$needleTeam   = "teamId";
							if(strpos($haystack, $needleGym) !== FALSE) {
								//<a href="/findLocationById.do;jsessionid=20430EA4274ECEE64189AF3D926BFD8A?locationId=LLLL" class="a_txt8">Halle&nbsp;(Ort)
								$args = substr($haystack, strpos($haystack,"?")+1);
								//locationId=822" class="a_txt8">Halle&nbsp;(Ort)
								parse_str( substr($args, 0, strpos($args,'"')), $arArgs);
								$arTable[$row][$id] = $arArgs['locationId'];
								$id++;
							} 
							if(strpos($haystack, $needleTeam) !== FALSE) {
								//<a href="/findTeamById.do;jsessionid=20430EA4274ECEE64189AF3D926BFD8A?teamId=TTTT" class="a_txt8">Team
								$args = substr($haystack, strpos($haystack,"?")+1);
								//teamId=TTTT" class="a_txt8">Team
								parse_str( substr($args, 0, strpos($args,'"')), $arArgs);
								$arTable[$row][$id] = $arArgs['teamId'];
								$id++;
							}
							$myValue = substr($haystack, strpos($haystack, ">")+1, strlen($haystack)-strpos($haystack, ">"));
							$arTable[$row][$id] = str_replace("\n", " ", trim($myValue));
						} else {
							$myValue = substr($haystack, 0, strpos($haystack, "<a"));
							if(trim($myValue) == "Halle") {
								$arTable[$row][$id] = "locationId";
								$id++;
							}
							if(trim($myValue) == "Heimmannschaft") {
								$arTable[$row][$id] = "homeId";
								$id++;
							}
							if(trim($myValue) == "Gastmannschaft") {
								$arTable[$row][$id] = "awayId";
								$id++;
							}
							$myValue = substr($haystack, 0, strpos($haystack, "<a"));
							$arTable[$row][$id] = str_replace("\n", " ", trim($myValue));
						}
					} else {
						$arTable[$row][$id] = str_replace("\n", " ", trim($arValue[0]));
					}
					//echo "(".strpos($haystack, "<a").").[".strlen($haystack)."]:[".strpos($haystack, ">")."]".$haystack."<br>\n";
				}
				$id++;
			}
			//array_pop($arTable[$row]);
			//if (empty($arTable[$row][0])) $arTable[$row]
			$row++;
		}
		
		//debug
		if ($debug_level == 2) {
			echo "<pre>";
			print_r($arTable);
			echo "</pre>";
			exit;
		}
		
		array_shift($arTable);
		
		return $arTable;
	} else {
		return $arTable = array();
	}
}


/**
 * Remove HTML tags, including invisible text such as style and
 * script code, and embedded objects.  Add line breaks around
 * block-level tags to prevent word joining after tag removal.
 */
 
function strip_html_tags( $text ) {
    $text = preg_replace(
        array(
          // Remove invisible content
            '@<head[^>]*?>.*?</head>@siu',
            '@<style[^>]*?>.*?</style>@siu',
            '@<script[^>]*?.*?</script>@siu',
            '@<object[^>]*?.*?</object>@siu',
            '@<embed[^>]*?.*?</embed>@siu',
            '@<applet[^>]*?.*?</applet>@siu',
            '@<noframes[^>]*?.*?</noframes>@siu',
            '@<noscript[^>]*?.*?</noscript>@siu',
            '@<noembed[^>]*?.*?</noembed>@siu',
          // Add line breaks before and after blocks
            '@</?((address)|(blockquote)|(center)|(del))@iu',
            '@</?((div)|(h[1-9])|(ins)|(isindex)|(p)|(pre))@iu',
            '@</?((dir)|(dl)|(dt)|(dd)|(li)|(menu)|(ol)|(ul))@iu',
            '@</?((table)|(th)|(td)|(caption))@iu',
            '@</?((form)|(button)|(fieldset)|(legend)|(input))@iu',
            '@</?((label)|(select)|(optgroup)|(option)|(textarea))@iu',
            '@</?((frameset)|(frame)|(iframe))@iu',
        ),
        array(
            ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ',
            "\n\$0", "\n\$0", "\n\$0", "\n\$0", "\n\$0", "\n\$0",
            "\n\$0", "\n\$0",
        ),
        $text );
    return strip_tags( $text );
}

?>
