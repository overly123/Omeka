<?php
// Ladies and Gentlemen, start your timers
define('APP_START', microtime(true));

// include the paths and define a theme path
include '../paths.php';
define('THEME_DIR', ADMIN_DIR.DIRECTORY_SEPARATOR.'themes');

/**
 * Check for a config file which, if not present implies that the
 * app has not been installed.
 */
if (!file_exists(CONFIG_DIR.DIRECTORY_SEPARATOR.'db.ini')) {
	echo 'It looks like you have not properly setup Omeka to run.  <a href="'.$root.dirname(dirname($_SERVER['PHP_SELF'])).DIRECTORY_SEPARATOR.'install/install.php">Click here to install Omeka.</a>';
	exit;
}

require_once 'Doctrine.compiled.php';

require_once 'Zend/Config/Ini.php';
$db = new Zend_Config_Ini(CONFIG_DIR.DIRECTORY_SEPARATOR.'db.ini', 'database');
Zend::register('db_ini', $db);

$dbh = new PDO($db->type.':host='.$db->host.';dbname='.$db->name, $db->username, $db->password);

Doctrine_Manager::connection($dbh);

// sets a final attribute validation setting to true
$manager = Doctrine_Manager::getInstance();
$manager->setAttribute(Doctrine::ATTR_VLD, true);
// not even sure that this does anything... n8
$manager->setAttribute(Doctrine::ATTR_FETCHMODE, Doctrine::FETCH_LAZY);

/**
 * The search listener causes a 1.5x - 2x decrease in speed on my machine -n8
 * Are we still planning on using Zend_Lucene for searching?  If not let's
 * pull these includes out
 */
// tack on the search capabilities
//require_once 'Kea'.DIRECTORY_SEPARATOR.'SearchListener.php';
require_once 'Kea'.DIRECTORY_SEPARATOR.'TimestampListener.php';
$chainListeners = new Doctrine_EventListener_Chain();
$chainListeners->add(new Kea_TimestampListener());
//$chainListeners->add(new Kea_SearchListener());
$manager->setAttribute(Doctrine::ATTR_LISTENER, $chainListeners);


// Use Zend_Config_Ini to store the info for the routes and db ini files
require_once 'Zend.php';

// Register the Doctrine Manager
Zend::register('doctrine', $manager);

Zend::register('routes_ini', new Zend_Config_Ini(CONFIG_DIR.DIRECTORY_SEPARATOR.'routes.ini'));
$config = new Zend_Config_Ini(CONFIG_DIR.DIRECTORY_SEPARATOR.'config.ini', 'site');
Zend::register('config_ini', $config);

// Require the front controller and router
require_once 'Zend/Controller/Front.php';
require_once 'Zend/Controller/RewriteRouter.php';

// Retrieve the ACL from the db, or create a new ACL object
require_once MODEL_DIR.DIRECTORY_SEPARATOR.'Option.php';
$options = $manager->getTable('option');
$results = $options->findByDql('name LIKE "acl"');
$acl = unserialize($results[0]->value);
Zend::register('acl', $acl);

// Initialize some stuff
$front = Kea_Controller_Front::getInstance();
$router = new Zend_Controller_RewriteRouter();
$router->addConfig(Zend::registry('routes_ini'), 'routes');
$front->setRouter($router);

require_once 'Zend/Controller/Request/Http.php';
$request = new Zend_Controller_Request_Http();
$front->setRequest($request);

// Removed 3/9/07 n8
//Zend::register('request', $request);

#############################################
# HERE IS WHERE WE SET THE ADMIN SWITCH
#############################################
$request->setParam('admin', true);
#############################################
# END ADMIN SWITCH
#############################################

require_once 'Zend/Controller/Response/Http.php';
$response = new Zend_Controller_Response_Http();
$front->setResponse($response);

// Removed 3/9/07 n8
//Zend::register('response', $response);

#############################################
# INITIALIZE PLUGINS
#############################################
require_once MODEL_DIR.DIRECTORY_SEPARATOR.'PluginTable.php';
require_once MODEL_DIR.DIRECTORY_SEPARATOR.'Plugin.php';

//Register all of the active plugins
$plugins = $manager->getTable('Plugin')->activeArray($router);
foreach( $plugins as $plugin )
{
	$front->registerPlugin($plugin);
}
$front->throwExceptions((boolean) $config->debug->exceptions);

#############################################
# SET THE CONTROLLER DIRECTORY IN THE FRONT CONTROLLER
#############################################
$front->addControllerDirectory(CONTROLLER_DIR);

#############################################
# CHECKING TO SEE IF THE USER IS LOGGED IN IS HANDLED BY
# THE Kea_Controller_Action::preDispatch() method
#############################################

#############################################
# DISPATCH THE REQUEST, AND DO SOMETHING WITH THE OUTPUT
#############################################
try{
	$front->dispatch();
}catch(Exception $e) {
	include BASE_DIR.DIRECTORY_SEPARATOR.'404.php';
}

if ((boolean) $config->debug->timer) {
	echo microtime(true) - APP_START;
}
// We're done here.
?>