<?php

$path = Site::$pathStack;

$ref = array_shift($path);

$source = Emergence\StaticKit\BuildWorkflow::getSource();
$repository = $source->getRepository();

$pathRef = "{$ref}:".implode('/', $path);
$pathHash = exec("hab pkg exec core/git git --git-dir={$repository->getGitDir()} rev-parse ".escapeshellarg($pathRef), $pathHashOutput, $pathHashStatus);

if ($pathHashStatus) {
    http_response_code(404);
    die('not found');
}

$pathType = exec("hab pkg exec core/git git --git-dir={$repository->getGitDir()} cat-file -t {$pathHash}");

if ($pathType == 'tree') {
    $pathHash = exec("hab pkg exec core/git git --git-dir={$repository->getGitDir()} rev-parse ".escapeshellarg("{$ref}:".implode('/', array_filter($path)).'/index.html'), $pathHashOutput, $pathHashStatus);

    if ($pathHashStatus) {
        http_response_code(404);
        die('directory has no index.html');
    }

    // ensure trailing slash
    if (array_pop($path)) {
        Site::redirect(array_merge(Site::$requestPath, [ '' ] ));
        exit();
    }

    // refresh type
    $pathType = exec("hab pkg exec core/git git --git-dir={$repository->getGitDir()} cat-file -t {$pathHash}");
}

if ($pathType == 'blob') {
    $extension = strtolower(substr(strrchr($pathRef, '.'), 1));

    if ($extension && array_key_exists($extension, SiteFile::$extensionMIMETypes)) {
        header('Content-Type: '.SiteFile::$extensionMIMETypes[$extension]);
    }

    passthru("hab pkg exec core/git git --git-dir={$repository->getGitDir()} cat-file -p {$pathHash}");
    exit();
}