<?php

require('../config.php');

$mysql = new mysqli(PHProfilerConfig::$db_host, PHProfilerConfig::$db_name, PHProfilerConfig::$db_password, PHProfilerConfig::$db_user);

function html_escape($str)
{
	return htmlentities($str, ENT_QUOTES, "UTF-8");
}

function MakeSureDateIsOK($string)
{
	return preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $string) != 0;
}

if (!isset($_GET['date']) || !MakeSureDateIsOK($_GET['date']))
{
	$date = date('Y-m-d H:i:s');
}

$days = 0;
if (isset($_GET['days']))
{
	$days = intval($_GET['days']);
}

if ($days == 0)
{
	$days = 1;
}


if (isset($_GET['sections']) && is_array($_GET['sections']))
{
	$sections = $_GET['sections'];
}
else
{
	$sections = array(array('id' => 0, 'name' => 'All pages'));
}

?>

<!doctype html>
<html class="no-js" lang="en">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">

	<title>PHProfiler - <?php
		print $sections[count($sections) - 1]['name'];
	?></title>
	<meta name="description" content="">

	<meta name="viewport" content="width=device-width,initial-scale=1">

	<link rel="stylesheet" href="css/style.css">
	<link rel="stylesheet" href="css/tablesorter/style.css">

	<!-- <script src="js/libs/modernizr-2.0.6.min.js"></script> -->
	<script src="js/libs/jquery-1.6.2.min.js"></script>
	<script src="js/libs/jquery.tablesorter.min.js"></script>
	<script>

$(document).ready(function() 
{ 
	$("#main-table").tablesorter( {sortList: [[0,0], [1,0]]} ); 
});

	</script>
</head>
<body>

<div id="container">
	<header>

<?php

function GenerateLink($sections, $date, $days)
{
	$last_section = $sections[count($sections) - 1];
	return '<a href="' . GenerateURLForSection($sections, $date, $days) . '">' . html_escape($last_section['name']) . '</a>';
}

function GenerateURLForSection($sections, $date, $days)
{
	return 'index.php?' . http_build_query(array('date' => $date, 'days' => $days, 'sections' => $sections));
}

$new_link_sections = array();

foreach($sections as $section)
{
	$new_link_sections[] = $section;
	
	if (count($new_link_sections) > 1)
	{
		print ' => ';
	}
	
	print GenerateLink($new_link_sections, $date, $days);
}

?>

	</header>
	<div id="main" role="main">
	
	<table id="main-table" class="tablesorter">
	<caption>

<?php

$last_section = $sections[count($sections) - 1];
$section_id = $last_section['id'];

if ($section_id == 0)
{
	print "All pages!";
}
else
{
	$query = "SELECT page.domain, section.`name`, ROUND(AVG(section_load.run_time) / POW(10, page_load.`precision`), page_load.`precision`) AS average_run_time "
		. "FROM section "
		. "JOIN page ON page.id = section.page_id "
		. "JOIN page_load ON page_load.page_id = section.page_id "
		. "JOIN section_load ON section_load.page_load_id = page_load.id AND section_load.section_id = section.id "
		. "WHERE section.id = $section_id AND page_load.`start` BETWEEN DATE_SUB('$date', INTERVAL $days DAY) AND '$date'";

	if (($res = $mysql->query($query)) && ($row = $res->fetch_assoc()))
	{
		print html_escape($row['domain']) . ' ' . html_escape($row['name']) . ' - average ' . html_escape($row['average_run_time']) . ' seconds';
	}
}

?>

	</caption>
	<thead>
		<tr>
			<th>Domain</th>
			<th>Name</th>
			<th>Average time per section</th>
			<th>Average number of times run</th>
			<th>Average total time</th>
			<th>Min time</th>
			<th>Max time</th>
		</tr>
	</thead>
	<tbody>
	
<?php

if ($section_id == 0)
{
	$query = "SELECT section.id AS section_id, page.domain, page.`file`, "
		. "ROUND(AVG(page_load.run_time) / POW(10, page_load.`precision`), page_load.`precision`) AS average_time_per_load, "
		. "MIN(page_load.run_time) / POW(10, page_load.`precision`) AS min_run_time, "
		. "MAX(page_load.run_time) / POW(10, page_load.`precision`) AS max_run_time, `page_load`.`precision` "
		. "FROM page "
		. "JOIN page_load ON page_load.page_id = page.id AND page_load.`start` BETWEEN DATE_SUB('$date', INTERVAL $days DAY) AND '$date' "
		. "JOIN section ON section.page_id = page.id AND section.parent_section_id = 0 "
		. "GROUP BY page.id "
		. "ORDER BY domain, `file`";
	
	if ($res = $mysql->query($query))
	{
		$last_section_index = count($sections);

		while ($row = $res->fetch_assoc())
		{
			$sections[$last_section_index] = array('id' => $row['section_id'], 'name' => $row['file']);

			print "<tr><td>{$row['domain']}</td><td>" . GenerateLink($sections, $date, $days) . "</a></td>"
				. "<td></td><td></td><td class='numeric-column'>" . number_format($row['average_time_per_load'], $row['precision']) 
				. "</td>"
				. "<td class='numeric-column'>" . number_format($row['min_run_time'], $row['precision']) 
				. "</td><td class='numeric-column'>" . number_format($row['max_run_time'], $row['precision']) 
				. "</td></tr>";
		}
	}
}
else
{
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

	print "\n<!-- " . $query . " -->\n";

	if ($res = $mysql->query($query))
	{
		$last_section_index = count($sections);

		while ($row = $res->fetch_assoc())
		{
			$sections[$last_section_index] = array('id' => $row['section_id'], 'name' => $row['section_name']);

			print "<tr><td>{$row['domain']}</td><td>" . GenerateLink($sections, $date, $days) . "</td>"
				. "<td class='numeric-column'>" . number_format($row['average_time_per_section'], $row['precision']) 
				. "</td><td class='numeric-column'>{$row['average_times_run']}"
				. "</td><td class='numeric-column'>" . number_format($row['average_time_per_page_load'], $row['precision'])
				. "</td><td class='numeric-column'>" . number_format($row['min_run_time'], $row['precision']) 
				. "</td><td class='numeric-column'>" . number_format($row['max_run_time'], $row['precision']) . "</td></tr>";
		}
	}
}

?>
	
	</tbody>
	</table>

	</div>
	<footer>

	</footer>
</div> <!--! end of #container -->

</body>
</html>
