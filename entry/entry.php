<?php

namespace entry;

spl_autoload_register(function ($ns) {
    $newNs=str_replace('\\', '/', $ns);
    require_once '../' . "$newNs.php";
});

function entry()
{
    $postSign=$_POST['sign'] ?? ''; //change frequency of the sign is 2 hours
    if(!Util::checkStrExist($postSign)) {
        Util::echoRetMsg(false, "sign does not exist");
    }
    unset($_POST['sign']);
    if($postSign != Util::getCheckSign($_POST)) {
        Util::echoRetMsg(false, "check sign error");
    }
    $url2path=[ //route the post[actionUrl] to php class
        'foo/bar1.action'=>'\ns1\FooAction::bar1',
        'foo/bar2.action'=>'\ns1\FooAction::bar2',
    ];
    $actionUrl=$_POST['actionUrl'] ?? '';
    if(! (Util::checkStrExist($actionUrl) && isset($url2path[$actionUrl])) ) {
        Util::echoRetMsg(false, "invalid connection!");
    }
    $url2path[$actionUrl]();
}

entry();

