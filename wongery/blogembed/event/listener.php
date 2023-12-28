<?php

namespace wongery\blogembed\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use mysqli;

define("BLOGSTARTTAG", '<blogembed>');
define("BLOGENDTAG", "</blogembed>");

class listener implements EventSubscriberInterface
{
	public static function getSubscribedEvents()
	{
		return [
			'core.text_formatter_s9e_configure_after' => 'configure_blogembed',
			'core.text_formatter_s9e_render_after' => 'parse_blogembed'
		];
	}

	public function configure_blogembed($event)
	{
		$configurator = $event['configurator'];

		// Let's unset any existing BBCode that might already exist
		unset($configurator->BBCodes['blog']);
		unset($configurator->tags['blog']);

		// We're going to use a custom filter, so...
		$configurator->attributeFilters->set('#blog', __CLASS__ . '::parse_blogembed');
		$configurator->BBCodes->bbcodeMonkey->allowedFilters[] = 'blogembed';

		// Let's create the new BBCode
		$configurator->BBCodes->addCustom(
			'[blog]{TEXT}[/blog]',
			'<blogembed>{TEXT}</blogembed>'
		);
		$tag = $event['configurator']->tags['BLOG'];
		$tag->rules->ignoreTags();
	}

	public function parse_blogembed($event)
	{
		$endpoint = "http://www.virtualwongery.com/w/api.php";
		// $endpoint = "https://www.wongery.com/w/api.php";
		// You'll have to change this manually.  Sorry.



		if (strpos($event['html'], BLOGSTARTTAG) === false)
			return true;
		$newstring = '';
		$oldstring = $event['html'];
		while (($pos = strpos($oldstring, BLOGSTARTTAG)) !== false) {
			$newstring .= substr($oldstring, 0, $pos);
			$oldstring = substr($oldstring, $pos + strlen(BLOGSTARTTAG));
			$blogtext = "";
			$pos = strpos($oldstring, BLOGENDTAG);
			$blogtext .= substr($oldstring, 0, $pos);
			$oldstring = substr($oldstring, $pos + strlen(BLOGENDTAG));
			$newstring .= MWParse(get_post($blogtext), $endpoint);
		}
		$newstring .= $oldstring;

		$event['html'] = $newstring;
	}
}

function get_post($key)
{
	echo (__DIR__ . "\n");
	require_once __DIR__ . '/../include/dbsettings.php';

	@$db = new mysqli($SERVER, $USER, $PASS, $DB);

	if (mysqli_connect_errno()) {
		echo "Error: Could not connect to database for some reason.";
		exit;
	}

	$query = 'select * from news where news_key = "' . $key . '"';
	$result = $db->query($query);

	$num_results = $result->num_rows;

	#  echo $num_results;

	if ($num_results == 0) {
		return "Error&mdash;no blog post found for given key";
	}
	$row = $result->fetch_assoc();
	return stripslashes($row['news_text']);
}


function MWParse($MWtext, $endPoint)
{
	$params = [
		"action" => "parse",
		"contentmodel" => "wikitext",
		"text" => $MWtext,
		"format" => "json",
	];
	$url = $endPoint;
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	// COMMENT THE FOLLOWING OUT FOR PRODUCTION VERSION
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));

	$output = curl_exec($ch);
	if (curl_errno($ch)) echo "<h1>ERROR :" . curl_error($ch) . "</h1>";
	curl_close($ch);

	$parseresult = json_decode($output, true);
	return $parseresult["parse"]["text"]["*"];
}
