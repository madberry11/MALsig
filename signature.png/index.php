<?php
/************************************************************************
*  saka's minimal signature script - v1.58 (anime/manga merged)
*  http://myanimelist.net/forum/?topicid=84446
*
*	This program is free software: you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation, either version 3 of the License, or
*	(at your option) any later version.
*
*	This program is distributed in the hope that it will be useful,
*	but WITHOUT ANY WARRANTY; without even the implied warranty of
*	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*	GNU General Public License for more details.
*
*	You should have received a copy of the GNU General Public License
*	along with this program.  If not, see <http://www.gnu.org/licenses/>.
*************************************************************************/
ob_start(); // buffer output to catch php warnings
@chdir(dirname(__FILE__)); // corrects environment path (ignore this)


// ENTER YOUR USERNAME
$user = "saka";

// let's configure the cache
$cachedpath = "cache/sig.png"; // a temporary copy of your signature will be stored here at the end
check_cache(10); // time to cache signature in minutes, you can comment out this line for testing but put it back afterward!

// IMPORTANT: YOUR 'cache' DIRECTORY MUST EXIST AND BE WRITABLE ON THE SERVER!!! (chmod 777 or change file properties using FTP)
//    * By default, the image only updates at most every ten minutes or when you make a change to this script, but there are
//      times when you might want it to always update (perhaps when trying different backgrounds, fonts or overlays). To disable
//      the cache, simply set the time to 0 instead of 10 in the check_cache(...) line above. Once your sig is working how you'd
//	    like, MAKE SURE TO PUT IT BACK TO SOMETHING REASONABLE. I do not recommend leaving the cache disabled or setting it for
//	    less than 7 minutes, since too low a setting can bombard MAL with lots of RSS requests and may also piss off your host.
//    * When you are done, you can set your forum signature to use [img]http://yourdomain/signature/sig.php[/img], and this file
//	    will output your signature image, or you can alternatively link to [img]http://yourdomain/signature/cache/sig.png[/img] and
//	    have a cronjob visit the php url occasionally.

///////////////////////////////////////////////////////////////////////////////////////////
// LET'S DOWNLOAD THE RSS FEED AND PARSE THE DATA - you should not have to change anything here
$animebuffer = download("http://myanimelist.net/rss.php?type=rw&u={$user}");
$mangabuffer = download("http://myanimelist.net/rss.php?type=rm&u={$user}");
if ( !$animebuffer or !$mangabuffer ) die("Could not download RSS feed");

// lets fix the status in the manga feeds so they make sense, and so we can differentiate from anime (silly Xinil)
$mangabuffer = strtr($mangabuffer, array('>Plan to Watch'=>'>Plan to Read','>Watching'=>'>Reading','>Rewatching'=>'>Re-Reading','>On-Hold'=>'>Reading On Hold','>Completed'=>'>Finished Reading') );

// easiest to parse if we just merge the feeds together and parse all at once
$buffer = $animebuffer . $mangabuffer;

// sanitize the information we saved to the buffer (no newlines/tabs and replace xml entities)
$buffer = strtr($buffer, array("\n" => '', "\r" => '', "\t" => '', '&lt;' => '<', '&gt;'=>'>', '&amp;' => '&', '&quot;' => '"', '&apos;'=>"'") );

// these lines just extract the anime title and status information into $titles[] and $status[] arrays, plus other info
preg_match_all("/<item><title>([^<]*) - ([^<]*?)<\/title>/i", $buffer, $titlematches);
preg_match_all("/<description><![CDATA[([^]]*) - ([d?]+) of ([d?]+) ([^]]*)]]></description>/", $buffer, $statusmatches);
preg_match_all("/<pubDate>([^<]*)<\/pubDate>/i", $buffer, $timematches);
preg_match_all("@<link>https?://(?:www\.)?myanimelist\.net/(anime|manga)/(\d+)/[^<]*</link>@i", $buffer, $linkmatches);
$titles = $titlematches[1]; // $titles is now an array of titles
$status = $statusmatches[1]; // $status is now an array of statuses
$current = $statusmatches[2]; // $current is now an array of all the current episodes/chapters
$totals = $statusmatches[3]; // $totals is now an array of the total number of episodes in each series
$units = $statusmatches[4]; // $units is now an array of 'episode(s)' or 'chapter(s)'
$timestamps = $timematches[1]; // $timestamps is now an array of dates watched/read
$types = $linkmatches[1]; // $types is now an array containing the type ('anime' or 'manga')
$subtypes = $titlematches[2]; // $subtypes now has the detailed type if you want it ('TV','Movie','Manga','Novel',...)
$ids = $linkmatches[2]; // $ids is now an array containing the unique numeric mal series id

