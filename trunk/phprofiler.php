<?php

class ProfileRecord
{
	public $name;
	public $started;
	public $ended;
	public $children;
	public $detail;
	private $parent;
	
	function __construct($name, $started, $detail)
	{
		$this->children = array();
		$this->started = $started;
		$this->detail = $detail;
		$this->name = $name;
	}
	
	public function SetParent($parent)
	{
		$this->parent = $parent;
	}
	
	public function GetParent()
	{
		if (isset($this->parent))
		{
			return $this->parent;
		}
		return false;
	}
	
	public function AddChild($child)
	{
		$child->SetParent($this);
		$this->children[] = $child;
	}
	
	public function End($timestamp)
	{
		$this->ended = $timestamp;
	}
}

class Profiler
{
	public $starting_timestamp;
	public $precision;
	public $master_record;
	public $domain;
	public $top_name;
	protected $current_sections;
	protected $current_record;
	protected $report_url;
	protected $enabled;
	
	function __construct($report_url, $precision = 3)
	{
		$this->enabled = true;
		$this->precision = $precision;
		$this->report_url = $report_url;
		
		$this->starting_timestamp = time();
		$this->top_name = $_SERVER['PHP_SELF'];
		$this->domain = $_SERVER['HTTP_HOST'];

		$this->current_sections = array();
		$this->master_record = new ProfileRecord($this->top_name, $this->GetArbitraryTimestamp());
		$this->current_record = $this->master_record;
	}
	
	function __destruct()
	{
		if (!$this->enabled) return null;
		
		$this->EndSection($this->top_name);
		
		$this->SendToReportServer();
	}
		
	protected function SendToReportServer()
	{
		// Based on http://stackoverflow.com/questions/124462/asynchronous-php-calls/2924987#2924987
		$params = array('domain' => $this->domain, 'starting_timestamp' => $this->starting_timestamp, 'precision' => $this->precision, 
			'data' => json_encode($this));
		foreach ($params as $key => &$val)
		{
			$post_params[] = $key . '=' . urlencode($val);
		}
		$post_string = implode('&', $post_params);

		$parts = parse_url($this->report_url);

		$fp = fsockopen($parts['host'],	isset($parts['port']) ? $parts['port'] : 80, $errno, $errstr, 30);

		$out = "POST " . $parts['path'] . " HTTP/1.1\r\n";
		$out .= "Host: " . $parts['host'] . "\r\n";
		$out .= "Content-Type: application/x-www-form-urlencoded\r\n";
		$out .= "Content-Length: " . strlen($post_string)."\r\n";
		$out .= "Connection: Close\r\n\r\n";
		$out .= $post_string;

		fwrite($fp, $out);
		fclose($fp);
	}
	
	protected function GetArbitraryTimestamp()
	{
		list($fractional, $seconds) = explode(" ", microtime());

		return intval((intval($seconds) - $this->starting_timestamp) . substr($fractional, 2, $this->precision));
	}
	
	public function BeginSection($label, $detail = "")
	{
		if (!$this->enabled) return null;
		
		$label = (string)$label;

		$this->current_sections[] = $label;

		$new_record = new ProfileRecord($label, $this->GetArbitraryTimestamp(), $detail);
		$this->current_record->AddChild($new_record);
		$this->current_record = $new_record;
	}
	
	public function EndSection($label)
	{
		if (!$this->enabled) return null;

		$label = (string)$label;

		$last_label = array_pop($this->current_sections);
		if ($label != $last_label && $label != $this->top_name)
		{
			trigger_error("Sanity check!  EndSection('$label') was called, when the last label was really $last_label");
			$this->enabled = false;
		}

		$this->current_record->End($this->GetArbitraryTimestamp());
		$this->current_record = $this->current_record->GetParent();
	}
}

?>