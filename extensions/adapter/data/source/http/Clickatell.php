<?php

namespace li3_clickatell\extensions\adapter\data\source\http;


/**
 * li3_Clickatell
 *
 * Clickatell API Datasource Wrapper extension for Lithium
 *
 *
 * @see \lithium\data\source\Http
 *
 * @link http://www.clickatell.com/developers/clickatell_api.php
 */

class Clickatell extends \lithium\data\source\Http {

	/**
	 * Clickatell API Session ID.
	 */
	private $session_id = null;

	/**
	 * Constructor
	 *
	 * @param array $config
	 */
	public function __construct(array $config = array()) {
		$defaults = array(
			'host'				=> 'api.clickatell.com',
			'scheme'			=> 'https',
			'port'				=> '443',
			'method'			=> 'get',
			'service'			=> 'rest',
			'format'			=> 'xml',
			'method_prefix'		=> 'clickatell',
			'encoding'			=> 'UTF-8',
			'socket'			=> 'Context'
		);

 		$config += $defaults;

		if (!isset($config['api_id'])) {
			throw new ConfigException('Clickatell api_id is not configured.');
		}
		if (!isset($config['api_username'])) {
			throw new ConfigException('Clickatell api_username is not configured.');
		}
		if (!isset($config['api_password'])) {
			throw new ConfigException('Clickatell api_password is not configured.');
		}

		parent::__construct($config);
	}

	/**
	 * Query the Clickatell API server.
	 *
	 * @param string $path Relative path or callback.
	 * @param array $data Query string data to pass in URL.
	 * @return string Server response.
	 */
	private function _query($path, $data = array()) {
		try {
// pr(compact('path', 'data'), 'args: ');
			$result = $this->connection->get($path, $data);
// pr($result, 'response: ');
			if (preg_match_all('/^[OI][KD]:\s*(.*?)\s*(To:.+?)?$/m', $result, $matches, PREG_SET_ORDER)) {
				// if there is only one resultset, return the id only, otherwise return an array
				// the array version is like: array(array('original line', 'parsed id', 'other data'))
				return count($matches) > 1? $matches : $matches[0][1];
			}
			else if (preg_match('/^ERR:(\s*(\d+),\s*)?(.*)$/', $result, $matches)) {
				throw new ClickatellAPIQueryException($matches[3], $matches[2]);
			}
			throw new ClickatellAPIQueryException('Unexpected Result: '. $result, 6660);
		} catch (ClickatellAPIQueryException $e) {
			switch ($e->getCode()) { // see if its something we know how to solve
				case '001': // Authentication failed
				case '003': // Session ID expired
				case '005': // Missing session ID
					// force re-authentication
					static $tries = 0;
					if ($this->authenticate(true) && $tries++ < 1) { // if it works
						$this->_query($path, $data); // try one more time
					}
					break;
				case '002': // Unknown username or password
				case '004': // Account frozen
				case '007': // IP Lockdown violation You have locked down the API instance to a specific IP address and then sent from an IP address different to the one you set.
				case '101': // Invalid or missing parameters
				case '102': // Invalid user data header
				case '103': // Unknown API message ID
				case '104': // Unknown client message ID
				case '105': // Invalid destination address
				case '106': // Invalid source address
				case '107': // Empty message
				case '108': // Invalid or missing API ID
				case '109': // Missing message ID This can be either a client message ID or API message ID. For example when using the stop message command.
				case '110': // Error with email message
				case '111': // Invalid protocol
				case '112': // Invalid message type
				case '113': // Maximum message parts exceeded The text message component of the message is greater than	the permitted 160 characters (70 Unicode characters). Select concat equal to 1,2,3-N to overcome this by splitting the message across multiple messages.
				case '114': // Cannot route message	This implies that the gateway is not currently routing messages to this network prefix. Please email support@clickatell.com with the mobile number in question.
				case '115': // Message expired
				case '116': // Invalid Unicode data
				case '120': // Invalid delivery time
				case '121': // Destination mobile number blocked. This number is not allowed to receive messages from us and has been put on our block list.
				case '122': // Destination mobile opted out
				case '123': // Invalid Sender ID A sender ID needs to be registered and approved before it can be successfully used in message sending.
				case '128': // Number delisted This error may be returned when a number has been delisted.
				case '130': // Maximum MT limit exceeded until <UNIX TIME STAMP>. This error is returned when an account has exceeded the maximum number of MT messages which can be	sent daily or monthly. You can send messages again on the date indicated by the UNIX TIMESTAMP.
				case '201': // Invalid batch ID
				case '202': // No batch template
				case '301': // No credit left
				case '302': // Max allowed credit
				default:
					// nothing we can do
					throw $e; // bubble up
					break;
			}
		}
	}

