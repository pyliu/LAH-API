<?php
/**
 * Oracle 9i CLI Worker for PHP 8.5 Bridge Mode
 * Reads JSON payload from STDIN: {user, pass, conn_str, nls, sql, binds}
 * Executes oci_* in PHP 7.x CLI and outputs base64-encoded raw rows as JSON to STDOUT.
 */
error_reporting(0);
putenv('NLS_LANG=American_America.US7ASCII');

$stdin = file_get_contents('php://stdin');
$req = json_decode($stdin, true);

if (!is_array($req)) {
    echo json_encode(array(
        'ok' => false,
        'error' => array('message' => 'Invalid JSON input to ora_cli_worker.php')
    ));
    exit(1);
}

if (!extension_loaded('oci8')) {
    echo json_encode(array(
        'ok' => false,
        'error' => array('message' => 'PHP CLI worker does not have oci8 extension loaded.')
    ));
    exit(1);
}

$user = $req['user'] ?? '';
$pass = $req['pass'] ?? '';
$conn_str = $req['conn_str'] ?? '';
$nls = $req['nls'] ?? 'US7ASCII';
$sql = $req['sql'] ?? '';
$binds = $req['binds'] ?? array();

$conn = @oci_connect($user, $pass, $conn_str, $nls);
if (!$conn) {
    $e = oci_error();
    echo json_encode(array(
        'ok' => false,
        'error' => is_array($e) ? $e : array('message' => 'oci_connect failed')
    ));
    exit(1);
}

$stid = @oci_parse($conn, $sql);
if (!$stid) {
    $e = oci_error($conn);
    @oci_close($conn);
    echo json_encode(array(
        'ok' => false,
        'error' => is_array($e) ? $e : array('message' => 'oci_parse failed')
    ));
    exit(1);
}

$bind_values = array();
if (is_array($binds)) {
    foreach ($binds as $bind_var => $bind_info) {
        if (is_array($bind_info) && array_key_exists('val', $bind_info)) {
            $bind_values[$bind_var] = !empty($bind_info['b64'])
                ? base64_decode($bind_info['val'])
                : $bind_info['val'];
        } else {
            $bind_values[$bind_var] = $bind_info;
        }
        @oci_bind_by_name($stid, $bind_var, $bind_values[$bind_var]);
    }
}

$r = @oci_execute($stid);
if (!$r) {
    $e = oci_error($stid);
    @oci_free_statement($stid);
    @oci_close($conn);
    echo json_encode(array(
        'ok' => false,
        'error' => is_array($e) ? $e : array('message' => 'oci_execute failed')
    ));
    exit(1);
}

$rows = array();
$stmt_type = @oci_statement_type($stid);
if ($stmt_type === 'SELECT') {
    while ($row = @oci_fetch_assoc($stid)) {
        $enc_row = array();
        foreach ($row as $k => $v) {
            $enc_row[$k] = is_string($v) ? base64_encode($v) : $v;
        }
        $rows[] = $enc_row;
    }
}

@oci_free_statement($stid);
@oci_close($conn);

echo json_encode(array(
    'ok' => true,
    'rows' => $rows
));
