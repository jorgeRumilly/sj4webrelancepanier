<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/classes/Sj4webRelancePanierInstaller.php';
require_once dirname(__FILE__) . '/classes/Sj4webRelancepanierSent.php';

class Sj4webRelancepanier extends Module
{
    const CONF_KEY_CURR = 'SJ4WEB_RP_ENC_KEY';
    const CONF_KEY_PREV = 'SJ4WEB_RP_ENC_KEY_PREV';


    public function __construct()
    {
        $this->name = 'sj4webrelancepanier';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'SJ4WEB.FR';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('SJ4WEB - Cart Reminder', [], 'Modules.Sj4webrelancepanier.Admin');
        $this->description = $this->trans('Automatically reminds customers about abandoned carts with optional discount codes and conversion tracking.', [], 'Modules.Sj4webrelancepanier.Admin');
        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => _PS_VERSION_];
    }

    public function install()
    {
        return parent::install()
            && Sj4webRelancePanierInstaller::installDb()
            && $this->registerHook('displayBackOfficeHeader')
            && $this->registerHook('actionValidateOrder')
            && $this->installTab()
            && $this->installMailsSentsTab()
            && Configuration::updateValue(self::CONF_KEY_CURR, $this->generateKey())
            && Configuration::updateValue(self::CONF_KEY_PREV, '');

    }

    public function uninstall()
    {
        return parent::uninstall()
            && Sj4webRelancePanierInstaller::uninstallDb()
            && $this->uninstallTab();
    }

    protected function installTab()
    {
        $parentTabId = Tab::getIdFromClassName('AdminSj4webRelancepanierParent');
        if($parentTabId){
            return true; // La tab parent existe déjà
        }

        $parentTab = new Tab();
        $parentTab->class_name = 'AdminSj4webRelancepanierParent';
        $parentTab->module = $this->name;
        $parentTab->id_parent = (int) Tab::getIdFromClassName('SELL'); // ou 0 pour racine
        $parentTab->active = 1;
        $parentTab->icon = 'shopping_basket'; // Icône de la tab, peut être modifié
        $parentTab->name = [];
        foreach (Language::getLanguages(true) as $lang) {
            $parentTab->name[$lang['id_lang']] = $this->trans('SJ4WEB - Cart Reminder', [], 'Modules.Sj4webrelancepanier.Admin');
        }
        $parentTab->add();
        if(!$parentTab->id) {
            return false; // Échec de l'installation de la tab parent
        }

        $tab = new Tab();
        $tab->class_name = 'AdminSj4webRelancepanier';
        $tab->id_parent = $parentTab->id;
        $tab->module = $this->name;
        $tab->active = 1;
        $tab->name = [];
        foreach (Language::getLanguages(true) as $lang) {
            $tab->name[$lang['id_lang']] = $this->trans('Campaigns', [], 'Modules.Sj4webrelancepanier.Admin');
        }
        return $tab->add();
    }

    protected function installMailsSentsTab() {
        $parentTabId = Tab::getIdFromClassName('AdminSj4webRelancepanierParent');
        if (!$parentTabId) {
            return false; // La tab parent n'existe pas
        }

        $currentTabId = Tab::getIdFromClassName('AdminSj4webRelancepanierSent');
        if ($currentTabId) {
            return true; // La tab existe déjà
        }

        $tab = new Tab();
        $tab->class_name = 'AdminSj4webRelancepanierSent';
        $tab->id_parent = $parentTabId;
        $tab->module = $this->name;
        $tab->active = 1;
        $tab->name = [];
        foreach (Language::getLanguages(true) as $lang) {
            $tab->name[$lang['id_lang']] = $this->trans('Sent mails', [], 'Modules.Sj4webrelancepanier.Admin');
        }
        return $tab->add();

    }

    protected function uninstallTab()
    {
        $tabClasses = ['AdminSj4webRelancepanier', 'AdminSj4webRelancepanierSent', 'AdminSj4webRelancepanierParent'];

        foreach ($tabClasses as $className) {
            $idTab = (int) Tab::getIdFromClassName($className);
            if ($idTab) {
                $tab = new Tab($idTab);
                $tab->delete();
            }
        }

        return true;
    }

    public function getContent()
    {
        // Actions
        if (Tools::isSubmit('sj4web_rp_rotate_key')) {
            $this->rotateKey();
            $this->confirmations[] = $this->trans('A new encryption key has been generated. Previous key kept for compatibility.', [], 'Modules.Sj4webrelancepanier.Admin');
        }
        if (Tools::isSubmit('sj4web_rp_clear_prev')) {
            $this->clearPrevKey();
            $this->confirmations[] = $this->trans('Previous key cleared.', [], 'Modules.Sj4webrelancepanier.Admin');
        }

        // Lecture
        $curr = (string) Configuration::get(self::CONF_KEY_CURR);
        $prev = (string) Configuration::get(self::CONF_KEY_PREV);

        // Form
        $fields_form = [
            'form' => [
                'legend' => ['title' => $this->trans('Unsubscribe encryption keys', [], 'Modules.Sj4webrelancepanier.Admin')],
                'input'  => [
                    [
                        'type' => 'text',
                        'label' => $this->trans('Current key', [], 'Modules.Sj4webrelancepanier.Admin'),
                        'name' => 'sj4web_rp_key_current',
                        'readonly' => true,
                        'desc' => $this->trans('Used to encrypt new unsubscribe links.', [], 'Modules.Sj4webrelancepanier.Admin'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('Previous key', [], 'Modules.Sj4webrelancepanier.Admin'),
                        'name' => 'sj4web_rp_key_previous',
                        'readonly' => true,
                        'desc' => $this->trans('Kept temporarily to accept old links. You can clear it.', [], 'Modules.Sj4webrelancepanier.Admin'),
                    ],
                ],
                'submit' => ['title' => $this->trans('Back', [], 'Modules.Sj4webrelancepanier.Admin')],
                'buttons' => [
                    [
                        'title' => $this->trans('Generate new key (rotate)', [], 'Modules.Sj4webrelancepanier.Admin'),
                        'class' => 'btn btn-primary',
                        'icon'  => 'process-icon-cogs',
                        'type'  => 'submit',
                        'name'  => 'sj4web_rp_rotate_key',
                    ],
                    [
                        'title' => $this->trans('Clear previous key', [], 'Modules.Sj4webrelancepanier.Admin'),
                        'class' => 'btn btn-default',
                        'icon'  => 'process-icon-eraser',
                        'type'  => 'submit',
                        'name'  => 'sj4web_rp_clear_prev',
                    ],
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->default_form_language = (int) $this->context->language->id;
        $helper->allow_employee_form_lang = (int) $this->context->language->id;
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submit_'.$this->name;
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->fields_value = [
            'sj4web_rp_key_current'  => $curr,
            'sj4web_rp_key_previous' => $prev !== '' ? $prev : $this->trans('(empty)', [], 'Modules.Sj4webrelancepanier.Admin'),
        ];

        return (!empty($this->confirmations)) ?($this->displayConfirmation(implode('<br>', $this->confirmations ?: []))) : ''
            . $helper->generateForm([$fields_form]);
    }

    public function hookDisplayBackOfficeHeader($params){
        if($this->context->controller instanceof AdminSj4webRelancepanierSentController) {
            $this->context->controller->addCSS($this->_path . 'views/css/sj4web_relancepannier_admin.css', 'all');
        }
    }

    public function hookActionValidateOrder($params)
    {
        $order = $params['order'];
        $customerId = (int)$order->id_customer;
        $cartId = (int)$order->id_cart;
        $sql = 'SELECT id_sent FROM '._DB_PREFIX_.'sj4web_relancepanier_sent
            WHERE (id_cart='.(int)$cartId.' OR (email="'.pSQL($order->getCustomer()->email).'" AND sent_at<= "'.pSQL($order->date_add).'"))
            ORDER BY sent_at DESC LIMIT 1';
        $rows = Db::getInstance()->executeS($sql);
        if ($rows) {
            $sent = new Sj4webRelancepanierSent((int)$rows[0]['id_sent']);
            $sent->id_order = (int)$order->id;
            $sent->conversion_date = $order->date_add;
            $sent->update();
        }
    }


    /** Génère une clé aléatoire (AES-256) */
    private function generateKey(): string
    {
        // 32 bytes ~ 256 bits ; base64 réduite OK
        return bin2hex(random_bytes(32));
    }

    /** Rotation : current -> previous, new current */
    private function rotateKey(): void
    {
        $curr = (string) Configuration::get(self::CONF_KEY_CURR);
        if ($curr !== '') {
            Configuration::updateValue(self::CONF_KEY_PREV, $curr);
        }
        Configuration::updateValue(self::CONF_KEY_CURR, $this->generateKey());
    }

    /** Vide la clé précédente (arrête la période de grâce) */
    private function clearPrevKey(): void
    {
        Configuration::updateValue(self::CONF_KEY_PREV, '');
    }
}
