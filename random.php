<?php
require_once('Parsedown.php'); // https://github.com/erusev/parsedown

$cr = $_GET["cr"];
?>

<form action="random.php" method="get">
 <h3>Summon Monster</h3>
 <p>CR: <input type="text" name="cr" value="<?php echo $cr; ?>"/></p>
 <p><input type="submit" /></p>
</form>
<hr/>

<?php
// Render fractional CR as decimal, ie .125, .25, .5
if ($cr) {
	switch($cr) {
		case "1/8":
		case "⅛":
			$cr = .125;
			break;
		case "1/4":
		case "¼":
			$cr = .25;
			break;
		case "1/2":
		case "½":
			$cr = .5;
			break;
		default:
			break;
	}
	
	$monster = random_monster_by_cr($cr);
	echo $monster;
	echo '<hr/>';
}
?>

<strong>Data: </strong><a href="https://open5e.com" target="_blank">open5e.com</a>
<br/>
<strong>Code: </strong><a href="https://github.com/nstefanski/random_5e_monster" target="_blank">github.com/nstefanski/random_5e_monster</a>
<br/>

<?php
$url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 'https://' : 'http://';
$url .= $_SERVER['HTTP_HOST'];
echo '<strong>Home: </strong><a href="' . $url . '">' . $_SERVER['HTTP_HOST'] . '</a>';

function random_monster_by_cr($cr) {
	$uri = "https://api.open5e.com/v1/monsters/?limit=1&cr=$cr";
	$monster_list = get_monsters($uri);
	$random = rand(1, $monster_list->count);

	$uri = "https://api.open5e.com/v1/monsters/?limit=1&cr=$cr&page=$random";
	$monster_list = get_monsters($uri);
	$monster = $monster_list->results[0];
	$md = markdown_monster($monster);
	
	$Parsedown = new Parsedown();
	return $Parsedown->text($md);
}

function get_monsters($uri) {

	$curl = curl_init();

	curl_setopt_array($curl, array(
		CURLOPT_URL => $uri,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_ENCODING => "",
		CURLOPT_MAXREDIRS => 10,
		CURLOPT_TIMEOUT => 30,
		CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		CURLOPT_CUSTOMREQUEST => "GET",
		CURLOPT_HTTPHEADER => array(
			"cache-control: no-cache",
			"content-type: application/x-www-form-urlencoded"
		),
	));

	$response = curl_exec($curl);
	$err = curl_error($curl);

	curl_close($curl);
	
	if ($err) {
		//echo "cURL Error #:" . $err;
	} else {
		if ($response) {
			$decoded = json_decode($response);
		}

		return $decoded;
	}
}

function markdown_monster($monster) {
	$md = "";
	
	if ($monster->group) {
		$md .= "## $monster->group\n";
	}
	if ($monster->desc) {
		$md .= "## $monster->name\n";
		$md .= "$monster->desc\n";
	}
	
	$md .= "### $monster->name\n";
	$md .= "_$monster->size $monster->type";
	$md .= ($monster->subtype <> "") ? " ($monster->subtype)" : "";
	$md .= ($monster->alignment <> "") ? ", $monster->alignment" . "_\n\n" : "_\n\n";
	
	$md .= "**Armor Class** $monster->armor_class";
	$md .= ($monster->armor_desc <> "") ? " _($monster->armor_desc)_  \n" : "  \n";
	$md .= "**Hit Points** $monster->hit_points";
	$md .= ($monster->hit_dice) ? " ($monster->hit_dice)  \n" : "  \n";
	
	$md .= "**Speed** ";
	if ($monster->speed->walk) {
		$md .= $monster->speed->walk . " ft.";
		unset($monster->speed->walk);
	} else {
		$md .= "0 ft.";
	}
	foreach ($monster->speed as $mvt => $dist) {
		$md .= ", $mvt $dist ft.";
	}
	$md .= "  \n";
	
	$md .= format_monster_saves($monster);
	
	$skills_total = count(get_object_vars($monster->skills));
	if ($skills_total > 0 || $monster->perception <> "") {
		if ($monster->skills->perception && $monster->skills->perception <> $monster->perception){
			$md .= "**Perception & Skills mismatch**  \n"; // not sure if this is possible?
			$monster->skills->perception = $monster->perception;
		}
		$md .= "**Skills** ";
		foreach ($monster->skills as $skill => $mod) {
			$ct++;
			$mod = ($mod >= 0) ? "+$mod" : $mod;
			$md .= ucwords($skill) . " $mod";
			if ($ct < $skills_total) {
				$md .= ", ";
			} else {
				$md .= "  \n";
			}
		}
	}
	
	$md .= ($monster->damage_vulnerabilities <> "") ? "**Damage Vulnerabilities** $monster->damage_vulnerabilities  \n" : "";
	$md .= ($monster->damage_resistances <> "") ? "**Damage Resistances** $monster->damage_resistances  \n" : "";
	$md .= ($monster->damage_immunities <> "") ? "**Damage Immunities** $monster->damage_immunities  \n" : "";
	$md .= ($monster->condition_immunities <> "") ? "**Condition Immunities** $monster->condition_immunities  \n" : "";
	$md .= ($monster->senses <> "") ? "**Senses** $monster->senses  \n" : "";
	$md .= ($monster->languages <> "") ? "**Languages** $monster->languages  \n" : "";
	$md .= "**Challenge** $monster->challenge_rating (" . number_format( get_monster_xp($monster->cr, $monster->actions) ) . " XP)  \n\n";
	
	foreach ($monster->special_abilities as $ability) {
		$ability->desc = ($ability->name == "Spellcasting") ? str_replace("•","-",$ability->desc) : $ability->desc;
		$md .= "**_$ability->name._** $ability->desc\n\n";
	}
	
	if ($monster->spell_list) {
		// these are links to spell descriptions
	}
	
	$md .= ($monster->actions) ? "#### Actions\n" : "";
	foreach ($monster->actions as $action) {
		$md .= "**_$action->name._** $action->desc\n\n";
	}
	
	$md .= ($monster->reactions) ? "#### Reactions\n" : "";
	foreach ($monster->reactions as $reaction) {
		$md .= "**_$reaction->name._** $reaction->desc\n\n";
	}
	
	$md .= ($monster->legendary_actions) ? "#### Legendary Actions\n$monster->legendary_desc\n\n" : "";
	foreach ($monster->legendary_actions as $action) {
		$md .= "**_$action->name._** $action->desc\n\n";
	}
	
	$md .= "_Source: [$monster->document__title]($monster->document__url)_  \n";
	$md .= "*API: https://api.open5e.com/v1/monsters/?slug__iexact=$monster->slug*";
	
	return $md;
}

