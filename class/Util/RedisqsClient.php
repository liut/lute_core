<?php
/*
----------------------------------------------------------------------------------------------------------------
Redis Queue Service 

Author: cloud

Useage:

$redisqs = new Util_RedisqsClient($host, $port);
$result = $redisqs->put($queue_name, $queue_data); //1. PUT text message into a queue. If PUT successful, return boolean: true. If an error occurs, return boolean: false. 
$result = $redisqs->get($queue_name); //2. GET text message from a queue. Return the queue contents. If an error occurs, return boolean: false. 
$result = $redisqs->gets($queue_name); //3. GET text message and pos from a queue. Return example: array("text message"). If an error occurs, return boolean: false. 
$result = $redisqs->status($queue_name); //4. View queue status
$result = $redisqs->status_json($queue_name); //5. View queue status in json. 
$result = $redisqs->view($queue_name, $queue_pos); //6. View the contents of the specified queue pos (id). Return the contents of the specified queue pos.
$result = $redisqs->reset($queue_name); //7. Reset the queue. If reset successful, return boolean: true. If an error occurs, return boolean: false
$result = $redisqs->maxqueue($queue_name, $num); //8. Change the maximum queue length of per-queue. If change the maximum queue length successful, return boolean: true. If  it be cancelled, return boolean: false
$result = $redisqs->synctime($num); //9. Change the interval to sync updated contents to the disk. If change the interval successful, return boolean: true. If  it be cancelled, return boolean: false

----------------------------------------------------------------------------------------------------------------
*/

class Util_RedisqsClient
{
	protected $_host;
	protected $_port;
	private $_redis;
	
	public function __construct($host='127.0.0.1', $port=6379) {
		$this->_host = $host;
		$this->_port = $port;
		$this->_redis = new Redis();
		$res = $this->_redis->connect($host, $port);
		if(true != $res){
			throw new Exception('Redis connect failed');
		}
	}

	public function put($queue_name, $queue_data)
	{
		return $this->_redis->lPush($queue_name, $queue_data);
	}
	
	public function get($queue_name)
	{
		return $this->_redis->rPop($queue_name);
	}
		
	public function gets($queue_name)
	{
		return $this->_redis->lRange($queue_name, 0, -1);
	}   
		
	public function status($queue_name)
	{
		return $this->_redis->lLen($queue_name);
	}
		
	public function view($queue_name, $queue_pos)
	{
		return $this->_redis->lIndex($queue_name, $queue_pos);
	}
		
	public function reset($queue_name)
	{
		return true;
	}
		
	public function maxqueue($queue_name, $num)
	{
		return true;
	}
		
	public function status_json($queue_name)
	{
		return json_encode($this->_redis->info('STATS'));
	}
	
	/**
	 * Performs a synchronous save.
	 *
	 * @return  bool:   TRUE in case of success, FALSE in case of failure.
	 * If a save is already running, this command will fail and return FALSE.
	 * @link    http://redis.io/commands/save
	 * @example $redis->save();
	 */
	public function synctime($num)
	{
		return $this->_redis->save();
	}
}

