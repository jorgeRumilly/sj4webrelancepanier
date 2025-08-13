<?php

class Sj4webRelancepanierMailHelper
{

    /** Bloc HTML produits (table email-safe) + version texte */
    public static function buildCartProducts(Cart $cart, Context $context, $local = null): array
    {
        $products = $cart->getProducts(); // résout déclinaisons, qty, prix TTC
        $currency = new Currency((int)$cart->id_currency);


        // HTML
        $tr = $context->getTranslator();
        $html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;border:1px solid #e5e7eb;border-radius:6px;overflow:hidden;">'
            . '<tr style="background:#f9fafb;">'
            . '<th align="left" style="padding:8px 10px;font-size:13px;">'. $tr->trans('Product', [], 'Modules.Sj4webrelancepanier.Emails', $local). '</th>'
            . '<th align="center" style="padding:8px 10px;font-size:13px;">'. $tr->trans('Qty', [], 'Modules.Sj4webrelancepanier.Emails', $local). '</th>'
            . '<th align="right" style="padding:8px 10px;font-size:13px;">'. $tr->trans('Price', [], 'Modules.Sj4webrelancepanier.Emails', $local). '</th>'
            . '</tr>';

        $lines = [];
        foreach ($products as $p) {
            $name = trim($p['name']);
            $qty  = (int)$p['cart_quantity'];
            $price = Tools::displayPrice((float)$p['price_wt'], $currency);
            $productUrl = $context->link->getProductLink((int)$p['id_product'], null, null, null, (int)$cart->id_lang, (int)$cart->id_shop, (int)$p['id_product_attribute'], false);

            $html .= '<tr>'
                . '<td style="padding:8px 10px;border-top:1px solid #e5e7eb;"><a href="'.$productUrl.'" style="color:#111827;text-decoration:none;">'.Tools::safeOutput($name).'</a></td>'
                . '<td align="center" style="padding:8px 10px;border-top:1px solid #e5e7eb;">'.$qty.'</td>'
                . '<td align="right" style="padding:8px 10px;border-top:1px solid #e5e7eb;white-space:nowrap;">'.$price.'</td>'
                . '</tr>';

            $lines[] = '- '.$qty.' x '.$name.' — '.$price;
        }
        $html .= '</table>';

        $text = implode("\n", $lines);

        return ['html' => $html, 'text' => $text];
    }

    /** Bloc promo : renvoie '' si pas de code, sinon un paragraphe prêt à injecter */
    public static function buildDiscountBlock(?string $code, $local=null): string
    {
        $code = trim((string) $code);
        if ($code === '') {
            return '';
        }

        $translator = Context::getContext()->getTranslator();

        // On laisse la traduction gérer le libellé, et on met le code en <strong>
        $label = $translator->trans(
            'Discount code: %code%',
            ['%code%' => '<strong>'.Tools::safeOutput($code).'</strong>'],
            'Modules.Sj4webrelancepanier.Emails',
            $local
        );

        return '<p style="margin:0;">'.$label.'</p>';
    }

}
