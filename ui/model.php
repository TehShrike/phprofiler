<?php

require_once('../phprofiler_config.php');

class PHProfilerUIModel
{
	private $mysql;
	
	function __construct()
	{
		$this->mysql = new mysqli(PHProfilerConfig::$db_host, PHProfilerConfig::$db_name, PHProfilerConfig::$db_password, PHProfilerConfig::$db_user);
	}

	function GetTableCaption($section_id, $date, $days)
	{
		$caption_html = "";
		if ($section_id != 0)
		{
			$GLOBALS['phprofiler']->BeginSection('Getting parent section data', "Section $section_id - $days days");
			$query = "SELECT page.domain, section.`name`, ROUND(AVG(section_load.run_time) / POW(10, page_load.`precision`), page_load.`precision`) AS average_run_time "
				. "FROM section "
				. "JOIN page ON page.id = section.page_id "
				. "JOIN page_load ON page_load.page_id = section.page_id "
				. "JOIN section_load ON section_load.page_load_id = page_load.id AND section_load.section_id = section.id "
				. "WHERE section.id = $section_id AND page_load.`start` BETWEEN DATE_SUB('$date', INTERVAL $days DAY) AND '$date'";

			if (($res = $this->mysql->query($query)) && ($row = $res->fetch_assoc()))
			{
				$caption_html = html_escape($row['domain']) . ' ' . html_escape($row['name']) . ' - average ' . html_escape($row['average_run_time']) . ' seconds';
			}
			$GLOBALS['phprofiler']->EndSection('Getting parent section data');
		}
		else
		{
			$caption_html = "All pages!";
		}
		return $caption_html;
	}

	function GenerateTopLevelSummaryTable($sections, $date, $days)
	{
		$last_section = $sections[count($sections) - 1];
		$section_id = $last_section['id'];
		
		$GLOBALS['phprofiler']->BeginSection('Getting child sections', "Section $section_id - $days days");
		
		$header = array(CreateRowObject('Domain'), CreateRowObject('Name'), CreateRowObject('Average number of times run'), 
			CreateRowObject('Average total times'), CreateRowObject('Min time'), CreateRowObject('Max times'));
			
		$table_attributes = array('id' => 'main-table', 'class' => 'tablesorter');
		$numeric_attribute = array('class' => 'numeric-column');
		
		$table_rows = array();

		$query = "SELECT section.id AS section_id, page.domain, page.`file`, "
			. "ROUND(AVG(page_load.run_time) / POW(10, page_load.`precision`), page_load.`precision`) AS average_time_per_load, "
			. "MIN(page_load.run_time) / POW(10, page_load.`precision`) AS min_run_time, "
			. "MAX(page_load.run_time) / POW(10, page_load.`precision`) AS max_run_time, `page_load`.`precision`, COUNT(*) AS `page_loads` "
			. "FROM page "
			. "JOIN page_load ON page_load.page_id = page.id AND page_load.`start` BETWEEN DATE_SUB('$date', INTERVAL $days DAY) AND '$date' "
			. "JOIN section ON section.page_id = page.id AND section.parent_section_id = 0 "
			. "GROUP BY page.id "
			. "ORDER BY domain, `file`";

		if ($res = $this->mysql->query($query))
		{
			$last_section_index = count($sections);

			while ($row = $res->fetch_assoc())
			{
				$sections[$last_section_index] = array('id' => $row['section_id'], 'name' => $row['file']);
				
				$table_rows[] = array(
					CreateRowObject($row['domain']),
					CreateRowObject(GenerateLink($sections, $date, $days)),
					CreateRowObject($row['page_loads'], $numeric_attribute),
					CreateRowObject(number_format($row['average_time_per_load'], $row['precision']), $numeric_attribute),
					CreateRowObject(number_format($row['min_run_time'], $row['precision']), $numeric_attribute),
					CreateRowObject(number_format($row['max_run_time'], $row['precision']), $numeric_attribute)
					);
			}
		}
		$GLOBALS['phprofiler']->EndSection('Getting child sections');

		return ReturnTableHTML($table_rows, $header, $table_attributes, $this->GetTableCaption($section_id, $date, $days));
	}

	function GenerateSummaryTable($sections, $date, $days)
	{
		$last_section = $sections[count($sections) - 1];
		$section_id = $last_section['id'];

		$GLOBALS['phprofiler']->BeginSection('Getting child sections', "Section $section_id - $days days");		
		
		$header = array(CreateRowObject('Domain'), CreateRowObject('Name'), CreateRowObject('Average time per section'), CreateRowObject('Average number of times run'), 
			CreateRowObject('Average total times'), CreateRowObject('Min time'), CreateRowObject('Max times'));

		$table_attributes = array('id' => 'main-table', 'class' => 'tablesorter');
		$numeric_attribute = array('class' => 'numeric-column');
		
		$table_rows = array();

		$query = "SELECT domain, section_id, section_name, ROUND(AVG(total_run_time), `precision`) AS average_time_per_page_load, "
			. "ROUND(AVG(average_run_time), `precision`) AS average_time_per_section, ROUND(AVG(times_run), 1) AS average_times_run, "
			. "MAX(total_run_time) AS max_run_time, MIN(total_run_time) AS min_run_time, `precision` "
			. "FROM "
			. "("
				. "SELECT page.domain, section.id AS section_id, section_load.id AS section_load_id, section.`name` AS section_name, "
				. "section_load.detail AS section_load_detail, SUM(section_load.run_time) / POW(10, page_load.`precision`) AS total_run_time, "
				. "ROUND(AVG(section_load.run_time) / POW(10, page_load.`precision`), page_load.`precision`) AS average_run_time,  "
				. "COUNT(section_load.id) AS times_run, page_load.`precision` AS `precision` "
				. "FROM section "
				. "JOIN page ON page.id = section.page_id "
				. "JOIN page_load ON page_load.page_id = section.page_id "
				. "LEFT JOIN section_load ON section_load.page_load_id = page_load.id AND section_load.section_id = section.id "
				. "WHERE section.parent_section_id = $section_id "
					. "AND page_load.`start` BETWEEN DATE_SUB('$date', INTERVAL $days DAY) AND '$date' "
				. "GROUP BY section.page_id, page_load.id, section.id "
			. ") AS butts "
			. "GROUP BY butts.section_id "
			. "ORDER BY domain, section_name";

		if ($res = $this->mysql->query($query))
		{
			$last_section_index = count($sections);

			while ($row = $res->fetch_assoc())
			{
				$sections[$last_section_index] = array('id' => $row['section_id'], 'name' => $row['section_name']);

				$table_rows[] = array(
					CreateRowObject($row['domain']),
					CreateRowObject(GenerateLink($sections, $date, $days)),
					CreateRowObject(number_format($row['average_time_per_section'], $row['precision']), $numeric_attribute),
					CreateRowObject($row['average_times_run'], $numeric_attribute),
					CreateRowObject(number_format($row['average_time_per_page_load'], $row['precision']), $numeric_attribute),
					CreateRowObject(number_format($row['min_run_time'], $row['precision']), $numeric_attribute),
					CreateRowObject(number_format($row['max_run_time'], $row['precision']), $numeric_attribute)
					);
			}
		}

		$GLOBALS['phprofiler']->EndSection('Getting child sections');
		
		return ReturnTableHTML($table_rows, $header, $table_attributes, $this->GetTableCaption($section_id, $date, $days));
	}
}

?>