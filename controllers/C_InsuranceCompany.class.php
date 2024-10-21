<?php

use OpenEMR\Common\Twig\TwigContainer;
use OpenEMR\Services\InsuranceCompanyService;

class C_InsuranceCompany extends Controller
{
    var $template_mod;
    var $icompanies;
    var $InsuranceCompany;

    public function __construct($template_mod = "general")
    {
        parent::__construct();
        $this->icompanies = array();
        $this->template_mod = $template_mod;
        $this->template_dir = __DIR__ . "/templates/insurance_companies/";
        $this->assign("FORM_ACTION", $GLOBALS['webroot'] . "/controller.php?" . attr($_SERVER['QUERY_STRING']));
        $this->assign("CURRENT_ACTION", $GLOBALS['webroot'] . "/controller.php?" . "practice_settings&insurance_company&");
        $this->assign("STYLE", $GLOBALS['style']);
        $this->assign("SUPPORT_ENCOUNTER_CLAIMS", $GLOBALS['support_encounter_claims']);
        $this->assign("SUPPORT_ELIGIBILITY_REQUESTS", $GLOBALS['enable_eligibility_requests']);
        $this->InsuranceCompany = new InsuranceCompany();
    }

    public function default_action()
    {
        return $this->list_action();
    }

    public function edit_action($id = "", $patient_id = "", $p_obj = null)
    {
        if ($p_obj != null && get_class($p_obj) == "insurancecompany") {
            $this->icompanies[0] = $p_obj;
        } elseif (empty($this->icompanies[0]) || $this->icompanies[0] == null || get_class($this->icompanies[0]) != "insurancecompany") {
            $this->icompanies[0] = new InsuranceCompany($id);
        }

        $x = new X12Partner();
        $this->assign("x12_partners", $x->_utility_array($x->x12_partner_factory()));

        $this->assign("insurancecompany", $this->icompanies[0]);


        // @VH: Get insurance company list for display as dropdown for parent company field [2024081401]
        // TODO: @VH move into module
        $insurancecompanyList = array('' => '');
        $insres = sqlStatement("SELECT * from insurance_companies ic WHERE id != ? ", array($id));
        while ($insrow = sqlFetchArray($insres)) {
            if (isset($insrow['id'])) {
                $insurancecompanyList[$insrow['id']] = $insrow['name'];
            }
        }
        $this->assign("insurancecompany_list", $insurancecompanyList);

        if (!empty($id)) {
            $pistorage_preference_sql_row = sqlQuery("SELECT * FROM `vh_pistorage_preference` WHERE `insurance_companies_id` = ? ", array($id));
        }
        $pistorage_preference_sql_row = !empty($pistorage_preference_sql_row) ? $pistorage_preference_sql_row : array();

        $this->assign("form_pharmacy", $pistorage_preference_sql_row["pharmacy"] ?? "");
        $this->assign("form_behavioral_health", $pistorage_preference_sql_row["behavioral_health"] ?? "");
        $this->assign("form_chiropractic_care", $pistorage_preference_sql_row["chiropractic_care"] ?? "");
        $this->assign("form_communication", $pistorage_preference_sql_row["communication"] ?? "");
        $this->assign("form_imaging", $pistorage_preference_sql_row["imaging"] ?? "");
        $this->assign("form_neurology", $pistorage_preference_sql_row["neurology"] ?? "");
        $this->assign("form_ortho", $pistorage_preference_sql_row["ortho"] ?? "");
        $this->assign("form_pain_management", $pistorage_preference_sql_row["pain_management"] ?? "");
        $this->assign("chiropractic_care_list", array("yes" => "Yes", "no" => "No"));
        $this->assign("communication_list", array("email" => "Email", "phone_call" => "Phone call"));
        // End

        return $this->fetch($GLOBALS['template_dir'] . "insurance_companies/" . $this->template_mod . "_edit.html");
    }

