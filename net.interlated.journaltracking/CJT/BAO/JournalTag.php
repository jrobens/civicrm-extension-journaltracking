<?php

/**
 * Business logic methods for journal tracking.
 *
 * jrobens@interlated.com.au 201207
 */
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
require_once 'CJT/DAO/JournalTag.php';

class CJT_BAO_JournalTag extends CJT_DAO_JournalTag
{

    /**
     * class constructor
     */
    function __construct()
    {
        parent::__construct();
    }

    /**
     * Loads values for the form given the id as a parameter.
     *
     * @param array $params (reference ) id of record to retrieve
     * @param array $defaults (reference ) an assoc array to hold the flattened values
     *
     * @return object CJT_BAO_Item object on success, null otherwise
     * @access public
     * @static
     */
    static function retrieve(&$params, &$defaults)
    {
        $tracker = new CJT_DAO_JournalTag();
        $tracker->copyValues($params);
        if ($tracker->find(true)) {
            // More like stash - just puts the values in the array.
            CRM_Core_DAO::storeValues($tracker, $defaults);
            return $tracker;
        }
        return null;
    }

    /**
     * Create an activity entry for each person on the list to recieve the journal
     *
     */
    static function createActivities($tracking_id, $all_sql, $catchup_string = '')
    {
        require_once 'api/api.php';

        $checked = FALSE;

        // Doing this means we might have had new people entered in the meantime.
        // We need to run the query as at a particular time.
        $dao2 = CRM_Core_DAO::executeQuery($all_sql);
        $count = 0;
        while ($dao2->fetch()) {
            $count++;
            $current_id = $dao2->id;

            $catchup_string_present = empty($catchup_string) ? '' : ' ' . $catchup_string;

            $params = array(
                'source_contact_id' => JournalTrackingVal::JOURNAL_ACTIVITY_SOURCE_CONTACT,
                'activity_type_id' => JournalTrackingVal::JOURNAL_ACTIVITY_TYPE_CODE,
                'target_contact_id' => $current_id,
                'subject' => 'Journal Batch ' . $tracking_id . $catchup_string_present,
                'activity_date_time' => date('YmdHis'), // '2011-06-02 14:36:13',
                'status_id' => JournalTrackingVal::JOURNAL_ACTIVITY_STATUS,
                'priority_id' => 1,
                'duration' => 1, // time spent on the activitiy
                'location' => 'Sydney',
                'details' => 'Journal export ' . $tracking_id,
                'version' => 3,
                JournalTrackingVal::JOURNAL_ACTIVITY_CUSTOM_TRACKING_FIELD_ID => $tracking_id,
            );


            $result = civicrm_api('activity', 'create', $params);
            if ($result['is_error']) {
                CRM_Core_Session::setStatus(ts('ERROR. Please check the batch. There was a problem recording activities'));
                watchdog("CiviJournal", "Failed to write activity journal tracking batch %tracking_id for %cid", array('%tracking_id' => $tracking_id, '%cid' => $current_id));
            }

            // Query for at least the first one.
            if (!$checked) {
                // TODO - isn't querying by custom data.
                $retrieve_query = array(
                    'source_contact_id' => JournalTrackingVal::JOURNAL_ACTIVITY_SOURCE_CONTACT,
                    JournalTrackingVal::JOURNAL_ACTIVTY_CUSTOM_TRACKING_FIELD_CODE => $tracking_id,
                    'version' => 3,
                );
                $check_data = civicrm_api('activity', 'get', $retrieve_query);
                if ($check_data['is_error']) {
                    $error_message = '';
                    if (array_key_exists($check_data, 'error_message')) {
                        $error_message = $check_data['error_message'];
                    }
                    watchdog("CiviJournal", "ERROR. Please check the batch. We could not retrieve the activity for the first record. %message", array('%message' => $error_message));
                    CRM_Core_Session::setStatus(ts('ERROR. Please check the batch. We could not retrieve the activity for the first record. %1', array(1 => $error_message)));
                }
                $checked = TRUE;
            }
        }
        return $count;
    }

}

?>    