<?php

/**
 * Class for Domus Medica Membership Migration to CiviCRM
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date Sep 2016
 * @license AGPL-3.0
 */
class CRM_Migratie_Membership extends CRM_Migratie_Domus {

  /**
   * Method to migrate incoming data
   * 
   * @return bool|array
   */
  public function migrate() {
    if ($this->validSourceData()) {
      // set insert clauses and params
      $this->setClausesAndParams();
      $insertQuery = 'INSERT INTO civicrm_membership SET '.implode(', ', $this->_insertClauses);
      try {
        CRM_Core_DAO::executeQuery($insertQuery, $this->_insertParams);
        $membership = $this->getCreatedMembership();
        $membershipLog = new CRM_Migratie_MembershipLog();
        $membershipLog->addMembership($membership);
        $contribution = new CRM_Migratie_Contribution();
        $contributionId = $contribution->addMembership($membership);
        $membershipPayment = new CRM_Migratie_MembershipPayment();
        $membershipPayment->addMembership($membership->id, $contributionId);
        $this->addCustomData($membership->id, $membership->contact_id);
        return TRUE;
      } catch (Exception $ex) {
        $this->_logger->logMessage('Error', 'Error from CRM_Core_DAO::executeQuery, could not insert membership with data '
          .implode('; ', $this->_sourceData).', not migrated. Error message : '.$ex->getMessage());
      }
    }
    return FALSE;
  }

  /**
   * Method to set membership custom data
   *
   * @param int $membershipId
   * @param int $contactId
   */
  private function addCustomData($membershipId, $contactId) {
    $customClauses = array();
    $customParams = array();
    // get custom data for membership
    $selectQuery = 'SELECT * FROM domus_value_membership_data WHERE entity_id = %1';
    $customData = CRM_Core_DAO::executeQuery($selectQuery, array(1 => array($contactId, 'Integer')));
    if ($customData->fetch()) {
      $customClauses[] = 'entity_id = %1';
      $customParams[1] = array($membershipId, 'Integer');
      $customClauses[] = 'authorization_dd = %2';
      $customParams[2] = array($customData->authorization_dd, 'Integer');
      $index = 2;
      if (!empty($customData->latest_update)) {
        $index++;
        $customClauses[] = 'latest_update = %'.$index;
        $customParams[$index] = array($customData->latest_update, 'String');
      }
      if (!empty($customData->note)) {
        $index++;
        $customClauses[] = 'note = %'.$index;
        $customParams[$index] = array($customData->note, 'String');
      }
      $customQuery = 'INSERT INTO civicrm_value_membership_data SET '.implode(', ', $customClauses);
      CRM_Core_DAO::executeQuery($customQuery, $customParams);
    }
  }

  /**
   * Implementation of method to set the insert clauses and params for address
   * 
   * @access private
   */
  public function setClausesAndParams() {
    $this->_insertClauses[] = 'contact_id = %1';
    $this->_insertParams[1] = array($this->_sourceData['contact_id'], 'Integer');
    $this->_insertClauses[] = 'membership_type_id = %2';
    $this->_insertParams[2] = array($this->_sourceData['membership_type_id'], 'Integer');
    $this->_insertClauses[] = 'join_date = %3';
    $this->_insertParams[3] = array($this->_sourceData['join_date'], 'String');
    $this->_insertClauses[] = 'start_date = %4';
    $this->_insertParams[4] = array($this->_sourceData['start_date'], 'String');
    $this->_insertClauses[] = 'status_id = %5';
    $this->_insertParams[5] = array($this->_sourceData['status_id'], 'Integer');
    $this->_insertClauses[] = 'source = %6';
    $this->_insertParams[6] = array($this->_sourceData['source'], 'String');
    $this->_insertClauses[] = 'is_test = %7';
    $this->_insertClauses[] = 'is_pay_later = %7';
    $this->_insertParams[7] = array(0, 'Integer');
    if (!empty($this->_sourceData['end_date'])) {
      $this->_insertClauses[] = 'end_date = %8';
      $this->_insertParams[8] = array($this->_sourceData['end_date'], 'String');
    }
  }
  
  /**
   * Implementation of method to validate if source data is good enough for membership
   *
   * @return bool
   */
  public function validSourceData() {

    if (!isset($this->_sourceData['contact_id'])) {
      $this->_logger->logMessage('Error', 'Membership has no contact_id, Membership not migrated. Source data is '.implode(';', $this->_sourceData));
      return FALSE;
    }
    if (!$this->validMembershipType() || !$this->validMembershipStatus()) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Method to retrieve created membership data
   *
   * @return bool|CRM_Core_DAO|object
   */
  private function getCreatedMembership() {
    $selectQuery = 'SELECT * FROM civicrm_membership WHERE contact_id = %1 
          AND civicrm_membership.membership_type_id = %2 AND source = %3 ORDER BY id DESC LIMIT 1';
    $selectParams = array(
      1 => $this->_insertParams[1],
      2 => $this->_insertParams[2],
      3 => $this->_insertParams[6]);
    $membership = CRM_Core_DAO::executeQuery($selectQuery, $selectParams);
    if ($membership->fetch()) {
      return $membership;
    } else {
      return FALSE;
    }
  }
}