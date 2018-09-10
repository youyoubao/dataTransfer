<?php
namespace dormscript\Data\Library;

/**
 * 根据每个表的配置信息将数据进行格式转移、写入目标数据库
 */
class SwitchData
{
    public $tablename;
    public $startid;
    public $endid;
    public $modelObj; //当前正在操作的表结构
    public $taskid; //处理当前任务的task进程ID

    public function run($tablename, $startid, $endid, $taskid, $ser = '')
    {
        $this->tablename = $tablename;
        $this->startid   = $startid;
        $this->endid     = $endid;
        $this->taskid    = $taskid;
        $this->modelObj  = \Data\Models\Models::getObj($tablename); //获取Models对象

        if (!empty($ser)) {
            $this->modelObj->setSwooleSer($ser);
        }
        try {
            $srcData = $this->readRs(); //从源表中读取数据
            if (empty($srcData)) {
                return true;
            }
            $newData = array();
            foreach ($srcData as $key => $row) {
                $newRs = $this->analysisRs($row); //做数据格式转换
                if ($newRs !== false) {
                    $newData[$key] = $newRs;
                }
            }
            $this->writeRs($newData); //将数据写入目标数据表
            if ($this->modelObj->callbackRow == 'src') {
                $this->modelObj->callback($srcData);
            } elseif ($this->modelObj->callbackRow == 'desc') {
                $this->modelObj->callback($newData);
            } elseif ($this->modelObj->callbackRow == 'merge') {
                $mergeData = array();
                foreach ($srcData as $key => $value) {
                    if (isset($newData[$key])) {
                        $mergeData[$key] = array_merge($value, $newData[$key]);
                    }
                }
                $this->modelObj->callback($mergeData);
            }
        } catch (\Exception $e) {
            error_log("\nerror:" . $e->getMessage(), 3, "error.log");
            return false;
        }
        return true;
    }

    /**
     * 根据配置，获取转移对应关系
     * @return [type] [description]
     */
    public function readRs()
    {
        $sql = '';
        if (!$this->modelObj->srcSql) {
            $sql = "select * from {$this->modelObj->getTablename()} ";
        } else {
            $sql = $this->modelObj->srcSql;
        }
        $sql .= " where {$this->modelObj->primaryKey} >= {$this->startid} and {$this->modelObj->primaryKey} < {$this->endid} ";
        $sql .= 'order by ' . $this->modelObj->primaryKey . ' ASC';
        $dbType = !empty($this->modelObj->readDbName) ? $this->modelObj->readDbName : 'read';
        $row    = \Data\Library\Db::exeSql($dbType, $sql, $this->taskid);
        return $row;
    }

    /**
     * 根据字段对应关系将源数据$row转换(处理一行数据)
     * @param  [type] $row [description]
     * @return [type]      [description]
     */
    public function analysisRs($row)
    {
        $Arr = array();
        foreach ($this->modelObj->fieldMap as $des => $src) {
            if (strpos($src, '/') !== false) {
                $a = explode('/', $src);
                if (strpos($a[0], ',')) {
                    $par   = '';
                    $param = explode(',', $a[0]);
                    foreach ($param as $p) {
                        if (array_key_exists($p, $row)) {
                            $par[] = $row[$p];
                        } else {
                            $par[] = $p;
                        }
                    }
                    $Arr[$des] = $row[$des] = call_user_func(array($this->modelObj, $a[1]), $par);
                    if ($Arr[$des] === false) {
                        return false;
                    }
                    //自定义函数，返回false，表示跳过当前记录
                } else {
                    if ($a['0'] == '*') {
                        $Arr[$des] = $row[$des] = call_user_func(array($this->modelObj, $a[1]), $row);
                    } elseif (array_key_exists($a['0'], $row)) {
                        $Arr[$des] = $row[$des] = call_user_func(array($this->modelObj, $a[1]), $row[$a[0]]);
                    } else {
                        $Arr[$des] = $row[$des] = call_user_func(array($this->modelObj, $a[1]), $a[0]);
                    }
                }
                if ($Arr[$des] === false) {
                    return false;
                }
            } else {
                $Arr[$des] = $row[$des] = $row[$src];
            }
            if (stripos($des, '-')) {
                $temp = array_combine(explode("-", $des), $Arr[$des]);
                unset($Arr[$des]);
                foreach ($temp as $key => $value) {
                    $Arr[$key] = $row[$key] = $value;
                }
            }
        }
        return $Arr;
    }

    /**
     * 将数据写到目标数据库
     * @param  [type] $newData [description]
     * @return [type]          [description]
     */
    public function writeRs($newData)
    {
        if (empty($newData)) {
            return '';
        }
        $fieldarray = array_keys(current($newData));
        $fields     = implode(",", $fieldarray);

        $sql = "replace into {$this->modelObj->descTable} " . "(" . $fields . ") VALUES";
        foreach ($newData as $line) {
            $sql .= "\n(";
            foreach ($line as $field) {
                $sql .= "'" . addslashes($field) . "',";
            }
            $sql = substr($sql, 0, -1) . "),";
        }
        $sql = substr($sql, 0, -1);
        $rs  = \Data\Library\Db::exeSql($this->modelObj->writeDbName, $sql, $this->taskid);
    }
}
