<?php
/**
 * @package admin
 * @copyright Copyright 2005-2009, Andrew Berezin eCommerce-Service.com
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: find_extra_spaces.php v 1.2.4 2023 March 14 torvista
 */

@ini_set('display_errors', '1');
error_reporting(E_ALL);

$is_safe_mode = ini_get('safe_mode') == '1' ? 1 : 0;
if (!$is_safe_mode && function_exists('set_time_limit')) set_time_limit(0);

//chdir(getcwd() . '/../');

$usingFTP = true;

$actionEdit = false;
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_POST['file'])) {
  foreach ($_POST['file'] as $k => $v) {
    $_POST['file'][$k] = str_replace('\\\\', '\\', $v);
  }
  $actionEdit = true;
  if (isset($_POST['change']) && $_POST['change'] == 'yes') {
    $change = true;
  } else {
    $change = false;
  }
}

$time_start = microtime_float();
$basedir = getcwd();
$basedir_strlen = strlen($basedir);
$files = rdir($basedir, '@\.php$@', 1);
$time['rdir'] = (microtime_float()-$time_start);
//echo timefmt($time['rdir']) . "<br />\n";

$bad_files = 0;
$canWrite = false;
$time_start = microtime_float();

//echo 'Directory: ' . $basedir . "<br />\n";
echo '<form action="' . basename($_SERVER['SCRIPT_NAME']) . '?action=edit" method="post">' . "\n";
echo '<table border="1" cellpadding="2" cellspacing="0">' . "\n";
echo '<tr>';
echo '  <th>Edit</th>';
echo '  <th>File</th>';
echo '  <th><a href="https://en.wikipedia.org/wiki/Byte-order_mark" target="_blank">BOM</a></th>';
echo '  <th>Extra space<br />on top</th>';
echo '  <th>Extra space<br />on bottom</th>';
echo '  <th>Message</th>';
echo '</tr>' . "\n";
foreach ($files as $file) {
  $flag_BOM = '&nbsp;';
  $flag_END = '&nbsp;';
  $flag_START = '&nbsp;';
  $message = '';

  $bufOrig = file_get_contents($file);
  $bufOut = $bufOrig;
  if (substr($bufOut, 0, 3) == "\xEF\xBB\xBF") {
    $bufOut = substr($bufOut, 3);
    $flag_BOM = 'UTF-8';
  } elseif (substr($bufOut, 0, 2) == "\xFE\xFF") {
    $bufOut = substr($bufOut, 2);
    $flag_BOM = 'UTF-16 (BE)';
  } elseif (substr($bufOut, 0, 2) == "\xFF\xFE") {
    $bufOut = substr($bufOut, 2);
    $flag_BOM = 'UTF-16 (LE)';
  }
  $bufOut = trim($bufOut);
  if ((substr($bufOut, -2) == '?'.'>' ) && $bufOrig !== $bufOut && $bufOrig !== $bufOut . "\n" && $bufOrig !== $bufOut . "\r\n" && $bufOrig !== $bufOut . "\n\r") {
    $flag_END = 'Yes';
  }
  if (substr($bufOut, 0, 2) == '<'.'?' && substr($bufOrig, 0, 2) != '<'.'?') {
    $flag_START = 'Yes';
  }
  if ($flag_END != '&nbsp;' || $flag_START != '&nbsp;' || $flag_BOM != '&nbsp;') {
    $boxExtra = '&nbsp;';
    $file = substr($file, $basedir_strlen);
    if ($actionEdit && in_array($file, $_POST['file'])) {
      if ($change && substr($bufOut, -2) == '?'.'>') {
        $bufOut = substr($bufOut, 0, -2);
        $bufOut = trim($bufOut);
        $bufOut .= "\n\n" . '// EOF';
      }
      if (is_writable($basedir . $file)) {
        if (!file_put_contents($basedir . $file, $bufOut)) {
          $message = '<span style="color: #FF0000">Error writing file!</span>';
        } else {
        	$message = '<span style="color: #00FF00">Fixed!!!</span>';
        }
      } elseif ($usingFTP) {
      	if (!empty($_POST['ftp']['Server']) && !empty($_POST['ftp']['User']) && !empty($_POST['ftp']['Password'])) {
      	}
      }
    } else {
      if (is_writable($basedir . $file) || $usingFTP) {
        $canWrite = true;
        $boxExtra = '<input name="file[]" value="' . $file . '" type="checkbox" CHECKED>';
      }
    }
    echo '<tr>' .
         '  <td align="center">' . $boxExtra . '</td>' .
         '  <td>' . $file . '</td>' .
         '  <td align="center">' . $flag_BOM . '</td>' .
         '  <td align="center">' . $flag_START . '</td>' .
         '  <td align="center">' . $flag_END . '</td>' .
         '  <td align="center">' . $message . '</td>' .
         '</tr>' . "\n";
    $bad_files++;
  }

}
echo '</table>' . "\n";
echo 'Total files - ' . sizeof($files) . "<br />\n";
echo 'Bad files - ' . $bad_files . "<br />\n";
$time['read'] = (microtime_float()-$time_start);
if ($canWrite) {
  echo 'Replace ending "?'.'>" by "// EOF" ?' . '<input name="change" value="yes" type="checkbox" checked>' . "<br />\n";
  if (false) {
?>
  <fieldset>
    <legend>FTP Access:</legend>
    <label for="ftp-Server" style="width: 20em;">Server:</label>
    <input name="ftp[Server]" id="ftp-Server" size="40" type="text">
    <br />
    <label for="ftp-User" style="width: 20em;">User:</label>
    <input name="ftp[User]" id="ftp-User" size="16" type="text">
    <br />
    <label for="ftp-Password" style="width: 20em;">Password:</label>
    <input name="ftp[Password]" id="ftp-Password" size="16" type="text">
    <br />
    <label for="ftp-Directory" style="width: 20em;">Directory:</label>
    <input name="ftp[Directory]" id="ftp-Directory" size="80" type="text">
  </fieldset>
<?php
  }
  echo '<input class="button" value="Replace selected files" type="submit">';
}
echo '</form>';
//echo timefmt($time['read']) . "<br />\n";

/**
 * @param $path
 * @param bool $patterns
 * @param int $flag
 * @return array
 */
function rdir($path, $patterns=false, int $flag=0): array
{
  $files = [];
  if ($d = dir($path)) {
    while (false !== ($file = $d->read())) {
      if ($file == '.' or $file == '..') continue;
      $file = $d->path . '/' . $file;
      $is_dir = is_dir($file);
      if ($patterns !== false && !$is_dir) {
        if (!preg_match($patterns, $file)) {
          continue;
        }
      }
      if ($flag == 0 ||
          ($flag == 1 && !$is_dir) ||
          ($flag == 2 && $is_dir)) {
        $files[] = $file;
      }
      if ($is_dir) {
        $files = array_merge($files, rdir($file, $patterns, $flag));
      }
    }
    $d->close();
  }
  return $files;
}

/**
 * @param $s
 * @return string
 */
function timefmt($s): string
{
  $m = floor($s/60);
  $s = $s - $m*60;
  $h = floor($m/60);
  $m = $m - $h*60;
  if ($h > 0) {
    $tfmt = $h . ":" . $m . ":" . number_format($s, 4);
  } elseif ($m > 0) {
    $tfmt = $m . ":" . number_format($s, 4);
  } else {
    $tfmt = number_format($s, 4);
  }
  return $tfmt;
}

/**
 * @return float
 */
function microtime_float(): float
{
   list($usec, $sec) = explode(" ", microtime());
   return ((float)$usec + (float)$sec);
}
