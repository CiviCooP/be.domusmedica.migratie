<?php

/**
 * Class for Domus Medica MembershipLog Migration to CiviCRM
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date Sep 2016
 * @license AGPL-3.0
 */
class CRM_Migratie_MembershipLog {
  protected $_insertClauses = array();
  protected $_insertParams = array();

  /**
   * CRM_Migratie_MembershipLog constructor.
   */
  function __construct() {
    $this->_insertParams = array();
    $this->_insertClauses = array();
  }

  /**
   * Method to add a membership log from a membership dao
   *
   * @param object $membership
   */
  public function addMembership($membership) {
    $this->_insertClauses[] = 'membership_id = %1';
    $this->_insertParams[1] = array($membership->id, 'Integer');
    $this->_insertClauses[] = 'status_id = %2';
    $this->_insertParams[2] = array($membership->status_id, 'Integer');
    $this->_insertClauses[] = 'start_date = %3';
    $this->_insertParams[3] = array($membership->start_date, 'String');
    $this->_insertClauses[] = 'modified_id = %4';
    $this->_insertParams[4] = array(1, 'Integer');
    $this->_insertClauses[] = 'modified_date = %5';
    $modifiedDate = new DateTime();
    $this->_insertParams[5] = array($modifiedDate->format('Y-m-d'), 'String');
    $this->_insertClauses[] = 'membership_type_id = %6';
    $this->_insertParams[6] = array($membership->membership_type_id, 'Integer');
    if (!empty($membership->end_date)) {
      $this->_insertClauses[] = 'end_date = %7';
      $this->_insertParams[7] = array($membership->end_date, 'String');
    }
    $insertQuery = 'INSERT INTO civicrm_membership_log SET '.implode(', ', $this->_insertClauses);
    CRM_Core_DAO::executeQuery($insertQuery, $this->_insertParams);
  }
}