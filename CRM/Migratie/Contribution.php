<?php

/**
 * Class for Domus Medica Contribution Migration to CiviCRM (only for memberships?)
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date Sep 2016
 * @license AGPL-3.0
 */
class CRM_Migratie_Contribution {
  protected $_insertClauses = array();
  protected $_insertParams = array();
  protected $_membershipTypes = array();
  protected $_paymentInstrumentId = NULL;
  protected $_completedStatusId = NULL;
  protected $_currency = NULL;

  /**
   * CRM_Migratie_MembershipLog constructor.
   */
  function __construct() {
    $this->_insertParams = array();
    $this->_insertClauses = array();
    $this->setMembershipTypes();
    $this->setCompletedStatusId();
    $this->_paymentInstrumentId = 9;
    $this->_currency = "EUR";
  }

  /**
   * Method to set the completed contribution status id
   */
  private function setCompletedStatusId() {
    try {
      $this->_completedStatusId = civicrm_api3('OptionValue', 'getvalue', array(
        'option_group_id' => 'contribution_status',
        'name' => 'Completed',
        'return' => 'value'));
    } catch (CiviCRM_API3_Exception $ex) {
      $this->_completedStatusId = NULL;
    }
  }

  /**
   * Method the retrieve the membership types
   */
  private function setMembershipTypes() {
    try {
      $membershipTypes = civicrm_api3('MembershipType', 'get', array());
      foreach ($membershipTypes['values'] as $membershipType) {
        $this->_membershipTypes[$membershipType['id']] = $membershipType;
      }
    } catch (CiviCRM_API3_Exception $ex) {
      $this->_membershipTypes = array();
    }
  }

  /**
   * Function to generate the contribution source value for a membership
   *
   * @param object $membership
   * @return string $source
   */
  private function generateContributionSource($membership) {
    $source = $this->_membershipTypes[$membership->membership_type_id]['name'].' Lidmaatschap: Offline inschrijving';
    try {
      $displayName = civicrm_api3('Contact', 'getvalue', array(
        'id' => $membership->contact_id,
        'return' => 'display_name'));
      $source .= ' (met '.$displayName.' )';
    } catch (CiviCRM_API3_Exception $ex) {}
    return $source;
  }

  /**
   * Method to find the membership amount for a membership type
   *
   * @param int $membershipTypeId
   * @return int
   */
  private function findMembershipAmount($membershipTypeId) {
    if (isset($this->_membershipTypes[$membershipTypeId]['minimum_fee'])) {
      return $this->_membershipTypes[$membershipTypeId]['minimum_fee'];
    } else {
      return 0;
    }
  }

  /**
   * Method to find financial type id for a membership type
   *
   * @param int $membershipTypeId
   * @return int
   */
  private function findFinancialTypeId($membershipTypeId) {
    if (isset($this->_membershipTypes[$membershipTypeId]['financial_type_id'])) {
      return $this->_membershipTypes[$membershipTypeId]['financial_type_id'];
    } else {
      return 2;
    }
  }

  /**
   * Method to add a contribution for a membership
   *
   * @param object $membership
   * @return mixed
   */
  public function addMembership($membership) {
    $this->_insertClauses[] = 'contact_id = %1';
    $this->_insertParams[1] = array($membership->contact_id, 'Integer');
    $this->_insertClauses[] = 'financial_type_id = %2';
    $this->_insertParams[2] = array($this->findFinancialTypeId($membership->membership_type_id), 'Integer');
    $this->_insertClauses[] = 'payment_instrument_id = %3';
    $this->_insertParams[3] = array($this->_paymentInstrumentId, 'Integer');
    $this->_insertClauses[] = 'receive_date = %4';
    $this->_insertParams[4] = array($membership->start_date, 'String');
    $this->_insertClauses[] = 'total_amount = %5';
    $this->_insertClauses[] = 'net_amount = %5';
    $this->_insertParams[5] = array($this->findMembershipAmount($membership->membership_type_id), 'Money');
    $this->_insertClauses[] = 'currency = %6';
    $this->_insertParams[6] = array($this->_currency, 'String');
    $this->_insertClauses[] = 'source = %7';
    $this->_insertParams[7] = array($this->generateContributionSource($membership), 'String');
    $this->_insertClauses[] = 'is_test = %8';
    $this->_insertParams[8] = array($membership->is_test, 'Integer');
    $this->_insertClauses[] = 'is_pay_later = %9';
    $this->_insertParams[9] = array($membership->is_pay_later, 'Integer');
    $this->_insertClauses[] = 'contribution_status_id = %10';
    $this->_insertParams[10] = array($this->_completedStatusId, 'Integer');
    $insertQuery = 'INSERT INTO civicrm_contribution SET '.implode(', ', $this->_insertClauses);
    CRM_Core_DAO::executeQuery($insertQuery, $this->_insertParams);
    return $this->getCreatedContributionId();
  }

  /**
   * Method to get the created contribution id
   *
   * @return null|string
   */
  private function getCreatedContributionId() {
    $query = 'SELECT id FROM civicrm_contribution WHERE contact_id = %1 AND financial_type_id = %2 
      AND source = %3 AND contribution_status_id = %4 AND payment_instrument_id = %5 
      ORDER BY id DESC LIMIT 1';
    $params = array(
      1 => $this->_insertParams[1],
      2 => $this->_insertParams[2],
      3 => $this->_insertParams[7],
      4 => $this->_insertParams[10],
      5 => $this->_insertParams[3]);
    return CRM_Core_DAO::singleValueQuery($query, $params);
  }
}