// sort all of the arrays by the timestamps, so that the most recent entries are first
$timestamps = array_map('strtotime',$timestamps); // converts to numeric unix timestamps
array_multisort($timestamps,SORT_DESC,$titles,$status,$current,$totals,$units,$types,$subtypes,$ids);


// LET'S FORMAT EACH UPDATE ONE AT A TIME HOWEVER WE WANT
for($i = 0; $i < count($titles); $i++) {
	// FORMAT THE TITLE VALUES
	// limit the titles to 45 characters; adjust this to your needs
	$titles[$i] = textlimit($titles[$i],45);

	// FORMAT THE TIME VALUES
	// Default is something like 'Mon 12:00pm' - see http://php.net/manual/en/function.date.php
	$times[$i] = date('D h:ia',$timestamps[$i]);

	// FORMAT THE UNITS (episodes/chapters/volumes)
	$units[$i] = strtr( $units[$i], array(
		'episodes' => 'ep.',
		'chapters' => 'ch.',
		'volumes' => 'vol.'
	));

	// FORMAT THE STATUS VALUES - you can change the format as you like for each
	// do not change the values on the left, only put your desired status on the right
	// you can add special array values as desired (for example "$current[$i]/$totals[$i] $units[$i]" becomes "2/13 episodes")
	// be careful of unclosed quotes, and every substitution needs a comma except the last!
	$status[$i] = strtr( $status[$i], array(
		"Plan to Watch" => "Plan to Watch",
		"Plan to Read" => "Plan to Read",
		"Watching" => "Just watched $units[$i] $current[$i]",
		"Reading" => "Reading at $units[$i] $current[$i]/$totals[$i]",
		"Rewatching" => "Rewatched $units[$i] $current[$i]",
		"Re-Reading" => "Re-reading $units[$i] $current[$i]",
		"On-Hold" => "On Hold at $units[$i] $current[$i]/$totals[$i]",
		"Reading On Hold" => "Reading On Hold",
		"Completed" => "Completed",
		"Finished Reading" => "Finished Reading",
		"Dropped" => "Dropped"
	));
}

///////////////////////////////////////////////////////////////////////////////////////////
// LET'S START GENERATING THE SIGNATURE IMAGE

$sigimage = open_image("background.png"); // load your background image

// WRITE THE TEXT ONTO THE IMAGE
$font = 'font.ttf'; // if you use another font, make sure you copy the *.ttf file into the same directory
// let's define a font color - the last four arguments are the red, green, blue, and alpha
// (so 0,0,0,0 = solid black and 255,255,255,70 = semi-transparent white)
$color = imagecolorallocatealpha($sigimage,255,255,0,0);
$color2 = imagecolorallocatealpha($sigimage,230,230,230,0);
// draw the text - the template is imagettftextalign(image, font size, angle, x-pos, y-pos, font color, fontfile, text output, 'c' or 'l' or 'r')
imagettftextalign($sigimage,18,0,250,40,$color,$font,$titles[0],'c');
imagettftextalign($sigimage,14,0,250,55,$color2,$font,$status[0],'c');
imagettftextalign($sigimage,18,0,250,83,$color,$font,$titles[1],'c');
imagettftextalign($sigimage,14,0,250,99,$color2,$font,$status[1],'c');

// OVERLAY ANOTHER IMAGE over the font and background (optional); remove the // at the beginning of the line below
//overlay_image("overlay.png");

// finally, let's output our pretty signature image to the browser
$error = ob_get_clean();
if( !$error ) { // if no errors/warnings
	header("Content-type: image/png");
	imagepng($sigimage);
	imagepng($sigimage, $cachedpath); // try to save a copy of our signature to the cache location we set earlier
} else echo $error;

