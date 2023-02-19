<?php namespace cmsix;

/**
 * Read data file from $path.  Return dictionary sorted by keys.
 */
function read(?string $path = './db.txt'): array
{
    $data = [];
    $file = fopen($path, 'r');
    if (!is_resource($file)) {
        echo "Can't open '$path' file".PHP_EOL;
        return $data;
    }
    while (($line = fgets($file))) {
        $parts = array_map('trim', explode("\t", $line));
        $key   = array_shift($parts);
        $end   = array_shift($parts) ?? '';
        $value = [];
        while (($line = fgets($file)) and trim($line) != $end) {
            array_push($value, trim($line));
        }
        if ($end != '') {
            $value = trim(implode("\n", $value));
        } else if(count($value) == 1) {
            $value = $value[0];
        }
        $data[$key] = $value;
    }
    fclose($file);
    ksort($data);
    return $data;
}

/**
 * Filter $arr by keys using $regexp.
 */
function get(array $arr, string $regexp): array
{
    return array_filter(
        $arr,
        fn($key) => preg_match($regexp, $key),
        ARRAY_FILTER_USE_KEY
    );
}
