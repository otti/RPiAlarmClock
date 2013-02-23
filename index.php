<!DOCTYPE html>
	<html>
		<head>

		<meta charset="utf-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1" />
		<meta name="apple-mobile-web-app-capable" content="yes" />
		<meta name="apple-mobile-web-app-status-bar-style" content="black" />
		<title>Weck-O-Mat</title>


		<link rel="stylesheet" href="http://code.jquery.com/mobile/latest/jquery.mobile.css" />
		<link type="text/css" href="http://dev.jtsage.com/cdn/datebox/latest/jqm-datebox.min.css" rel="stylesheet" /> 
		<link type="text/css" href="http://dev.jtsage.com/jQM-DateBox2/css/demos.css" rel="stylesheet" /> 

		<script type="text/javascript" src="http://code.jquery.com/jquery-1.9.1.min.js"></script> 
		<script type="text/javascript" src="http://code.jquery.com/mobile/latest/jquery.mobile.js"></script>
		<script type="text/javascript" src="http://dev.jtsage.com/cdn/datebox/latest/jqm-datebox.core.min.js"></script>
		<script type="text/javascript" src="http://dev.jtsage.com/cdn/datebox/latest/jqm-datebox.mode.flipbox.min.js"></script>
		<script type="text/javascript" src="http://dev.jtsage.com/cdn/datebox/i18n/jquery.mobile.datebox.i18n.de_DE.utf8.js"></script>

		
        <script>
            try {

    $(function() {

    });

  } catch (error) {
    console.error("Your javascript has an error: " + error);
  }
        </script>
    </head>
    <body>
	
	
	
	<?php
		error_reporting(E_ALL);
		ini_set('display_errors', 1);
		
		
		function UpdateDaysToWork($xml, $ActionId, $PostVar, $XMLDayName)
		{
			if( isset($_REQUEST[$PostVar]) )
			{
				if( $_REQUEST[$PostVar] == "on" )
				{
					$DaysToWork = $xml->TimedAction[(int)$ActionId]->daystowork->DayOfWeek;
					$IsAlreadySet = 0;
					foreach($DaysToWork as $Day)
					{
						if( $Day == $XMLDayName )
							$IsAlreadySet = 1;
					}
					
					if( $IsAlreadySet == 0 )
						$xml->TimedAction[(int)$ActionId]->daystowork->addChild("DayOfWeek", $XMLDayName);	
				}
			}
			else
			{
				$DaysToWork = $xml->TimedAction[(int)$ActionId]->daystowork->DayOfWeek;
				$Pos = 0;
				foreach($DaysToWork as $Day)
				{
					if( $Day == $XMLDayName )
						break;
					$Pos++;
				}
				unset($xml->TimedAction[(int)$ActionId]->daystowork->DayOfWeek[$Pos]);
			}
		}

		
		$XML_Filename = "actions.xml";
		// Read XML-File
		// -----------------------------------------
		$xml_file = file($XML_Filename);
		$xml_doc = "";
		foreach($xml_file AS $row)
			$xml_doc = $xml_doc . $row;

		$xml = new SimpleXMLElement($xml_doc);
		// -----------------------------------------

		$WriteXML = 0;
		

		
		if( isset($_REQUEST['add']) )
		{
			$child = $xml->addChild("TimedAction");
			if( $_REQUEST['add'] == "light" )
				$child->addAttribute("xsi:type", "TimedAction_PWM", "http://www.w3.org/2001/XMLSchema-instance");
			if( $_REQUEST['add'] == "radio" )
				$child->addAttribute("xsi:type", "TimedAction_Music", "http://www.w3.org/2001/XMLSchema-instance");
			$child->addChild("Name");
			$child->Name = "Neu";
			if( $_REQUEST['add'] == "light" )
			{
				$child->addChild("PWM_Frequency");
				$child->PWM_Frequency = "200";
				$child->addChild("PWM_Inverted");
				$child->PWM_Inverted = "true";
			}
			$child->addChild("active");
			$child->active = "false";
			$child->addChild("force");
			$child->force = "false";
			$StartTime = $child->addChild("StartTime");
			$StartTime->addChild("hour");
			$StartTime->hour = "07";
			$StartTime->addChild("minute");
			$StartTime->minute = "00";
			$child->addChild("RampTime");
			$child->RampTime = "5";
			$child->addChild("LagTime");
			$child->LagTime = "5";
			$child->addChild("Value_max");
			$child->Value_max = "100";
			if( $_REQUEST['add'] == "radio" )
			{
				$child->addChild("Stream");
				$child->Stream = "http://gffstream.ic.llnwd.net/stream/gffstream_stream_wdr_einslive_a";
			}
			$child->addChild("daystowork");

			$WriteXML = 1;
		}
		
		// Wenn Submit --> Datei schreiben
		if( isset($_REQUEST['Page']) && isset($_REQUEST['ActionId']) )
		{
			$ActionId = $_REQUEST['ActionId'];

			if( isset($_REQUEST['Active']) )
			{
				if( $_REQUEST['Active'] == "off" )
					$xml->TimedAction[(int)$ActionId]->active = "false";
				else
					$xml->TimedAction[(int)$ActionId]->active = "true";
			}		
			
			if( isset($_REQUEST['AlarmTime']) )
			{
				$time = explode(":",$_REQUEST['AlarmTime']);
				$xml->TimedAction[(int)$ActionId]->StartTime->hour = $time[0];
				$xml->TimedAction[(int)$ActionId]->StartTime->minute = $time[1];
			}
			
			UpdateDaysToWork($xml, $ActionId, "DaySa", "Saturday");
			UpdateDaysToWork($xml, $ActionId, "DaySu", "Sunday");
			UpdateDaysToWork($xml, $ActionId, "DayMo", "Monday");
			UpdateDaysToWork($xml, $ActionId, "DayTu", "Tuesday");
			UpdateDaysToWork($xml, $ActionId, "DayWe", "Wednesday");
			UpdateDaysToWork($xml, $ActionId, "DayTh", "Thursday");
			UpdateDaysToWork($xml, $ActionId, "DayFr", "Friday");

			if( isset($_REQUEST['RampTime']) )
				$xml->TimedAction[(int)$ActionId]->RampTime = $_REQUEST['RampTime'];

			if( isset($_REQUEST['LagTime']) )
				$xml->TimedAction[(int)$ActionId]->LagTime = $_REQUEST['LagTime'];
				
			if( isset($_REQUEST['Brightness']) )
				$xml->TimedAction[(int)$ActionId]->Value_max = $_REQUEST['Brightness'];
				
			if( isset($_REQUEST['Volume']) )
				$xml->TimedAction[(int)$ActionId]->Value_max =  $_REQUEST['Volume'];
				
			if( isset($_REQUEST['StreamLink']) )
				$xml->TimedAction[(int)$ActionId]->Stream = $_REQUEST['StreamLink'];

			if( isset($_REQUEST['ActionName']) )
				$xml->TimedAction[(int)$ActionId]->Name = $_REQUEST['ActionName'];			

			if( isset($_REQUEST['Delete']) )
				unset($xml->TimedAction[(int)$ActionId]);
			
			$WriteXML = 1;
		}
		
		// reformat and write if modified
		// --------------------------------------------
		if( $WriteXML == 1 )
		{
			$dom = new DOMDocument('1.0');
			$dom->preserveWhiteSpace = false;
			$dom->formatOutput = true;
			$dom->loadXML($xml->asXML());
			$dom->saveXML();
			$dom->save($XML_Filename);
		}
		
		
		
		// Link zur aktuellen Aktion erstellen
		// -------------------------------------------
		if( isset($_REQUEST['Page']) )
		{
			$link = "http://" . $_SERVER['SERVER_NAME'] . $_SERVER['SCRIPT_NAME'] . "?";

			$first_loop = true;
			foreach($_REQUEST as $key=>$value)
			{
				if( $first_loop == false )
					$link = $link . "&";
					
				$first_loop = false;

				$link = $link . $key . "=" . $value;
			} 
		}
		
		function CheckDay($xml_arr, $preset)
		{
			foreach($xml_arr as $Day)
			{
				if( $Day == $preset )
				{
					return "checked=\"checked\"";
				}
			}
			return "";
		}
		
		function CheckActive($arg)
		{
			if( $arg == "true" )
				return "selected=\"selected\"";
			else
				return "";
		}
		
		function CollapsibleIcon($status)
		{
			if( $status == "true" )
				return "data-collapsed-icon=\"check\"";
			else
				return "data-collapsed-icon=\"delete\"";
		}
		

	
	echo "			<div data-role=\"page\" id=\"page_wecker\" data-theme=\"a\">\r\n";
	echo "				<div data-role=\"header\" data-id=\"header\">\r\n";
	echo "					<div data-role=\"navbar\">\r\n";
	echo "						<ul>\r\n";
	echo "							<li><a href=\"#page_wecker\" class=\"ui-btn-active ui-state-persist\">Wecker</a></li>\r\n";
	echo "							<li><a href=\"#page_radio\">Radio</a></li>\r\n";
	echo "							<li><a href=\"#page_licht\">Licht</a></li>\r\n";
	echo "						</ul>\r\n";
	echo "					</div>\r\n";
	echo "				</div>\r\n";

	
	//print_r($_REQUEST);
	//echo $debug;

	
	for($actions=0; $actions<count($xml); $actions++)
	{
		if( $xml->TimedAction[$actions]->attributes("xsi", "TRUE")->type == "TimedAction_PWM" )
			$Action = "Licht";
		if( $xml->TimedAction[$actions]->attributes("xsi", "TRUE")->type == "TimedAction_Music" )
			$Action = "Radio";
			
		if( $xml->TimedAction[$actions]->Name == "STATIC_LIGHT" || $xml->TimedAction[$actions]->Name == "STATIC_RADIO" )
			continue;
			
			
		$Icon = CollapsibleIcon($xml->TimedAction[$actions]->active);
		echo "			<div data-role=\"collapsible\" " . $Icon . " data-content-theme=\"a\">\r\n";
		echo "				<h3>" . $Action .": ". $xml->TimedAction[$actions]->Name." - " . $xml->TimedAction[$actions]->StartTime->hour .":". $xml->TimedAction[$actions]->StartTime->minute . "</h3>\r\n";
		echo "				<form name=\"Wecker\" action=\"index.php\" method=\"post\">\r\n";
		echo "				<input type=\"hidden\" name=\"Page\" value=\"Alarm\">\r\n";
		echo "				<input type=\"hidden\" name=\"ActionId\" value=\"".$actions."\">\r\n";	
		echo "				<div class=\"ui-grid-a\">\r\n";
		echo "					<div class=\"ui-block-a\">\r\n";
		echo "						<br>\r\n";
			$Selected = CheckActive($xml->TimedAction[$actions]->active);
		echo "						<div data-role=\"fieldcontain\">\r\n";
		echo "							<select name=\"Active\" data-role=\"slider\">\r\n";
		echo "								<option " . $Selected . " value=\"off\">Aus</option>\r\n";
		echo "								<option " . $Selected . " value=\"on\">An</option>\r\n";
		echo "							</select> \r\n";
		echo "						</div>\r\n";
		echo "					</div>\r\n";
		echo "					<div class=\"ui-block-b\">\r\n";
		echo "						<div data-role=\"fieldcontain\">\r\n";
		echo "							<div class=\"content-primary\"> <!-- sorgt dafuer, dass die FlipBox angeigt wird, wenn man auf das Inputfeld klickt -->\r\n";
		echo "								<label>Startzeit: <br></label>\r\n";
		echo "								<input name=\"AlarmTime\" value=\"" . $xml->TimedAction[$actions]->StartTime->hour .":". $xml->TimedAction[$actions]->StartTime->minute . "\" type=\"text\" data-role=\"datebox\" data-options='{\"mode\":\"timeflipbox\", \"useNewStyle\":true}' />\r\n";
		echo "							</div>\r\n";
		echo "						</div>\r\n";		
		echo "					</div>\r\n";
		echo "				</div>\r\n";
			$DaysToWork = $xml->TimedAction[$actions]->daystowork->DayOfWeek;
		echo "				Wochentag:\r\n";
		echo "				<div data-role=\"fieldcontain\">\r\n";
		echo "					<p>\r\n";
		echo "					<fieldset data-role=\"controlgroup\" data-type=\"horizontal\">\r\n";
			$checked = CheckDay($DaysToWork,  "Saturday");
		echo "						<input type=\"checkbox\" name=\"DaySa\" id=\"DaySa\" class=\"custom\" " .$checked."  />\r\n";
		echo "						<label for=\"DaySa\">Sa</label>	\r\n";
			$checked = CheckDay($DaysToWork,  "Sunday");
		echo "						<input type=\"checkbox\" name=\"DaySu\" id=\"DaySu\" class=\"custom\" ".$checked."  />\r\n";
		echo "						<label for=\"DaySu\">So</label>\r\n";
		echo "					</fieldset>\r\n";
		echo "					<br>\r\n";
		echo "					<fieldset data-role=\"controlgroup\" data-type=\"horizontal\">\r\n";
			$checked = CheckDay($DaysToWork,  "Monday");
		echo "						<input type=\"checkbox\" name=\"DayMo\" id=\"DayMo\" class=\"custom\" ".$checked."  />\r\n";
		echo "						<label for=\"DayMo\">Mo</label>\r\n";
			$checked = CheckDay($DaysToWork,  "Tuesday");
		echo "						<input type=\"checkbox\" name=\"DayTu\" id=\"DayTu\" class=\"custom\" ".$checked."  />\r\n";
		echo "						<label for=\"DayTu\">Di</label>\r\n";
			$checked = CheckDay($DaysToWork,  "Wednesday");
		echo "						<input type=\"checkbox\" name=\"DayWe\" id=\"DayWe\" class=\"custom\" ".$checked."  />\r\n";
		echo "						<label for=\"DayWe\">Mi</label>\r\n";
			$checked = CheckDay($DaysToWork,  "Thursday");
		echo "						<input type=\"checkbox\" name=\"DayTh\" id=\"DayTh\" class=\"custom\" ".$checked."  />\r\n";
		echo "						<label for=\"DayTh\">Do</label>\r\n";
			$checked = CheckDay($DaysToWork,  "Friday");
		echo "						<input type=\"checkbox\" name=\"DayFr\" id=\"DayFr\" class=\"custom\" ".$checked."  />\r\n";
		echo "						<label for=\"DayFr\">Fr</label>\r\n";
		echo "					</fieldset>\r\n";
		echo "					</p>\r\n";
		echo "				</div>\r\n";
		echo "				<div data-role=\"fieldcontain\">\r\n";
		echo "					<label>Rampenzeit [min]: </label>\r\n";
		echo "					<input name=\"RampTime\" value=\"" . $xml->TimedAction[$actions]->RampTime . "\" min=\"0\" max=\"60\" step=\"5\" data-highlight=\"true\" type=\"range\" />\r\n";
		echo "				</div>\r\n";
		echo "				<div data-role=\"fieldcontain\">\r\n";
		echo "					<label>Nachlaufzeit [min]: </label>\r\n";
		echo "					<input name=\"LagTime\" value=\"" . $xml->TimedAction[$actions]->LagTime . "\" min=\"0\" max=\"60\" step=\"5\" data-highlight=\"true\" type=\"range\" />\r\n";
		echo "				</div>\r\n";
if( $Action == "Licht" )
{		
		echo "				<div data-role=\"fieldcontain\">\r\n";
		echo "					<label>Max. Helligkeit [%]: </label>\r\n";
		echo "					<input name=\"Brightness\" value=\"" . $xml->TimedAction[$actions]->Value_max . "\" min=\"0\" max=\"100\" step=\"5\" data-highlight=\"true\" type=\"range\" />\r\n";
		echo "				</div>\r\n";
}

if( $Action == "Radio" )
{		
		echo "					<div data-role=\"fieldcontain\">\r\n";
		echo "						<label>Max. Lautst&auml;rke [%]: </label>\r\n";
		echo "						<input name=\"Volume\" value=\"" . $xml->TimedAction[$actions]->Value_max . "\" min=\"0\" max=\"100\" step=\"5\" data-highlight=\"true\" type=\"range\" />\r\n";
		echo "					</div>\r\n";
		echo "					<div>\r\n";
		echo "						<label>Stream Link: </label>\r\n";
		echo "						<input type=\"text\" name=\"StreamLink\" value=\"" . $xml->TimedAction[$actions]->Stream . "\"  />\r\n";
		echo "					</div>\r\n";
}
		echo "					<div>\r\n";
		echo "						<label>Name: </label>\r\n";
		echo "						<input type=\"text\" name=\"ActionName\" value=\"" . $xml->TimedAction[$actions]->Name . "\"  />\r\n";
		echo "					</div>\r\n";
		echo "					<div data-role=\"fieldcontain\">\r\n";
		echo "						<input type=\"checkbox\" name=\"Delete\" id=\"Delete\" />\r\n";
		echo "						<label for=\"Delete\">L&ouml;schen</label>\r\n";
		echo "					</div>\r\n";
		echo "				<input value=\"OK\" type=\"submit\"/>\r\n";
		echo "				</form>\r\n";
		echo "			</div>\r\n";
				
	}

		echo "			<div data-role=\"collapsible\" data-collapsed-icon=\"plus\" data-expanded-icon=\"plus\" data-content-theme=\"a\">\r\n";
		echo "				<h3>Neu</h3>\r\n";
		echo "              <form name=\"Wecker\" action=\"index.php\" method=\"post\">\r\n";
		echo "					<input type=\"hidden\" name=\"add\" value=\"light\">\r\n";
		echo "					<input value=\"Neue Licht-Aktion\" type=\"submit\"/>\r\n";
		echo "				</form>\r\n";	
		echo "              <form name=\"Wecker\" action=\"index.php\" method=\"post\">\r\n";
		echo "					<input type=\"hidden\" name=\"add\" value=\"radio\">\r\n";
		echo "					<input value=\"Neue Radio-Aktion\" type=\"submit\"/>\r\n";
		echo "				</form>\r\n";			
		echo "			</div>\r\n";
		
		if( isset($link) )
			echo "<a href=\"" . $link . "\"> Link zu dieser Aktion </a>";
			
		echo "			</div> <!-- Content -->\r\n";
		echo "		</div> <!-- Page -->\r\n";

