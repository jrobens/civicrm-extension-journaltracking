<?php

/**
 *  Journal tracking, main function.
 *
 * We run the journal export as at 'now'. There are no dates in the query and it makes no difference
 * as people get a catchup batch afterwards anyway.
 *
 * jrobens@interlated.com.au 201207
 *
 */
require_once 'journaltracking.civix.php';
require_once 'JournalTrackingVal.php';

/**
 * Implementation of hook_civicrm_config
 */
function journaltracking_civicrm_config(&$config)
{
    _journaltracking_civix_civicrm_config($config);
}

/**
 * Implementation of hook_civicrm_xmlMenu
 *
 * @param $files array(string)
 */
function journaltracking_civicrm_xmlMenu(&$files)
{
    _journaltracking_civix_civicrm_xmlMenu($files);
}

/**
 * Implementation of hook_civicrm_install( )
 */
function journaltracking_civicrm_install()
{
    $journalTrackingRoot =
        dirname(__FILE__) . DIRECTORY_SEPARATOR;

    $journalTrackingSql =
        $journalTrackingRoot . DIRECTORY_SEPARATOR .
        'journaltracking_install.sql';

    CRM_Utils_File::sourceSQLFile(
        CIVICRM_DSN, $journalTrackingSql
    );

    // rebuild the menu so our path is picked up
    CRM_Core_Invoke::rebuildMenuAndCaches();
}

/**
 * Implementation of hook_civicrm_uninstall( )
 */
function journaltracking_civicrm_uninstall()
{
    $journalTrackingRoot =
        dirname(__FILE__) . DIRECTORY_SEPARATOR;

    $journalTrackingSql =
        $journalTrackingRoot . DIRECTORY_SEPARATOR .
        'journaltracking.uninstall.sql';

    CRM_Utils_File::sourceSQLFile(
        CIVICRM_DSN, $journalTrackingSql
    );

    // rebuild the menu so our path is picked up
    CRM_Core_Invoke::rebuildMenuAndCaches();
}

require_once 'CRM/Contact/Form/Search/Interface.php';

/**
 * Search interface for performing queries against contacts.
 *
 */
class net_interlated_journaltracking implements CRM_Contact_Form_Search_Interface
{

    protected $_formValues;

    /**
     *
     *
     * @param type $formValues
     */
    function __construct(&$formValues)
    {
        if (!empty($formValues)) {
            $this->_formValues = $formValues;
        }

        /**
         * Define the columns for search result rows
         *
         * Used to define the CSV export.
         */
        $this->_columns = array(
            ts('Contact ID') => 'id',
            ts('Name') => 'name',
            ts('Line 1') => 'street_address',
            ts('Line 2') => 'supplemental_address_1',
            ts('Line 3') => 'supplemental_address_2',
            ts('City') => 'city',
            ts('State') => 'state',
            ts('Post Code') => 'postal_code',
            ts('Country') => 'country',
            ts('Email') => 'email',
        );

        // Custom fields see ActivitySearch.php
    }

    /**
     * Define the smarty template used to layout the search form and results listings.
     */
    function templateFile()
    {
        return 'JournalResults.tpl';
    }

    /**
     * Present a form to the user with.
     * Not used, but required to be extended under the search interface.
     *
     * @param type $form
     */
    function buildForm(&$form)
    {
        // Nothing to see here.
    }

  /**
   * Build a list of actions - there are no actions at present (export etc).
   *
   * @param CRM_Core_Form_Search $form
   * @return array|void
   */
  function buildTaskList(CRM_Core_Form_Search $form)   {
       /*
        * 4.6.0: CRM_Contact_Form_Search_Interface->buildTaskList
Classes which implement this interface must implement a new method called buildTaskList. This method is responsible for building the list of actions (e.g., Add to Group) that may be performed on set of search results. It differs from hook_civicrm_searchTasks in that the hook allows a developer to specify tasks by entity (e.g., Contact, Event, etc.) whereas buildTaskList provides the ability to target a specific form. The new method takes a CRM_Core_Form_Search object as an argument and should return an array. Dump CRM_Core_Form_Search->_taskList to learn about the format of the array. The array returned by buildTaskList will completely replace the task list.

Aside from the community-maintained custom searches in CRM/Contact/Form/Search/Custom/, this change does not affect CiviCRM core. Custom searches which extend CRM_Contact_Form_Search_Custom_Base (as do those built on civix) will not be affected, as the method is implemented there.

See CRM-15965 for more information.
        *
        */
    }

