<?php
namespace Data;

//注册自动加载类
define("BASEDIR", __DIR__);
include BASEDIR . "/Library/Loader.php";
spl_autoload_register('\Data\Library\Loader::autoloder');

include "config.php";

/**
 * 数据迁移
 * 1：从配置文件读取所有需要迁移的数据表
 * 2：获取所有表的最小、最大ID
 * 3：并行调task对每个数据表进行数据迁移
 * 4：支持动态修改需要转移的表，及每个表应该有多少个进程来转移
 */
class DataTransfer
{
    private $serv;
    private $setting; //设置每个表应该由几个进程来处理
    private $curId; //每个表当前处理到的位置
    private $maxId; //每个表的最大ID
    private $minId; //记录表的最小ID
    private $taskinfo; //所有task需要处理的表
    private $step = 100; //每次处理的数据数量（以后改成动态可调的）
    private $startTime;
    public function __construct()
    {
        global $setting;
        date_default_timezone_set('PRC');
        $this->startTime = time();
        $this->setting                   = $setting;
        $this->max_task_num              = 500;
        list($this->curId, $this->maxId) = $this->getId();
        $this->minId = $this->curId;

        $this->serv = new \swoole_server("0.0.0.0", 9501);
        $this->serv->set(array(
            'worker_num'      => 1,
            'daemonize'       => false,
            'max_request'     => 10000,
            'dispatch_mode'   => 3,
            'debug_mode'      => 1,
            'task_worker_num' => $this->max_task_num,
        ));
        $this->serv->on('WorkerStart', array($this, 'onWorkerStart'));
        $this->serv->on('Close', array($this, 'onClose'));
        $this->serv->on('Receive', array($this, 'onReceive'));
        $this->serv->on('Task', array($this, 'onTask'));
        $this->serv->on('Finish', array($this, 'onFinish'));
        $this->serv->start();
    }
    /**
     * 获取每个表的最大ID，最小ID
     * @return [type] [description]
     */
    public function getId()
    {
        unset(\Data\Library\Db::$dbPool['0']);
        $min = $max = array();
        foreach ($this->setting as $tablename => $taskNum) {
            $modelObj     = \Data\Models\Models::getObj($tablename);
            $dbType       = !empty($modelObj->readDbName) ? $modelObj->readDbName : 'read';
            $tableNameStr = str_replace(".", "_", $tablename);
            //解决库重名时desc table出错的问题
            $realTableName = $modelObj->getTablename();

            //获取表主键名字
            $descSql     = "desc $realTableName";
            $row         = \Data\Library\Db::exeSql($dbType, $descSql, 1);
            $primiaryKey = current($row['0']);

            //查出最小ID
            $sql                = "select * from $realTableName order by $primiaryKey ASC limit 0,1";
            $row                = \Data\Library\Db::exeSql($dbType, $sql, 1);
            $min[$tableNameStr] = empty($row['0']) ? 0 : intval(current($row['0']));

            //查出最大ID
            $sql                = "select * from $realTableName order by $primiaryKey DESC limit 0,1";
            $row                = \Data\Library\Db::exeSql($dbType, $sql, 1);
            $max[$tableNameStr] = empty($row['0']) ? 0 : intval(current($row['0']));

            if($modelObj->descTable) {
                //从目标库中读出相关信息
                $descSql     = "desc ".$modelObj->descTable;
                $row         = \Data\Library\Db::exeSql('write', $descSql, 1);
                $primiaryKey = current($row['0']);
                
                $sql = "select * from ".$modelObj->descTable. " order by $primiaryKey DESC limit 0,1";
                $row                = \Data\Library\Db::exeSql('write', $sql, 1);
                if (empty($row)) {
                    $descMaxId = 0;
                } else {
                    $descMaxId = intval(current($row['0']));
                }
                if ($descMaxId > $min[$tableNameStr]) {
                    $min[$tableNameStr] = $descMaxId;
                }
            }
        }
        unset(\Data\Library\Db::$dbPool['0']);
        return array($min, $max);
    }

    public function onWorkerStart($serv, $worker_id)
    {
        if ($worker_id != 0) {
            return '';
        }
        sleep(1); //等待task进程启动
        $taskid = 1;
        //根据配置的进程数量，启动所有表的数据迁移
        foreach ($this->setting as $tablename => $maxnum) {
            //启用多个task进程来处理表
            for ($i = 0; $i < $maxnum; $i++) {
                $this->addTask($tablename, $taskid++);
            }
        }
    }

    public function onClose($serv, $fd, $from_id)
    {
        echo "Client {$fd} close connection\n";
    }

    public function onReceive($serv, $fd, $from_id, $str)
    {
        $params = explode("-", $str);
        echo "\n get data:" . $params['0'] . "\t";
        switch ($params['0']) {
            case 'reassign':
                $ret = $this->reassign();
                break;
            case 'setCurID':
                $ret = $this->setCurID($params['1'], $params['2']);
                break;
            case 'getinfo':
                $ret = $this->getInfo();
                break;
            case 'singleData':
                $ret = $this->singleData($params['1'], $params['2']);
                break;
        }
        $serv->send($fd, $ret);
    }

    public function onTask($serv, $task_id, $from_id, $param)
    {
        //处理数据表tablename中ID>=$startid && $ID<$startid+100的记录
        list($tablename, $startid, $endid, $taskid) = $param;

        if ($tablename == "delDb" && $startid = -1 && $endid = -1) {
            //删除mysql连接请求
            \Data\Library\Db::delDbPool($taskid);
            return false;
        }
        echo "\n OnTask: $taskid \t $tablename \t $startid - $endid ";
        $obj = new Library\SwitchData();
        $rs  = $obj->run($tablename, $startid, $endid, $taskid, $serv);
        while (!$rs) {
            //执行出错时，一直重试。关闭数据库连接，sleep 5秒
            \Data\Library\Db::delDbPool($taskid);
            \Data\Models\Models::delObj($tablename);
            echo "\n 处理( $tablename, $startid, $endid )出错，释放所有资源并重新连接mysql \n";
            sleep(5);
            $rs = $obj->run($tablename, $startid, $endid, $taskid);
        }
        if ($startid + 1 == $endid) {
            //增量更新，处理完成后不再重新处理
            return array($taskid);
        }
        return $taskid;
    }

