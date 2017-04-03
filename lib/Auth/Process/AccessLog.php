<?php

/**
 * Filter to store information sobre los usuarios que han comenzado el proceso de logeo.
 *
 * La información se almacenará en una tabla similar a la siguiente
 *
 * <code>
 * CREATE TABLE `lastaccess` (
 *  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
 *  `username` varchar(256) NOT NULL,
 *  `service` varchar(256) NOT NULL,
 *  `date` bigint(20) NOT NULL,
 *  `ip` varchar(255) NOT NULL,
 *  `browser` varchar(255) NOT NULL DEFAULT '""',
 *  `os` varchar(255) NOT NULL DEFAULT '""'
 *) ENGINE=InnoDB
 * </code>
 *
 * Los cuatro primeros campos son obligatorios, el resto es optativo dependiendo de la
 * información que quremos almacenar. La configuracion del filtro se hace mediante el fann_get_cascade_candidate_change_fraction
 *
 * <code>
 * $config = array (
 *
 * 'sets' => array(
 *		'set1' => array(
 *    	 	'uidfield' => 'eduPersonPrincipalName',
 *    		'servicefield'  => 'entityid',
 *  			'store' => array (
 *				  'class' => 'accesslog:SQLStore',
 *		   	'dsn'		=>	'mysql:host=myhost;dbname=mydatabase',
 *			  'username'	=>	'myuser',
 *				  'password'	=>	'mypassword',
 *		    ),
 *				'mapping' => array('ip' => "ip", 'browser' => 'browser', 'os' =>'os'))
 *			    'table' => 'lastaccess',
 *		    	'removeafter' => 3, // months
 *		),
 *)
 *);
 * </code>
 *
 * En él podemos indicar tantas fuentes de almacenamiento como queramos. Para cada fuente
 * indicaremos el attribute de donde obtendremos  el identificador de usuario. El
 * attributo de donde ontendremos el identificador del servicio. El tipo de almacenamisnto
 * (sólo implementado accesslog:SQLStore) y las credenciales para acceder a el.
 * El mapeos entre campos y la información que almacenaremos en ellos (sólo disponible
 * información de IP, navegador y sistema operativo). También podemos indicar cada cuańto borrar datos.
 *
 * Cuando definamos el filtro debemos indicar qué conjunto de almacenamiento usaremos
 * para almacenar los datos:
 :
 * <code>
 * 'authproc' => array(
 *   50 => array('class' => class' => 'accesslog:AccessLog' , 'set' => 'set1'),
 * ),
 * </code>
 *
 *
 * @author Olav Morken, UNINETT AS.
 * @package simpleSAMLphp
 */
class sspmod_accesslog_Auth_Process_AccessLog extends SimpleSAML_Auth_ProcessingFilter
{
    private $store = null;

    /**
     * The attribute we should generate the targeted id from, or NULL if we should use the
     * UserID.
     */
    private $attribute = null;

    private $uidfield;
    private $servicefield;
    private $table;
    private $mapping;
    /**
     * Initialize this filter.
     *
     * @param array $config  Configuration information about this filter.
     * @param mixed $reserved  For future use.
     */
    public function __construct($config, $reserved)
    {
        parent::__construct($config, $reserved);
        assert('is_array($config)');

        $mconfig = SimpleSAML_Configuration::getConfig('module_accesslog.php');
        $sets = $mconfig->getConfigList('sets', array());

        if (array_key_exists('set', $config)) {
            $set = $sets[$config['set']];
            if (is_null($set)) {
                throw new Exception('Invalid attribute name given to accesslog:AccessLog filter.');
            }
        } else {
            $set = $sets['set1'];
        }

        $this->uidfield     = $set->getString('uidfield', "eduPersonPrincipalName");
        $this->servicefield = $set->getString('servicefield', "entityid");
        $this->table = $set->getString('table', "lastaccess");
        $this->mapping = $set->getArray('mapping', array('ip' => "ip", 'browser' => 'browser', 'os' =>'os'));
        $this->store = $this->getstore($set->getArray('store'));
    }


