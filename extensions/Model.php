<?php

namespace li3_clickatell\extensions;

use li3_clickatell\extensions\model\Behaviors;

class Model extends \lithium\data\Model {

	public static function getApiId() {
		return static::getConfig('api_id');
	}

	public static function getFrom() {
		return static::getConfig('from');
	}

	private static function getConfig($field) {
		$self = static::_object();
		$conn = $self::connection();
		return $conn->_config[$field];
	}

	/**
	 * Catches all context method calls and, if it's proper to call,
	 * starts the API request process. Otherwise invokes the method.
	 *
	 * @access public
	 * @param string $method
	 * @param array $params
	 * @return mixed
	 */
	public static function __callStatic($method, $params) {
		switch ($method) {
			case 'authenticate':
			case 'ping':
			case 'send':
			case 'query':
			case 'startbatch':
			case 'senditem':
			case 'quicksend':
			case 'endbatch':
			case 'send_bulk':
				$self = static::_object();
				$conn = $self::connection();
				return $conn->invokeMethod($method, $params); // forward
				break;
		}

		return parent::__callStatic($method, $params); // ignore
	}
}

?>