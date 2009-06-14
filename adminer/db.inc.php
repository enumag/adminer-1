<?php
$tables_views = array_merge((array) $_POST["tables"], (array) $_POST["views"]);

if ($tables_views && !$error) {
	$result = true;
	$message = "";
	$dbh->query("SET foreign_key_checks = 0");
	if (isset($_POST["truncate"])) {
		if ($_POST["tables"]) {
			foreach ($_POST["tables"] as $table) {
				if (!queries("TRUNCATE " . idf_escape($table))) {
					$result = false;
					break;
				}
			}
			$message = lang('Tables have been truncated.');
		}
	} elseif (isset($_POST["move"])) {
		$rename = array();
		foreach ($tables_views as $table) {
			$rename[] = idf_escape($table) . " TO " . idf_escape($_POST["target"]) . "." . idf_escape($table);
		}
		$result = queries("RENAME TABLE " . implode(", ", $rename));
		$message = lang('Tables have been moved.');
	} elseif ((!isset($_POST["drop"]) || !$_POST["views"] || queries("DROP VIEW " . implode(", ", array_map('idf_escape', $_POST["views"]))))
	&& (!$_POST["tables"] || ($result = queries((isset($_POST["optimize"]) ? "OPTIMIZE" : (isset($_POST["check"]) ? "CHECK" : (isset($_POST["repair"]) ? "REPAIR" : (isset($_POST["drop"]) ? "DROP" : "ANALYZE")))) . " TABLE " . implode(", ", array_map('idf_escape', $_POST["tables"])))))
	) {
		if (isset($_POST["drop"])) {
			$message = lang('Tables have been dropped.');
		} else {
			while ($row = $result->fetch_assoc()) {
				$message .= htmlspecialchars("$row[Table]: $row[Msg_text]") . "<br />";
			}
		}
	}
	query_redirect(queries(), substr($SELF, 0, -1), $message, $result, false, !$result);
}

page_header(lang('Database') . ": " . htmlspecialchars($_GET["db"]), $error, false);
echo '<p><a href="' . htmlspecialchars($SELF) . 'database=">' . lang('Alter database') . "</a></p>\n";
echo '<p><a href="' . htmlspecialchars($SELF) . 'schema=">' . lang('Database schema') . "</a></p>\n";

echo "<h3>" . lang('Tables and views') . "</h3>\n";
$result = $dbh->query("SHOW TABLE STATUS");
if (!$result->num_rows) {
	echo "<p class='message'>" . lang('No tables.') . "</p>\n";
} else {
	echo "<form action='' method='post'>\n";
	echo "<table cellspacing='0' class='nowrap'>\n";
	echo '<thead><tr class="wrap"><td><input id="check-all" type="checkbox" onclick="form_check(this, /^(tables|views)\[/);" /></td><th>' . lang('Table') . '</th><td>' . lang('Engine') . '</td><td>' . lang('Collation') . '</td><td>' . lang('Data Length') . '</td><td>' . lang('Index Length') . '</td><td>' . lang('Data Free') . '</td><td>' . lang('Auto Increment') . '</td><td>' . lang('Rows') . '</td><td>' . lang('Comment') . "</td></tr></thead>\n";
	while ($row = $result->fetch_assoc()) {
		$name = $row["Name"];
		table_comment($row);
		echo '<tr' . odd() . '><td><input type="checkbox" name="' . (isset($row["Rows"]) ? 'tables' : 'views') . '[]" value="' . htmlspecialchars($name) . '"' . (in_array($name, $tables_views, true) ? ' checked="checked"' : '') . ' onclick="form_uncheck(\'check-all\');" /></td>';
		if (isset($row["Rows"])) {
			echo '<th><a href="' . htmlspecialchars($SELF) . 'table=' . urlencode($name) . '">' . htmlspecialchars($name) . "</a></th><td>$row[Engine]</td><td>$row[Collation]</td>";
			foreach (array("Data_length" => "create", "Index_length" => "indexes", "Data_free" => "edit", "Auto_increment" => "create", "Rows" => "select") as $key => $link) {
				$val = number_format($row[$key], 0, '.', lang(','));
				echo '<td align="right">' . (strlen($row[$key]) ? '<a href="' . htmlspecialchars("$SELF$link=") . urlencode($name) . '">' . str_replace(" ", "&nbsp;", ($key == "Rows" && $row["Engine"] == "InnoDB" && $val ? lang('~ %s', $val) : $val)) . '</a>' : '&nbsp;') . '</td>';
			}
			echo "<td>" . (strlen(trim($row["Comment"])) ? htmlspecialchars($row["Comment"]) : "&nbsp;") . "</td>";
		} else {
			echo '<th><a href="' . htmlspecialchars($SELF) . 'view=' . urlencode($name) . '">' . htmlspecialchars($name) . '</a></th><td colspan="8"><a href="' . htmlspecialchars($SELF) . "select=" . urlencode($name) . '">' . lang('View') . '</a></td>';
		}
		echo "</tr>\n";
	}
	echo "</table>\n";
	echo "<p><input type='hidden' name='token' value='$token' /><input type='submit' value='" . lang('Analyze') . "' /> <input type='submit' name='optimize' value='" . lang('Optimize') . "' /> <input type='submit' name='check' value='" . lang('Check') . "' /> <input type='submit' name='repair' value='" . lang('Repair') . "' /> <input type='submit' name='truncate' value='" . lang('Truncate') . "'$confirm /> <input type='submit' name='drop' value='" . lang('Drop') . "'$confirm /></p>\n";
	$dbs = get_databases();
	if (count($dbs) != 1) {
		$db = (isset($_POST["target"]) ? $_POST["target"] : $_GET["db"]);
		echo "<p>" . lang('Move to other database') . ($dbs ? ": <select name='target'>" . optionlist($dbs, $db) . "</select>" : ': <input name="target" value="' . htmlspecialchars($db) . '" />') . " <input type='submit' name='move' value='" . lang('Move') . "' /></p>\n";
	}
	echo "</form>\n";
}
$result->free();

