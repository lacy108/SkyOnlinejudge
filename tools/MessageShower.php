<?php
/*
 * 2016 Sky Online Judge Project
 */
 
$isCLI = ( php_sapi_name() == 'cli' );
if(!$isCLI)
{
    die("Please run me from the console - not from a web-browser!");
}

/// Settings
$SAVE_FILE  = 'log.txt';
$PORT       = 19620;
$IP         = 'localhost';


$h_output_file = @fopen($SAVE_FILE,'a');
if( $h_output_file === false )
{
    echo "Cannot Open SAVE_FILE".PHP_EOL;
}

$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_bind($socket,$IP,$PORT) or die('bild fail');
socket_listen($socket) or die('listen fail');
echo "MessageShower Server Start".PHP_EOL;

while(true)
{
    $connection = socket_accept($socket);
    while($data = socket_read($connection, 1024))
    {
        echo $data;
        if($h_output_file) fwrite($h_output_file,$data);
    }
    socket_close($connection);
}
fclose($h_output_file);
?>