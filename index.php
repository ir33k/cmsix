<?php
// TODO(irek): Warning: POST Content-Length of 11879652 bytes exceeds the limit of 8388608 bytes in Unknown on line 0
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
const FPATH    = cmsix\FPATH;           // Path to file with data
const DPATH    = 'db/';                 // Path to files dir

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
	if (!is_resource($file = fopen(FPATH, 'wb'))) {
		return;
	}
	foreach ($data as $k => $v) {
		fwrite($file, $k);
		$v = str_replace('', '', $v);
		$v = rtrim($v)."\n";
		// Append END indicator.  Default is single empty line
		// but if $v value contains empty lines then generate
		// unique id to mark end.
		if (str_contains($v, "\n\n")) {
			$id = "END-".uniqid();
			fwrite($file, "\t{$id}");
			$v .= "{$id}\n";
		}
		fwrite($file, "\n{$v}\n");
	}
	fclose($file);
}

/** Update database text file with changes in $new_data. */
function text_update(array $new_data): void
{
	$data = cmsix\read(FPATH);
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
		$data = cmsix\read(FPATH);
		$data[$key] = $value;
		text_write($data);
	}
}

/** Remove $keys with their values from database text file. */
function text_rm(array $keys): void
{
	$data = cmsix\read(FPATH);
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
	       str_contains($file_name, cmsix\PREFIX) ||
	       is_dir($file_name);
}

/** List all file names from $path dir. */
function file_ls(string $path): array
{
	$file_names = [];
	if (!($dir = opendir($path))) {
		return $file_names;
	}
	while ($file_name = readdir($dir)) {
		array_push($file_names, $file_name);
	}
	closedir($dir);
	sort($file_names);
	return $file_names;
}

/** Upload $files to dir $path.  Return paths to uploaded files. */
function file_add(array $files, string $path): array
{
	// $files = [
	//   name      : ['img01.jpeg', 'img02.jpeg']
	//   full_path : ['img01.jpeg', 'img02.jpeg']
	//   type      : ['image/jpeg', 'image/jpeg']
	//   tmp_name  : ['/tmp/php1L', '/tmp/phpvX']
	//   error     : [0, 0]
	//   size      : [308725, 161037]
	// ]
	// TODO(irek): Investigate size limits.
	// TODO(irek): Check for errors in files.
	// TODO(irek): There is no check if file already exists.
	// I want to give possiblity to overwrite existing files.
	// But this might not be the best way to do it.
	$uploaded_file_paths = [];
	$count = count($files['name']);
	for ($i = 0; $i < $count; $i++) {
		$file_name = $files['name'][$i];
		if (file_ignore($file_name)) {
			continue;
		}
		$src = $files['tmp_name'][$i];
		$dst = $path.$file_name;
		move_uploaded_file($src, $dst);
		array_push($uploaded_file_paths, $dst);
	}
	return $uploaded_file_paths;
}

/** Optimaze images under $file_paths. */
function file_img_optimize(array $file_paths): void
{
	// TODO(irek): It might be necessary to support other
	// extension as fallback when GD is not loaded.
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
		foreach (cmsix\SIZES as $dst_w) {
			if ($src_w < $dst_w) {
				continue;
			}
			$dst_h = floor($src_h * ($dst_w / $src_w));
			$dst_img = imagecreatetruecolor($dst_w, $dst_h);
			imagecopyresampled($dst_img, $src_img, 0, 0, 0, 0, $dst_w, $dst_h, $src_w, $src_h);
			$dst_path = $src_path.cmsix\PREFIX.$dst_w;
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

/** Remove $file_names from dir $path along with internal CMSIX files. */
function file_rm(array $file_names, string $path): void
{
	$to_remove = [];
	if (!($dir = opendir($path))) {
		return;
	}
	while ($file_name = readdir($dir)) {
		foreach ($file_names as $v) {
			if ($file_name == $v or
			    str_starts_with($file_name, $v.cmsix\PREFIX)) {
				array_push($to_remove, $file_name);
			}
		}
	}
	closedir($dir);
	foreach ($to_remove as $v) {
		// TODO(irek): Handle PHP errors, hide them or use them for my own errors?
		// https://www.php.net/manual/en/function.error-reporting.php
		unlink(DPATH.$v);
	}
}

/** Url path to root of admin page (to this file). */
$url_root = str_replace('index.php', '', $_SERVER['SCRIPT_NAME']);

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
		header('Location: '.$url_root.'login?msg=Unauthorized!');
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
			header('Location: '.$url_root);
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
	if (!is_dir(DPATH) and !mkdir(DPATH)) {
		array_push($msg, "ERROR: Can't open and make ".DPATH);
		goto skip;                              // Fatal error
	}
	if (isset($_FILES['files'])) {
		$uploaded_file_paths = file_add($_FILES['files'], DPATH);
		file_img_optimize($uploaded_file_paths);
	}
	if (isset($_GET['remove']) and
	    isset($_GET['files'])) {
		file_rm($_GET['files'], DPATH);
	}
	$data = file_ls(DPATH);
	$data = array_filter($data, fn($v) => !file_ignore($v));
	break;
case Page::Text:
	$data = [];
	if (!is_resource(fopen(FPATH, 'rb'))) {
		array_push($msg, "ERROR: Can't open ".FPATH);
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
	$data = cmsix\read(FPATH);
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
		<li><a href=<?=$url_root?>>texts</a>
		<li><a href=<?=$url_root.'file'?>>files</a>
		<li><a href=<?=$url_root.'logout'?>>logout</a>
	</menu>
<?php endif ?>
<?php switch($page): ?>
<?php case Page::Hash: ?>
	<h1>Hash</h1>
	<p>Create hash and salt for given password. Use created values in <code>index.php</code>.</p>
	<form>
		<input type=password name=pass placeholder=password required autofocus>
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
		<li><a href=<?=$url_root.'login'?>>login page</a></li>
	</ul>
<?php break ?>
<?php case Page::Text: ?>
	<style>
	input[type=text], textarea {
		min-width: 100%;
		max-width: 100%;
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
		<textarea cols=80 rows=5 name=value placeholder=value required></textarea>
		<input type=submit name=add value=add />
	</form>
	<h2>Modify or remove existing values</h2>
	<form id=text-edit method=post></form>
	<form id=text-remove method=get></form>
	<?php foreach ($data as $k => $v): ?>
		<h3 id=<?=$k?>><code><?=$k?></code></h3>
		<textarea form=text-edit cols=80 rows=<?=substr_count($v,"\n")+1?> name=<?=$k?>><?=$v?></textarea>
		<label for="remove-<?=$k?>">Remove</label>
		<input form=text-remove id="remove-<?=$k?>" type=checkbox name=keys[] value="<?=$k?>">
	<?php endforeach ?>
	<p>
		<input form=text-remove name=remove type=submit value=remove>
		<input form=text-edit type=submit value=edit>
	</p>
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
				<a href=<?=$url_root.DPATH.$f?>>
					<?=$url_root.DPATH.$f?>
					<?php if (file_is_img($f)): ?>
						<?=cmsix\img(DPATH.$f, [ "sizes" => "20vw" ])?>
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