    public function onFinish($serv, $task_id, $param)
    {
        if (is_array($param)) {
            //增量
            $taskid                  = current($param);
            $this->taskinfo[$taskid] = '';
        } elseif ($param) {
            echo "\n onFinish : taskid: { $param } ";
            if($this->taskinfo[$param]) {
                $this->addTask($this->taskinfo[$param], $param);
            }
        } else {
            echo "\n close mysqli \n";
        }
    }

    /**
     * 根据tablename获取数据表迁移到的最大ID
     * @param  [type] $tablename [description]
     * @return [type]            [description]
     */
    private function getcurId($tablename)
    {
        $startid = $endid = 0;
        $tmp     = str_replace(".", "_", $tablename); //对表名转义

        if (!isset($this->maxId[$tmp]) || !isset($this->curId[$tmp])) {
            list($this->curId, $this->maxId) = $this->getId();
            $this->minId = $this->curId;
        }
        if (!isset($this->curId[$tmp])) {
            $this->curId[$tmp] = 1;
        }
        $startid = $this->curId[$tmp];

        if ($startid > $this->maxId[$tmp]) {
            //所有数据已经处理结束
            return false;
        } elseif ($startid + $this->step > $this->maxId[$tmp]) {
            //最后一次取数据不足100条
            $endid             = $this->maxId[$tmp] + 1;
            $this->curId[$tmp] = $endid;
        } else {
            $endid             = $startid + $this->step;
            $this->curId[$tmp] = $endid;
        }
        return array($startid, $endid);
    }

    private function addTask($tablename, $taskid)
    {
        $Ids = $this->getcurId($tablename); //获取开始ID
        if ($Ids === false) {
            $this->taskinfo[$taskid] = '';
            $this->delDb($taskid);
            return false;
        }
        list($startid, $endid) = $Ids;

        echo "\n addTask: { $taskid }";
        $this->taskinfo[$taskid] = $tablename; //taskid这个进程用来处理tablename。在进行进程数量调整之前，taskid不会去处理其它表数据
        $this->serv->task(array($tablename, $startid, $endid, $taskid), $taskid); //投递task任务
    }
    /**
     * 断开task中的数据库连接
     * @param  int $taskid 进程ID
     * @return [type]         [description]
     */
    private function delDb($taskid)
    {
        $this->serv->task(array("delDb", -1, -1, $taskid), $taskid);
    }

    /**
     * 处理单条数据
     * @param  string $tablename 库名+表名（.分隔)
     * @param  int $id        主键ID
     * @return [type]            [description]
     */
    private function singleData($tablename, $id)
    {
        //找到一个空闲进程来处理这个请求（只有task400 - task500处理增量）
        for ($i = $this->max_task_num - 100; $i < $this->max_task_num; $i++) {
            if (empty($this->taskinfo[$i])) {
                $taskid = $i;
                $this->delDb($taskid);
                break;
            }
        }
        //如果未找到空闲进程
        if ($i == $this->max_task_num) {
            return "fail";
        }
        $this->taskinfo[$taskid] = $tablename; //taskid这个进程用来处理tablename。在进行进程数量调整之前，taskid不会去处理其它表数据
        $this->serv->task(array($tablename, $id, $id + 1, $taskid), $taskid); //投递task任务
        return 'OK';
    }
    /**
     * 根据配置文件重新分配task
     * @return [type] [description]
     */
    public function reassign()
    {
        $task_work_id = 0;
        include "config.php";
        $this->setting = $setting;
        foreach ($this->setting as $tablename => $maxnum) {
            for ($i = 0; $i < $maxnum; $i++) {
                $task_work_id += 1;
                //如果进程正处于休息状态，启动进程
                if (empty($this->taskinfo[$task_work_id])) {
                    $this->delDb($task_work_id);
                    $this->addTask($tablename, $task_work_id);
                } else {
                    $this->taskinfo[$task_work_id] = $tablename;
                }
            }
        }
        for ($i = $task_work_id + 1; $i < $this->max_task_num; $i++) {
            $this->taskinfo[$i] = '';
        }
        return json_encode(array_count_values($this->taskinfo));
    }
    public function setCurID($tablename, $Id)
    {
        $tmp               = str_replace(".", "_", $tablename); //对表名转义
        $this->curId[$tmp] = $Id;
        return "setCurId success !";
    }
    /**
     * 获取当前处理的状态
     * @return [type] [description]
     */
    public function getInfo()
    {
        $ret     = '';
        $taskNum = array_count_values($this->taskinfo);
        $ret .= json_encode($taskNum);
        //状态信息：所有表的进度:   tablename    现在ID   最大ID   几个进程在处理
        foreach ($this->setting as $tablename => $v) {
            $status[] = array(
                'tablename' => $tablename,
                'curId'     => $this->curId[str_replace('.', '_', $tablename)],
                'maxId'     => $this->maxId[str_replace('.', '_', $tablename)],
                'minId' => $this->minId[str_replace('.', '_', $tablename)],
            );
        }
        $ret .= "\n" . json_encode($status);
        $ret .= "\n" . json_encode(array_filter($this->taskinfo));
        $ret .= "\n" . $this->startTime;
        return $ret;
    }
}

new DataTransfer();
