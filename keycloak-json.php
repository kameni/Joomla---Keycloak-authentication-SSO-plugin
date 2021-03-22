<?php
define('_JEXEC', 1 );
define('JPATH_BASE', dirname(dirname(dirname(dirname(__FILE__)))));

require_once ( JPATH_BASE .'/includes/defines.php' );
require_once ( JPATH_BASE .'/includes/framework.php' );

$mainframe = JFactory::getApplication('site');
$mainframe->initialise();

$plugin = JPluginHelper::getPlugin('system', 'keycloak');
$pluginParams = new JRegistry();
$pluginParams->loadString($plugin->params);
$config = JFactory::getConfig();

$realm = $pluginParams->get('realm','');
$server_url = $pluginParams->get('server-url','');
$client_id = $pluginParams->get('client-id','');
$client_secret = $pluginParams->get('client-secret','');
header('Content-type: application/json');
?>
{
  "realm": "<?php echo $realm; ?>",
  "auth-server-url": "<?php echo $server_url; ?>/auth",
  "ssl-required": "none",
  "resource": "<?php echo $client_id; ?>",
  "credentials": {
    "secret": "<?php echo $client_secret; ?>"
  },
  "use-resource-role-mappings": true,
  "policy-enforcer": {}
}
