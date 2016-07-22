<?php

/**
 * Class for Domus Medica Note Migration to CiviCRM
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date July 2016
 * @license AGPL-3.0
 */
class CRM_Migratie_Note extends CRM_Migratie_Domus {

  /**
   * Method to migrate incoming data
   * 
   * @return bool|array
   */
  public function migrate() {
    if ($this->validSourceData()) {
      if ($this->contactExists($this->_sourceData['entity_id'])) {
        // set insert clauses and params
        $this->setClausesAndParams();
        $insertQuery = 'INSERT INTO civicrm_note SET '.implode(', ', $this->_insertClauses);
        try {
          CRM_Core_DAO::executeQuery($insertQuery, $this->_insertParams);
          return TRUE;
        } catch (Exception $ex) {
          $this->_logger->logMessage('Error', 'Error from CRM_Core_DAO::executeQuery, could not insert note with data '
            .implode('; ', $this->_sourceData).', not migrated. Error message : '.$ex->getMessage());
        }         
      } else {
        $this->_logger->logMessage('Error', 'Could not find a contact with contact_id '
          .$this->_sourceData['entity_id'].' for note, not migrated.');
      }
    }
    return FALSE;
  }

  /**
   * Implementation of method to set the insert clauses and params for note
   */
  public function setClausesAndParams() {
    $this->_insertClauses[] = 'entity_id = %1';
    $this->_insertClauses[] = 'contact_id = %1';
    $this->_insertParams[1] = array($this->_sourceData['entity_id'], 'Integer');
    $this->_insertClauses[] = 'entity_table = %2';
    $this->_insertParams[2] = array($this->_sourceData['entity_table'], 'String');
    $this->_insertClauses[] = 'note = %3';
    $this->_insertParams[3] = array($this->_sourceData['note'], 'String');
    $this->_insertClauses[] = 'modified_date = %4';
    $this->_insertParams[4] = array(date('Y-m-d'), 'Date');
    $this->_insertClauses[] = 'subject = %5';
    $this->_insertParams[5] = array('Opmerking uit Access Database', 'String');
    $this->_insertClauses[] = 'privacy = %6';
    $this->_insertParams[6] = array(0, 'Integer');
  }

  /**
   * Implementation of method to validate if source data is good enough for note
   *
   * @return bool
   */
  public function validSourceData() {
    if (!isset($this->_sourceData['entity_id'])) {
      $this->_logger->logMessage('Error', 'Note has no entity_id, not migrated. Source data is '.implode(';', $this->_sourceData));
      return FALSE;
    }
    if (empty($this->_sourceData['note'])) {
      $this->_logger->logMessage('Error', 'Note is empty, not migrated. Source data is '.implode(';', $this->_sourceData));
      return FALSE;
    }
    return TRUE;
  }
}