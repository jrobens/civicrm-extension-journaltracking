<?php

/**
 * @file
 * Specify values for journal tracking. i.e. configuration items and static data.
 *
 * 201206
 * jrobens@interlated.com.au
 */

/**
 *
 */
class JournalTrackingVal
{
    // List for civicrm_membership.membership_type_id

    // excludes 6,10,16. AGM excludes 6,14,15 - so don't expect exactly the same results.
    const MEMBERSHIP_TYPES = "'1','2','3','4','5','7','8','9','11','12','13','14','16','17'";
    const MEMBERSHIP_STATUSES = "'1','2'";

    // Activities
    const JOURNAL_ACTIVITY_TYPE_CODE = "36";
    const JOURNAL_ACTIVITY_SOURCE_CONTACT = "1";
    const JOURNAL_ACTIVITY_STATUS = "2"; // 2 = completed. (1=scheduled,3=cancelled,4=left message,5=unreachable,6=not required)
    // The field in civicrm_value_esa_journal_tracking_14 to store the data
    // Vulnerable to the way the custom fields are setup. If installing check this field.
    // This is the database column name that we need to query by.
    const JOURNAL_ACTIVTY_CUSTOM_TRACKING_FIELD_CODE = 'batch_id_101';
    // This is the code to tell civicrm how to save the data via the api
    const JOURNAL_ACTIVITY_CUSTOM_TRACKING_FIELD_ID = 'custom_101_-1';

    // Catchup Activities
    // Vulnerable to the way the custom fields are setup. If installing check this field.
    const CATCHUP_ACTIVITY_CUSTOM_FIELD = "civicrm_value_esa_journal_tracking_14";
    const ACTIVITY_TYPE_NAME = 'Tracked Journal';
    const ACTIVITY_CODE_PREFIX = 'AEJ';
}

/*
 * mysql> select id,name from civicrm_membership_type;
+----+--------------------------------+
| id | name                           |
+----+--------------------------------+
|  1 | Standard Member                |
|  2 | Family Member                  |
|  3 | Concession: Low income         |
|  4 | Concession: Retired            |
|  5 | Concession: Student            |
|  6 | EMR Subscription               |
|  7 | Foundation/Life member         |
|  8 | Standard Member: International |
|  9 | Concession: International      |
| 10 | Association                    |
| 11 | Standard Member: 5yrs          |
| 12 | Standard Member: 10yrs         |
| 13 | Family Member: International   |
| 14 | Bulletin Only                  |
| 15 | Member Tester                  |
| 16 | Cross Tasman Member            |
| 17 | Standard Member: 15yrs         |
+----+--------------------------------+
 */

?>
