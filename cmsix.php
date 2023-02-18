<?php
namespace cmsix;

class Cmsix
{
    private readonly mixed $file; // Database text file
    public array $data;           // Parsed $file lines
    
    function __construct(string $path = "./db.txt")
    {
        $this->file = fopen($path, 'r');
        $this->data = [];

        if (!is_resource($this->file)) {
            echo "Can't open '$path' file".PHP_EOL;
            return;
        }
        while (($line = fgets($this->file))) {
            $this->parse_line($line);
        }
        ksort($this->data);
    }

    function __destruct()
    {
        fclose($this->file);
    }

    private function parse_line(string $line): void
    {
        $parts = array_map('trim', explode("\t", $line));
        $key   = $parts[0];
        $entry = ["type" => strtolower($parts[1])];

        switch ($entry["type"]) {
        case "area":            // Multi line textarea
            $end = $parts[2];
            $entry["value"] = "";
            // TODO(irek): Validate all esential parts of line
            // like this one that is very much required.
            while (($line = fgets($this->file)) and trim($line) != $end) {
                $entry["value"] .= $line;
            }
            unset($end);
            $entry["value"] = trim($entry["value"]);
            break;
        case "bool":
            $entry["value"] = strtolower($parts[2]) == "true";
            break;
        case "int":
            $entry["value"] = intval($parts[2]) ?? "0";
            break;
        case "text":
            $entry["value"] = $parts[2] ?? "Lorem ipsum";
            break;
        case "url":
            $entry["url"]   = $parts[2] ?? "#";
            $entry["title"] = $parts[3] ?? "link";
            $entry["value"] = $parts[4] ?? "link";
            break;
        case "img":
            $entry["url"]   = $parts[2] ?? "";
            $entry["title"] = $parts[3] ?? "image";
            break;
        default:
            // TODO(irek): Some kind of error or warrning would be nice.
            echo "Invalid field type ".$entry["type"].PHP_EOL;
            return;             // Skip invalid type
        }
        $this->data[$key] = $entry;
    }

    public function get(?string $regexp = "//", ?string $type = NULL): array
    {
        return array_filter(
            $this->data,
            fn($v, $k) => preg_match($regexp, $k) // Filter by key
            and (!$type or $v["type"] == $type),  // Filter by type
            ARRAY_FILTER_USE_BOTH
        );
    }
}

// TODO(irek): $path could be set by value of cookie or query
// parameter.  That way we can have multiple databased on server and
// switch between them for test purposes.
$path  = "./db.txt";
$cmsix = new Cmsix($path);

$nav   = $cmsix->get("/^nav/");     // Get website navigation links
$area  = $cmsix->get("//", "area"); // Get entries with area type

print_r($nav);
print_r($area);
