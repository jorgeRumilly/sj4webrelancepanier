<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/classes/Sj4webRelancePanierInstaller.php';

class Sj4webRelancepanier extends Module
{
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
        $this->installMailsSentsTab();
        if(!$this->isRegisteredInHook('displayBackOfficeHeader')) {
            $this->registerHook('displayBackOfficeHeader');
        }
    }

    public function install()
    {
        return parent::install()
            && Sj4webRelancePanierInstaller::installDb()
            && $this->registerHook('displayBackOfficeHeader')
            && $this->installTab()
            && $this->installMailsSentsTab();

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
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminSj4webRelancepanier'));
    }

    public function hookDisplayBackOfficeHeader($params){
        if($this->context->controller instanceof AdminSj4webRelancepanierSentController) {
            $this->context->controller->addCSS($this->_path . 'views/css/sj4web_relancepannier_admin.css', 'all');
        }
    }

}
