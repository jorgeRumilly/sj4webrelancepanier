<?php

if(!defined('_PS_VERSION_')) {
    exit;
}

class Sj4webRelancepanierUnsubscribeModuleFrontController extends ModuleFrontController
{
    /** Pas de layout complet, on affiche un message simple. */
    public $display_column_left = false;
    public $display_column_right = false;
    public function initContent()
    {
        parent::initContent();

        // --- MODE TEST : force l'écran d'erreur si ?error=1
        $forceError = (int) Tools::getValue('error', 0) === 1;

        $email = Tools::getValue('e');
        $token = Tools::getValue('t');

        if ($forceError || !$this->isValidSignature($email, $token)) {
            $this->setTemplate('module:sj4webrelancepanier/views/templates/front/unsubscribe_error.tpl');
            return;
        }

        // Insertion "ignore" (désinscription globale par email)
        Db::getInstance()->execute('
            INSERT IGNORE INTO `'._DB_PREFIX_.'sj4web_relancepanier_unsubscribed` (`email`, `date_add`)
            VALUES ("'.pSQL($email).'", NOW())
        ');

        $this->context->smarty->assign(['email' => $email]);
        $this->setTemplate('module:sj4webrelancepanier/views/templates/front/unsubscribe_ok.tpl');
    }


    private function isValidSignature($email, $token): bool
    {
        if (!$email || !$token) {
            return false;
        }
        $secret = (string) Configuration::get('SJ4WEB_RP_SECRET') ?: _COOKIE_KEY_;
        $expected = hash_hmac('sha256', $email, $secret);
        return hash_equals($expected, $token);
    }


}
