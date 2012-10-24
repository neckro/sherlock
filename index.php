<?php
//ini_set('display_errors', 1);
session_start();

/*
	A simple frontend for sherlock.c, to check similarities between
	text documents.  Do what thou will.
	neckro@gmail.com https://github.com/neckro/sherlock/
*/

class Sherlock {
	protected $session_prefix = "sherlock_";
	protected $sherlock_bin = "bin/sherlock";
	protected $threshold = 0;
	protected $zerobits;
	protected $chainlength;

	public function __construct() {
		$this->zerobits = $this->getParameter('zerobits', 0, 31, 4);
		$this->chainlength = $this->getParameter('chainlength', 1, 200, 3);
	}

	public function printResults() {
		if (empty($_FILES))
			return '';
		$rows = '';
		try {
			$results = $this->runCompare();
			if (empty($results)) {
				$rows = "<tr><td colspan='3' class='noresult'>No results!</td></tr>\n";
			} else {
				$rows = '';
				foreach($results as $r)
					$rows .= "<tr><td>{$r[1]}</td><td>{$r[2]}</td><td>{$r[0]}</td></tr>\n";
			}
		} catch (Exception $e) {
			$rows = "<tr><td colspan='3' class='noresult'>Error: " . $e->getMessage() . "</td></tr>\n";
		}
		$out  = "<table class='results'><tbody>\n";
		$out .= "<thead><tr><th>Suspect</th><th>Corpus</th><th>Match %</th></tr></thead>\n<tbody>\n";
		$out .= "{$rows}</tbody></table>\n";
		return $out;
	}

	protected function runCompare() {
		// compare each in set A against all in set B and return results
		$files_a = $this->getFileList($_FILES['files_a']);
		$files_b = $this->getFileList($_FILES['files_b']);
		$b_list = implode(' ', array_keys($files_b));
		$results = array();
		if (empty($b_list) || empty($files_a))
			return $results;

		$sherlock_out = '';
		foreach($files_a as $fa=>$na)
			$sherlock_out .= $this->runSherlock("$fa $b_list");
		foreach(explode("\n", $sherlock_out) as $line)
			if (preg_match('/(.*) <and> (.*) <:> ([0-9]+)%/', $line, $matches))
				if (array_key_exists($matches[1], $files_a) && array_key_exists($matches[2], $files_b))
					$results[] = array($matches[3], $files_a[$matches[1]], $files_b[$matches[2]]);
		return $results;
	}

	protected function runSherlock($filelist) {
		$cmd = "{$this->sherlock_bin} -t {$this->threshold} -z {$this->zerobits} -n {$this->chainlength} $filelist";
		echo "<!-- $cmd -->\n";
		$retval = shell_exec($cmd);
		if ($retval === null) {
			throw new Exception("shell_exec error!");
			return '';
		}
		return $retval;
	}

	protected function getFileList($in) {
		$out = array();
		$in = $this->normalizeFileList($in);
		foreach($in as $f)
			$out[$f['tmp_name']] = $f['name'];
		return $out;
	}

	protected function normalizeFileList($in) {
		// php upload handling is stupid  -ed.
		$out = array();
		foreach($in as $field=>$dataset)
			foreach($dataset as $index=>$data)
				$out[$index][$field] = $data;
		return $out;
	}

	protected function getParameter($param, $min, $max, $default) {
		$s = @$_POST[$param];
		if (empty($s))
			$s = @$_SESSION[$this->prefix.$param];
		if (empty($s))
			$s = $default;
		$s = (int)$s;
		if ($min != null && $s < $min)
			$s = $default;
		if ($max != null && $s > $max)
			$s = $default;
		return $s;
	}
}

$sherlock = new Sherlock();

?><!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN">
<html>
<head>
<style type="text/css">
table, form {
	display: block;
	margin: 0em auto;
}
form table td {
	text-align: left;
	vertical-align: top;
}
form table td p {
	text-align: center;
}
form table tr td:first-child {
	text-align: right;
}
table.results {
	margin: 1em auto;
	border-collapse: collapse;
}
table.results tr, table.results th {
	border: 1px solid black;
}
table.results td, table.results th {
	text-align: right;
	padding: 5px;
}
table.results td {
	font-family: monospace;
	border: 1px dotted gray;
}
table.results th {
	text-align: right;
	font-weight: bold;
}
table.results td.noresult {
	font-weight: bold;
	text-align: center;
	font-size: 1.5em;
}
h1 {
	text-align: center;
	font-family: fantasy;
	margin: 0em auto;
}
</style>

<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.8.0/jquery.min.js"></script>
<title>Sherlock</title>
</head>
<body>

<h1>Sherlock Naughty Finder</h1>

<form id="sherlock" action="" method="POST" enctype="multipart/form-data">
<table><tbody>
	<tr><td colspan="2"><hr /><p> This page uses <a href="http://sydney.edu.au/engineering/it/~scilect/sherlock/">Sherlock</a> to find naughties. </p><hr /></td></tr>
	<tr><td>Granularity: <br/> (0 to 31, default: 4)</td>
		<td><input type="text" name="zerobits" value="<?= $zerobits ?>" /></td></tr>
	<tr><td>Chain length: <br /> (1 or more, default: 3 words)</td>
		<td><input type="text" name="chainlength" value="<?= $chainlength ?>" /> words</td></tr>
	<tr><td>Files to check (suspect):</td>
		<td><input type="file" name="files_a[]" multiple="multiple" /></td></tr>
	<tr><td>Files to check <i>against</i> (corpus):</td>
		<td><input type="file" name="files_b[]" multiple="multiple" /></td></tr>
	<tr><td></td><td><input type="submit" /> <input type="button" id="resetButton" value="Reset" /></td></tr>
</tbody></table>
</form>

<?php echo $sherlock->printResults(); ?>

<script type="text/javascript">
	$(document).ready(function() {
		$('#resetButton').click(function() {
			$('input[type="text"]').val('');
			$('#sherlock').submit();
		});
	});
</script>

</body>
</html>