	/**
	 * Authenticate with the Clickatell API Server.
	 *
	 * @param boolean $force Flushes session_id cache
	 * @return string Clickatell API Session ID.
	 */
	public function authenticate($force = false) {
		if (!$force && !empty($this->session_id)) {
			return $this->session_id;
		}

		return $this->session_id = $this->_query('/http/auth', array(
			'api_id'	=> $this->_config['api_id'],
			'user'		=> $this->_config['api_username'],
			'password'	=> $this->_config['api_password']
		));
	}

	/**
	 * Ping the Clickatell API Server to maintain session.
	 * Ideally called every 10 minutes or so.
	 *
	 * This command prevents the session ID from expiring in periods of inactivity. The session ID is set to expire
	 * after 15 minutes of inactivity. You may have multiple concurrent sessions using the same session ID.
	 *
	 * @return boolean TRUE on success, FALSE on failure
	 */
	public function ping() {
		return (bool) $this->_query('/http/ping', array('session_id' => $this->authenticate()));
	}

	/**
	 * Send a single text message via Clickatell API Server.
	 *
	 * Each message returns a unique identifier in the form of an API message ID. This can be used to track and
	 * monitor any given message. The API message ID (apiMsgid) is returned after each post.
	 *
	 * @param string $to Recipient longcode telephone number.
	 * @param string $text Outgoing message body.
	 * @return string API Message ID
	 */
	public function send($to, $txt) {
		return $this->_query('/http/sendmsg', array(
			'session_id'	=> $this->authenticate(),
			'to'			=> $to,
			'text'			=> $txt,
		) + (empty($this->_config['from'])? array() : array( // optional
			'mo'			=> 1, // enables reply ability
			'from'			=> $this->_config['from'],
		)) + (empty($this->_config['callback'])? array() : array( // optional
			'callback'		=> $this->_config['callback'], // determines how server will respond
			'deliv_ack'		=> 1 // acknowledge delivery
		)));
	}

	/**
	 * Check the status of a message queued for delivery on the Clickatell API Server.
	 *
		 * This command returns the status of a message. You can query the status with either the apimsgid or
		 * climsgid. The API Message ID (apimsgid) is the message ID returned by the Gateway when a message
		 * has been successfully submitted. If you specified your own unique client message ID (climsgid) on
		 * submission, you may query the message status using this value. You may also authenticate with api_id,
		 * user and password.
	 *
	 * @param string $apimsgid API Message ID.
	 * @return string API Message Status
	 */
	public function query($apimsgid) {
		return $this->_query('/http/querymsg', array(
			'session_id' => $this->authenticate(),
			'apimsgid'	 => $apimsgid
		));
	}

	/**
	 * 7.1 Start batch
	 *
	 * Once you have issued this command, you will be returned a batch ID that is to be used when sending
	 * multiple batch items. Included functionality also allows for message merging where you can substitute
	 * fields that you have defined in your template. The field names are called field1 though to fieldN.
	 * This command can take all the parameters of sendmsg, with the addition of a template, and the exception
	 * of both the destination address and the text fields. The template parameter must be URL encoded. It must
	 * be used before either the senditem or quicksend command.
	 *
	 * Command:
	 * http://api.clickatell.com/http_batch/startbatch?session_id=xxx&...............&template=Hi #field1#, your
	 * balance is #field2#.&from=Sender&deliv_ack=1
	 * Response:
	 * ID: batch id
	 * or:
	 * ERR: Error number, error description
	 *
	 * @param string $template
	 * @return string Batch ID
	 */
	public function startbatch($template) {
		return $this->_query('/http_batch/startbatch', array(
			'session_id'	=> $this->authenticate(),
			'template'		=> $template,
		) + (empty($this->_config['from'])? array() : array( // optional
			'mo'			=> 1, // enables reply ability
			'from'			=> $this->_config['from'],
		)) + (empty($this->_config['callback'])? array() : array( // optional
			'callback'		=> $this->_config['callback'], // determines how server will respond
			'deliv_ack'		=> 1 // acknowledge delivery
		)));
	}

