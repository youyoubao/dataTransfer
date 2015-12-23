<?php
namespace Data\Library;

class Loader
{
    public static function autoloder($class)
    {
        $class = str_replace("Data\\", "", $class);
        require_once BASEDIR . "/" . str_replace("\\", "/", $class) . ".php";
    }
}
