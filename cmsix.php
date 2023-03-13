<?php namespace cmsix;

const FPATH  = './db.txt';              // Default database file path
const PREFIX = '_cmsix_';               // To distinguish cmsix stuff
const SIZES  = [2048, 720, 320];        // Posibble image sizes

/** Read database text file in key value fashion. */
function read(?string $path = FPATH): array
{
	$res = [];
	if (!is_resource($file = fopen($path, 'rb'))) {
		return $res;
	}
	while ($line = fgets($file)) {
		if (strlen(trim($line)) == 0) {
			continue;               // Skip empty lines
		}
		$parts = array_map('trim', explode("\t", $line));
		$key = array_shift($parts);
		$end = (array_shift($parts) ?? '')."\n";
		$res[$key] = '';
		while (($line = fgets($file)) and $line != $end) {
			$res[$key] .= $line;
		}
		$res[$key] = rtrim($res[$key]);
	}
	fclose($file);
	ksort($res);
	return $res;
}

/** Split $str into non empty lines. */
function lines(string $str): array
{
	$res = explode("\n", $str);
	return array_filter($res);
}

/** Filter $arr array by keys using $pattern. */
function get(array $arr, string $pattern): array
{
	return array_filter(
		$arr,
		fn($key) => preg_match($pattern, $key),
		ARRAY_FILTER_USE_KEY
	);
}

/** Make HTML $tag string with $child and $attr attributes. */
function tag(string $tag, ?string $child = null, ?array $attr = []): string
{
	$attr_str = '';
	foreach ($attr as $k => $v) {
		$attr_str .= "{$k}=\"{$v}\" ";
	}
	return "<{$tag} {$attr_str} " . ($child ? ">{$child}</{$tag}>" : "/>");
}

/** Make img tag with $attr attributes, try to use "srcset" and "sizes". */
function img(string $src, ?array $attr = []): string
{
	$attr['src']     ??= $src;              // IDK why, but anyway
	$attr['loading'] ??= 'lazy';            // Default "loading"
	// If "srcset" attibute is not defined by hand in $attr then
	// check if file has internal files (with PREFIX) of different
	// sizes (SIZES).  If yes then define "srcset" and "sizes".
	if (!isset($attr['srcset'])) {
		$srcset = [];
		foreach (SIZES as $size) {
			$path = $src.PREFIX.$size;
			$ext  = pathinfo($src)['extension'];
			if ($ext) {
				$path .= ".{$ext}";
			}
			if (file_exists($path)) {
				array_push($srcset, "{$path} {$size}w");
			}
		}
		if (count($srcset)) {
			$attr['srcset'] = implode(', ', $srcset);
		}
	}
	if (isset($attr['srcset'])) {           // Only when "srcset"
		$attr['sizes'] ??= '100vw';     // Default "sizes"
	} else {
		unset($attr['sizes']);
	}
	return tag('img', null, $attr);
}
