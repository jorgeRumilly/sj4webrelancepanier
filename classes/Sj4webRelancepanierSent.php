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

class Sj4webRelancepanierSent extends ObjectModel
{
    /** @var int */
    public $id_sent;

    /** @var int */
    public $id_cart;

    /** @var int|null */
    public $id_customer;

    /** @var int */
    public $id_campaign;

    /** @var int */
    public $step;

    /** @var string */
    public $email;

    /** @var string|null */
    public $voucher_code;

    /** @var string */
    public $sent_at;

    /** @var int|null */
    public $id_order;

    /** @var string|null */
    public $conversion_date;

    public static $definition = [
        'table' => 'sj4web_relancepanier_sent',
        'primary' => 'id_sent',
        'fields' => [
            'id_cart' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'id_customer' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],
            'id_campaign' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'step' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'required' => true],
            'email' => ['type' => self::TYPE_STRING, 'validate' => 'isEmail', 'required' => true, 'size' => 255],
            'voucher_code' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 64],
            'sent_at' => ['type' => self::TYPE_DATE, 'validate' => 'isDate', 'required' => true],
            'id_order' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],
            'conversion_date' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
        ],
    ];

    /**
     * Retourne le nom de l'étape (T1 / T2 / T3) pour l'affichage.
     */
    public function getStepLabel()
    {
        return 'T' . (int)$this->step;
    }

    /**
     * Retourne true si cette relance a été convertie.
     */
    public function isConverted()
    {
        return !empty($this->id_order) && !empty($this->conversion_date);
    }
}
