<?php
declare(strict_types=1);

namespace ns1;
use entry\Util;
use entry\ProcDb;

class FooAction
{
    static function bar1()
    {

        $foo=$_POST['foo'] ?? '';
        Util::echoRetMsg(true, selectBar($foo));
    }
    static function bar2()
    {

    }
}

function selectBar($foo)
{
    return (new ProcDb())->selectDb("select * from foo where name=?", [$foo]);
}