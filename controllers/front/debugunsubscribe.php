<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class Sj4webrelancepanierDebugunsubscribeModuleFrontController extends ModuleFrontController
{
    public $display_header = false;
    public $display_footer = false;

    public function initContent()
    {
        parent::initContent();

        // 1) Sécurité : seulement pour un employé BO connecté
        if (!$this->context->employee || !$this->context->employee->isLoggedBack()) {
            header('HTTP/1.1 403 Forbidden');
            die('Forbidden');
        }
        // Cerise : limiter à certains profils
        $allowedProfiles = [1]; // SuperAdmin
        if (!in_array((int)$this->context->employee->id_profile, $allowedProfiles, true)) {
            header('HTTP/1.1 403 Forbidden');
            die('Forbidden');
        }
        // Petit plus SEO
        header('X-Robots-Tag: noindex');
        header('Content-Type: text/plain; charset=utf-8');

        $limit = max(1, (int)Tools::getValue('n', 10));

        // 2) Récup emails aléatoires depuis la table "sent"
        $rows = Db::getInstance()->executeS('
            SELECT DISTINCT LOWER(s.email) AS email
            FROM `' . _DB_PREFIX_ . 'sj4web_relancepanier_sent` s
            WHERE s.email IS NOT NULL AND s.email <> ""
            ORDER BY RAND()
            LIMIT ' . (int)$limit
        );

        if (!$rows) {
            echo "No emails found.\n";
            return;
        }

        foreach ($rows as $r) {
            $email = trim(Tools::strtolower($r['email']));
            if (!Validate::isEmail($email)) {
                continue;
            }

            // 3.1) Essai via API PrestaShop (inclure guests)
            $customer = new Customer();
            $customer->getByEmail($email, null, false); // ignoreGuest = false

            if (!Validate::isLoadedObject($customer) || empty($customer->id)) {
                // 3.2) Fallback SQL (tu as MariaDB => pas de getRow(), utilise executeS())
                $rowsC = Db::getInstance()->executeS('
                    SELECT id_customer FROM `' . _DB_PREFIX_ . 'customer`
                    WHERE LOWER(email) = "' . pSQL($email) . '"
                    ORDER BY id_customer DESC
                    LIMIT 1
                ');

                if (!$rowsC || empty($rowsC[0]['id_customer'])) {
                    echo $email . "\t(no customer)\n";
                    continue;
                }
                $customer = new Customer((int)$rowsC[0]['id_customer']);
                if (!Validate::isLoadedObject($customer)) {
                    echo $email . "\t(invalid customer)\n";
                    continue;
                }
            }

            // 4) Lien via TA méthode existante
            $link = Sj4webRelancepanierSender::getUnsubscribeLink($customer);
            echo $email . "\t" . $link . "\n";
        }
    }
}
