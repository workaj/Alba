<?php
defined('CMSPATH') or die; // prevent unauthorized access

// api style controller - end output
ob_end_clean();

// router

$segments = CMS::Instance()->uri_segments;
$segsize = sizeof($segments);

// get width
if ($segsize>=3) {
	$req_width = $segments[2] ?? null;
}
else {
	$req_width = $_GET['w'] ?? null;
}
// get format 
if ($segsize>=4) {
	$req_format = $segments[3] ?? null;
}
else {
	$req_format = $_GET['fmt'] ?? null;
}
// quality fixed for url param version
$req_quality = $_GET['q'] ?? 75;

// check quality param - size/width checked elsewhere
if (!is_numeric($req_quality)) {
	http_response_code(406); 
	exit(0);
}

function serve_file ($media_obj, $fullpath, $seconds_to_cache=31536000) {
	$seconds_to_cache = $seconds_to_cache;
	$ts = gmdate("D, d M Y H:i:s", time() + $seconds_to_cache) . " GMT";
	header("Expires: $ts");
	header("Pragma: cache");
	header("Cache-Control: max-age=$seconds_to_cache");
	header("Content-type: " . $media_obj->mimetype);

	// virtual 
	if (function_exists('virtual')) {
		virtual($fullpath);
	}
	else {
		readfile($fullpath);
	}
	exit(0);
}

function make_thumb ($src, $dest, $desired_width, $file, $quality=75, $mimetype) {
	if ($file->mimetype=='image/jpeg') {
		$source_image = imagecreatefromjpeg($src);
	}
	elseif ($file->mimetype=='image/webp') {
		$source_image = imagecreatefromwebp($src);
		imageAlphaBlending($source_image, false);
		imageSaveAlpha($source_image, true);
	}
	else {
		$source_image = imagecreatefrompng($src);
		imageAlphaBlending($source_image, false);
		imageSaveAlpha($source_image, true);
	}
	$width = imagesx($source_image);
	$height = imagesy($source_image);
	/* find the "desired height" of this thumbnail, relative to the desired width  */
	$desired_height = floor($height * ($desired_width / $width));
	/* create a new, "virtual" image */
	$virtual_image = imagecreatetruecolor($desired_width, $desired_height);
	/* copy source image at a resized size */
	if ($mimetype=='image/png'||$mimetype=='image/webp') {
		imageAlphaBlending($virtual_image, false);
		imageSaveAlpha($virtual_image, true);
	}
	imagecopyresampled($virtual_image, $source_image, 0, 0, 0, 0, $desired_width, $desired_height, $width, $height);
	/* create the physical thumbnail image to its destination */
	if ($mimetype=='image/jpeg') {
		imagejpeg($virtual_image, $dest, (int)$quality);
	}
	elseif ($mimetype=='image/webp') {
		// scale webp quality to 3/4 of jpeg quality
		// TODO: trust that folks will use a good q for webp? :)
		imagewebp($virtual_image, $dest, floor($quality*0.75));
	}
	else {
		imagepng($virtual_image, $dest);
	}
}

function get_image ($id) {
	$stmt = CMS::Instance()->pdo->prepare('select * from media where id=?');
	$stmt->execute(array(CMS::Instance()->uri_segments[1])); // already tested to be number
	$stmt->execute();
	return $stmt->fetch();
}

if ($segsize<2 || !is_numeric($segments[1]) ) {
	http_response_code(406); // not acceptable
	exit(0);
}

if ($segsize==2 && !$req_width && !$req_format && $req_quality==75) {
	// just image id and no get params of note
	// serve original uploaded image
	$image = get_image ($segments[1]);
	if ($image) {
		$fullpath = CMSPATH . '/images/processed/' . $image->filename;
		serve_file ($image, $fullpath);
	}
	else {
		http_response_code(404); // was h1 echo before. not great.
	}
	exit(0);
}

// reach here, got either segments for size or we have get 1 or more params

if ($segsize>1 || ($req_width||$req_format||$req_quality<>75)) {
	$image = get_image ($segments[1]);
	if ($image) {
		$original_path = CMSPATH . "/images/processed/" . $image->filename;
		// if no width param found - set to default image width
		if (!$req_width) {
			$req_width = $image->width;
		}
		//even if a specific version of these types of files is requested,
		//return the native image due to lack of php handling at this time
		if(File::$image_types[$image->mimetype]==2) {
			serve_file ($image, $original_path);
		}
		// check to see if format is og
		// assume format is original mimetype
		if ($req_format) {
			$mimetype = File::get_mimetype_by_format($req_format);
		}
		else {
			$mimetype = $image->mimetype;
		}
		if ($mimetype) {
			// get size
			if (!is_numeric($req_width)) {
				// get size from array lookup (web/thumb) - if fails, assume 1920
				$size = Image::$image_sizes[$req_width] ?? 1920;
				if (!$size) {
					http_response_code(406); // unknown size
					exit(0);
				}
			}
			else {
				$size = $req_width;
			}
			// got int size
			// NO UPSCALING - preserves quality
			if ($image->width <= $size) {
				$size = $image->width;
			}
			// if format shifted, add additional suffix to processed filename
			$newsize_path_suffix = ($mimetype!=$image->mimetype) ? "." . $req_format : "";
			// create unique path based on format/quality/size
			$newsize_path = CMSPATH . "/images/processed/q_" . $req_quality . "_" . $size . "w_" . $image->filename . $newsize_path_suffix;
			//echo "<h5>Path: " . $newsize_path . "</h5>"; CMS::pprint_r ($mimetype); exit(0);
			if (!file_exists($newsize_path)) {
				make_thumb($original_path, $newsize_path, $size, $image, $req_quality, $mimetype); 
			}
			// set mimetype in image object to match requested mimetype (might already be same...)
			// this makes sure header is correct 
			$image->mimetype = $mimetype;
			// serve existing file or new thumb if created above
			serve_file ($image, $newsize_path); 
		}
		else {
			http_response_code(406); // not acceptable mimetype
			exit(0);
		}
	}
	else {
		http_response_code(404); // was h1 echo before. not great.
		exit(0);
	}
}
exit(0);

