<?php
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

include 'mp.php';
$user = MP::getUser();
if(!$user) {
	header('Location: login.php?logout=1');
	die();
}

$lang = MP::getSetting('lang', 'ru');
$theme = MP::getSettingInt('theme');

try {
	include 'locale_'.$lang.'.php';
} catch (Exception $e) {
	$lang = 'ru';
	include 'locale_'.$lang.'.php';
}

$id = null;
if(isset($_POST['c'])) {
	$id = $_POST['c'];
} else if(isset($_GET['c'])) {
	$id = $_GET['c'];
} else {
	die();
}

header("Content-Type: text/html; charset=utf-8");
header("Cache-Control: private, no-cache, no-store");

function exceptions_error_handler($severity, $message, $filename, $lineno) {
	throw new ErrorException($message, 0, $severity, $filename, $lineno);
}
set_error_handler('exceptions_error_handler');

include 'themes.php';
Themes::setTheme($theme);
$reason = false;
if(isset($_POST['sent'])) {
	$msg = '';
	if(isset($_POST['msg'])) {
		$msg = $_POST['msg'];
	}
	$file = false;
	$filename = null;
	$type = null;
	$attr = false;
	if(isset($_FILES['file']) && $_FILES['file']['size'] != 0) {
		if($_FILES['file']['size'] > MAX_SEND_FILE_SIZE) {
			$reason = 'File is too large!';
		} else {
			$file = $_FILES['file']['tmp_name'];
			$filename = $_FILES['file']['name'];
			$extidx = strrpos($filename, '.');
			if($extidx === false) {
				$reason = 'Invalid file';
			} else {
				$ext = strtolower(substr($filename, $extidx+1));
				switch($ext) {
					case 'jpg':
					case 'jpeg':
					case 'png':
						$newfile = $file.'.'.$ext;
						if(!move_uploaded_file($file, $newfile)) {
							$reason = 'Failed to move file';
						} else {
							$type = 'inputMediaUploadedPhoto';
							$file = $newfile;
						}
						break;
					case 'mp3':
					case 'amr':
					case '3gp':
					case 'mp4':
					case 'gif':
					case 'zip':
					case 'jar':
					case 'jad':
					case 'sis':
					case 'sisx':
					case 'apk':
					case 'deb':
						$type = 'inputMediaUploadedDocument';
						$attr = true;
						break;
					default:
						$reason = 'This type of file ('.$ext.') is not supported!';
						break;
				}
			}
		}
	}
	if(!$reason) {
		try {
			if(!$file) {
				if(strlen($msg) > 0) {
					$MP = MP::getMadelineAPI($user);
					$MP->messages->sendMessage(['peer' => $id, 'message' => $msg]);
					header('Location: chat.php?c='.$id);
					die();
				}
			} else {
				$attributes = [];
				if($attr) {
					array_push($attributes, ['_' => 'documentAttributeFilename', 'file_name' => $filename]);
				}
				$MP = MP::getMadelineAPI($user);
				$MP->messages->sendMedia(['peer' => $id, 'message' => $msg, 'media' => 
				['_' => $type, 'file' => $file,
				'attributes' => $attributes
				]]);
				header('Location: chat.php?c='.$id);
				die();
			}
		} catch (Exception $e) {
			echo $e->getMessage();
		}
	}
}

echo '<head><title>'.$lng['sending_file'].'</title>';
echo Themes::head();
echo '</head>';
echo Themes::bodyStart();
echo '<div><a href="chat.php?c='.$id.'">'.$lng['back'].'</a></div><br>';
if($reason) {
	echo '<b>'.$reason.'</b>';
}
echo '<form action="file.php" method="post" enctype="multipart/form-data">';
echo '<input type="hidden" name="c" value="'.$id.'">';
echo '<input type="hidden" name="sent" value="1">';
echo '<textarea name="msg" value="" style="width: 100%"></textarea><br>';
echo '<input type="file" id="file" name="file"><br>';
echo '<input type="submit" value="'.$lng['send'].'">';
echo '</form>';
echo Themes::bodyEnd();