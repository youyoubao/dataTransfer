<?php
namespace dormscript\Data\Library;

class RabbitMq
{
    public $conn;
    public $channel;

    public function writeQueue($e_name, $str, $routerKey = '', $isBroad = 0)
    {
        //创建交换机对象
        $ex = new \AMQPExchange($this->channel);
        $ex->setName($e_name);
        //$ex->setType(AMQP_EX_TYPE_FANOUT); //direct类型
        $ex->setType(AMQP_EX_TYPE_DIRECT); //direct类型
        $ex->setFlags(AMQP_DURABLE); //持久化
        //echo "Exchange Status:" . $ex->declare() . "\n";
        //发送消息
        if ($isBroad) {
            $ex->publish($str, $routerKey, AMQP_NOPARAM, array('delivery_mode' => '2'));
        } else {
            $ex->publish($str, $routerKey);
        }
    }
    public function readQueue($q_name)
    {
        $q = new \AMQPQueue($this->channel);
        $q->setName($q_name);
        //$q->setFlags(AMQP_DURABLE);
        //$q->declare();
        //$q->bind('exchange', $bindingkey);
        //消息获取
        $messages = $q->get(AMQP_AUTOACK);
        if ($messages) {
            return $messages->getBody();
        } else {
            return false;
        }
    }

    public function reconnect($conn_args)
    {
        //创建连接和channel
        $this->conn = new \AMQPConnection($conn_args);
        try {
            if (!$this->conn->connect()) {
                die("Cannot connect to the broker!\n");
            }
        } catch (EXCEPTION $e) {
            sleep(300);
            if (!$this->conn->connect()) {
                die("Cannot connect to the broker!\n");
            }
        }
        $this->channel = new \AMQPChannel($this->conn);
    }
    /**
     * 入RabbitMQ队列
     * @param [type] $exName    [交换机名]
     * @param [type] $routingKey [路由名]
     * @param [type] $value     [队列的值]
     * @param [type] $dbType     [数据库类型,默认为mysql]
     * 按照此规则生成的默认队列名称为 exName_routeKey_dbType;值为value
     */
    public function set($exName, $routingKey, $value, $dbType = 'mysql')
    {
        //创建交换机,设置交换机名
        $ex = new \AMQPExchange($this->channel);
        $ex->setName($exName);
        $ex->setType(AMQP_EX_TYPE_DIRECT); //广播模式
        $ex->setFlags(AMQP_DURABLE); //交换器进行持久化，即 RabbitMQ 重启后会自动重建
        // $ex->declareExchange();
        //设置队列名
        $queue = new \AMQPQueue($this->channel);
        $queue->setName($exName . '_' . $routingKey . '_' . $dbType);
        $queue->setFlags(AMQP_DURABLE); //队列进行持久化，即 RabbitMQ 重启后会自动重建
        $queue->declareQueue();
        //交换机和路由绑定到队列
        $queue->bind($exName, $routingKey);
        //入队列
        if (is_array($value)) {
            $value = json_encode($value);
        }
        $ex->publish($value, $routingKey, AMQP_NOPARAM, array('delivery_mode' => '2'));
    }
}
