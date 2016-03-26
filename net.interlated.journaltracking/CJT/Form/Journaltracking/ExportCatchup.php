<?php

/*
  +--------------------------------------------------------------------+
  | CiviCJT version 4.1                                                |
  +--------------------------------------------------------------------+
  | Copyright CiviCJT LLC (c) 2004-2011                                |
  +--------------------------------------------------------------------+
  | This file is a part of CiviCJT.                                    |
  |                                                                    |
  | CiviCJT is free software; you can copy, modify, and distribute it  |
  | under the terms of the GNU Affero General Public License           |
  | Version 3, 19 November 2007 and the CiviCJT Licensing Exception.   |
  |                                                                    |
  | CiviCJT is distributed in the hope that it will be useful, but     |
  | WITHOUT ANY WARRANTY; without even the implied warranty of         |
  | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
  | See the GNU Affero General Public License for more details.        |
  |                                                                    |
  | You should have received a copy of the GNU Affero General Public   |
  | License and the CiviCJT Licensing Exception along                  |
  | with this program; if not, contact CiviCJT LLC                     |
  | at info[AT]civicrm[DOT]org. If you have questions about the        |
  | GNU Affero General Public License or the licensing of CiviCJT,     |
  | see the CiviCJT license FAQ at http://civicrm.org/licensing        |
  +--------------------------------------------------------------------+
 */

/**
 *
 * @package CJT
 * @copyright CiviCJT LLC (c) 2004-2011
 * $Id$
 *
 */
require_once 'CJT/BAO/JournalTag.php';

/**
 * This class generates form components for Location Type
 *
 */
class CJT_Form_Journaltracking_ExportCatchup extends CRM_Admin_Form
{

    /**
     * Porcess data.
     */
    function preProcess()
    {
        // Get the data from the http request
        // Form is based on JournalTag while the
        $this->set('BAOName', 'CJT_BAO_JournalTag');

        $this->_id = CRM_Utils_Request::retrieve('id', 'Positive', $this, false, 0);

        parent::preProcess();

        $session = CRM_Core_Session::singleton();
        $id_string = "";
        if (!empty($this->_id)) {
            $id_string = "&id=" . $this->_id;
        }
        $url = CRM_Utils_System::url('civicrm/journaltracking/view', 'reset=1' . $id_string);
        $session->pushUserContext($url);
    }

    /**
     * Set default values from the query string.
     *
     * @return type
     */
    function setDefaultValues()
    {
        $defaults = array();

        // Don't want to be able to copy, update or delete a batch.

        if ($this->_id) {
            $params = array('id' => $this->_id);
            CJT_BAO_JournalTag::retrieve($params, $defaults);
        }

        // Description is set in buildQuickForm
        if (!empty($this->_values['description'])) {
            $defaults['description'] = $this->_values['description'];
        }

        $defaults['catchup_id'] = $this->_id;

        return $defaults;
    }

    /**
     * Function to build the form.
     *
     * See Export.php for a similar implementation.
     *
     * @return None
     * @access public
     */
    public function buildQuickForm()
    {
        parent::buildQuickForm();

        if ($this->_action & CRM_Core_Action::DELETE) {
            return;
        }

        $this->applyFilter('__ALL__', 'trim');

        // id - so that it is not lost on resubmission of the form.
        // $this->add('hidden', 'id', 'id', CRM_Core_DAO::getAttribute('CJT_DAO_JournalTag', 'id'));
        $catchup_id_element = & $this->add('text', 'catchup_id', 'Catchup ID (Hidden)', '', FALSE);
        $catchup_id_element->setValue($this->_id);
        $catchup_id_element->freeze();

        $fieldName = 'code';
        $object = new CJT_DAO_JournalTag( );
        $fields = &$object->fields();
        $field = CRM_Utils_Array::value($fieldName, $fields);
        $code = CRM_Core_DAO::makeAttribute($field);
    
        // $element returned, but we use the object to address it.
        $element = & $this->add('text', 'code', ts('Code'), $code, TRUE);

        $fieldName = 'description';
        $object = new CJT_DAO_JournalTag( );
        $fields = &$object->fields();
        $field = CRM_Utils_Array::value($fieldName, $fields);
        $description = CRM_Core_DAO::makeAttribute($field);

        // Description
        $this->add('text', 'description', ts('Description'), $description, FALSE);

        // Same buttons as for export.
        $this->addButtons(array(
                array(
                    'type' => 'next',
                    'name' => ts('Export'),
                    'isDefault' => TRUE,
                ),
                array(
                    'type' => 'cancel',
                    'name' => ts('Finished'),
                ),
            )
        );
    }

    /**
     * Function to process the form
     *
     * @access public
     * @return None
     */
    public function postProcess()
    {

        // store the submitted values in an array
        $params = $this->exportValues();

        // Export csv. This time ensure we don't get anybody who has an activity against
        // them with this code.
        $tracker = new net_interlated_journaltracking($params);
        $catchup_sql = $tracker->catchup(JournalTrackingVal::ACTIVITY_CODE_PREFIX . $params['code']);

        $csvFullFilename = CRM_Utils_File::makeFileName('CiviReportCatchup.csv');

        $columns = $tracker->columns();

        try {
            $this->run_csv($catchup_sql, $csvFullFilename, $columns, $params['code']);
        } catch (Exception $e) {
            // no records found.
            CRM_Core_Session::setStatus(ts('No new journal recipients were found.', array()));
            $params['count'] = 0;
            $this->_recordCatchupBatch($params);

            // Needs to re-render the form.
            return;
        }

        $count = CJT_BAO_JournalTag::createActivities(JournalTrackingVal::ACTIVITY_CODE_PREFIX  . $params['code'],
            $catchup_sql);
        $params['count'] = $count;
        $this->_recordCatchupBatch($params);


        CRM_Core_Session::setStatus(ts('The journal distribution catchup \'%1\' has been saved.', array(1 => $params['code'] ? $params['code'] : '')));

        // Exit - don't re-render form.
        CRM_Utils_System::civiExit();
    }

