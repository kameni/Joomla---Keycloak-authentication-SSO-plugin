<?php
defined('_JEXEC') or die;

class PlgSystemKeycloak extends JPlugin {

	protected $autoloadLanguage = true;
	protected $oauth_client;
	protected $credentials = array();

	public function __construct(&$subject, $config)
	{
		parent::__construct($subject, $config);
		if(
			!$this->params->get('realm',false)
			||
			!$this->params->get('server-url',false)
			||
			!$this->params->get('client-id',false)
			||
			!$this->params->get('client-secret',false)
			||
			!$this->params->get('redirect',false)
		){
			return false;
		}
	}

public function onAfterRoute(){
if(isset($_GET['token'])) {
$options = array('action'=>'core.login.'.(JFactory::getApplication()->isAdmin()?'site':'admin'));
if($this->login($options)){
JFactory::getApplication()->redirect(JRoute::_('index.php'));
}
}
}

protected function login($options){
	$credentials = array();
	$response = new stdClass();
	$this->onUserAuthenticate($credentials,$options,$response);
	if($response->status == JAuthentication::STATUS_SUCCESS){
		JPluginHelper::importPlugin('user');
		$app = JFactory::getApplication();
		$response->password_clear = JUserHelper::genRandomPassword();
		$options['autoregister'] = true;
		$results = $app->triggerEvent('onUserLogin', array((array) $response, $options));
		$user = JFactory::getUser();
			if ($response->type == 'Cookie'){
				$user->set('cookieLogin', true);
			}
			if (in_array(false, $results, true) == false){
				$options['user'] = $user;
				$options['responseType'] = $response->type;
				JFactory::getApplication()->triggerEvent('onUserAfterLogin', array($options));
			}
		return true;
	}
	return false;
}

public function onUserAuthenticate($credentials, $options, &$response){
jimport('joomla.authentication.authentication');
jimport('joomla.user.authentication');
$response->type = 'JOAuth';

$access_token = $_GET['token'];
$url = ''.$this->params->get('server-url',false).'/auth/realms/'.$this->params->get('realm',false).'/protocol/openid-connect/userinfo';
$curl = curl_init($url);
curl_setopt($curl, CURLOPT_HEADER, true);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_POST, true);
curl_setopt($curl, CURLOPT_HEADER,'Accept: application/json');
$postData = "access_token=$access_token";
curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);
$json_response = curl_exec($curl);
$status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
if ($status != 200) {
$response->status = JAuthentication::STATUS_FAILURE;
return;
} else {
$json_out = strstr($json_response, '{');
$json_out=json_decode($json_out);
foreach ($json_out as $r => $v){if($r=="preferred_username"){$user=$v;}if($r=="email"){$email=$v;}if($r=="name"){$name=$v;}}
curl_close($curl);

if(!JUserHelper::getUserId($email)){
	$db = JFactory::getDbo();
	$query = $db->getQuery(true);
		$query->select($db->qn(array('id', 'username')))
		->from($db->qn('#__users'))
		->where($db->qn('email').'='.$db->q($email));
		$ud = $db->setQuery($query)->loadObject();
		if($ud->id){
			$response->username = $ud->username;
			}else{
				$response->username = $user;
			}
		}else{$response->username = $user;}
	$response->email    = $email;
	$response->fullname = $name;
	$response->status = JAuthentication::STATUS_SUCCESS;
	$response->error_message = '';
}
}

public function onUserAfterLogin($options){
$user = $options['user'];
$access_token = $_GET['token'];
if($access_token !=""){
require_once('/srv/websites/occrp/plugins/system/keycloak/URLSafeBase64.php');
require_once('/srv/websites/occrp/plugins/system/keycloak/jwt.php');

$jwe = JOSE_JWT::decode($access_token);
$i=0;
foreach($jwe as $a => $v){
$i++;
if($i=="2"){
$out = json_decode(json_encode($v), true);
$role = $out['resource_access']['joomla-oauth']['roles']['0'];
$role1 = $out['resource_access']['joomla-oauth']['roles']['1'];
}
}

if($role == "joomla-manager"){JUserHelper::addUserToGroup($user->id, '6');}elseif($role1 == "joomla-manager"){JUserHelper::addUserToGroup($user->id, '6');}else{if(in_array('6', $user->getAuthorisedGroups())){JUserHelper::removeUserFromGroup($user->id, '6');}}
if($role == "joomla-administrator"){JUserHelper::addUserToGroup($user->id, '7');}elseif($role1 == "joomla-administrator"){JUserHelper::addUserToGroup($user->id, '7');}else{if(in_array('7', $user->getAuthorisedGroups())){JUserHelper::removeUserFromGroup($user->id, '7');}}

$user->save(true);
}

}

public function onBeforeRender(){
$user = JFactory::getUser();
$app  = JFactory::getApplication();
if($user->id != 0){
}else{
$app = JFactory::getApplication();
$appinput = JFactory::getApplication()->input;
$appcomponent = $appinput->get('option');
$appview = $appinput->get('view');
	if($app->isAdmin()){
	$doc = JFactory::getApplication()->getDocument();
	$doc->addScript(''.$this->params->get('server-url',false).'/auth/js/keycloak.js');
	$doc->addScript('/plugins/system/keycloak/keycloak-back.js');
	}
	if($app->isSite() && $appcomponent=="com_users" && $appview=="login"){
	$doc = JFactory::getApplication()->getDocument();
	$doc->addScript(''.$this->params->get('server-url',false).'/auth/js/keycloak.js');
	$doc->addScript('/plugins/system/keycloak/keycloak-front.js');
	}
}
}

public function onUserLogout($user, $options){
return true;
}

public function onUserAfterLogout(){
$app = JFactory::getApplication();
$app->redirect(JRoute::_(''.$this->params->get('server-url',false).'/auth/realms/'.$this->params->get('realm',false).'/protocol/openid-connect/logout?redirect_uri='.urlencode($this->params->get('redirect',false)).'', false));
}

}
