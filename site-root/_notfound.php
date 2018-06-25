<?php

$path = Site::$pathStack;
$filename = array_pop($path);

if (
    $filename
    && strpos($filename, '.') === false
    && $htmlNode = Site::resolvePath(array_merge(
        [ 'site-root' ],
        $path,
        [ $filename . '.html' ]
    ))
) {
    $htmlNode->outputAsResponse();
} else {
    return RequestHandler::throwNotFoundError();
}
