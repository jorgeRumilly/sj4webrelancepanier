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

class Sj4webRelancepanierCartFinder
{
    /**
     * Récupère les paniers à relancer pour une campagne et une étape (1, 2, 3).
     */
    public static function findCartsToRelance(Sj4webRelancepanierCampaign $campaign, int $step): array
    {
        if (!in_array($step, [1, 2, 3])) {
            return [];
        }

        return ($step === 1)
            ? self::findCartsForFirstWave($campaign)
            : self::findCartsForNextWave($campaign, $step);
    }

    /**
     * Retourne un tableau du nombre de paniers éligibles à chaque vague.
     */
    public static function countCartsToRelance(Sj4webRelancepanierCampaign $campaign): array
    {
        return [
            1 => count(self::findCartsToRelance($campaign, 1)),
            2 => count(self::findCartsToRelance($campaign, 2)),
            3 => count(self::findCartsToRelance($campaign, 3)),
        ];
    }

    private static function getTimestampInterval(int $value, string $unit): string
    {
        return '-' . (int)$value . ' ' . Tools::strtolower($unit);
    }

    private static function findCartsForFirstWave(Sj4webRelancepanierCampaign $campaign): array
    {
        $delay = (int)$campaign->delay_time1;
        $unit = $campaign->delay_unit1;
        $startDelay = (int)$campaign->start_time;
        $startUnit = $campaign->start_unit;

        if ($delay <= 0 || $startDelay <= 0) {
            return [];
        }

        $limitDate = date('Y-m-d H:i:s', strtotime(self::getTimestampInterval($delay, $unit)));
        $startDate = date('Y-m-d H:i:s', strtotime(self::getTimestampInterval($startDelay, $startUnit)));

        return self::getEligibleCarts($campaign->id, 1, $startDate, $limitDate);
    }

    private static function sqlIntervalClause(int $value, string $unit): string
    {
        // Map propre -> unité SQL
        switch (Tools::strtolower($unit)) {
            case 'minute':
                $sqlUnit = 'MINUTE';
                break;
            case 'hour':
                $sqlUnit = 'HOUR';
                break;
            case 'day':
                $sqlUnit = 'DAY';
                break;
            case 'month':
                $sqlUnit = 'MONTH';
                break;
            default:
                $sqlUnit = 'HOUR';
        }
        $value = max(0, (int)$value);
        return "INTERVAL {$value} {$sqlUnit}";
    }

    private static function findCartsForNextWave(Sj4webRelancepanierCampaign $campaign, int $step): array
    {
        $delayCol = 'delay_time' . (int)$step;
        $unitCol = 'delay_unit' . (int)$step;

        $delay = (int)$campaign->$delayCol;
        $unit = (string)$campaign->$unitCol;

        if ($delay <= 0) {
            return [];
        }

        $prevStep = (int)$step - 1;
        $interval = self::sqlIntervalClause($delay, $unit);

        // Sélectionne directement les paniers éligibles :
        // - ont reçu la vague précédente (s=prev)
        // - délai écoulé (s.sent_at <= NOW() - interval)
        // - pas déjà relancés à cette vague (s2 null)
        // - pas de commande depuis (ni sur le panier, ni client après sent_at)
        // - client valide, non désinscrit
        $sql = "
        SELECT s.id_cart
        FROM " . _DB_PREFIX_ . "sj4web_relancepanier_sent s
        INNER JOIN " . _DB_PREFIX_ . "cart c
            ON c.id_cart = s.id_cart
        INNER JOIN " . _DB_PREFIX_ . "customer cu
            ON cu.id_customer = c.id_customer
        LEFT JOIN " . _DB_PREFIX_ . "sj4web_relancepanier_unsubscribed u
            ON u.email = cu.email
        LEFT JOIN " . _DB_PREFIX_ . "sj4web_relancepanier_sent s2
            ON s2.id_cart = s.id_cart AND s2.step = " . (int)$step . "
        LEFT JOIN " . _DB_PREFIX_ . "orders o_cart
            ON o_cart.id_cart = s.id_cart
        LEFT JOIN " . _DB_PREFIX_ . "orders o_cust
            ON o_cust.id_customer = cu.id_customer AND o_cust.date_add > s.sent_at
        WHERE s.id_campaign = " . (int)$campaign->id . "
          AND s.step = " . (int)$prevStep . "
          AND s.sent_at <= (NOW() - {$interval})
          AND s2.id_sent IS NULL
          AND u.id_unsubscribed IS NULL
          AND o_cart.id_order IS NULL
          AND o_cust.id_order IS NULL
          AND c.id_customer > 0
        ORDER BY s.sent_at ASC
    ";

        $rows = Db::getInstance()->executeS($sql);
        return array_map('intval', array_column($rows, 'id_cart'));
    }


    private static function getEligibleCarts(int $campaignId, int $step, string $startDate, string $limitDate): array
    {
        $excludedCarts = Db::getInstance()->executeS("
            SELECT id_cart FROM " . _DB_PREFIX_ . "sj4web_relancepanier_sent
            WHERE id_campaign = $campaignId AND step = $step
        ");
        $excludedIds = array_map('intval', array_column($excludedCarts, 'id_cart'));
        $excludedSql = $excludedIds ? 'AND c.id_cart NOT IN (' . implode(',', $excludedIds) . ')' : '';

        $sql = "
            SELECT c.id_cart
            FROM " . _DB_PREFIX_ . "cart c
            LEFT JOIN " . _DB_PREFIX_ . "orders o ON o.id_cart = c.id_cart
            LEFT JOIN " . _DB_PREFIX_ . "customer cu ON cu.id_customer = c.id_customer
            LEFT JOIN " . _DB_PREFIX_ . "sj4web_relancepanier_unsubscribed u ON u.email = cu.email
            WHERE o.id_order IS NULL
              AND c.date_upd < '" . pSQL($startDate) . "'
              AND c.date_upd >= '" . pSQL($limitDate) . "'
              AND c.id_customer > 0
              AND cu.id_customer IS NOT NULL
              AND u.id_unsubscribed IS NULL
              $excludedSql
            ORDER BY c.date_upd ASC
        ";
//              AND c.date_upd BETWEEN '" . pSQL($startDate) . "' AND '" . pSQL($limitDate) . "'

        $rows = Db::getInstance()->executeS($sql);
        return array_map('intval', array_column($rows, 'id_cart'));
    }

    private static function isUnsubscribed(string $email): bool
    {
        $rows = Db::getInstance()->executeS("
            SELECT 1 FROM " . _DB_PREFIX_ . "sj4web_relancepanier_unsubscribed
            WHERE email = '" . pSQL($email) . "'
            LIMIT 1
        ");
        return !empty($rows);
    }
}
