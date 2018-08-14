<?php

namespace Emergence\StaticKit;

use Exception;

use Site;
use Emergence_FS;

use Emergence\Git\Source;
use Emergence\Slack\API as SlackAPI;
use Emergence\Util\Url as UrlUtil;


class BuildWorkflow
{
    public static $source = 'content';
    public static $command = 'hab pkg exec core/node npm run build';
    public static $chatChannel;

    public static function getSource()
    {
        static $source = null;

        if (!$source) {
            $source = Source::getById(static::$source);
        }

        return $source;
    }

    public static function describeSource()
    {
        return static::getSource()->getCommitDescription();
    }

    public static function pullSource()
    {
        $source = static::getSource();
        $source->fetch();
        return $source->pull();
    }

    public static function postChatMessage(array $message)
    {
        if (!static::$chatChannel) {
            return;
        }

        return SlackAPI::request('chat.postMessage', [
            'post' => array_merge([
                'username' => Site::getConfig('primary_hostname'),
                'channel' => static::$chatChannel
            ], $message)
        ]);
    }

    public static function run()
    {
        print "Pulling...\n\n";
        static::postChatMessage([
            'text' => 'New code pushed to GitHub, pulling to server...'
        ]);

        $pullResult = static::pullSource();
        $head = static::describeSource();


        print "\n\nBuilding...\n\n";
        static::postChatMessage([
            'text' => 'Pulled source revision `'.$head.'`, building site...'
        ]);

        try {
            $buildResult = static::build();
        } catch (Exception $e) {
            static::postChatMessage([
                'attachments' => json_encode([
                    [
                        'title' => 'Build failed',
                        'text' => $e->getMessage(),
                        'mrkdwn_in' => [],
                        'color' => 'danger'
                    ]
                ])
            ]);

            return;
        }


        print "\n\nDone\n";
        $siteUrl = UrlUtil::buildAbsolute('/');
        static::postChatMessage([
            'attachments' => json_encode([
                [
                    'title' => "Updated build available: $siteUrl",
                    'title_link' => $siteUrl,
                    'text' => $buildResult['buildOutput'],
                    'mrkdwn_in' => [],
                    'color' => 'good',
                    'fields' => [
                        [
                            'title' => 'Files Updated',
                            'value' => $buildResult['filesUpdated'],
                            'short' => true
                        ],
                        [
                            'title' => 'Files Deleted',
                            'value' => $buildResult['filesDeleted'],
                            'short' => true
                        ]
                    ]
                ]
            ])
        ]);
    }

    public static function build()
    {
        // get git source repository
        $source = static::getSource();
        $repository = $source->getRepository();


        // get studio directory
        $studioPath = Site::$rootPath . '/site-data/studio';

        if (!is_dir($studioPath)) {
            mkdir($studioPath, 0770, true);
        }


        // prepare fresh head directory
        $headPath = "{$studioPath}/head";

        if (is_dir($headPath)) {
            exec("rm -R {$headPath}");
        }

        mkdir($headPath, 0770, true);


        // change into head directory
        chdir($headPath);


        // export source to head directory
        $exportResult = exec("hab pkg exec core/git git --git-dir={$repository->getGitDir()} archive HEAD | tar x; echo \$?");
        
        if ($exportResult != '0') {
            throw new Exception("Failed with exit code {$exportResult} to export HEAD to {$headPath}");
        }


        // make node_modules reusable between builds
        $nodeModulesPath = "{$studioPath}/node_modules";
        if (!is_dir($nodeModulesPath)) {
            mkdir($nodeModulesPath, 0770, true);
        }

        symlink($nodeModulesPath, "{$headPath}/node_modules");


        // configure execution environment
        putenv("HOME={$studioPath}");
        putenv('LD_LIBRARY_PATH='.implode(':', [
            trim(exec('hab pkg path core/libpng')) . '/lib'
        ]));
        putenv('PATH='.implode(':', [
            trim(exec('hab pkg path core/node')) . '/bin',
            '/bin'
        ]));


        // run npm install
        exec("hab pkg exec core/node npm install 2>&1", $npmOutput, $npmStatus);
        $npmOutput = implode(PHP_EOL, $npmOutput);
        $npmOutput = preg_replace('#\\x1b[[][^A-Za-z]*[A-Za-z]#', '', $npmOutput);


        if ($npmStatus !== 0) {
            throw new Exception("npm install failed with exit code {$npmStatus}:\n\n{$npmOutput}");
        }


        // run build
        chdir("{$headPath}");
        exec(static::$command.' 2>&1', $buildOutput, $buildStatus);
        $buildOutput = implode(PHP_EOL, $buildOutput);
        $buildOutput = preg_replace('#\\x1b[[][^A-Za-z]*[A-Za-z]#', '', $buildOutput);
        
        if ($buildStatus !== 0) {
            throw new Exception("build failed with exit code {$buildStatus}:\n\n{$buildOutput}");
        }


        // import build into VFS
        $results = Emergence_FS::importTree("{$headPath}/build", "site-root", [
            'exclude' => [
                '#^/connectors(/|$)#',
                '#\.php$#'
            ]
        ]);


        // append additional results info
        $results['npmOutput'] = $npmOutput;
        $results['buildOutput'] = $buildOutput;


        return $results;
    }
}