    /**
     * Run the sql and export CSV data.
     *
     * @param string $all_sql
     * @param string $fileName
     * @param array $header
     */
    private function run_csv($all_sql, $fileName, array $header, $tracking_id)
    {
        $writeHeader = TRUE;
        $offset = 0;
        $limit = 20000;

        require_once 'CRM/Core/Report/Excel.php';
        // $limitQuery = $all_sql . "LIMIT $offset, $limit";
        $dao = CRM_Core_DAO::executeQuery($all_sql);

        // No records then return
        if ($dao->N <= 0) {
            // no records - then still need to redirect to the same place.
            throw new Exception("No records found");
        }

        $columns = array_values($header);
        $labels = array_keys($header);

        $componentDetails = array();
        while ($dao->fetch()) {
            $row = array();

            foreach ($columns as $column_num => $column) {
                $row[$column] = $dao->$column;
            }

            $componentDetails[] = $row;
        }

        // function writeCSVFile($fileName, &$header, &$rows, $titleHeader = NULL, $outputHeader = TRUE
        $core_report = new CRM_Core_Report_Excel();
        $core_report->writeCSVFile($fileName, $labels, $componentDetails);
    }

    /**
     * Save a catchup batch instance.
     *
     * @param array $params
     */
    private function _recordCatchupBatch(array $params)
    {
        // action is taken depending upon the mode
        require_once 'CJT/DAO/JournalTagCatchup.php';
        $item = new CJT_DAO_JournalTagCatchup();

        // No code is fail.
        $item->code = $params['code'];
        $item->activity_code = JournalTrackingVal::ACTIVITY_CODE_PREFIX . $params['code'];

        $item->description = $params['description'];

        // 0 is empty in php universe
        $item->count = 0;
        if (!empty($params['count'])) {
            $item->count = $params['count'];
        }

        require_once 'CRM/Utils/Date.php';

        $item->catchup_date = CRM_Utils_Date::processDate(date('YmdHis'));

        // Only changes the description
        $item->save();

        CRM_Core_Session::setStatus(ts('The description for the export \'%1\' has been saved.', array(1 => $item->description ? $item->description : $item->code)));
    }

}


/* Catchup SQL is standard batch +

  SELECT
        civicrm_contact.id, civicrm_contact.display_name as name,
        civicrm_address.street_address,
        civicrm_address.supplemental_address_1,
        civicrm_address.supplemental_address_2,
        civicrm_address.city,
        civicrm_state_province.abbreviation as state,
        civicrm_address.postal_code,
        civicrm_country.name as country,
        civicrm_email.email
                 FROM
      civicrm_contact
        LEFT JOIN civicrm_address ON civicrm_contact.id = civicrm_address.contact_id
        LEFT JOIN civicrm_membership  ON civicrm_contact.id = civicrm_membership.contact_id
        LEFT JOIN civicrm_state_province ON civicrm_address.state_province_id = civicrm_state_province.id
        LEFT JOIN civicrm_country ON civicrm_address.country_id = civicrm_country.id
        OUTER JOIN civicrm_email ON civicrm_contact.id = civicrm_email.contact_id
        OUTER JOIN civicrm_value_esa_lists__bulletins_4 ON civicrm_contact.id = civicrm_value_esa_lists__bulletins_4.entity_id WHERE civicrm_membership.membership_type_id IN ('1','2','3','4','5','7','8','9','11','12','13','14','16','17') AND civicrm_membership.status_id IN ('1','2') AND civicrm_membership.is_test = 0 AND civicrm_contact.is_deleted = 0 AND civicrm_address.is_primary = 1 AND civicrm_email.is_primary = 1 AND (civicrm_value_esa_lists__bulletins_4.aej_electronic_only_52 <> 1 OR civicrm_value_esa_lists__bulletins_4.aej_electronic_only_52 IS NULL) AND NOT civicrm_contact.id IN (SELECT civicrm_relationship.contact_id_b FROM civicrm_contact LEFT JOIN civicrm_relationship ON
          (civicrm_relationship.contact_id_a = civicrm_contact.id) WHERE civicrm_relationship.contact_id_b IS NOT NULL) AND NOT civicrm_contact.id IN (
    SELECT target_contact_id FROM civicrm_option_value cov
        LEFT JOIN civicrm_activity activity ON activity.activity_type_id = cov.value
        LEFT JOIN civicrm_activity_target cat ON cat.activity_id = activity.id
        LEFT JOIN civicrm_value_esa_journal_tracking_14 tracking ON tracking.entity_id = activity.id
        WHERE cov.name = 'Tracked Journal'
            AND tracking.batch_id_101 = 'AEJ001'
)
*/