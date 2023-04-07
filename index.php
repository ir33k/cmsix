<?php
/**
 * Administration panel for CMSIX CMS.
 *
 * You start at /login page that requires password to authorize.
 * There is only one user so only password is needed, no usernames here.
 * To setup password go to /hash page, provide password and submit.
 * You will get values for PASS_HASH and PASS_SALT to paste in this file.
 * Default values are few lines below, generated for password "12345".
 * USING DEFAULT PASSWORD IS NOT RECOMMENDED!
 *
 * After setting password and authorization ou can get to main pages.
 * Default /text page allowes to modify values in database text file.
 * Page /file is used to upload, view and remove files.
 * Uploaded images are resized to multiple sizes, defined in cmsix.php.
 * To logout visit /logout page.
 */

require './cmsix.php';

/**#@+
 * Password SALT and HASH can be generated on /hash page.
 * Salt is just a string with random characters.
 * Hash is value returned by password_hash(), salt + password.
 */
const PASS_HASH = '$2y$10$sj7Q4h.T6iOlh1K24ZEOe.sytdfkUCgFt1n9/nxOLlxLpYeEXN9Gi';
const PASS_SALT = 'batHMwtcn/HgAT86DpFvNjs5Zl57N0TMJ8K50B4TKdU=';

/**#@-*/

const S_KEY    = 'SID';                 // $_SESSION key
const S_COOKIE = 'CMSIX_SID';           // Browser cookie name
const S_EXPIRY = 60*60;                 // 60m, cookie expire time

enum Page {             // Each possible page, used to set $page var
	case Hash;      // Page for creating password hash and salt
	case Login;     // Login page, default when not authorized
	case Logout;    // Upon visiting logout from active session
	                // NEXT PAGES REQUIRE AUTHENTICATION
	case Text;      // Main page for editing values in DB file
	case File;      // Page to view, add and remove files
}

/** Replace database text file with content of $data. */
function text_write(array $data): void
{
	if (!is_resource($file = fopen(CMSIX_PATH_TEXT, 'wb'))) {
		return;
	}
	foreach ($data as $k => $v) {
		// Just in case replace all instances of CMSIX_TEXT_SEPARATOR.
		$v = preg_replace(
			'/^'.CMSIX_TEXT_SEPARATOR.'.*/',
			'==================================================',
			$v
		);
		fwrite($file, sprintf(
			"%s\n%s\n%s\n\n",
			CMSIX_TEXT_SEPARATOR,
			$k,
			rtrim(str_replace('', '', $v))
		));
	}
	fclose($file);
}

/** Update database text file with changes in $new_data. */
function text_update(array $new_data): void
{
	$data = cmsix_read();
	$write = false;
	foreach($new_data as $k => $v) {
		if (isset($data[$k]) and
		    strlen(trim($k)) and
		    strlen(trim($v))) {
			$data[$k] = $v;
			$write = true;
		}
	}
	if ($write) {
		text_write($data);
	}
}

/** Add new text with $key of $value to database text file. */
function text_add(string $key, string $value): void
{
	if (strlen(trim($key)) and
	    strlen(trim($value) and
	    !isset($date[$key]))) {
		$data = cmsix_read();
		$data[$key] = $value;
		text_write($data);
	}
}

/** Remove $keys with their values from database text file. */
function text_rm(array $keys): void
{
	$data = cmsix_read();
	$write = false;
	foreach($keys as $k) {
		if (isset($data[$k])) {
			unset($data[$k]);
			$write = true;
		}
	}
	if ($write) {
		text_write($data);
	}
}

/** Return true if $file name has bitmap image extension. */
function file_is_img(string $file_name): bool
{
	$ext = pathinfo($file_name)['extension'] ?? '';
	return in_array($ext, ['jpg', 'jpeg', 'gif', 'png']);
}

/** Return true if given $file_name should be ignored. */
function file_ignore(string $file_name): bool
{
	return str_starts_with($file_name, '.') ||
	       str_ends_with($file_name, '.html') ||
	       str_ends_with($file_name, '.php') ||
	       str_contains($file_name, CMSIX_FILE_PREFIX) ||
	       is_dir($file_name);
}

/** List all file names from $path dir. */
function file_ls(): array
{
	$file_names = [];
	if (!($dir = opendir(CMSIX_PATH_FILE))) {
		return $file_names;
	}
	while ($file_name = readdir($dir)) {
		array_push($file_names, $file_name);
	}
	closedir($dir);
	sort($file_names);
	return $file_names;
}