    /**
     * Apply filter to add the ImmutableID.
     *
     * @param array &$state  The current state.
     */
     public function process(&$state)
     {
         assert('is_array($state)');
         assert('array_key_exists("Attributes", $state)');
         assert('array_key_exists("Destination", $state)');

         if (!array_key_exists($this->uidfield, $state['Attributes'])) {
             throw new Exception('accesslog:AccessLog: Missing attribute \'' . $this->uidfield.
                '\', which is needed to store accesslog info');
         }

         $userID = $state['Attributes'][$this->uidfield][0];


         if (!array_key_exists($this->servicefield, $state['Destination'])) {
             throw new Exception('accesslog:AccessLog: Missing attribute \'' . $this->servicefield.
                '\', which is needed to store accesslog info');
         }

         $service = $state['Destination'][$this->servicefield];

         $date =  date_create();
         $date = date_timestamp_get($date);

         $attributes[$this->servicefield] =  $service;
         $attributes[$this->uidfield]     =  $userID;
         $attributes["date" ]             =  $date;

         foreach ($this->mapping as $key => $value) {
             $attributes[$key] = $this->get_attribute($value);
         }

         $this->store->storeAttributes($attributes);
     }

    /**
     * Get and initialize the configured store
     *
     * @param array $config	 Configuration information about this filter.
     */
    private function getStore($config)
    {
        if (!array_key_exists("class", $config)) {
            throw new Exception('No collector class specified in configuration');
        }
        $attributes[$this->servicefield] =  $service;
        $attributes[$this->uidfield]     =  $userID;
        $storeConfig = $config;
        $storeConfig['mapping'] = array_merge(array("service" => $this->servicefield,"username" => $this->uidfield,"date" =>"date"), $this->mapping);
        $storeConfig['table']   = $this->table;
        $storeClassName = SimpleSAML_Module::resolveClass($storeConfig['class'], 'Store', 'sspmod_accesslog_SimpleStore');
        unset($storeConfig['class']);
        return new $storeClassName($storeConfig);
    }

    private function get_attribute($val)
    {
        return call_user_func(array($this,"get_attribute_$val"));
    }

    private function get_attribute_ip()
    {
        $ipaddress = '';
        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED'])) {
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        } elseif (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_FORWARDED'])) {
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        } else {
            $ipaddress = 'UNKNOWN';
        }

        return $ipaddress;
    }

    private function get_attribute_os()
    {
        $user_agent   =  $_SERVER['HTTP_USER_AGENT'];
        $os_platform  =   "Unknown OS Platform";
        $os_array    =   array(
          '/windows nt 10/i'     =>  'Windows 10',
          '/windows nt 6.3/i'     =>  'Windows 8.1',
          '/windows nt 6.2/i'     =>  'Windows 8',
          '/windows nt 6.1/i'     =>  'Windows 7',
          '/windows nt 6.0/i'     =>  'Windows Vista',
          '/windows nt 5.2/i'     =>  'Windows Server 2003/XP x64',
          '/windows nt 5.1/i'     =>  'Windows XP',
          '/windows xp/i'         =>  'Windows XP',
          '/windows nt 5.0/i'     =>  'Windows 2000',
          '/windows me/i'         =>  'Windows ME',
          '/win98/i'              =>  'Windows 98',
          '/win95/i'              =>  'Windows 95',
          '/win16/i'              =>  'Windows 3.11',
          '/macintosh|mac os x/i' =>  'Mac OS X',
          '/mac_powerpc/i'        =>  'Mac OS 9',
          '/linux/i'              =>  'Linux',
          '/ubuntu/i'             =>  'Ubuntu',
          '/iphone/i'             =>  'iPhone',
          '/ipod/i'               =>  'iPod',
          '/ipad/i'               =>  'iPad',
          '/android/i'            =>  'Android',
          '/blackberry/i'         =>  'BlackBerry',
          '/webos/i'              =>  'Mobile'
    );

        foreach ($os_array as $regex => $value) {
            if (preg_match($regex, $user_agent)) {
                $os_platform    =   $value;
            }
        }

        return $os_platform;
    }

    public function get_attribute_browser()
    {
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        $browser        =   "Unknown Browser";

        $browser_array  =   array(
                            '/msie/i'       =>  'Internet Explorer',
                            '/firefox/i'    =>  'Firefox',
                            '/safari/i'     =>  'Safari',
                            '/chrome/i'     =>  'Chrome',
                            '/edge/i'       =>  'Edge',
                            '/opera/i'      =>  'Opera',
                            '/netscape/i'   =>  'Netscape',
                            '/maxthon/i'    =>  'Maxthon',
                            '/konqueror/i'  =>  'Konqueror',
                            '/mobile/i'     =>  'Handheld Browser'
                        );

        foreach ($browser_array as $regex => $value) {
            if (preg_match($regex, $user_agent)) {
                $browser    =   $value;
            }
        }

        return $browser;
    }
}
