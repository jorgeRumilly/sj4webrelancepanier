<?php


require_once _PS_MODULE_DIR_ . 'sj4webrelancepanier/classes/Sj4webRelancepanierSent.php';
require_once _PS_MODULE_DIR_ . 'sj4webrelancepanier/classes/Sj4webRelancepanierCartFinder.php';
require_once _PS_MODULE_DIR_ . 'sj4webrelancepanier/classes/Sj4webRelancepanierCampaign.php';
require_once _PS_MODULE_DIR_ . 'sj4webrelancepanier/classes/Sj4webRelancepanierSender.php';

class AdminSj4webRelancepanierSentController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'sj4web_relancepanier_sent';
        $this->className = 'Sj4webRelancepanierSent';
        $this->lang = false;
        $this->module = Module::getInstanceByName('sj4webrelancepanier');
        $this->identifier = 'id_sent';

        parent::__construct();

        $this->_select .= ' c.name AS campaign_name ';
        $this->_join .= ' LEFT JOIN ' . _DB_PREFIX_ . 'sj4web_relancepanier_campaign c ON (c.id_campaign = a.id_campaign) ';

        $this->_filter = '';
        $this->_defaultOrderBy = 'sent_at';
        $this->_defaultOrderWay = 'DESC';

        $this->fields_list = [
            'id_sent' => ['title' => 'ID', 'class' => 'fixed-width-xs'],
            'campaign_name' => ['title' => $this->trans('Campaign', [], 'Modules.Sj4webrelancepanier.Admin')],
            'step' => ['title' => $this->trans('Step', [], 'Modules.Sj4webrelancepanier.Admin')],
            'email' => ['title' => $this->trans('Email', [], 'Modules.Sj4webrelancepanier.Admin')],
            'voucher_code' => ['title' => $this->trans('Voucher', [], 'Modules.Sj4webrelancepanier.Admin')],
            'sent_at' => ['title' => $this->trans('Sent on', [], 'Modules.Sj4webrelancepanier.Admin'), 'type' => 'datetime'],
            'id_order' => ['title' => $this->trans('Order ID', [], 'Modules.Sj4webrelancepanier.Admin')],
            'conversion_date' => ['title' => $this->trans('Converted on', [], 'Modules.Sj4webrelancepanier.Admin'), 'type' => 'datetime'],
        ];

        $this->fields_list['step']['type'] = 'select';
        $this->fields_list['step']['list'] = [
            1 => 'T1',
            2 => 'T2',
            3 => 'T3',
        ];
        $this->fields_list['step']['filter_key'] = 'a!step';

        $this->fields_list['id_campaign'] = [
            'title' => $this->trans('Campaign ID', [], 'Modules.Sj4webrelancepanier.Admin'),
            'type' => 'int',
            'filter_key' => 'a!id_campaign',
        ];

        $this->fields_list['conversion_date']['callback'] = 'displayConvertedStatus';


        $this->bulk_actions = false;
        $this->list_no_link = true;
    }

    public function renderList()
    {
        $this->addRowAction('view');

        $html = '';

        // Résumé des relances à envoyer
        $html .= $this->renderPendingRelanceStats();

        return $html . parent::renderList();
    }

    public function initToolbar()
    {
        parent::initToolbar();
        // Désactive le bouton "Ajouter"
        unset($this->toolbar_btn['new']);
        unset($this->toolbar_btn['add']);
        $this->toolbar_btn['force_send_step1'] = [
            'href' => $this->context->link->getAdminLink('AdminSj4webRelancepanierSent') . '&force_send_step1=1',
            'desc' => $this->trans('Force T1 sending', [], 'Modules.Sj4webrelancepanier.Admin'),
            'icon' => 'process-icon-mail',
        ];

        $this->toolbar_btn['force_send_step2'] = [
            'href' => $this->context->link->getAdminLink('AdminSj4webRelancepanierSent') . '&force_send_step2=1',
            'desc' => $this->trans('Force T2 sending', [], 'Modules.Sj4webrelancepanier.Admin'),
            'icon' => 'process-icon-mail',
        ];

        $this->toolbar_btn['force_send_step3'] = [
            'href' => $this->context->link->getAdminLink('AdminSj4webRelancepanierSent') . '&force_send_step3=1',
            'desc' => $this->trans('Force T3 sending', [], 'Modules.Sj4webrelancepanier.Admin'),
            'icon' => 'process-icon-mail',
        ];

    }

    public function postProcess()
    {
        foreach ([1, 2, 3] as $step) {
            if (Tools::isSubmit("force_send_step{$step}")) {
                $this->processForceSendStep($step);
            }
        }

        parent::postProcess();
    }

    protected function processForceSendStep(int $step)
    {
        if ($step === 1) {
            $campaign = Sj4webRelancepanierCampaign::getActiveCampaign();
            if (!$campaign) {
                $this->errors[] = $this->trans('No active campaign found.', [], 'Modules.Sj4webrelancepanier.Admin');
                return;
            }

            $this->sendForCampaign($campaign, $step);
            return;
        }

        // T2 ou T3 → on parcourt toutes les campagnes ayant envoyé la vague précédente
        $prevStep = $step - 1;

        $campaigns = Db::getInstance()->executeS("
        SELECT DISTINCT id_campaign
        FROM " . _DB_PREFIX_ . "sj4web_relancepanier_sent
        WHERE step = $prevStep
    ");

        if (!$campaigns) {
            $this->confirmations[] = $this->trans('No campaigns to process for step %step%.', ['%step%' => $step], 'Modules.Sj4webrelancepanier.Admin');
            return;
        }

        $totalSent = 0;
        foreach ($campaigns as $row) {
            $campaign = new Sj4webRelancepanierCampaign((int)$row['id_campaign']);

            $delayCol = 'delay_time' . $step;
            if ((int)$campaign->$delayCol <= 0) {
                continue;
            }

            $totalSent += $this->sendForCampaign($campaign, $step);
        }

        if ($totalSent > 0) {
            $this->confirmations[] = $this->trans('%count% emails sent for step %step%.', [
                '%count%' => $totalSent,
                '%step%' => $step
            ], 'Modules.Sj4webrelancepanier.Admin');
        } else {
            $this->confirmations[] = $this->trans('No carts to send for step %step%.', [
                '%step%' => $step
            ], 'Modules.Sj4webrelancepanier.Admin');
        }
    }


    /**
     * Sends relance emails for the specified campaign and step.
     *
     * @param Sj4webRelancepanierCampaign $campaign
     * @param int $step
     * @return int Number of emails sent
     */
    protected function sendForCampaign(Sj4webRelancepanierCampaign $campaign, int $step): int
    {
        $carts = Sj4webRelancepanierCartFinder::findCartsToRelance($campaign, $step);

        if (empty($carts)) {
            return 0;
        }

        return Sj4webRelancepanierSender::sendRelanceEmails($campaign, $step, $carts);
    }

    protected function renderPendingRelanceStats(): string
    {
        $tpl = _PS_MODULE_DIR_ . 'sj4webrelancepanier/views/templates/admin/_relance_summary.tpl';
        if (!is_file($tpl)) {
            return '';
        }

        $active = Sj4webRelancepanierCampaign::getActiveCampaign();
        $activeData = null;

        if ($active) {
            $count = Sj4webRelancepanierCartFinder::countCartsToRelance($active)[1];
            $activeData = [
                'id' => (int)$active->id,
                'name' => $active->name,
                'count' => $count,
            ];
        }

        // Campagnes qui ont envoyé un T1
        $rows = Db::getInstance()->executeS("
        SELECT DISTINCT id_campaign
        FROM " . _DB_PREFIX_ . "sj4web_relancepanier_sent
        WHERE step = 1
    ");

        $followups = [];

        foreach ($rows as $row) {
            $campaign = new Sj4webRelancepanierCampaign((int)$row['id_campaign']);
            $counts = Sj4webRelancepanierCartFinder::countCartsToRelance($campaign);

            $t2 = ((int)$campaign->delay_time2 > 0 && $counts[2] > 0) ? $counts[2] : 0;
            $t3 = ((int)$campaign->delay_time3 > 0 && $counts[3] > 0) ? $counts[3] : 0;

            if ($t2 || $t3) {
                $followups[] = [
                    'name' => $campaign->name,
                    't2' => $t2,
                    't3' => $t3,
                ];
            }
        }

        $this->context->smarty->assign([
            'active_campaign' => $activeData,
            'followup_campaigns' => $followups,
        ]);

        return $this->context->smarty->fetch($tpl);
    }


}