$Pos = 0;
foreach($xml as $RadioAction)
{
	if( $RadioAction->Name == "STATIC_RADIO" )
		break;
	$Pos++;
}
$Selected = CheckActive($xml->TimedAction[$Pos]->active);
		
		echo "				<!-- Page Radio -->\r\n";
		echo "				 <div data-role=\"page\" id=\"page_radio\" data-theme=\"a\">\r\n";
		echo "					<div data-role=\"header\" data-id=\"header\">\r\n";
		echo "						<div data-role=\"navbar\">\r\n";
		echo "							<ul>\r\n";
		echo "							<li><a href=\"#page_wecker\">Wecker</a></li>\r\n";
		echo "							<li><a href=\"#page_radio\" class=\"ui-btn-active ui-state-persist\">Radio</a></li>\r\n";
		echo "							<li><a href=\"#page_licht\">Licht</a></li>\r\n";
		echo "							</ul>\r\n";
		echo "						</div>\r\n";
		echo "					</div>\r\n";
		echo "					<div data-role=\"content\">\r\n";
		echo "						<form name=\"Radio\" action=\"index.php\" method=\"post\">\r\n";
		echo "						<input type=\"hidden\" name=\"Page\" value=\"Radio\">\r\n";
		echo "						<input type=\"hidden\" name=\"ActionId\" value=\"".$Pos."\">\r\n";	
		echo "						<div data-role=\"fieldcontain\">\r\n";
		echo "							<select name=\"Active\" data-role=\"slider\">\r\n";
		echo "								<option value=\"off\">Aus</option>\r\n";
		echo "								<option value=\"on\" ".$Selected." >An</option>\r\n";
		echo "							</select> \r\n";
		echo "						</div>\r\n";
		echo "						<div data-role=\"fieldcontain\">\r\n";
		echo "								<label>Lautst&auml;rke [%]: </label>\r\n";
		echo "								<input name=\"Volume\" value=\"" . $xml->TimedAction[$Pos]->Value_max . "\" min=\"0\" max=\"100\" step=\"5\" data-highlight=\"true\" type=\"range\" />\r\n";
		echo "						</div>\r\n";
		echo "						<div data-role=\"fieldcontain\">\r\n";
		echo "							<label>Stream Link: </label>\r\n";
		echo "							<input type=\"text\" name=\"StreamLink\" value=\"" . $xml->TimedAction[$Pos]->Stream . "\"  />\r\n";
 		echo "		               </div>\r\n";
		echo "						<input value=\"OK\" type=\"submit\"/>\r\n";
		echo "						</form>\r\n";
		echo "					</div> <!-- Content -->\r\n";
		echo "				 </div> <!-- Page -->\r\n";