function format_monster_saves($monster) {
	$abilities = array('strength','dexterity','constitution','intelligence','wisdom','charisma');
	$saves = array();
	
	//$md = "| STR | DEX | CON | INT | WIS | CHA |\r\n";
	//$md .= "|-----|-----|-----|-----|-----|-----|\r\n";
	
	// Render this table in html because Parsedown is having trouble with the line breaks
	$md = "<table>
	         <thead>
	           <tr>
	             <th>STR</th>
	             <th>DEX</th>
	             <th>CON</th>
	             <th>INT</th>
	             <th>WIS</th>
	             <th>CHA</th>
	           </tr>
	         </thead>
	         <tbody>
	           <tr>";
	
	foreach ($abilities as $ability) {
		$score = $monster->$ability;
		$md .= "<td>$score ";
		
		$mod = floor(($score - 10)/2);
		if ($mod >= 0) {
			$md .= "(+$mod)</td>";
		} else {
			$md .= "($mod)</td>";
		}
		
		// get all set saves to add after the table
		$save = $ability . "_save";
		if ( $monster->$save <> "" ) {
			$saves[] = ucfirst( substr($ability,0,3) ) . ' +' . $monster->$save;
		}
	}
	$md .= "    </tr>
	          </tbody>
	        </table>\n\n";
	//$md .= "|\n\n";
	
	if ($saves) {
		$md .= "**Saving Throws** $saves[0]";
		for ($i = 1; $i < count($saves); $md .= ", $saves[$i]", $i++ );
		$md .= "  \n";
	}
	
	return $md;
}

function get_monster_xp($cr, $actions) {
	// Monster XP is not regular, so we just need to encode all the XP values per CR
	switch ($cr) {
		case 0: 
			// Most CR 0 creatures are worth 10 XP, but a few are worth 0 XP. In the SRD, 
			// this is limited to animals like the frog with no listed actions.
			if($actions) {
				return 10;
			} else {
				return 0;
			}
		case .125:
			return 25;
		case .25:
			return 50;
		case .5:
			return 100;
		case 1:
			return 200;
		case 2:
			return 450;
		case 3:
			return 700;
		case 4:
			return 1100;
		case 5:
			return 1800;
		case 6:
			return 2300;
		case 7:
			return 2900;
		case 8:
			return 3900;
		case 9:
			return 5000;
		case 10:
			return 5900;
		case 11:
			return 7200;
		case 12:
			return 8400;
		case 13:
			return 10000;
		case 14:
			return 11500;
		case 15:
			return 13000;
		case 16:
			return 15000;
		case 17:
			return 18000;
		case 18:
			return 20000;
		case 19:
			return 22000;
		case 20:
			return 25000;
		case 21:
			return 33000;
		case 22:
			return 41000;
		case 23:
			return 50000;
		case 24:
			return 62000;
		case 25:
			return 75000;
		case 26:
			return 90000;
		case 27:
			return 105000;
		case 28:
			return 120000;
		case 29:
			return 135000;
		case 30:
			return 155000;
		default:
			return null;
			break;
	}
	return null;
}
