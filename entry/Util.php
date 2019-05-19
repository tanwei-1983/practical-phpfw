<?php


declare(strict_types=1);

namespace entry;


class Util
{
    static function getCheckSign($wxInfo, $signKey='KEY') //signKey is changed every 2 hours
    {
        $signArr=[];
        ksort($wxInfo);
        foreach($wxInfo as $key=>$value){
            $signArr[]="$key=$value"; //value must not null
        }
        $signStr=implode('&', $signArr);
        $signStr.="&key=$signKey";
        return strtoupper(md5($signStr));
    }

    static function checkStrExist(?string $var)
    {
        return isset($var) && trim($var)!=='';
    }

    static function echoRetMsg($retFlag=true, $retInfo='SUCCESS', $actionUrl=null)
    {
        //$retInfoS=is_string($retInfo) ? $retInfo : json_encode($retInfo);
        echo json_encode(['retFlag'=>$retFlag, 'retInfo'=>$retInfo, 'actionUrl'=>$actionUrl]);
        exit();
    }

}