    public function list_action()
    {
        $twig = new TwigContainer(null, $GLOBALS['kernel']);

        $insuranceCompanyService = new InsuranceCompanyService();
        $results = $insuranceCompanyService->search([]);
        $iCompanies = [];
        if ($results->hasData()) {
            foreach ($results->getData() as $record) {
                // @VH: Added attn, ins_type_code, parent_company for display [2024081401][2023011605]
                // TODO @VH move into module and make neat and clean code for display insurance list.
                $ins_type_code_array = $insuranceCompanyService->getInsuranceTypesCached();
                $parent_company = "";
                if (isset($record['parent_company']) && !empty($record['parent_company'])) {
                    $parent_company = new InsuranceCompany($record['parent_company']);
                }

                $company = [
                    'id' => $record['id'],
                    'name' => $record['name'],
                    'line1' => $record['line1'],
                    'line2' => $record['line2'],
                    'city' => $record['city'],
                    'state' => $record['state'],
                    'zip' => $record['zip'],
                    'phone' => $record['work_number'],
                    'fax' => $record['fax_number'],
                    'cms_id' => $record['cms_id'],
                    'x12_default_partner_name' => $record['x12_default_partner_name'],
                    'inactive' => $record['inactive'],
                    'attn' => $record['attn'],
                    'ins_type_code' => $ins_type_code_array[$record['ins_type_code']] ?? '',
                    'parent_company' => !empty($parent_company) ? $parent_company->get_name() : ''
                ];
                $iCompanies[] = $company;
            }
            usort($iCompanies, function ($a, $b) {
                return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
            });
        }
        $templateVars = [
            'CURRENT_ACTION' => $GLOBALS['webroot'] . "/controller.php?" . "practice_settings&insurance_company&"
            ,'icompanies' => $iCompanies
        ];

        return $twig->getTwig()->render('insurance_companies/general_list.html.twig', $templateVars);
    }


    public function edit_action_process()
    {
        if ($_POST['process'] != "true") {
            return;
        }

        if (is_numeric($_POST['id'])) {
            $this->icompanies[0] = new InsuranceCompany($_POST['id']);
        } else {
            $this->icompanies[0] = new InsuranceCompany();
        }

        self::populate_object($this->icompanies[0]);

        $this->icompanies[0]->persist();
        $this->icompanies[0]->populate();

        // @VH: Save pistorage preference for insurance
        if (!empty($this->icompanies[0]) && isset($this->icompanies[0]->id) && !empty($this->icompanies[0]->id)) {
            $form_pharmacy = $_POST['form_pharmacy'] ?? "";
            $form_behavioral_health = $_POST['form_behavioral_health'] ?? "";
            $form_chiropractic_care = $_POST['form_chiropractic_care'] ?? "";
            $form_communication = $_POST['form_communication'] ?? "";
            $form_imaging = $_POST['form_imaging'] ?? "";
            $form_neurology = $_POST['form_neurology'] ?? "";
            $form_ortho = $_POST['form_ortho'] ?? "";
            $form_pain_management = $_POST['form_pain_management'] ?? "";

            $pistorage_preference_sql_row = sqlQuery("SELECT count(`id`) as total_count FROM `vh_pistorage_preference` WHERE `insurance_companies_id` = ? ", array(trim($this->icompanies[0]->id)));

            if (!empty($pistorage_preference_sql_row) && $pistorage_preference_sql_row['total_count'] > 0) {
                // Update record
                sqlStatement("UPDATE vh_pistorage_preference SET pharmacy = '" . $form_pharmacy . "', behavioral_health = '" . $form_behavioral_health . "', chiropractic_care = '" . $form_chiropractic_care . "', communication = '" . $form_communication . "', imaging = '" . $form_imaging . "', neurology = '" . $form_neurology . "', ortho = '" . $form_ortho . "', pain_management = '" . $form_pain_management . "' WHERE insurance_companies_id = ? ", array($this->icompanies[0]->id));
            } else {
                // Insert record
                $pistorageid = sqlInsert("INSERT INTO vh_pistorage_preference (insurance_companies_id, pharmacy, behavioral_health, chiropractic_care, communication, imaging, neurology, ortho, pain_management) VALUES ('" . $this->icompanies[0]->id . "', '" . $form_pharmacy . "', '" . $form_behavioral_health . "', '" . $form_chiropractic_care . "', '" . $form_communication . "', '" . $form_imaging . "', '" . $form_neurology . "', '" . $form_ortho . "', '" . $form_pain_management . "')");
            }
        }
        // END

        $_POST['process'] = "";
        header('Location:' . $GLOBALS['webroot'] . "/controller.php?" . "practice_settings&insurance_company&action=list");//Z&H
    }
}
