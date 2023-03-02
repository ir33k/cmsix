<?php namespace cmsix;

/** CMSIX version number. */
const VERSION = '1.0';

/** Prefix used to avoid potential conflict in key names. */
const PREFIX = '_CMSX_PREFIX';

/** Default database file path. */
const FPATH = './db.txt';

/** Read data file from $path. */
function read(?string $path = FPATH): array
{
	$lines = [];            // Stores value as trimed lines
	$texts = [];            // Stores value original string
	$file = fopen($path, 'rb');
	if (!is_resource($file)) {
		echo "Can't open '$path' file".PHP_EOL;
		return $data;
	}
	while (($line = fgets($file))) {
		if (strlen(trim($line)) == 0) {
			continue; // Skip empty lines
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

/** Write $data to file $path. */
function write(array $data, ?string $path = FPATH): bool {
	$file = fopen($path, 'wb');
	if (!is_resource($file)) {
		echo "Can't open '$path' file".PHP_EOL;
		return false;
	}
	foreach ($data['texts'] as $k => $v) {
		fwrite($file, $k);
		$v = str_replace('', '', $v);
		$v = rtrim($v) . "\n";
		// Append end indicator.  By default use single empty line but
		// if $v value contains empty lines then generate unique id to
		// mark end.
		if (str_contains($v, "\n\n")) {
			$id = "END-".uniqid();
			fwrite($file, "\t".$id);
			$v .= $id."\n";
		}
		fwrite($file, "\n".$v."\n");
	}
	return fclose($file);
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
