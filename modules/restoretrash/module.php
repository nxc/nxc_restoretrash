<?php

$Module = array( "name" => "nxcRestoretrash",
                 "variable_parameters" => true );

$ViewList = array();

$ViewList["trash"] = array(
    "functions" => array ( 'restore' ),
    "script" => "trash.php",
    "default_navigation_part" => 'ezsetupnavigationpart',
    "params" => array() );
$ViewList['restore'] = array(
    'functions' => array( 'restore' ),
    'default_navigation_part' => 'ezsetupnavigationpart',
    'ui_context' => 'administration',
    'script' => 'restore.php',
    'single_post_actions' => array( 'ConfirmButton' => 'Confirm',
                                    'CancelButton' => 'Cancel',
                                    'AddLocationAction' => 'AddLocation' ),
    'post_action_parameters' => array( 'Confirm' => array( 'RestoreType' => 'RestoreType' ) ),
    'params' => array( 'ObjectID' ) );

$FunctionList = array();
$FunctionList['restore'] = array();

?>
