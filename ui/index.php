<?php

require_once('../phprofiler.php');
require_once('./model.php');
require_once('./table_shenanigans.php');

$GLOBALS['phprofiler'] = new Profiler('http://isoftdata.com/phprofiler/reporter.php', 5);

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
	date_default_timezone_set('UTC');
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

$lolmvc = new PHProfilerUIModel();

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
	$GLOBALS['phprofiler']->BeginSection('Iterating over sections', $section['name']);
	$new_link_sections[] = $section;
	
	if (count($new_link_sections) > 1)
	{
		print ' => ';
	}
	
	print GenerateLink($new_link_sections, $date, $days);
	$GLOBALS['phprofiler']->EndSection('Iterating over sections');
}

?>

	</header>
	<div id="main" role="main">

<?php

$section_id = $sections[count($sections) - 1]['id'];

if ($section_id == 0)
{
	print $lolmvc->GenerateTopLevelSummaryTable($sections, $date, $days);
}
else
{
	print $lolmvc->GenerateSummaryTable($sections, $date, $days);
}

?>
	</div> <!-- end of #main -->
</div> <!--! end of #container -->

</body>
</html>