// TODO(irek): Make it better.
// See first comment on: https://www.php.net/manual/en/features.file-upload.php
/** Upload $files.  Return paths to uploaded files. */
function file_add(array $files): array
{
	// Check for $_FILES Corruption Attack.
	if (!isset($files['error']) ||
	    !is_array($files['error'])) {
		throw new RuntimeException('Invalid parameters.');
	}
	$uploaded_file_paths = [];
	$count = count($files['name']);
	for ($i = 0; $i < $count; $i++) {
		switch ($files['error'][$i]) {
		case UPLOAD_ERR_OK:
			break;
		case UPLOAD_ERR_NO_FILE:
		case UPLOAD_ERR_INI_SIZE:
			// TODO(irek): I need to handle that.
		case UPLOAD_ERR_FORM_SIZE:
		default:
			// TODO(irek): I rly need to handle errors.
			// throw new RuntimeException('Unknown errors.');
			continue 2;               // Skip file with error
		}
		$file_name = $files['name'][$i];
		if (file_ignore($file_name)) {
			continue;
		}
		$src = $files['tmp_name'][$i];
		$dst = CMSIX_PATH_FILE.$file_name;
		move_uploaded_file($src, $dst);
		array_push($uploaded_file_paths, $dst);
	}
	return $uploaded_file_paths;
}

/** Optimaze images. */
function file_img_optimize(array $file_paths): void
{
	// TODO(irek): Enable progression in JPG and PNG images.
	// TODO(irek): It might be necessary to support other extension.
	// As fallback when GD is not loaded.
	// Also error/warning message would be nice. 
	if (!extension_loaded('gd')) {
		return;
	}
	$supported_ext = [];
	$info = gd_info();
	if ($info['JPEG Support']) {
		array_push($supported_ext, 'jpg');
		array_push($supported_ext, 'jpeg');
	}
	if ($info['PNG Support']) {
		array_push($supported_ext, 'png');
	}
	if ($info['WBMP Support']) {
		array_push($supported_ext, 'bmp');
	}
	// TODO(irek): Looks like it will be necessary to support GIFs.
	// But not just any support but support with optimizations.
	// This will require a lot of code.
	foreach ($file_paths as $src_path) {
		$src_ext = pathinfo($src_path)['extension'];
		if (!in_array($src_ext, $supported_ext)) {
			continue;
		}
		[$src_w, $src_h] = getimagesize($src_path);
		$src_img;
		switch ($src_ext) {
		case 'bmp'  : $src_img = imagecreatefrombmp  ($src_path); break;
		case 'jpg'  :
		case 'jpeg' : $src_img = imagecreatefromjpeg ($src_path); break;
		case 'png'  : $src_img = imagecreatefrompng  ($src_path); break;
		}
		if (!$src_img) {
			continue;
		}
		foreach (CMSIX_SIZES as $dst_w) {
			if ($src_w < $dst_w) {
				continue;
			}
			$dst_h = floor($src_h * ($dst_w / $src_w));
			$dst_img = imagecreatetruecolor($dst_w, $dst_h);
			imagecopyresampled($dst_img, $src_img, 0, 0, 0, 0, $dst_w, $dst_h, $src_w, $src_h);
			$dst_path = $src_path.CMSIX_FILE_PREFIX.$dst_w;
			if ($src_ext) {
				$dst_path .= ".{$src_ext}";
			}
			switch ($src_ext) {
			case 'bmp'  : imagebmp  ($dst_img, $dst_path); break;
			case 'jpg'  :
			case 'jpeg' : imagejpeg ($dst_img, $dst_path); break;
			case 'png'  : imagepng  ($dst_img, $dst_path); break;
			}
			imagedestroy($dst_img);
		}
		imagedestroy($src_img);
	}
}

/** Remove $file_names along with internal CMSIX files. */
function file_rm(array $file_names): void
{
	$to_remove = [];
	if (!($dir = opendir(CMSIX_PATH_FILE))) {
		return;
	}
	while ($file_name = readdir($dir)) {
		foreach ($file_names as $v) {
			if ($file_name == $v or
			    str_starts_with($file_name, $v.CMSIX_FILE_PREFIX)) {
				array_push($to_remove, $file_name);
			}
		}
	}
	closedir($dir);
	foreach ($to_remove as $v) {
		// TODO(irek): Handle PHP errors, hide them or use them for my own errors?
		// https://www.php.net/manual/en/function.error-reporting.php
		unlink(CMSIX_PATH_FILE.$v);
	}
}

// TODO(irek): Handle errors by creating log files.  Currently a lot
// of functions, especially the file related function, are very silent
// about errors making it very script like experience.  I would like
// to log all errors and warnings and display them to the user.
//
// https://www.php.net/manual/en/errorfunc.examples.php
// https://www.php.net/manual/en/errorfunc.constants.php
//
/** Messages to show on page, if any. */
$msg = isset($_GET['msg']) ? [$_GET['msg']] : [];

/** Pages that require authorization. */
$auth_pages = [Page::Text, Page::File];

