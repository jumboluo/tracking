<?php
class EpollSocketServer
{
	private static $socket;
	private static $connections;
	private static $buffers;
	
	public static $errno;
	public static $errstr;

	function EpollSocketServer ($port)
	{
		if (!extension_loaded('libevent')) {
			self::$errstr = "请先安装libevent扩展库。";
			return;
		}

		if ($port < 1024) {
			self::$errstr = "端口号不应小于1024";
			return;
		}
		
		set_time_limit(0);

		$socket_server = stream_socket_server("tcp://0.0.0.0:{$port}", self::$errno, self::$errstr);

		stream_set_blocking($socket_server, 0); // 非阻塞

		$base = event_base_new();
		$event = event_new();
		event_set($event, $socket_server, EV_READ | EV_PERSIST, array(__CLASS__, 'ev_accept'), $base);
		event_base_set($event, $base);
		event_add($event);
		event_base_loop($base);

		self::$connections = array();
		self::$buffers = array();
	}

	function ev_accept($socket, $flag, $base)
	{
		static $id = 0;

		$connection = stream_socket_accept($socket);
		stream_set_blocking($connection, 0);

		$id++; // increase on each accept

		$buffer = event_buffer_new($connection, array(__CLASS__, 'ev_read'), array(__CLASS__, 'ev_write'), array(__CLASS__, 'ev_error'), $id);
		event_buffer_base_set($buffer, $base);
		event_buffer_timeout_set($buffer, 30, 30);
		event_buffer_watermark_set($buffer, EV_READ, 0, 0xffffff);
		event_buffer_priority_set($buffer, 10);
		event_buffer_enable($buffer, EV_READ | EV_PERSIST);

		// we need to save both buffer and connection outside
		self::$connections[$id] = $connection;
		self::$buffers[$id] = $buffer;
	}

	function ev_error($buffer, $error, $id)
	{
		event_buffer_disable(self::$buffers[$id], EV_READ | EV_WRITE);
		event_buffer_free(self::$buffers[$id]);
		fclose(self::$connections[$id]);
		unset(self::$buffers[$id], self::$connections[$id]);
	}

	function ev_read($buffer, $id)
	{
		static $ct = 0;
		$ct_last = $ct;
		$ct_data = '';
		while ($read = event_buffer_read($buffer, 1024)) {
			$ct += strlen($read);
			$ct_data .= $read;
		}
		$ct_size = ($ct - $ct_last) * 8;
		if(APP_DEBUG===true) Log::write("[$id] " . __METHOD__ . " > " . $ct_data, Log::INFO);
//		event_buffer_write($buffer, "Received $ct_size byte data./r/n");
	}

	function ev_write($buffer, $id)
	{
		if(APP_DEBUG===true) Log::write("[$id] " . __METHOD__ , Log::INFO);
	}
}