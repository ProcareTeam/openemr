<?php

require_once(dirname(__FILE__) . "/../../../sites/default/sqlconf.php");

if(empty($sqlconf['host']) || empty($sqlconf['port']) || empty($sqlconf['login']) || empty($sqlconf['dbase'])) {
	exit();
}

// Connect to the database
$conn11 = new mysqli($sqlconf['host'], $sqlconf['login'], $sqlconf['pass'], $sqlconf['dbase']);

// Check if connected successfully
if ($conn11->connect_error) {
    die("Connection failed: " . $conn11->connect_error);
}

// Read SQL file
$sqlFile11 = dirname(__FILE__) . "/../tables/temp_sql.sql";
$sql11 = file_get_contents($sqlFile11);


// Split SQL queries by delimiter (usually ';')
$queries11 = explode(';', $sql11);


// Execute each query
foreach ($queries11 as $query) {
    // Trim whitespace and check if query is not empty
    $query = trim($query);
    if (!empty($query)) {
        // Execute query
        if ($conn11->query($query) === TRUE) {
            echo "Query executed successfully: $query <br>";
        } else {
            echo "Error executing query: $query - " . $conn11->error . "<br>";
        }
    }
}

// Close connection
$conn11->close();