/** Page to handle and render, default to Page::Text. */
$page = Page::Text;

switch (preg_split('/[\/\?\#]/', $_SERVER['PATH_INFO'] ?? '/')[1]) {
case 'hash'   : $page = Page::Hash   ; break;
case 'login'  : $page = Page::Login  ; break;
case 'logout' : $page = Page::Logout ; break;
case 'file'   : $page = Page::File   ; break;
}

session_start();

if (in_array($page, $auth_pages)) {             // Authorize
	$session = $_SESSION[S_KEY]   ?? null;
	$cookie  = $_COOKIE[S_COOKIE] ?? null;
	// Redirect to login page if not authorized.
	if (!$session or !$cookie or $session !== $cookie) {
		header('Location: '.CMSIX_URL_ROOT.'/login?msg=Unauthorized!');
		exit;                           // Unauthorized, redirect
	}
	// Refresh session cookie expire time.
	setcookie(S_COOKIE, $cookie, [
		'expires'  => time() + S_EXPIRY,
		'samesite' => 'Strict',
	]);                                     // Authorized
}
switch ($page) {
case Page::Hash:
	if (isset($_GET['pass'])) {             // Create hash & salt
		$salt = base64_encode(random_bytes(32));
		$hash = password_hash($salt.$_GET['pass'], PASSWORD_DEFAULT);
		header('Content-Type: text/plain; charset=utf-8');
		echo "// Use in index.php:\n\n";
		echo "const PASS_HASH = '{$hash}';\n";
		echo "const PASS_SALT = '{$salt}';\n";
		exit;                           // End here
	}                                       // Else render page
	break;
case Page::Login:
	if (isset($_POST['pass'])) {
		if (password_verify(PASS_SALT.$_POST['pass'], PASS_HASH)) {
			$session = uniqid();
			$_SESSION[S_KEY] = $session;
			setcookie(S_COOKIE, $session, [
				'expires'  => time() + S_EXPIRY,
				'samesite' => 'Strict',
			]);
			header('Location: '.CMSIX_URL_ROOT);
			exit;                           // Loged in
		}
		array_push($msg, 'Invalid password!');  // Wrong pass
	}
	break;
case Page::Logout:
	$_SESSION[S_KEY] = null;                        // Unnecessary
	setcookie(S_COOKIE, '', time()-1);              // Del cookie
	session_destroy();
	break;
case Page::File:
	$data = [];
	// Check if our directory with files can be used.
	// Otherwise it makes no sense to do anything on files page.
	if (!is_dir(CMSIX_PATH_FILE) and !mkdir(CMSIX_PATH_FILE)) {
		array_push($msg, "ERROR: Can't open and make ".CMSIX_PATH_FILE);
		goto skip;                              // Fatal error
	}
	if (isset($_FILES['files'])) {
		$uploaded_file_paths = file_add($_FILES['files']);
		file_img_optimize($uploaded_file_paths);
	}
	if (isset($_GET['remove']) and
	    isset($_GET['files'])) {
		file_rm($_GET['files']);
	}
	$data = file_ls();
	$data = array_filter($data, fn($v) => !file_ignore($v));
	break;
case Page::Text:
	$data = [];
	if (!is_resource(fopen(CMSIX_PATH_TEXT, 'rb'))) {
		array_push($msg, "ERROR: Can't open ".CMSIX_PATH_TEXT);
		goto skip;                              // Fatal error
	}
	if (count($_POST)) {
		text_update($_POST);
	}
	if (isset($_GET['add']) and
	    isset($_GET['key']) and
	    isset($_GET['value'])) {
		text_add($_GET['key'], $_GET['value']);
	}
	if (isset($_GET['remove']) and
	    isset($_GET['keys'])) {
		text_rm($_GET['keys']);
	}
	$data = cmsix_read();
	break;
}
skip:           // GOTO label to skip code in case of fatal error
                // but you still want to show website with message.
?>
<!DOCTYPE html>
<html lang=en>
<head>
	<meta charset=utf-8 />
	<meta name=author content=irek />
	<meta name=keywords content=cms,cmsix,admin,php />
	<meta name=description content=cmsix_cms_admin_page />
	<meta name=apple-mobile-web-app-capable content=yes />
	<meta name=mobile-web-app-capable content=yes />
	<meta name=viewport content=width=device-width,initial-scale=1,maximum-scale=1 />
	<meta name=theme-color content=#000000 />
	<meta name=msapplication-navbutton-color content=#000000 />
	<meta name=apple-mobile-web-app-status-bar-style content=#000000 />
	<title>cmsix</title>
