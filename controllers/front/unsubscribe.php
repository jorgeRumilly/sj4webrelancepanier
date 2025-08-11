<?php
if (!defined('_PS_VERSION_')) { exit; }

require_once dirname(__FILE__) . '/../../classes/Sj4webRelancepanierCrypto.php';

class Sj4webrelancepanierUnsubscribeModuleFrontController extends ModuleFrontController
{
    public $display_column_left = false;
    public $display_column_right = false;

    public function initContent()
    {
        // Empêche l’indexation par Google & co
        header('X-Robots-Tag: noindex');

        parent::initContent();

        // Mode test pour forcer l'erreur visuelle
        $forceError = (int) Tools::getValue('error', 0) === 1;

        // Token opaque
        $token   = (string) Tools::getValue('u', '');
        $key     = (string) Configuration::get('SJ4WEB_RP_ENC_KEY');
        $prevKey = (string) Configuration::get('SJ4WEB_RP_ENC_KEY_PREV');

        $email = null;
        if (!$forceError && $token !== '') {
            $email = Sj4webRelancepanierCrypto::decryptToken($token, $key, $prevKey);
            if ($email && Validate::isEmail($email)) {
                // Insert idempotent (email unique)
                Db::getInstance()->execute('
                    INSERT IGNORE INTO `'._DB_PREFIX_.'sj4web_relancepanier_unsubscribed`
                    (`email`, `hash`, `unsubscribed_at`)
                    VALUES ("'.pSQL($email).'", "'.pSQL(Sj4webRelancepanierCrypto::emailStaticHash($email)).'", NOW())
                ');
                $this->context->smarty->assign(['email' => $email]);
                $this->setTemplate('module:sj4webrelancepanier/views/templates/front/unsubscribe_ok.tpl');
                return;
            }
        }

        // Rétro-compat éventuelle (si tu as déjà envoyé e/t)
        $e = Tools::strtolower(trim((string) Tools::getValue('e', '')));
        $t = (string) Tools::getValue('t', '');
        if (!$forceError && $e && $t) {
            $expectedHmac = hash_hmac('sha256', $e, $key);
            if (hash_equals($expectedHmac, $t) && Validate::isEmail($e)) {
                Db::getInstance()->execute('
                    INSERT IGNORE INTO `'._DB_PREFIX_.'sj4web_relancepanier_unsubscribed`
                    (`email`, `hash`, `unsubscribed_at`)
                    VALUES ("'.pSQL($e).'", "'.pSQL(Sj4webRelancepanierCrypto::emailStaticHash($e)).'", NOW())
                ');
                $this->context->smarty->assign(['email' => $e]);
                $this->setTemplate('module:sj4webrelancepanier/views/templates/front/unsubscribe_ok.tpl');
                return;
            }
        }

        // Erreur / lien invalide
        $this->setTemplate('module:sj4webrelancepanier/views/templates/front/unsubscribe_error.tpl');
    }
}
