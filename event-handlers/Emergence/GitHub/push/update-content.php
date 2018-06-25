<?php

header('Content-Type: text/plain');


// leave GitHub at least a little output for its webhook UI
print "Received GitHub push event\n\n";



// ignore pushes that aren't to the content repository's upstream
$upstreamBranch = Emergence\StaticKit\BuildWorkflow::getSource()->getUpstreamBranch();

if ($_EVENT['ref'] == 'refs/heads/'.$upstreamBranch) {
    print "Running build\n";

    // let GitHub off the line, any further output won't be logged to their webhook UI
    Site::finishRequest(false);

    // execute build workflow
    Emergence\StaticKit\BuildWorkflow::run();
} else {
    die("Ignoring push to branch that isn't $upstreamBranch\n");
}