</head>
<body>
<style>
body {
	background-color: #fff;
	padding: min(4vw, 4vh);
	max-width: 38em;
	line-height: 1.3;
	font-family:     /* Source: http://web.mit.edu/jmorzins/www */
		"Bookman Old Style",    /* Windows, MacOS           */
		"Serifa BT",            /* Windows XP               */
		"URW Bookman L",        /* Unix+X+FontConfig        */
		"itc bookman",          /* Unix+X                   */
		times, serif;           /* Fallback options         */
}
a { color: #012fe2 }
a:hover { text-decoration: none }
.msg { padding: .5em 1em; outline: 1px solid }
@media (prefers-color-scheme: dark) {
	body { background-color: #000; color: #fff }
	a { color: #ffef6e }
}
</style>
<?php foreach ($msg as $m): ?>
	<p class=msg><?=$m?></p>
<?php endforeach ?>
<?php if (in_array($page, $auth_pages)):                // Nav menu ?>
	<style>
	menu {
		list-style-type: none;
		display: flex;
		justify-content: flex-end;
		gap: 1em;
	}
	</style>
	<menu>
		<li><a href=<?=CMSIX_URL_ROOT?>>texts</a>
		<li><a href=<?=CMSIX_URL_ROOT.'/file'?>>files</a>
		<li><a href=<?=CMSIX_URL_ROOT.'/logout'?>>logout</a>
	</menu>
<?php endif ?>
<?php switch($page): ?>
<?php case Page::Hash: ?>
	<h1>Hash</h1>
	<p>Create hash and salt for given password. Use created values in <code>index.php</code>.</p>
	<form>
		<input type=text name=pass placeholder=password required autofocus>
		<input type=submit>
	</form>
<?php break ?>
<?php case Page::Login: ?>
	<h1>Login</h1>
	<p>Password should be given to you by website administrator.</p>
	<form method=post>
		<input type=password name=pass placeholder=password required autofocus>
		<input type=submit value=login>
	</form>
<?php break ?>
<?php case Page::Logout: ?>
	<h1>Logout</h1>
	<p>You have been loged out.</p>
	<ul>
		<li><a href=/>home page</a></li>
		<li><a href=<?=CMSIX_URL_ROOT.'login'?>>login page</a></li>
	</ul>
<?php break ?>
<?php case Page::Text: ?>
	<style>
	input[type=text], textarea {
		resize: vertical;
		width: 100%;
		box-sizing: border-box;
		border: solid 1px currentColor;
	}
	@media (prefers-color-scheme: dark) {
		textarea {
			background-color: #000;
			color: #fff;
		}
	}
	</style>
	<h1>Texts</h1>
	<h2>Add new value</h2>
	<form>
		<input type=text name=key placeholder=key required />
		<textarea cols=80 rows=5 name=value placeholder=value rows=5 required></textarea>
		<input type=submit name=add value=add />
	</form>
	<h2>Modify or remove existing values</h2>
	<form id=text-edit method=post></form>
	<form id=text-remove method=get></form>
	<?php foreach ($data as $k => $v): ?>
		<h3 id=<?=$k?>><code><?=$k?></code></h3>
<!-- This strange new line in textarea is necessary.                    -->
<!-- Without it we will lose first empty line in value starts with one. -->
		<textarea form=text-edit name=<?=$k?>>
<?=$v?></textarea>
		<label for="remove-<?=$k?>">Remove</label>
		<input form=text-remove id="remove-<?=$k?>" type=checkbox name=keys[] value="<?=$k?>">
	<?php endforeach ?>
	<p>
		<input form=text-remove name=remove type=submit value="Remove selected">
		<input form=text-edit type=submit value=Update>
	</p>
	<script>
	// Set textarea height so it shows whole text.
	for (const el of document.querySelectorAll('textarea')) {
		el.style.height = el.scrollHeight + 8 + 'px' // 8px padding
	}
	</script>
<?php break ?>
<?php case Page::File: ?>
	<style>
	img { display: block; max-width: 30%; border: 1px solid #000 }
	li { margin-bottom: 2em }
	</style>
	<h1>Files</h1>
	<h2>Upload</h2>
	<form method=post enctype=multipart/form-data>
		<input type=file name=files[] multiple required>
		<input type=submit value=upload>
	</form>
	<h2>View and remove</h2>
	<form>
		<?php foreach ($data as $k => $f): ?>
			<p>
				<a href=<?=CMSIX_URL_FILE.$f?>>
					<?=$f?>
					<?php if (file_is_img($f)): ?>
						<?=cmsix_img($f, [ "sizes" => "20vw" ])?>
					<?php endif ?>
				</a>
				<label for=remove<?=$k?>>Remove</label>
				<input id=remove<?=$k?> type=checkbox name=files[] value=<?=$f?> />
			</p>
		<?php endforeach ?>
		<input type=submit name=remove value=remove />
	</form>
<?php break ?>
<?php endswitch ?>
</body>
</html>