	/**
	 * 7.2 Sending messages to existing batch
	 * Note: The fields 1-N that you defined in the startbatch command are used to optionally personalise the
	 * message.
	 *
	 * Command:
	 * http://api.clickatell.com/http_batch/senditem?session_id=xxx&batch_id=xxx&to=123456789&field1=Joe&fie
	 * ld2=$150........
	 *
	 * Response:
	 * ID: apimsgid
	 * or:
	 * ERR: Error number, error description
	 *
	 * @param string $batch_id Batch ID from startbatch()
	 * @param array $to List of Recipient longcodes.
	 * @param array $fields (optional) Template field values.
	 * @return string API Message ID
	 */
	public function senditem($batch_id, $to, $fields = array()) {
		return $this->_query('/http_batch/senditem', array(
			'session_id'	=> $this->authenticate(),
			'batch_id'		=> $batch_id,
			'to'			=> join(',', $to)
		) + $fields // optional
		);
	}

	/**
	 * 7.3 Quick send to batch
	 * Where one has the requirement to send the same message to multiple recipients, you can use the
	 * quicksend command. This command offers low overhead and maximum throughput. It is essentially a
	 * reference to a predefined template and a string of destination addresses.
	 * Command:
	 * http://api.clickatell.com/http_batch/quicksend?session_id=xxx&batch_id=xxx&to=123456789,234567890,34
	 * 5678901,etc
	 * Response:
	 * ID: apimsgid To: xxxxx
	 * or:
	 * ERR: Error number, error description
	 * Note: A response is returned for each destination address on a new line. The	newline character (\n) is
	 * used to create the line break.
	 *
	 * @param string $batch_id Batch ID from startbatch()
	 * @param array $to List of Recipient longcodes.
	 * @return string API Message ID, To
	 */
	// @TODO: Verify this is not technically limited to the maximum length of a URL. Or maybe it is supposed to be POSTDATA.
	public function quicksend($batch_id, $to) {
		return $this->_query('/http_batch/quicksend', array(
			'session_id'	=> $this->authenticate(),
			'batch_id'		=> $batch_id,
			'to'			=> join(',', $to)
		));
	}

	/**
	 * 7.4 End batch
	 * This command ends a batch and is not required (following a batch send). Batches will expire automatically
	 * after 24 hours.
	 *
	 * Command:
	 * http://api.clickatell.com/http_batch/endbatch?session_id=xxx&batch_id=xxx
	 * Response:
	 * OK
	 * or:
	 * ERR: Error number, error description
	 *
	 * @param string $batch_id Batch ID from startbatch()
	 * @return boolean
	 */
	public function endbatch($batch_id) {
		return $this->_query('/http_batch/endbatch', array(
			'session_id'	=> $this->authenticate(),
			'batch_id'		=> $batch_id
		));
	}

	/**
	 * Send a bulk text messages via Clickatell API Server.
	 *
	 * @param array $to List of Recipient longcode telephone numbers.
	 * @param string $template Outgoing message body, with optional #field# markers.
	 * @param array $fields (optional) Field values. If not specified, it is assumed message is the same for everyone.
	 * @return boolean
	 */
	public function send_bulk($to, $template, $fields = array()) {
		$batch_id = $this->startbatch();
		$this->senditem($batch_id, $to, $fields);
		return $this->endbatch($batch_id);
	}

}

class ClickatellAPIQueryException extends \lithium\data\model\QueryException {
	public function __construct($msg, $code) {
		$this->message = $msg;
		$this->code = $code; // preserve string
	}
}

?>