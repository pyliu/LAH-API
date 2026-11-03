<?php
/**
 * Oracle 9i CLI Worker for PHP 8.5 Bridge Mode
 * Reads Binary-Transparent Envelope JSON payload from STDIN:
 *   {action, user, pass, conn_str, nls, sql_b64 (or sql), binds}
 * Executes oci_* in PHP 7.x CLI and outputs Binary-Transparent Envelope JSON to STDOUT.
 */
error_reporting(0);
putenv('NLS_LANG=American_America.US7ASCII');

/**
 * Format oci_error() into a UTF-8 safe and Base64-preserved array so json_encode never fails on CP950 bytes.
 *
 * @param array|false|null $e
 * @param string $fallback_msg
 * @return array
 */
function ora_worker_format_error($e, $fallback_msg) {
    $raw_msg = (is_array($e) && isset($e['message'])) ? (string)$e['message'] : (string)$fallback_msg;
    $raw_sql = (is_array($e) && isset($e['sqltext'])) ? (string)$e['sqltext'] : '';

    $utf8_msg = $raw_msg;
    if ($utf8_msg !== '') {
        if (function_exists('mb_check_encoding') && !@mb_check_encoding($utf8_msg, 'UTF-8')) {
            $converted = @mb_convert_encoding($utf8_msg, 'UTF-8', 'CP950');
            if (is_string($converted) && $converted !== '') {
                $utf8_msg = $converted;
            }
        }
        if (function_exists('iconv')) {
            $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $utf8_msg);
            if (is_string($clean)) {
                $utf8_msg = $clean;
            }
        }
    }

    return array(
        'code'        => (is_array($e) && isset($e['code'])) ? (int)$e['code'] : 0,
        'message'     => $utf8_msg,
        'message_b64' => base64_encode($raw_msg),
        'offset'      => (is_array($e) && isset($e['offset'])) ? (int)$e['offset'] : 0,
        'sqltext_b64' => base64_encode($raw_sql)
    );
}

$stdin = file_get_contents('php://stdin');
$req = json_decode($stdin, true);

if (!is_array($req)) {
    echo json_encode(array(
        'ok'      => false,
        'channel' => 'cli_proc_open',
        'error'   => ora_worker_format_error(null, 'Invalid JSON input to ora_cli_worker.php')
    ));
    exit(1);
}

$action = isset($req['action']) ? (string)$req['action'] : 'query';
$has_oci8 = extension_loaded('oci8') && function_exists('oci_connect');

if ($action === 'ping') {
    echo json_encode(array(
        'ok'          => $has_oci8,
        'channel'     => 'cli_proc_open',
        'php_version' => PHP_VERSION,
        'oci8'        => $has_oci8
    ));
    exit($has_oci8 ? 0 : 1);
}

if (!$has_oci8) {
    echo json_encode(array(
        'ok'      => false,
        'channel' => 'cli_proc_open',
        'error'   => ora_worker_format_error(null, 'PHP CLI worker does not have oci8 extension loaded.')
    ));
    exit(1);
}

$user = isset($req['user']) ? (string)$req['user'] : '';
$pass = isset($req['pass']) ? (string)$req['pass'] : '';
$conn_str = isset($req['conn_str']) ? (string)$req['conn_str'] : '';
$nls = !empty($req['nls']) ? (string)$req['nls'] : 'US7ASCII';

if (isset($req['sql_b64']) && is_string($req['sql_b64']) && $req['sql_b64'] !== '') {
    $decoded_sql = base64_decode($req['sql_b64'], true);
    $sql = ($decoded_sql !== false) ? $decoded_sql : (isset($req['sql']) ? (string)$req['sql'] : '');
} else {
    $sql = isset($req['sql']) ? (string)$req['sql'] : '';
}

$binds = (isset($req['binds']) && is_array($req['binds'])) ? $req['binds'] : array();

$conn = @oci_connect($user, $pass, $conn_str, $nls);
if (!$conn) {
    $e = @oci_error();
    echo json_encode(array(
        'ok'      => false,
        'channel' => 'cli_proc_open',
        'error'   => ora_worker_format_error($e, 'oci_connect failed')
    ));
    exit(1);
}

$stid = @oci_parse($conn, $sql);
if (!$stid) {
    $e = @oci_error($conn);
    @oci_close($conn);
    echo json_encode(array(
        'ok'      => false,
        'channel' => 'cli_proc_open',
        'error'   => ora_worker_format_error($e, 'oci_parse failed')
    ));
    exit(1);
}

$bind_values = array();
foreach ($binds as $bind_var => $bind_info) {
    if (is_array($bind_info) && array_key_exists('val', $bind_info)) {
        $bind_values[$bind_var] = !empty($bind_info['b64'])
            ? base64_decode((string)$bind_info['val'])
            : $bind_info['val'];
    } else {
        $bind_values[$bind_var] = $bind_info;
    }
    @oci_bind_by_name($stid, $bind_var, $bind_values[$bind_var]);
}

$r = @oci_execute($stid);
if (!$r) {
    $e = @oci_error($stid);
    @oci_free_statement($stid);
    @oci_close($conn);
    echo json_encode(array(
        'ok'      => false,
        'channel' => 'cli_proc_open',
        'error'   => ora_worker_format_error($e, 'oci_execute failed')
    ));
    exit(1);
}

$rows_b64 = array();
$stmt_type = (string)@oci_statement_type($stid);
$affected_rows = 0;

if ($stmt_type === 'SELECT') {
    while ($row = @oci_fetch_assoc($stid)) {
        $enc_row = array();
        foreach ($row as $k => $v) {
            $enc_row[] = array(
                'k_b64'  => base64_encode((string)$k),
                'is_str' => is_string($v),
                'v'      => is_string($v) ? base64_encode($v) : $v
            );
        }
        $rows_b64[] = $enc_row;
        $affected_rows++;
    }
} else {
    $num_rows = @oci_num_rows($stid);
    $affected_rows = ($num_rows !== false) ? (int)$num_rows : 0;
}

@oci_free_statement($stid);
@oci_close($conn);

echo json_encode(array(
    'ok'            => true,
    'channel'       => 'cli_proc_open',
    'stmt_type'     => $stmt_type,
    'affected_rows' => $affected_rows,
    'rows_b64'      => $rows_b64
));
