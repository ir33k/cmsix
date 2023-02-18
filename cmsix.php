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
        $key   = $parts[0];
        $type  = strtolower($parts[1]);
        $entry;
        // Search for non text content when there are more than 2
        // parts in line.  Otherwise force default behavior.
        switch (count($parts) > 2 ? $parts[1] : null) {
        case 'area':            // Multi line textarea
            $end = $parts[2];
            $entry = '';
            // TODO(irek): Validate all esential parts of line
            // like this one that is very much required.
            while (($line = fgets($file)) and trim($line) != $end) {
                $entry .= $line;
            }
            unset($end);
            $entry = trim($entry);
            break;
        case 'bool':
            $entry = strtolower($parts[2]) == 'true';
            break;
        case 'int':
            $entry = intval($parts[2]) ?? 0;
            break;
        case 'url':
            $entry = [
                'href'  => $parts[2] ?? '#',
                'title' => $parts[3] ?? 'link',
                'text'  => $parts[4] ?? 'link',
            ];
            break;
        case 'img':
            $entry = [
                'src'   => $parts[2] ?? '',
                'title' => $parts[3] ?? 'image',
            ];
            break;
        default:              // Regular text, no type prefix required
            $entry = $parts[1] ?? 'Lorem ipsum';
        }
        $data[$key] = $entry;
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
