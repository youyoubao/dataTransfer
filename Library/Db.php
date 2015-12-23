<?php
namespace Data\Library;

//多进程来调用这个Db。如果A进程创建的mysql连接，B进程是不能使用的，所以需要标记连接是哪个进程创建的
//其实这种方法是有问题的，但在当前情景下可正常运行
//
//
//以前使用的mysql底层驱动是mysql,后来改成mysqli，部分函数未升级
//使用静态二维数组dbPool完全没用，一维就可以，稍后修改代码
class Db
{
    public static $dbPool;
    public static function exeSql($dbname, $sql, $taskid = 0)
    {
        global $db;
        $ret = array();
        if ($taskid == 0) {
            $taskid = key(end(self::$dbPool));
        }
        if (!isset(self::$dbPool[$taskid][$dbname]) || empty(self::$dbPool[$taskid][$dbname])) {
            $db1 = mysqli_connect($db[$dbname]['host'], $db[$dbname]['user'], $db[$dbname]['pswd']) or die("连接 '" . $dbname . "'库失败");
            mysqli_query($db1, "SET NAMES 'UTF8'");
            self::$dbPool[$taskid][$dbname] = $db1;
        } else {
            $db1 = self::$dbPool[$taskid][$dbname];
        }

        if (!$query = mysqli_query($db1, $sql)) {
            throw new \Exception("出錯的SQL：" . $sql . "\t" . mysqli_errno($db1) . ": " . mysqli_error($db1));
        }
        if ($query instanceof \mysqli_result) {
            while ($row = mysqli_fetch_array($query, MYSQLI_ASSOC)) {
                $ret[] = $row;
            }
            return $ret;
        } else {
            return $query;
        }
    }
    public static function delDbPool($taskid)
    {
        if (empty(self::$dbPool)) {
            return true;
        }
        foreach (self::$dbPool as $value) {
            foreach ($value as $dbtype) {
                mysqli_close($dbtype);
            }
        }
        self::$dbPool = '';
    }
}
