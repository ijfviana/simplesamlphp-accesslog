<?php
/* 
 * Configuration for the Cron module.
 */

$config = array (

	'sets' => array(
		'set1' => array(
			    'class' => 'accesslog:AccessLog',
    			    'uidfield' => 'eduPersonPrincipalName',
    			    'servicefield'  => 'entityid', 
    			    'store' => array (
				'class' => 'accesslog:SQLStore',
			   	'dsn'		=>	'mysql:host=myhost;dbname=mydatabase',
				'username'	=>	'myuser', 
				'password'	=>	'mypassword',
			    ),

			'table' => 'lastaccess',
			'removeafter' => 3, // months
		),
	)
);
?>
