<?php

abstract class Socket {
	protected $sock;
	protected $timeout_send;
	protected $timeout_recv;
	public $sendbuf_size = 65535;
	public $recvbuf_size = 65535;

	public $errCode = 0;
	public $errMsg = '';
	public $host; //Server Host
	public $port; //Server Port

	const ERR_RECV_TIMEOUT = 11; //接收数据超时，server端在规定的时间内没回包
	const ERR_INPROGRESS = 115; //正在处理中

	/**
	 * 错误信息赋值
	 */
	protected function set_error() {
		$this->errCode = socket_last_error($this->sock);
		$this->errMsg = socket_strerror($this->errCode);
		socket_clear_error($this->sock);
	}
	/**
	 * 设置超时
	 * @param float $recv_timeout 接收超时
	 * @param float $send_timeout 发送超时
	 */
	function set_timeout($timeout_recv, $timeout_send) {
		$_timeout_recv_sec = (int) $timeout_recv;
		$_timeout_send_sec = (int) $timeout_send;

		$this->timeout_recv = $timeout_recv;
		$this->timeout_send = $timeout_send;

		$_timeout_recv = array('sec' => $_timeout_recv_sec, 'usec' => (int) (($timeout_recv - $_timeout_recv_sec) * 1000 * 1000));
		$_timeout_send = array('sec' => $_timeout_send_sec, 'usec' => (int) (($timeout_send - $_timeout_send_sec) * 1000 * 1000));

		$this->setopt(SO_RCVTIMEO, $_timeout_recv);
		$this->setopt(SO_SNDTIMEO, $_timeout_send);
	}
	/**
	 * 设置socket参数
	 */
	function setopt($opt, $set) {
		socket_set_option($this->sock, SOL_SOCKET, $opt, $set);
	}

	/**
	 * 获取socket参数
	 */
	function getopt($opt) {
		return socket_get_option($this->sock, SOL_SOCKET, $opt);
	}

	function get_socket() {
		return $this->sock;
	}

	/**
	 * 设置buffer区
	 * @param $sendbuf_size
	 * @param $recvbuf_size
	 */
	function set_bufsize($sendbuf_size, $recvbuf_size) {
		$this->setopt(SO_SNDBUF, $sendbuf_size);
		$this->setopt(SO_RCVBUF, $recvbuf_size);
	}
	/**
	 * 析构函数
	 */
	function __destruct() {
		$this->close();
	}
}

/**
 * TCP客户端
 * @author 兰戈
 */
class Tcp_client extends Socket {
	/**
	 * 是否重新连接
	 */
	public $try_reconnect = true;
	public $connected = false; //是否已连接

	/**
	 * 发送数据
	 * @param string $data
	 * @return bool | int
	 */
	function send($data) {
		$length = strlen($data);
		$written = 0;
		$t1 = microtime(true);
		//总超时，for循环中计时
		while ($written < $length) {
			$n = socket_send($this->sock, substr($data, $written), $length - $written, null);
			//超过总时间
			if (microtime(true) > $this->timeout_send + $t1) {
				return false;
			}
			if ($n === false) {
				$errno = socket_last_error($this->sock);
				//判断错误信息，EAGAIN EINTR，重写一次
				if ($errno == 11 or $errno == 4) {
					continue;
				} else {
					return false;
				}
			}
			$written += $n;
		}
		return $written;
	}

	/**
	 * 接收数据
	 * @param int $length 接收数据的长度
	 * @param bool $waitall 等待接收到全部数据后再返回，注意这里超过包长度会阻塞住
	 */
	function recv($length = 65535, $waitall = 0) {
		if ($waitall) {
			$waitall = MSG_WAITALL;
		}

		$ret = socket_recv($this->sock, $data, $length, $waitall);

		if ($ret === false) {
			$this->set_error();
			//重试一次，这里为防止意外，不使用递归循环
			if ($this->errCode == 4) {
				socket_recv($this->sock, $data, $length, $waitall);
			} else {
				return false;
			}
		}
		return $data;
	}

	/**
	 * 连接到服务器
	 * 接受一个浮点型数字作为超时，整数部分作为sec，小数部分*100万作为usec
	 *
	 * @param string $host 服务器地址
	 * @param int $port 服务器地址
	 * @param float $timeout 超时默认值，连接，发送，接收都使用此设置
	 */
	function connect($host, $port, $timeout = 0.1, $nonblock = false) {
		//判断超时为0或负数
		if (empty($host) or empty($port) or $timeout <= 0) {
			$this->errCode = -10001;
			$this->errMsg = "param error";
			return false;
		}
		$this->host = $host;
		$this->port = $port;
		//创建socket
		$this->sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		if ($this->sock === false) {
			$this->set_error();
			return false;
		}
		//设置connect超时
		$this->set_timeout($timeout, $timeout);
		$this->setopt(SO_REUSEADDR, 1);
		//非阻塞模式下connect将立即返回
		if ($nonblock) {
			socket_set_nonblock($this->sock);
			@socket_connect($this->sock, $this->host, $this->port);
			return true;
		} else {
			//这里的错误信息没有任何意义，所以屏蔽掉
			if (@socket_connect($this->sock, $this->host, $this->port)) {
				$this->connected = true;
				return true;
			} elseif ($this->try_reconnect) {
				if (@socket_connect($this->sock, $this->host, $this->port)) {
					$this->connected = true;
					return true;
				}
			}
		}
		$this->set_error();
		trigger_error("connect server[{$this->host}:{$this->port}] fail.errno={$this->errCode}|{$this->errMsg}");
		return false;
	}

	/**
	 * 关闭socket连接
	 */
	function close() {
		if ($this->sock) {
			socket_close($this->sock);
		}
		$this->sock = null;
	}

	/**
	 * 是否连接到服务器
	 * @return bool
	 */
	function isConnected() {
		return $this->connected;
	}
}
