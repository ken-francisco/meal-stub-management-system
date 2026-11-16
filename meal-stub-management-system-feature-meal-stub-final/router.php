
<?php

$requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$decodedPath = rawurldecode($requestPath);

// Block direct access to the storage directory
if (preg_match('#^/storage(?:/|$)#i', $decodedPath)) {
    http_response_code(404);
    exit('404 - Not Found');
}

// Let PHP's built-in server handle normal files
return false;