if ($dbh->server_info >= 5) {
	echo '<p><a href="' . htmlspecialchars($SELF) . 'createv=">' . lang('Create view') . "</a></p>\n";
	echo "<h3>" . lang('Routines') . "</h3>\n";
	$result = $dbh->query("SELECT * FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = '" . $dbh->escape_string($_GET["db"]) . "'");
	if ($result->num_rows) {
		echo "<table cellspacing='0'>\n";
		while ($row = $result->fetch_assoc()) {
			echo "<tr>";
			echo "<td>" . htmlspecialchars($row["ROUTINE_TYPE"]) . "</td>";
			echo '<th><a href="' . htmlspecialchars($SELF) . ($row["ROUTINE_TYPE"] == "FUNCTION" ? 'callf=' : 'call=') . urlencode($row["ROUTINE_NAME"]) . '">' . htmlspecialchars($row["ROUTINE_NAME"]) . '</a></th>';
			echo '<td><a href="' . htmlspecialchars($SELF) . ($row["ROUTINE_TYPE"] == "FUNCTION" ? 'function=' : 'procedure=') . urlencode($row["ROUTINE_NAME"]) . '">' . lang('Alter') . "</a></td>";
			echo "</tr>\n";
		}
		echo "</table>\n";
	}
	$result->free();
	echo '<p><a href="' . htmlspecialchars($SELF) . 'procedure=">' . lang('Create procedure') . '</a> <a href="' . htmlspecialchars($SELF) . 'function=">' . lang('Create function') . "</a></p>\n";
}

if ($dbh->server_info >= 5.1 && ($result = $dbh->query("SHOW EVENTS"))) {
	echo "<h3>" . lang('Events') . "</h3>\n";
	if ($result->num_rows) {
		echo "<table cellspacing='0'>\n";
		echo "<thead><tr><th>" . lang('Name') . "</th><td>" . lang('Schedule') . "</td><td>" . lang('Start') . "</td><td>" . lang('End') . "</td></tr></thead>\n";
		while ($row = $result->fetch_assoc()) {
			echo "<tr>";
			echo '<th><a href="' . htmlspecialchars($SELF) . 'event=' . urlencode($row["Name"]) . '">' . htmlspecialchars($row["Name"]) . "</a></th>";
			echo "<td>" . ($row["Execute at"] ? lang('At given time') . "</td><td>" . $row["Execute at"] : lang('Every') . " " . $row["Interval value"] . " " . $row["Interval field"] . "</td><td>$row[Starts]") . "</td>";
			echo "<td>$row[Ends]</td>";
			echo "</tr>\n";
		}
		echo "</table>\n";
	}
	$result->free();
	echo '<p><a href="' . htmlspecialchars($SELF) . 'event=">' . lang('Create event') . "</a></p>\n";
}