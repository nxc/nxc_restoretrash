<?php

$Module = array( "name" => "nxcRestoretrash",
                 "variable_parameters" => true );

$ViewList = array();

$ViewList["trash"] = array(
    "functions" => array ( 'restore' ),
    "script" => "trash.php",
    "default_navigation_part" => 'ezsetupnavigationpart',
    "params" => array() );

$FunctionList = array();
$FunctionList['restore'] = array();

?>
