<?php

// row['content'] = content
// row['attributes'] = attributes
// attributes[attribute name] = value

function CreateRowObject($content, $attributes = array())
{
	return array('content' => $content, 'attributes' => $attributes);
}

function ReturnAttributeHTML($ary)
{
	$output = "";
	foreach ($ary as $attribute => $value)
	{
		$output .= " $attribute='$value'";
	}
	return $output;
}

function ReturnRowHTML($ary, $header = false)
{
	$output = "<tr>";
	$tag = $header ? "th" : "td";
	
	foreach ($ary as $row)
	{
		$output .= "<$tag";
		if (isset($row['attributes']))
		{
			$output .= ReturnAttributeHTML($row['attributes']);
		}
		$output .= ">" . $row['content'] . "</$tag>";
	}
	
	$output .= "</tr>";
	
	return $output;
}

function ReturnTableHTML($rows, $header = array(), $table_attributes = array(), $caption = "")
{
	$output = '<table id="main-table" class="tablesorter">';
	
	if (strlen($caption))
	{
		$output .= "<caption>" . html_escape($caption) . "</caption>";
	}
	
	$output .= "<thead>" . ReturnRowHTML($header, true) . "</thead><tbody>";
	
	foreach($rows as $row)
	{
		$output .= ReturnRowHTML($row);
	}

	$output .= "</tbody></table>";
	
	return $output;
}

?>