    /*
     * -- membership number, name, line 1, line 2, line 3, city, state, code, country
      -- supplemental_address3 doesn't seem to be used.
      -- Could check join_date, start_date, end_date etc for 'is financial' or membership_status table?
      select cc.id, CONCAT_WS(' ', first_name, last_name) as name, street_address, supplemental_address_1, supplemental_address_2, city, csp.abbreviation as State, postal_code, country.name as Country
      FROM civicrm_contact cc
      LEFT JOIN civicrm_address ca ON cc.id = ca.contact_id
      LEFT JOIN civicrm_membership cm ON cc.id = cm.contact_id
      LEFT JOIN civicrm_state_province csp ON ca.state_province_id = csp.id
      LEFT JOIN civicrm_country country ON ca.country_id = country.id

      -- rules for 'journal eligible'
      -- 1= standard member, 7 = life member
      WHERE cm.membership_type_id IN (1,7)
      AND is_primary = 1 -- primary addresses only
     */

    /**
     * Construct the search query
     */
    function all($offset = 0, $rowcount = 0, $sort = null, $includeContactIDs = false, $onlyIDs = false)
    {

        $select = '
        c.id,
        c.display_name as name,
        civicrm_address.street_address, 
        civicrm_address.supplemental_address_1, 
        civicrm_address.supplemental_address_2, 
        civicrm_address.city, 
        civicrm_state_province.abbreviation as state, 
        civicrm_address.postal_code, 
        civicrm_country.name as country,
        civicrm_email.email
                ';

        $from = $this->from();

        // Needs the parameter but it is not used.
        $where = $this->where($includeContactIDs);

        if (!empty($where)) {
            $where = "WHERE $where";
        }

        $sql = " SELECT $select FROM $from $where GROUP BY c.id ";
        return $sql;
    }

    /**
     * Add a join and where condition limiting contacts with activities matching the code.
     *
     * @param type $offset
     * @param type $rowcount
     * @param type $sort
     * @param type $includeContactIDs
     * @param type $onlyIDs
     * @return type
     */
    function catchup($tracking_code, $offset = 0, $rowcount = 0, $sort = null, $includeContactIDs = false, $onlyIDs = false)
    {
        $sql_all = $this->all($offset = 0, $rowcount = 0, $sort = null, $includeContactIDs = false, $onlyIDs = false);

        $sql = preg_replace('/GROUP BY c\.id/', '', $sql_all);

        /*$sql .= "AND NOT c.id IN (
    SELECT target_contact_id FROM civicrm_option_value cov
        LEFT JOIN civicrm_activity activity ON activity.activity_type_id = cov.value
        LEFT JOIN civicrm_activity_target cat ON cat.activity_id = activity.id 
        LEFT JOIN civicrm_value_esa_journal_tracking_14 tracking ON tracking.entity_id = activity.id
        WHERE cov.name = '";*/
        $sql .= "AND NOT c.id IN (
          SELECT cac.contact_id FROM civicrm_option_value cov
        LEFT JOIN civicrm_activity activity ON activity.activity_type_id = cov.value
        LEFT JOIN civicrm_activity_contact cac ON cac.activity_id = activity.id 
        LEFT JOIN civicrm_value_esa_journal_tracking_14 tracking ON tracking.entity_id = activity.id
        WHERE cov.name = '";
        $sql .= JournalTrackingVal::ACTIVITY_TYPE_NAME;
        $sql .= "'
            AND tracking.batch_id_101 = '" . $tracking_code . "'
)";

