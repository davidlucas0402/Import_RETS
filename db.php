<?php

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'webflow');

function init_connection()
{
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if (mysqli_connect_errno()) {
        return false;
    }

    $conn->set_charset("utf8");

    return $conn;
}

function process_param($param)
{
    if (is_null($param)) {
        $param = "";
    }

    if (is_string($param)) {
        $param = str_replace("'", "\'", $param);
        $param = "'$param'";
    } else if (is_bool($param)) {
        if ($param) {
            $param = "1";
        } else {
            $param = "0";
        }
    }

    return $param;
}

function run_query($sql)
{
    global $conn;

    $rows = [];

    if ($result = $conn->query($sql)) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }

        $result->close();
    }

    return $rows;
}

function select_table($table, $wheres = [], $sort = '')
{
    global $conn;

    $sql = "SELECT * FROM $table WHERE 1=1 ";

    foreach ($wheres as $key => $val) {
        $val = process_param($val);
        $sql .= " AND `$key` = $val";
    }

    if(!empty($sort)) {
        $sql .= "ORDER BY $sort";
    }

    $rows = [];

    if ($result = $conn->query($sql)) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }

        $result->close();
    }

    return $rows;
}

function select_row($table, $wheres = [], $sort = '')
{
    $rows = select_table($table, $wheres, $sort);
    if(empty($rows)) {
        return null;
    }

    return $rows[0];
}

function insert_table($table, $fields)
{
    global $conn;

    $keys = "";
    $values = "";

    foreach ($fields as $key => $val) {
        $keys .= "`$key`,";

        $val = process_param($val);
        $values .= "$val,";
    }

    $keys = substr($keys, 0, -1);
    $values = substr($values, 0, -1);

    $sql = "INSERT INTO $table ($keys) VALUES ($values) ";
    $ret = $conn->query($sql);

    $insert_id = false;

    if ($ret !== false) {
        $insert_id = $conn->insert_id;
    }

    return $insert_id;
}

function update_table($table, $fields, $wheres)
{
    global $conn;

    $sets = "";

    foreach ($fields as $key => $val) {
        $val = process_param($val);
        $sets .= "`$key` = $val,";
    }

    $sets = substr($sets, 0, -1);

    $sql = "UPDATE $table SET $sets WHERE 1=1 ";

    foreach ($wheres as $key => $val) {
        $val = process_param($val);
        $sql .= " AND `$key` = $val";
    }

    $ret = $conn->query($sql);

    return $ret;
}

function insert_or_update_table($table, $inserts, $updates)
{
    global $conn;

    $keys = "";
    $values = "";

    foreach ($inserts as $key => $val) {
        $keys .= "`$key`,";

        $val = process_param($val);
        $values .= "$val,";
    }

    $update_query = "";

    foreach ($updates as $key => $val) {
        $val = process_param($val);
        $update_query .= "$key = $val,";
    }

    $keys = substr($keys, 0, -1);
    $values = substr($values, 0, -1);
    $update_query = substr($update_query, 0, -1);

    $sql = "INSERT INTO $table ($keys) VALUES ($values) ON DUPLICATE KEY UPDATE $update_query";

    $ret = $conn->query($sql);

    $insert_id = false;

    if ($ret !== false) {
        $insert_id = $conn->insert_id;
    }

    return $insert_id;
}

function delete_table($table, $wheres = [])
{
    global $conn;

    $sql = "DELETE FROM $table WHERE 1=1 ";

    foreach ($wheres as $key => $val) {
        $val = process_param($val);
        $sql .= " AND `$key` = $val";
    }

    $ret = $conn->query($sql);

    return $ret;
}

function update_setting($table, $name, $value)
{
    $exist_setting = select_table($table, array(
        'name' => $name
    ));

    if(empty($exist_setting)) {
        insert_table($table, array(
            'value' => $value,
            'name' => $name,
        ));
    }
    else{
        update_table($table, array(
            'value' => $value,
        ), array(
            'name' => $name,
        ));
    }

}

function get_settings($table)
{
    $_settings = select_table($table);
    $settings = [];
    foreach ($_settings as $setting) {
        $settings[$setting['name']] = $setting['value'];
    }
    return $settings;
}

$conn = init_connection();
