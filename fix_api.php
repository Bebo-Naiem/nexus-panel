<?php
$lines = file('api.php');
// The clean part ends at line 1401 (?>). We want to keep everything before that.
// Line 1401 is index 1400.
$lines = array_slice($lines, 0, 1400); 

$newCode = <<<'EOD'

// Update Management Functions
function handleCheckUpdate() {
    global $pdo;

    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        throw new Exception('Admin access required');
    }

    if (!is_dir('.git')) {
        return [
            'success' => true,
            'current_hash' => 'Unknown (Not a git repo)',
            'remote_hash' => 'Unknown',
            'update_available' => false,
            'message' => 'System was not installed via Git. Updates must be done manually.'
        ];
    }

    // Get current hash
    $currentHash = trim(shell_exec('git rev-parse HEAD'));

    // Fetch remote
    shell_exec('git fetch origin');

    // Get remote hash
    $remoteHash = trim(shell_exec('git rev-parse origin/main'));

    $updateAvailable = ($currentHash !== $remoteHash);

    return [
        'success' => true,
        'current_hash' => $currentHash,
        'remote_hash' => $remoteHash,
        'update_available' => $updateAvailable,
        'message' => $updateAvailable ? 'New version available.' : 'System is up to date.'
    ];
}

function handlePerformUpdate() {
    global $pdo;

    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        throw new Exception('Admin access required');
    }

    if (!is_dir('.git')) {
        throw new Exception('Not a git repository. Cannot update automatically.');
    }

    // Capture output including stderr
    $output = shell_exec('git pull origin main 2>&1');
    $success = (strpos($output, 'up to date') !== false || strpos($output, 'Fast-forward') !== false || strpos($output, 'Updating') !== false);

    return [
        'success' => $success,
        'output' => $output
    ];
}
?>
EOD;

file_put_contents('api.php', implode("", $lines) . $newCode);
echo "Fixed api.php";
?>
