<?php

require './cmsix.php';

$data = cmsix\read();

print_r(cmsix\get($data, "/^nav/"));
print_r($data['page-about-description']);
