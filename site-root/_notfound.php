<?php

$path = Site::$pathStack;
$filename = array_pop($path);
$filenameIsExtensionless = $filename && strpos($filename, '.') === false;

if (
    (
        $filenameIsExtensionless
        && $htmlNode = Site::resolvePath(array_merge(
            [ 'site-root' ],
            $path,
            [ $filename . '.html' ]
        ))
    )
    || (
        ($filenameIsExtensionless || !$filename)
        && (
            $treeNode = Site::resolvePath(array_filter(array_merge(
                [ 'site-root' ],
                $path,
                [ $filename ]
            )))
        )
        && $treeNode instanceof SiteCollection
        && (
            $htmlNode = $treeNode->getChild('index.html')
        )
    )
) {
    // ensure trailing slash
    if ($treeNode && $filename) {
        Site::redirect(array_merge(Site::$pathStack, ['']));
    }

    // render HTML as response
    $htmlNode->outputAsResponse();
} else {
    return RequestHandler::throwNotFoundError();
}
