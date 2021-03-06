<?php

/**
 * @package plugins.metadata
 * @subpackage Scheduler.Transform.Debug
 */

// /opt/kaltura/app/batch
chdir(dirname( __FILE__ ) . "/../../../../batch");

require_once("bootstrap.php");

$iniFile = "../configurations/batch.ini";		// should be the full file path

$kdebuger = new KGenericDebuger($iniFile);
$kdebuger->run('KAsyncTransformMetadata');

?>