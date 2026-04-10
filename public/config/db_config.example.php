<?php
// Legacy path retained for compatibility with setup documentation.
$ADOdbpath = "backends/adodb";

// Port to connect to database on. Set to "" for the default port.
$dbport = "";

// Hostname of the database server.
$ADODB_SESSION_CONNECT = "127.0.0.1";

// Username and password to connect to the database.
$ADODB_SESSION_USER = "change_me";
$ADODB_SESSION_PWD = "change_me";

// Name of the SQL database.
$ADODB_SESSION_DB = "change_me";

// Define a random crypto key for ADOdb to use for encrypted sessions.
$ADODB_CRYPT_KEY = "change_me_to_a_random_secret";

// Database driver used by the local PDO compatibility layer.
$ADODB_SESSION_DRIVER = "pdo_mysql";

// Set this to 1 to use db persistent connections, 0 otherwise.
$db_persistent = 0;

// Table prefix for the database.
$db_prefix = "bnt_";
