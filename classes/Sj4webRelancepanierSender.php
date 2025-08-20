<?php
/**
 * SJ4WEB - Abandoned Cart Reminder (sj4webrelancepanier)
 *
 * Automatically reminds customers about abandoned carts with optional
 * discount codes and conversion tracking.
 *
 * Copyright (C) 2025  SJ4WEB.FR
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

require_once dirname(__FILE__) . '/Sj4webRelancepanierCrypto.php';
require_once dirname(__FILE__) . '/Sj4webRelancepanierMailHelper.php';
class Sj4webRelancepanierSender
{
    public static function sendRelanceEmails(Sj4webRelancepanierCampaign $campaign, int $step, array $cart_ids): int
    {
        $nb_sent = 0;
        // ---- TEST MODE (à retirer après tests)
        $TEST_MODE = true;
//        $TEST_RECIPIENTS = [
//            'jorge.sj4web@gmail.com',
//            'jmartinscifea@gmail.com',
//            'jorge.martins@sj4web.fr',
//        ];
        $TEST_RECIPIENTS = [];
        $TEST_MAX = 5;
        $sentCount = 0;
        // -------------------------------------

        foreach ($cart_ids as $id_cart) {

            // stop si on a atteint la limite de tests
            if ($TEST_MODE && $sentCount >= $TEST_MAX) {
                break; // on sort de la boucle principale d'envoi
            }


            $ctx  = Context::getContext();
            $cart = new Cart($id_cart);
            $customer = new Customer($cart->id_customer);
            $idLang = $cart->id_lang;
            if($idLang){
                $local_lang = Language::getLocaleById($idLang);
            } else {
                $local_lang = $ctx->language->locale;
            }

            if (!Validate::isLoadedObject($customer) || !Validate::isLoadedObject($cart)) {
                continue;
            }

            // Génère le lien de désinscription
            $unsubscribe_link = self::getUnsubscribeLink($customer);

            $cart_recover_link = $ctx->link->getPageLink(
                'order',
                null,
                (int)$cart->getAssociatedLanguage()->getId(),
                http_build_query([
                    'step' => 1,
                    'recover_cart' => $cart->id,
                    'token_cart' => md5(_COOKIE_KEY_ . 'recover_cart_' . $cart->id),
                ])
            );

            // Produits (HTML+TXT)
            $prods = Sj4webRelancepanierMailHelper::buildCartProducts($cart, $ctx, $local_lang);

            // genérer le code de réduction (s'il y a lieu)
            $discount_code = self::generateDiscountCode($campaign, $customer, $step);
            $discount_block = Sj4webRelancepanierMailHelper::buildDiscountBlock($discount_code ?: '', $local_lang);

            $template_vars = [
                '{firstname}' => $customer->firstname,
                '{lastname}' => $customer->lastname,
                '{cart_link}' => $cart_recover_link,
                '{unsubscribe_link}' => $unsubscribe_link,

                '{cart_products_html}'  => $prods['html'],
                '{cart_products_text}'  => $prods['text'],

                // Affichage conditionnel : la ligne "code promo" n'apparaît que si block non vide
                '{discount_block}'      => $discount_block,
            ];

            if ($TEST_MODE) {
                // désactive l'opt-out réel pendant les tests
                if (!empty($template_vars['{unsubscribe_link}'])) {
                    $template_vars['{unsubscribe_link}'] .= (strpos($template_vars['{unsubscribe_link}'], '?') !== false ? '&' : '?') . 'error=1';
                }
                // route l’e-mail vers l’un des 3 destinataires
                $toEmail = (count($TEST_RECIPIENTS) &&  isset($TEST_RECIPIENTS[$sentCount % count($TEST_RECIPIENTS)]) ? $TEST_RECIPIENTS[$sentCount % count($TEST_RECIPIENTS)] : $customer->email);
                $toName = 'SJ4WEB Test #' . ($sentCount + 1);
                $subject = '[TEST] ' . $ctx->getTranslator()->trans(
                        'Cart reminder – Step %step%',
                        ['%step%' => $step],
                        'Emails.Subject',
                        $local_lang
                    );
            } else {
                $toEmail = $customer->email;
                $toName = $customer->firstname . ' ' . $customer->lastname;
                $subject = $ctx->getTranslator()->trans(
                    'Cart reminder – Step %step%',
                    ['%step%' => $step],
                    'Emails.Subject',
                    $local_lang
                );
            }
            $mail_sent = Mail::Send(
                (int)$cart->id_lang,
                'relance_step' . (int)$step,  // relance_step1/2/3
                $subject,
                $template_vars,
                $toEmail,
                $toName,
                null, null, null, null,
                _PS_MODULE_DIR_ . 'sj4webrelancepanier/mails/',
                false,
                (int)$cart->id_shop
            );

            if ($TEST_MODE) {
                $sentCount++;
            }

            if ($mail_sent) {
                // Log en base
                $sent = new Sj4webRelancepanierSent();
                $sent->id_campaign = (int)$campaign->id;
                $sent->id_cart = (int)$cart->id;
                $sent->id_customer = (int)$customer->id;
                $sent->email = $customer->email;
                $sent->voucher_code = $discount_code ?: '';
                $sent->id_order = 0; // Pas encore converti
                $sent->conversion_date = null; // Pas encore converti
                $sent->sent_at = date('Y-m-d H:i:s');
                $sent->step = (int)$step;
                $sent->date_add = date('Y-m-d H:i:s');
                $sent->add();

                $nb_sent++;
            }
        }

        return $nb_sent;
    }

    /**
     * @param Customer $customer
     * @return string
     */
    public static function getUnsubscribeLink(Customer $customer): string
    {
        $email = Tools::strtolower(trim($customer->email));
        $key = (string)Configuration::get('SJ4WEB_RP_ENC_KEY');

        $token = Sj4webRelancepanierCrypto::encryptEmail($email, $key);

        return Context::getContext()->link->getModuleLink(
            'sj4webrelancepanier',
            'unsubscribe',
            ['u' => $token], // pas d'email en clair
            true
        );
    }


    public static function generateDiscountCode(Sj4webRelancepanierCampaign $campaign, Customer $customer, int $step)
    {
        $percent_field = 'percent_time' . $step;
        $percent = (int)$campaign->$percent_field;

        if ($percent <= 0) {
            return null;
        }

        $code = sprintf('RELANCE-%d-S%d-C%d', $campaign->id, $step, $customer->id);

        // Vérifie s’il existe déjà
        $result = (int)Db::getInstance()->executeS('SELECT id_cart_rule FROM ' . _DB_PREFIX_ . 'cart_rule WHERE code = "' . pSQL($code) . '"');
        if (!empty($result)) {
            return $code;
        }

        $cart_rule = new CartRule();
        $cart_rule->reduction_percent = $percent;
        $cart_rule->code = $code;
        $cart_rule->id_customer = $customer->id;
        $cart_rule->quantity = 1;
        $cart_rule->quantity_per_user = 1;
        $cart_rule->date_from = date('Y-m-d H:i:s');
        $cart_rule->date_to = date('Y-m-d H:i:s', strtotime('+2 days'));
        $cart_rule->highlight = true;
        $cart_rule->active = true;
        $cart_rule->name = [];

        foreach (Language::getLanguages() as $lang) {
            $cart_rule->name[$lang['id_lang']] = Context::getContext()->getTranslator()->trans(
                'Cart reminder – Step %step%',
                ['%step%' => $step],
                'Modules.Sj4webrelancepanier.Admin',
                $lang['locale']
            );

        }

        $cart_rule->add();

        return $code;
    }

}
