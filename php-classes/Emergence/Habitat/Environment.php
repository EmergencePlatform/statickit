<?php

namespace Emergence\Habitat;

use Exception;

use Cache;


class Environment
{
    public static $packageTTL = 60;


    private $packages;
    private $extraVariables;
    private $variables;

    public function __construct($packages = [], array $extraVariables = [])
    {
        $this->packages = is_array($packages) ? $packages : [$packages];
        $this->extraVariables = $extraVariables;
    }

    public static function getPackagePath($packageIdent)
    {
        $cacheKey = "hab:{$packageIdent}:path";

        // try to get from cache first
        $path = Cache::fetch($cacheKey);

        if ($path !== false) {
            return $path;
        }

        // query habitat
        $path = exec('hab pkg path '.escapeshellarg($packageIdent)) ?: null;

        // store in cache, short TTL if not found
        Cache::store($cacheKey, $path, $path ? static::$packageTTL : 5);

        return $path;
    }

    public static function getPackageRuntimeEnvironment($packageIdent)
    {
        $packagePath = static::getPackagePath($packageIdent);

        if (!$packagePath) {
            throw new Exception('Could not resolve habitat path for package ident: '.$packageIdent);
        }

        $cacheKey = "hab:{$packageIdent}:runtime_env";

        // try to get from cache first
        $env = Cache::fetch($cacheKey);

        if ($env !== false) {
            return $env;
        }

        // query habitat
        $env = [];
        $lines = file("{$packagePath}/RUNTIME_ENVIRONMENT", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            list ($key, $value) = explode('=', $line, 2);
            $env[$key] = $value;
        }

        // store in cache, short TTL if not found
        Cache::store($cacheKey, $env, static::$packageTTL);

        return $env;
    }

    public static function getPackageLibraryPath($packageIdent)
    {
        $packagePath = static::getPackagePath($packageIdent);

        if (!$packagePath) {
            throw new Exception('Could not resolve habitat path for package ident: '.$packageIdent);
        }

        $cacheKey = "hab:{$packageIdent}:library_path";

        // try to get from cache first
        $path = Cache::fetch($cacheKey);

        if ($path !== false) {
            return $path;
        }

        // query habitat
        $path = null;

        if (file_exists("{$packagePath}/LD_RUN_PATH")) {
            $path = explode(':', trim(file_get_contents("{$packagePath}/LD_RUN_PATH")));
        }

        // store in cache, short TTL if not found
        Cache::store($cacheKey, $path, static::$packageTTL);

        return $path;
    }

    public function getVariables()
    {
        if ($this->variables !== null) {
            return $this->variables;
        }

        // build environment from scratch
        $this->variables = $this->extraVariables;

        foreach (array_merge(['core/hab'], $this->packages) as $packageIdent) {
            $packageEnv = static::getPackageRuntimeEnvironment($packageIdent);
            $this->appendVariables($packageEnv);

            if ($libraryPath = static::getPackageLibraryPath($packageIdent)) {
                $this->appendVariables([
                    'LD_LIBRARY_PATH' => implode(':', $libraryPath)
                ]);
            }
        }

        return $this->variables;
    }

    public function appendVariables(array $variables)
    {
        // ensure $this->variables is initialized
        $this->getVariables();

        // set new or append existing values
        foreach ($variables as $key => $value) {
            if (empty($this->variables[$key])) {
                $this->variables[$key] = $value;
                continue;
            }

            switch ($key) {
                case 'PATH':
                case 'LD_LIBRARY_PATH':
                    $this->variables[$key] .= ':'.$value;
                    break;
                default:
                    throw new Exception('No strategy to append environment variable: '.$key);
            }
        }
    }

    public function exec($packageIdent, $command, &$output = null, &$return_var = null)
    {
        if (is_array($command)) {
            $command = implode(' ', $command);
        }

        $command = "hab pkg exec {$packageIdent} {$command}";

        foreach ($this->getVariables() as $key => $value) {
            $command = $key.'='.escapeshellarg($value).' '.$command;
        }

        switch (func_num_args()) {
            case 1:
                return exec($command);
            case 2:
                return exec($command, $output);
            default:
                return exec($command, $output, $return_var);
        }
    }
}