$Pos = 0;
foreach($xml as $RadioAction)
{
	if( $RadioAction->Name == "STATIC_LIGHT" )
		break;
	$Pos++;
}
$Selected = CheckActive($xml->TimedAction[$Pos]->active);
		
		echo "				 <!-- Page Light -->\r\n";
		echo "				 <div data-role=\"page\" id=\"page_licht\" data-theme=\"a\">\r\n";
		echo "					<div data-role=\"header\" data-id=\"header\">\r\n";
		echo "						<div data-role=\"navbar\">\r\n";
		echo "							<ul>\r\n";
		echo "							<li><a href=\"#page_wecker\">Wecker</a></li>\r\n";
		echo "							<li><a href=\"#page_radio\">Radio</a></li>\r\n";
		echo "							<li><a href=\"#page_licht\" class=\"ui-btn-active ui-state-persist\">Licht</a></li>\r\n";
		echo "							</ul>\r\n";
		echo "						</div>\r\n";
		echo "					</div>\r\n";
		echo "					<div data-role=\"content\">\r\n";
		echo "						<form name=\"Licht\" action=\"index.php\" method=\"post\">\r\n";
		echo "						<input type=\"hidden\" name=\"Page\" value=\"Light\">\r\n";
		echo "						<input type=\"hidden\" name=\"ActionId\" value=\"".$Pos."\">\r\n";			
		echo "						<div data-role=\"fieldcontain\">\r\n";
		echo "							<select name=\"Active\" data-role=\"slider\">\r\n";
		echo "								<option value=\"off\">Aus</option>\r\n";
		echo "								<option value=\"on\" ".$Selected." >An</option>\r\n";
		echo "							</select> \r\n";
		echo "						</div>\r\n";
		echo "						<div data-role=\"fieldcontain\">\r\n";
		echo "								<label>Helligkeit [%]: </label>\r\n";
		echo "								<input name=\"Brightness\" value=\"" . $xml->TimedAction[$Pos]->Value_max . "\" min=\"0\" max=\"100\" step=\"5\" data-highlight=\"true\" type=\"range\" />\r\n";
		echo "						</div>\r\n";
		echo "						<input value=\"OK\" type=\"submit\"/>\r\n";
		echo "						</form>\r\n";
		echo "					</div> <!-- Content -->\r\n";
		echo "				</div> <!-- Page -->\r\n";

?>
	
</body>
</html>