///////////////////////////////////////////////////////////////////////////////////////////
// Don't modify below here... just a few helping functions that can be called in the above code

// textlimit($string, $length) takes any $string you pass it and returns it shortened to $length characters (use it to limit printed string length)
function textlimit($string, $length=25) {
	return ( strlen(trim($string)) > $length ? trim( substr(trim($string),0,$length-3) )."..." : $string );
}

// textlimitpx($string, $pixels, $font, $size) returns the shortened $string that fits within exactly $pixels width horizontally when using $font and $size
function textlimitpx($string, $pixels, $font, $size) {
	for($k = strlen(trim($string)); $k > 0; $k--) {	if (textwidth(textlimit($string,$k), $font, $size) <= $pixels) break;	}
	return textlimit($string,$k);
}

// textwidth($string, $font, $size) returns the pixel width of the final text with those parameters
function textwidth($string, $font, $size) {
	$box = imagettfbbox($size,0,$font,$string);
	return $box[2] - $box[0];
}

// overlay_image($baseimage,$overlaypath,$x,$y) opens the image at $imagepath and overlays it onto $sigimage at position ($x, $y)
// most image types should work, but 24-bit/true color PNG is recommended if you need transparency
function overlay_image($overlaypath,$x=0,$y=0) {
	global $sigimage;
	$overlay = is_string($overlaypath)?open_image($overlaypath):$overlaypath; // open, or assume opened if non-string
	imagecopy($sigimage, $overlay, $x, $y, 0, 0, imagesx($overlay), imagesy($overlay)); // overlay onto our base image
	@imagedestroy($overlay); // clean up memory, since we don't need the overlay image anymore
}

// open_image($path) will load an image into memory so we can work with it, and die with an error if we fail
function open_image($path) {
	$image = @imagecreatefromstring(file_get_contents($path));
	if (!$image) die("could not open image ($path) make sure it exists");
	imagealphablending($image,true); imagesavealpha($image,true); // preserve transparency
	return $image;
}

// check_cache($minutes) returns a cached image and stops execution if $minutes has not passed since the last update or failure
function check_cache($minutes) {
	global $cachedpath;
	if ( !( is_writable($cachedpath) or is_writable(dirname($cachedpath)) and !file_exists($cachedpath) ) )
		die("The cache is not writable; please change it to 777 permissions using FTP.\n<br />\$cachedpath = {$cachedpath}");
	if ( time() - @filemtime($cachedpath) < 60*$minutes and @filemtime(basename($_SERVER['SCRIPT_NAME'])) < @filemtime($cachedpath) ) {
		header("Content-type: image/png");
		echo file_get_contents($cachedpath);
		exit(0);
	}
	if ( file_exists($cachedpath) ) touch($cachedpath);
}

// imagettftextalign() is basically a wrapper for imagettftext() to add the ability to center/right-align text to a point
// the $align argument can be 'c' for center, 'r' for right align, or 'l' for left align (default)
function imagettftextalign(&$img,$size,$angle,$x,$y,&$c,$font,$string,$align='l') {
	$box = imagettfbbox($size,$angle,$font,$string);
	$w = $box[2] - $box[0];
	$h = $box[3] - $box[1];
	switch (strtolower($align)) {
		case 'r': $x -= $w; $y -= $h; break;
		case 'c': $x -= $w/2; $y -= $h/2; break;
	}
	imagettftext($img,$size,$angle,$x,$y,$c,$font,$string);
}

// download() downloads the content at $url and returns the raw string, not including the http header
function download($url) {
	if(function_exists('curl_init')) {
		$ch = curl_init($url);
		curl_setopt($ch,CURLOPT_HEADER,false);
		curl_setopt($ch,CURLOPT_USERAGENT,'Taiga');
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
		$results = curl_exec($ch); // download!
		curl_close($ch);
	}
	if (!$results) $results = file_get_contents($url); // curl failed, try url_fopen
	if (!$results) die("Could not download from $url"); // give up
	return $results;
}

?>
