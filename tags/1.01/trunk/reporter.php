<?php

require('./config.php');

if (isset($_POST['data']))
{
	$lol = new PHPProfilerReporter(PHProfilerConfig::$db_host, PHProfilerConfig::$db_name, PHProfilerConfig::$db_password, PHProfilerConfig::$db_user);

	$lol->ProcessJSON($_POST['data']);
}

class SectionHierarchy
{
	protected $mysql;
	public $page_id;
	public $id;
	public $parent_id;
	public $name;
	public $parent;
	public $children;
	
	function __construct($mysql, $page_id, $parent_id = 0)
	{
		$this->mysql = $mysql;
		$this->page_id = $page_id;
		$this->parent_id = $parent_id;
		$this->children = array();
	}
	
	function SetParent($parent)
	{
		$this->parent_id = $parent->id;
		$this->parent = $parent;
	}
	
	function AddChild($child)
	{
		$child->SetParent($this);
	
		$this->children[$child->id] = $child;
		$this->children[$child->name] = $child;
	}
	
	private function InsertChild($name)
	{
		if ($this->mysql->real_query("INSERT INTO `section` (`page_id`, `name`, `parent_section_id`) VALUES "
			. "($this->page_id, '" . $this->mysql->real_escape_string($name) . "', $this->id)"))
		{
			$child = new SectionHierarchy($this->mysql, $this->page_id, $this->id);
			$child->id = intval($this->mysql->insert_id);
			$child->name = $name;
			$this->AddChild($child);
			return $child;
		}
		else
		{
			return false;
		}
	}
	
	function GetChild($name)
	{
		if (isset($this->children[$name]))
		{
			return $this->children[$name];
		}
		else
		{
			return $this->InsertChild($name);
		}
	}
}

class PHPProfilerReporter
{
	protected $profile;
	protected $mysql;
	protected $page_id;
	protected $page_load_id;
	protected $sections;

	private function MatchSectionsWithProfilerObjects($section, $profile)
	{
		// profile: children, name

		$profile->section_id = $section->id;

		foreach ($profile->children as $child)
		{
			//print "Calling GetChild for child " . $child->name . "\n";
			$this->MatchSectionsWithProfilerObjects($section->GetChild($child->name), $child);
		}
	}

	// This function assumes that there will be a SINGLE section that has no parent (parent_section_id will be 0)
	private function LoadAllSections($mysql, $page_id)
	{
		if ($this->mysql->real_query('SELECT `id`, `name`, `parent_section_id` '
			. 'FROM `section` '
			. "WHERE `page_id` = $page_id "
			. 'ORDER BY `parent_section_id`, `page_id`'))
		{
			$res = $this->mysql->store_result();
			$sections = array();

			while ($row = $res->fetch_array())
			{
				$section_id = intval($row[0]);
				$name = $row[1];
				$parent_section_id = intval($row[2]);

				$sections[$section_id] = new SectionHierarchy($mysql, $page_id, $parent_section_id);
				$sections[$section_id]->id = $section_id;
				$sections[$section_id]->name = $name;

				if (!isset($sections['top']) && $parent_section_id == 0)
				{
					$sections['top'] = $sections[$section_id];
				}
			}

			foreach ($sections as $current)
			{
				if ($current->parent_id != 0)
				{
					$sections[$current->parent_id]->AddChild($current);
				}
			}

			return $sections;
		}

		return false;
	}

	function __construct($host, $username, $password, $database)
	{
		$this->mysql = new mysqli($host, $username, $password, $database);
		
		if (mysqli_connect_error()) 
		{
			die('Connect Error (' . mysqli_connect_errno() . ') ' . mysqli_connect_error());
		}
	}
	
	private function SavePage()
	{
		$inserted_new = false;
		
		if (!isset($this->profile->domain) || !isset($this->profile->top_name))
		{
			die("Missing domain and name!");
		}
		
		$res = $this->mysql->query("SELECT `id` FROM `page` WHERE `domain` = '" 
			. $this->mysql->real_escape_string($this->profile->domain) 
			. "' AND `file` = '" . $this->mysql->real_escape_string($this->profile->top_name) . "'");
			
		if ($res && $row = $res->fetch_array())
		{
			$this->page_id = intval($row[0]);
		}
		else
		{
			$this->mysql->query("INSERT IGNORE INTO `page` (`domain`, `file`) VALUES ('" . $this->mysql->real_escape_string($this->profile->domain) 
				. "', '" . $this->mysql->real_escape_string($this->profile->top_name) . "')");
			$this->page_id = $this->mysql->insert_id;
			$this->mysql->query("INSERT IGNORE INTO `section` (`page_id`, `name`) VALUES ($this->page_id, '" 
				. $this->mysql->real_escape_string($this->profile->master_record->name) . "')");
		}
	}
	
	private function SavePageLoad()
	{
		if (is_numeric($this->profile->starting_timestamp) && is_numeric($this->profile->precision)
			&& is_numeric($this->profile->master_record->started) && is_numeric($this->profile->master_record->ended)
			&& $this->mysql->real_query("INSERT INTO `page_load` (`page_id`, `precision`, `run_time`, `start`) VALUES "
			. "(" . $this->GetPageID() . ", " . $this->profile->precision . ", " . ($this->profile->master_record->ended - $this->profile->master_record->started) 
			. ", FROM_UNIXTIME(" . $this->profile->starting_timestamp . "))"))
		{
			$this->page_load_id = $this->mysql->insert_id;
		}
		else
		{
			print "Didn't run the page load query!  Something was wrong!\n";
		}
	}
	
	private function GetSectionLoadQuery($profile)
	{
		$query = ",(" . $this->GetPageLoadID() . ", $profile->section_id, '" . $this->mysql->real_escape_string($profile->detail) . "', "
			. ($profile->ended - $profile->started) . ")";
			
		foreach ($profile->children as $child)
		{
			$query .= $this->GetSectionLoadQuery($child);
		}
		
		return $query; 
	}
	
	private function SaveSectionLoads()
	{
		return $this->mysql->real_query("INSERT INTO `section_load` (`page_load_id`, `section_id`, `detail`, `run_time`) VALUES "
			. substr($this->GetSectionLoadQuery($this->profile->master_record), 1));
	}
	
	private function GetPageID()
	{
		if (!isset($this->page_id))
		{
			$this->SavePage();
		}
		
		return $this->page_id;
	}
	
	private function GetPageLoadID()
	{
		if (!isset($this->page_load_id))
		{
			$this->SavePageLoad();
		}
		
		return $this->page_load_id;
	}
	
	private function MatchUpSections()
	{
		if (!isset($this->sections))
		{
			$this->sections = $this->LoadAllSections($this->mysql, $this->GetPageID());
		}
		
		$this->MatchSectionsWithProfilerObjects($this->sections['top'], $this->profile->master_record);
	}
	
	public function ProcessJSON($json)
	{
		$this->profile = json_decode($json);
		
		if (is_null($this->profile))
		{
			print "Error parsing JSON!";
			return null;
		}
		
		$this->SavePage();
		$this->MatchUpSections();
		$this->SavePageLoad();
		$this->SaveSectionLoads();
	}
}


?>