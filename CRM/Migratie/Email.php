<?php

/**
 * Class for Domus Medica Email Migration to CiviCRM
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date July 2016
 * @license AGPL-3.0
 */
class CRM_Migratie_Email extends CRM_Migratie_Domus {

  /**
   * Method to migrate incoming data
   * 
   * @return bool|array
   */
  public function migrate() {
    if ($this->validSourceData()) {
      if ($this->contactExists($this->_sourceData['contact_id'])) {
        // set insert clauses and params
        $this->setClausesAndParams();
        $insertQuery = 'INSERT INTO civicrm_email SET '.implode(', ', $this->_insertClauses);
        try {
          CRM_Core_DAO::executeQuery($insertQuery, $this->_insertParams);
          return TRUE;
        } catch (Exception $ex) {
          $this->_logger->logMessage('Error', 'Error from CRM_Core_DAO::executeQuery, could not insert email with data '
            .implode('; ', $this->_sourceData).', not migrated. Error message : '.$ex->getMessage());
        }         
      } else {
        $this->_logger->logMessage('Error', 'Could not find a contact with contact_id '
          .$this->_sourceData['contact_id'].' for email, not migrated.');
      }
    }
    return FALSE;
  }

  /**
   * Implementation of method to set the insert clauses and params for email
   * 
   * @access private
   */
  public function setClausesAndParams() {
    $this->checkIsPrimary();
    $this->_insertClauses[] = 'contact_id = %1';
    $this->_insertParams[1] = array($this->_sourceData['contact_id'], 'Integer');
    $this->_insertClauses[] = 'is_primary = %2';
    $this->_insertParams[2] = array($this->_sourceData['is_primary'], 'Integer');
    $this->_insertClauses[] = 'location_type_id = %3';
    $this->_insertParams[3] = array($this->_sourceData['location_type_id'], 'Integer');
    $this->_insertClauses[] = 'is_billing = %4';
    $this->_insertParams[4] = array(0, 'Integer');
    $this->_insertClauses[] = 'on_hold = %4';
    $this->_insertClauses[] = 'is_bulkmail = %4';
    $this->_insertClauses[] = 'email = %5';
    $this->_insertParams[5] = array($this->_sourceData['email'], 'String');
  }

  /**
   * Implementation of method to validate if source data is good enough for email
   *
   * @return bool
   */
  public function validSourceData() {

    if (!isset($this->_sourceData['contact_id'])) {
      $this->_logger->logMessage('Error', 'Email has no contact_id, email not migrated. Source data is '.implode(';', $this->_sourceData));
      return FALSE;
    }

    if (empty($this->_sourceData['email'])) {
      $this->_logger->logMessage('Error', 'Email has an empty emailaddress, email not migrated. Source data is '.implode(';', $this->_sourceData));
      return FALSE;
    }

    if (!$this->validLocationType()) {
      return FALSE;
    }
    return TRUE;
  }
}