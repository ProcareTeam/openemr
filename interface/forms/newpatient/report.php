<?php

/**
 * Encounter form report function.
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Brady Miller <brady.g.miller@gmail.com>
 * @author    Robert Down <robertdown@live.com
 * @copyright Copyright (c) 2019 Brady Miller <brady.g.miller@gmail.com>
 * @copyright Copyright (c) 2023 Robert Down <robertdown@live.com
 * @copyright Copyright (c) 2023 Providence Healthtech
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once(dirname(__file__) . "/../../globals.php");
require_once($GLOBALS['srcdir']."/wmt-v2/wmtstandard.inc");

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Services\AppointmentService;
use OpenEMR\Services\UserService;
use OpenEMR\Common\Twig\TwigContainer;
use OpenEMR\Billing\BillingUtilities;

// @VH: Added param 'suppress_reason'
function newpatient_report($pid, $encounter, $cols, $id, $suppress_reason = FALSE)
{
    // @VH: Print [V100010][V100016]
    global $doNotPrintField;
    $isReport = ((isset($doNotPrintField) && $doNotPrintField === true)) ? true : false;
    $billing = BillingUtilities::getBillingByEncounter($pid, $encounter, "*");
    // END

    $res = sqlStatement("select e.*, f.name as facility_name from form_encounter as e join facility as f on f.id = e.facility_id where e.pid=? and e.id=?", array($pid,$id));
    $twig = new TwigContainer(__DIR__, $GLOBALS['kernel']);
    $t = $twig->getTwig();
    $encounters = [];
    $userService = new UserService();
    while ($result = sqlFetchArray($res)) {
        $hasAccess = (empty($result['sensitivity']) || AclMain::aclCheckCore('sensitivities', $result['sensitivity']));
        $rawProvider = $userService->getUser($result["provider_id"]);
        $rawRefProvider = $userService->getUser($result["referring_provider_id"]);
        $calendar_category = (new AppointmentService())->getOneCalendarCategory($result['pc_catid']);
        $reason = (!$hasAccess) ? false : $result['reason'];
        $provider = (!$hasAccess) ? false : $rawProvider['fname'] .
            (($rawProvider['mname'] ?? '') ? " " . $rawProvider['mname'] . " " : " ") .
            $rawProvider['lname'] .
            ($rawProvider['suffix'] ? ", " . $rawProvider['suffix'] : '') .
            ($rawProvider['valedictory'] ? ", " . $rawProvider['valedictory'] : '');
        $referringProvider = (!$hasAccess || !$rawRefProvider) ? false : $rawRefProvider['fname'] . " " . $rawRefProvider['lname'];
        $posCode = (!$hasAccess) ? false : sprintf('%02d', trim($result['pos_code'] ?? false));
        $posCode = ($posCode && $posCode != '00') ? $posCode : false;
        $facility_name = (!$hasAccess) ? false : $result['facility_name'];

        // @VH: Get case field info [V100016] 
        $desc = 'No Case Attached';
        $case_link = sqlQuery('SELECT * FROM case_appointment_link WHERE encounter = ?', array($encounter));
        if(!isset($case_link['pc_eid'])) $case_link['pc_eid'] = '';
        if(!isset($case_link['enc_case'])) $case_link['enc_case'] = '';
        if($case_link['pc_eid']) {
            $sql = 'SELECT oe.pc_case, c.*, c.id AS case_id, users.* FROM ' .
            'openemr_postcalendar_events AS oe LEFT JOIN form_cases AS c ' .
            'ON (oe.pc_case = c.id) LEFT JOIN users ON (c.employer = users.id) ' .
            'WHERE oe.pc_eid = ?';
            $case = sqlQuery($sql, array($case_link['pc_eid']));
        }
        
        if ((!$case_link['pc_eid'] && $case_link['enc_case']) || ($case_link['pc_eid'] && empty($case))) {
            $sql = 'SELECT c.*, c.id AS case_id, users.* FROM ' .
            'form_cases AS c LEFT JOIN users ON (c.employer = users.id) ' .
            'WHERE c.id = ?';
            $case = sqlQuery($sql, array($case_link['enc_case']));
        }
        if(!isset($case['case_id'])) $case['case_id'] = '';
        $result['form_case'] = $case['case_id'];
        if($case['case_id']) $case_desc = $case['case_id'];
        if($case['case_id']) $desc = $case['case_description'];
        if(!$suppress_reason && $isReport === false) $case_desc .= ' - '. $desc;

        // @VH: Get Taxomony Title for provider
        $taxomony = sqlQuery("select * from list_options where option_id = '". $rawProvider['taxonomy'] ."' and list_id like 'taxonomy'");

        // @VH: Get supervisor provider [V100016]
        $supervisorProvider = $userService->getUser($result["supervisor_id"]);
        $supervisor_name = !empty($supervisorProvider) ? $supervisorProvider['lname'] . ", " . $supervisorProvider['fname'] : "";
        // End

        // @VH: Prepare billing code values [V100016]
        $first = TRUE;
        $billing_text = "";
        foreach($billing as $item) {
            if(!$first) $billing_text .= ', ';
            $billing_text .= $item['code_type'] . ':' . $item['code'];
            if($item['units']) $billing_text .= ' (' . $item['units'] . ')';
            $first = FALSE;
        }
        // END


        // @VH: modified param list to template [V100016] 
        $encounters[] = [
            'category' => xl_appt_category($calendar_category[0]['pc_catname']),
            'reason' => $reason,
            'provider' => $provider,
            'referringProvider' => $referringProvider,
            'posCode' => $posCode,
            'facility' => $facility_name,
            'taxomony' => $taxomony['title'],
            'case_description' => $case_desc,
            'supervisor_provider' => $supervisor_name,
            'billing_code' => $billing_text
        ];
    }
    echo $t->render("templates/report.html.twig", ['encounters' => $encounters]);
}
