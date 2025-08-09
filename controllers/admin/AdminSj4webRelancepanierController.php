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

require_once dirname(__FILE__) . '/../../classes/Sj4webRelancepanierCampaign.php';

class AdminSj4webRelancepanierController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'sj4web_relancepanier_campaign';
        $this->className = 'Sj4webRelancepanierCampaign';
        $this->lang = false;
        $this->module = Module::getInstanceByName('sj4webrelancepanier');
        $this->identifier = 'id_campaign';
        $this->_defaultOrderBy = 'id_campaign';
        $this->_defaultOrderWay = 'DESC';
        parent::__construct();

        $this->fields_list = [
            'id_campaign' => ['title' => 'ID', 'class' => 'fixed-width-xs'],
            'name' => ['title' => $this->trans('Name', [], 'Modules.Sj4webrelancepanier.Admin')],
            'status' => [
                'title' => $this->trans('Status', [], 'Modules.Sj4webrelancepanier.Admin'),
                'type' => 'select',
                'list' => ['draft' => 'Draft', 'active' => 'Active', 'archived' => 'Archived'],
                'filter_key' => 'a!status',
            ],
            'delay_time1' => ['title' => $this->trans('T1 (h)', [], 'Modules.Sj4webrelancepanier.Admin'), 'search' => false],
            'percent_time1' => ['title' => $this->trans('BR T1 (%)', [], 'Modules.Sj4webrelancepanier.Admin'), 'search' => false],
            'delay_time2' => ['title' => $this->trans('T2 (h)', [], 'Modules.Sj4webrelancepanier.Admin'), 'search' => false],
            'percent_time2' => ['title' => $this->trans('BR T2 (%)', [], 'Modules.Sj4webrelancepanier.Admin'), 'search' => false],
            'delay_time3' => ['title' => $this->trans('T3 (h)', [], 'Modules.Sj4webrelancepanier.Admin'), 'search' => false],
            'percent_time3' => ['title' => $this->trans('BR T3 (%)', [], 'Modules.Sj4webrelancepanier.Admin'), 'search' => false],
        ];
        $this->addRowAction('edit');
        $this->addRowAction('delete');
        $this->addRowAction('markAsActive');
    }

    public function renderForm()
    {
        $isLocked = false;
        if ($id = Tools::getValue('id_campaign')) {
            $campaign = new Sj4webRelancepanierCampaign($id);
            $isLocked = $campaign->status === 'active' && $campaign->hasGeneratedMails();
        }

        $timeUnitOptions = [
            ['id_option' => 'minute', 'name' => $this->trans('Minutes', [], 'Modules.Sj4webrelancepanier.Admin')],
            ['id_option' => 'hour', 'name' => $this->trans('Hours', [], 'Modules.Sj4webrelancepanier.Admin')],
            ['id_option' => 'day', 'name' => $this->trans('Days', [], 'Modules.Sj4webrelancepanier.Admin')],
            ['id_option' => 'month', 'name' => $this->trans('Months', [], 'Modules.Sj4webrelancepanier.Admin')],
        ];

        $this->fields_form = [
            'legend' => [
                'title' => $this->trans('Cart reminder campaign', [], 'Modules.Sj4webrelancepanier.Admin'),
            ],
            'input' => [
                [
                    'type' => 'text',
                    'label' => $this->trans('Name', [], 'Modules.Sj4webrelancepanier.Admin'),
                    'name' => 'name',
                    'required' => true,
                    'disabled' => $isLocked,
                ],
                [
                    'type' => 'select',
                    'label' => $this->trans('Campaign status', [], 'Modules.Sj4webrelancepanier.Admin'),
                    'name' => 'status',
                    'required' => true,
                    'options' => [
                        'query' => [
                            ['id_option' => 'draft', 'name' => $this->trans('Draft', [], 'Modules.Sj4webrelancepanier.Admin')],
                            ['id_option' => 'active', 'name' => $this->trans('Active', [], 'Modules.Sj4webrelancepanier.Admin')],
                            ['id_option' => 'archived', 'name' => $this->trans('Archived', [], 'Modules.Sj4webrelancepanier.Admin')],
                        ],
                        'id' => 'id_option',
                        'name' => 'name',
                    ],
                    'disabled' => $isLocked,
                ],
                [
                    'type' => 'text',
                    'label' => $this->trans('Start delay', [], 'Modules.Sj4webrelancepanier.Admin'),
                    'name' => 'start_time',
                    'required' => true,
                    'disabled' => $isLocked,
                    'desc' => $this->trans(
                        'Minimum delay after cart creation before considering it as abandoned.',
                        [],
                        'Modules.Sj4webrelancepanier.Admin'
                    )
                ],
                [
                    'type' => 'select',
                    'label' => $this->trans('Start delay unit', [], 'Modules.Sj4webrelancepanier.Admin'),
                    'name' => 'start_unit',
                    'required' => true,
                    'options' => [
                        'query' => $timeUnitOptions,
                        'id' => 'id_option',
                        'name' => 'name',
                    ],
                    'disabled' => $isLocked,
                ]
            ],
        ];

        foreach ([1, 2, 3] as $i) {
            $this->fields_form['input'][] = [
                'type' => 'text',
                'label' => $this->trans("Delay T$i", [], 'Modules.Sj4webrelancepanier.Admin'),
                'name' => "delay_time$i",
                'required' => false,
                'disabled' => $isLocked,
            ];
            $this->fields_form['input'][] = [
                'type' => 'select',
                'label' => $this->trans("Delay T$i unit", [], 'Modules.Sj4webrelancepanier.Admin'),
                'name' => "delay_unit$i",
                'required' => true,
                'options' => [
                    'query' => $timeUnitOptions,
                    'id' => 'id_option',
                    'name' => 'name',
                ],
                'disabled' => $isLocked,
            ];
            $this->fields_form['input'][] = [
                'type' => 'switch',
                'label' => $this->trans("Enable BR for T$i", [], 'Modules.Sj4webrelancepanier.Admin'),
                'name' => "discount_time$i",
                'is_bool' => true,
                'values' => [
                    ['id' => "discount_time{$i}_on", 'value' => 1, 'label' => $this->trans('Yes', [], 'Admin.Global')],
                    ['id' => "discount_time{$i}_off", 'value' => 0, 'label' => $this->trans('No', [], 'Admin.Global')],
                ],
                'disabled' => $isLocked,
            ];
            $this->fields_form['input'][] = [
                'type' => 'text',
                'label' => $this->trans("BR T$i (%)", [], 'Modules.Sj4webrelancepanier.Admin'),
                'name' => "percent_time$i",
                'required' => false,
                'disabled' => $isLocked,
            ];
        }

        $this->fields_form['submit'] = [
            'title' => $this->trans('Save', [], 'Admin.Actions'),
        ];

        return parent::renderForm();
    }

    public function displayMarkAsActiveLink($token = null, $id)
    {
        $token = $token ?: $this->token;
        $href = self::$currentIndex . '&activate' . $this->table . '&' . $this->identifier . '=' . $id . '&token=' . $token;
        return '<a href="' . $href . '" class="activate"><i class="icon-check"></i> ' . $this->trans('Mark as active', [], 'Modules.Sj4webrelancepanier.Admin') . '</a>';
    }

    public function postProcess()
    {
        parent::postProcess();

        if (Tools::isSubmit('submitAdd' . $this->table) || Tools::isSubmit('submitAdd' . $this->className)) {
            $id = (int)Tools::getValue('id_campaign');
            $status = Tools::getValue('status');
            $campaign = new Sj4webRelancepanierCampaign($id);
            if ($campaign->hasGeneratedMails() && $status == 'draft') {
                $this->errors[] = $this->trans(
                    'This campaign has already sent emails. You cannot set it to draft.',
                    [],
                    'Modules.Sj4webrelancepanier.Admin'
                );
                return false;
            }
            if ($status == 'active') {
                $this->setToArchived($id);
            }
        } elseif (Tools::isSubmit('activate' . $this->table)) {
            $id = (int)Tools::getValue($this->identifier);
            $campaign = new Sj4webRelancepanierCampaign($id);
            if ($campaign->status !== 'draft') {
                $this->errors[] = $this->trans(
                    'Campaign must be in draft status to be marked as active.',
                    [],
                    'Modules.Sj4webrelancepanier.Admin'
                );
                return false;
            } else {
                $res = Db::getInstance()->execute('UPDATE ' . _DB_PREFIX_ . 'sj4web_relancepanier_campaign SET status = "active" WHERE id_campaign = ' . (int)$id);
                if ($res) {
                    $this->setToArchived($id);
                }
            }
        }
    }

    protected function setToArchived($id)
    {
        return Db::getInstance()->execute('UPDATE ' . _DB_PREFIX_ . 'sj4web_relancepanier_campaign SET status = "archived" WHERE status = "active" AND id_campaign != ' . (int)$id);
    }
}
