var keycloak = new Keycloak('/plugins/system/keycloak/keycloak-json.php');

keycloak.init({ onLoad: 'login-required', checkLoginIframe: false });
keycloak.onAuthSuccess = function() {
var joomla_admin = keycloak.hasResourceRole('joomla-administrator', 'joomla-oauth');
var joomla_manager = keycloak.hasResourceRole('joomla-manager', 'joomla-oauth');

if(joomla_admin == true || joomla_manager == true) {
window.location = 'index.php?token='+ keycloak.token;
}else{
window.location = '../index.php';
}
}