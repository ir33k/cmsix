<?php namespace cmsix;

const VERSION = '1.0';          // Cmsix version
const FPATH   = './db.txt';     // Default database file path
const PREFIX  = '_cmsix_';      // To distinguish cmsix internals

function read(?string $path = FPATH): array
{
	$lines = [];            // Value as trimed, non empty, lines
	$texts = [];            // Value original string
	if (!is_resource($file = fopen($path, 'rb'))) {
		return ['lines' => [], 'texts' => []];
	}
	while (($line = fgets($file))) {
		if (strlen(trim($line)) == 0) {
			continue;               // Skip empty lines
		}
		$parts = array_map('trim', explode("\t", $line));
		$key = array_shift($parts);
		$end = (array_shift($parts) ?? '')."\n";
		$lines[$key] = [];
		$texts[$key] = '';
		while (($line = fgets($file)) and $line != $end) {
			if (strlen(rtrim($line)) > 0) {
				array_push($lines[$key], rtrim($line));
			}
			$texts[$key] .= $line;
		}
		$texts[$key] = rtrim($texts[$key]);
	}
	fclose($file);
	ksort($lines);
	ksort($texts);
	return ['lines' => $lines, 'texts' => $texts];
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