        $sql .= 'GROUP BY c.id ';
        return $sql;
    }

    /**
     * Alters the date display in the Activity Date Column. We do this after we already have
     * the result so that sorting on the date column stays pertinent to the numeric date value
     * @param type $row
     */
    function alterRow(&$row)
    {
        // $row['activity_date'] = CRM_Utils_Date::customFormat($row['activity_date'], '%B %E%f, %Y %l:%M %P');
    }

    // Regular JOIN statements here to limit results to contacts who have activities.
    function from()
    {
        return "
      civicrm_contact c
        LEFT JOIN civicrm_address ON c.id = civicrm_address.contact_id
        LEFT JOIN civicrm_membership  ON c.id = civicrm_membership.contact_id
        LEFT JOIN civicrm_state_province ON civicrm_address.state_province_id = civicrm_state_province.id
        LEFT JOIN civicrm_country ON civicrm_address.country_id = civicrm_country.id
        LEFT JOIN civicrm_email ON c.id = civicrm_email.contact_id
        LEFT JOIN civicrm_value_esa_lists__bulletins_4 ON c.id = civicrm_value_esa_lists__bulletins_4.entity_id";
    }

    /**
     * WHERE clause is an array built from any required JOINS plus conditional filters based on search criteria field values
     *
     */
    function where($includeContactIDs = FALSE)
    {
        $clauses = array();

        // Journal tracking types
        $clauses[] = "civicrm_membership.membership_type_id IN (" . JournalTrackingVal::MEMBERSHIP_TYPES . ")";

        // New or current
        $clauses[] = "civicrm_membership.status_id IN (" . JournalTrackingVal::MEMBERSHIP_STATUSES . ")";
        $clauses[] = "civicrm_membership.is_test = 0";
        $clauses[] = "c.is_deleted = 0";
        $clauses[] = "civicrm_address.is_primary = 1";
        $clauses[] = "civicrm_email.is_primary = 1";
        // Remove electronic only.
        $clauses[] = "(civicrm_value_esa_lists__bulletins_4.aej_electronic_only_52 <> 1 OR civicrm_value_esa_lists__bulletins_4.aej_electronic_only_52 IS NULL)";
        // Remove children in family relationships
        $clauses[] = "NOT c.id IN (SELECT civicrm_relationship.contact_id_b FROM civicrm_contact LEFT JOIN civicrm_relationship ON
          (civicrm_relationship.contact_id_a = civicrm_contact.id) WHERE civicrm_relationship.contact_id_b IS NOT NULL)";

        // add where for batch date.
        return implode(' AND ', $clauses);
    }

    /**
     * Functions below generally don't need to be modified
     */
    function count()
    {
        $sql = $this->all();

        $dao = CRM_Core_DAO::executeQuery($sql, CRM_Core_DAO::$_nullArray);
        return $dao->N;
    }

    function contactIDs($offset = 0, $rowcount = 0, $sort = null)
    {
        return $this->all($offset, $rowcount, $sort, false, true);
    }

    function &columns()
    {
        return $this->_columns;
    }

    function summary()
    {
        return null;
    }

}

/*


        SELECT
        c.id,
        c.display_name as name,
        civicrm_address.street_address,
        civicrm_address.supplemental_address_1,
        civicrm_address.supplemental_address_2,
        civicrm_address.city,
        civicrm_state_province.abbreviation as state,
        civicrm_address.postal_code,
        civicrm_country.name as country,
        civicrm_email.email
                 FROM
      civicrm_contact c
        LEFT JOIN civicrm_address ON c.id = civicrm_address.contact_id
        LEFT JOIN civicrm_membership  ON c.id = civicrm_membership.contact_id
        LEFT JOIN civicrm_state_province ON civicrm_address.state_province_id = civicrm_state_province.id
        LEFT JOIN civicrm_country ON civicrm_address.country_id = civicrm_country.id
        LEFT JOIN civicrm_email ON c.id = civicrm_email.contact_id
        LEFT JOIN civicrm_value_esa_lists__bulletins_4 ON c.id = civicrm_value_esa_lists__bulletins_4.entity_id WHERE civicrm_membership.membership_type_id IN ('1','2','3','4','5','7','8','9','11','12','13','14','16','17') AND civicrm_membership.status_id IN ('1','2') AND civicrm_membership.is_test = 0 AND c.is_deleted = 0 AND civicrm_address.is_primary = 1 AND civicrm_email.is_primary = 1 AND (civicrm_value_esa_lists__bulletins_4.aej_electronic_only_52 <> 1 OR civicrm_value_esa_lists__bulletins_4.aej_electronic_only_52 IS NULL) AND NOT c.id IN (SELECT civicrm_relationship.contact_id_b FROM civicrm_contact LEFT JOIN civicrm_relationship ON
          (civicrm_relationship.contact_id_a = civicrm_contact.id) WHERE civicrm_relationship.contact_id_b IS NOT NULL) GROUP BY c.id



select * From civicrm_value_esa_lists__bulletins_4 where entity_id = 24000
*/

