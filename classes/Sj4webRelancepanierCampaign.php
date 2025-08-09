<?php

class Sj4webRelancepanierCampaign extends ObjectModel
{
    public $id_campaign;
    public $name;
    public $status; // draft, active, archived

    public $start_time;
    public $start_unit;

    public $delay_time1;
    public $delay_unit1;
    public $discount_time1;
    public $percent_time1;

    public $delay_time2;
    public $delay_unit2;
    public $discount_time2;
    public $percent_time2;

    public $delay_time3;
    public $delay_unit3;
    public $discount_time3;
    public $percent_time3;

    public $date_add;
    public $date_upd;

    public static $definition = [
        'table' => 'sj4web_relancepanier_campaign',
        'primary' => 'id_campaign',
        'fields' => [
            'name' => ['type' => self::TYPE_STRING, 'required' => true, 'validate' => 'isGenericName', 'size' => 128],
            'status' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 16],

            'start_time' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'start_unit' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 16],

            'delay_time1' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'delay_unit1' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 16],
            'discount_time1' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
            'percent_time1' => ['type' => self::TYPE_FLOAT, 'validate' => 'isFloat'],

            'delay_time2' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'delay_unit2' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 16],
            'discount_time2' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
            'percent_time2' => ['type' => self::TYPE_FLOAT, 'validate' => 'isFloat'],

            'delay_time3' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'delay_unit3' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 16],
            'discount_time3' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
            'percent_time3' => ['type' => self::TYPE_FLOAT, 'validate' => 'isFloat'],

            'date_add' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
            'date_upd' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
        ],
    ];

    /**
     * Check if this campaign has generated any emails.
     * @return bool
     */
    public function hasGeneratedMails()
    {
        $sql = 'SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'sj4web_relancepanier_sent WHERE id_campaign = ' . (int)$this->id_campaign;
        return (bool)Db::getInstance()->getValue($sql);
    }

    /**
     * Get the most recent active campaign.
     * @return self|null
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function getActiveCampaign()
    {
        $sql = 'SELECT `id_campaign` FROM `' . _DB_PREFIX_ . 'sj4web_relancepanier_campaign` WHERE `status` = "active" ORDER BY `id_campaign` DESC LIMIT 1';
        $result = Db::getInstance()->executeS($sql);
        if (!$result || !is_array($result) || count($result) === 0) {
            return null; // No active campaign found
        }
        $id = (int) $result[0]['id_campaign'];
        return $id ? new self($id) : null;
    }
}
