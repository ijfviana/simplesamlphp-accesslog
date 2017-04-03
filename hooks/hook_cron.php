<?php
/**
 * Hook to run a cron job.
 *
 * @param array &$croninfo  Output
 */
function accesslog_hook_cron(&$croninfo) {
	assert('is_array($croninfo)');
	assert('array_key_exists("summary", $croninfo)');
	assert('array_key_exists("tag", $croninfo)');

	SimpleSAML_Logger::info('cron [accesslog]: Running cron in cron tag [' . $croninfo['tag'] . '] ');

	$mconfig = SimpleSAML_Configuration::getConfig('module_accesslog.php');
	$sets = $mconfig->getConfigList('sets', array());

	foreach ($sets AS $setkey => $set) {
			$remove = $set->getInteger('removeafter',3);
			$store  = $set->getArray('store');
			$table  = $set->getString('table');

			try {
				$conn = new PDO($store["dsn"], $store["username"], $store["password"]);

				// set the PDO error mode to exception
    		$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    		// sql to delete a record
    		$sql = "DELETE FROM $table WHERE date < (UNIX_TIMESTAMP() - (2592000 * $remove))";
    		$del = $conn->prepare($sql);
				$del->execute();
				$cuenta = $del->rowCount();
				$croninfo['summary'][] = "$cuenta rows deteled";
			}
			catch(PDOException $e)
    	{
				$croninfo['summary'][] = 'Error during accesslog: ' . $e->getMessage();
				SimpleSAML_Logger::error('cron [accesslog]: Running cron in cron tag [' . $croninfo['tag'] . '] ' . $e->getMessage());
    	}
			//DELETE FROM myTable WHERE dateEntered < DATE_SUB(NOW(), INTERVAL 3 MONTH);
	}
}
