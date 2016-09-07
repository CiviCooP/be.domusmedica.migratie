<?php

/**
 * Class for Domus Medica MembershipPayment Migration to CiviCRM
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date Sep 2016
 * @license AGPL-3.0
 */
class CRM_Migratie_MembershipPayment {
  protected $_insertClauses = array();
  protected $_insertParams = array();
  protected $_logger = NULL;

  /**
   * CRM_Migratie_MembershipLog constructor.
   */
  function __construct() {
    $this->_insertParams = array();
    $this->_insertClauses = array();
    $this->_logger = new CRM_Migratie_Logger('membership_payment');
  }

  /**
   * Method to add a membership log from a membership dao
   *
   * @param int $membershipId
   * @param int $contributionId
   */
  public function addMembership($membershipId, $contributionId) {
    if (empty($membershipId) || empty($contributionId)) {
      $this->_logger->logMessage('Error', 'Invalid data for membership_payment, membership id is '.$membershipId
        .' and contribution id is '.$contributionId);
    }
    $this->_insertClauses[] = 'membership_id = %1';
    $this->_insertParams[1] = array($membershipId, 'Integer');
    $this->_insertClauses[] = 'contribution_id = %2';
    $this->_insertParams[2] = array($contributionId, 'Integer');
    $insertQuery = 'INSERT INTO civicrm_membership_payment SET '.implode(', ', $this->_insertClauses);
    CRM_Core_DAO::executeQuery($insertQuery, $this->_insertParams);
  }
}