<?php

/**
 * Class for Domus Medica Entity Tag Migration to CiviCRM
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date July 2016
 * @license AGPL-3.0
 */
class CRM_Migratie_EntityTag extends CRM_Migratie_Domus {

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
        $insertQuery = 'INSERT INTO civicrm_entity_tag SET '.implode(', ', $this->_insertClauses);
        try {
          CRM_Core_DAO::executeQuery($insertQuery, $this->_insertParams);
          return TRUE;
        } catch (Exception $ex) {
          $this->_logger->logMessage('Error', 'Error from CRM_Core_DAO::executeQuery, could not insert entity_tag with data '
            .implode('; ', $this->_sourceData).', not migrated. Error message : '.$ex->getMessage());
        }         
      } else {
        $this->_logger->logMessage('Error', 'Could not find a contact with contact_id '
          .$this->_sourceData['entity_id'].' for entity_tag, not migrated.');
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
    $this->_insertClauses[] = 'entity_table = %1';
    $this->_insertParams[1] = array($this->_sourceData['entity_table'], 'String');
    $this->_insertClauses[] = 'entity_id = %2';
    $this->_insertParams[2] = array($this->_sourceData['entity_id'], 'Integer');
    $this->_insertClauses[] = 'tag_id = %3';
    $this->_insertParams[3] = array($this->_sourceData['tag_id'], 'Integer');
  }

  /**
   * Implementation of method to validate if source data is good enough for entity_tag
   *
   * @return bool
   */
  public function validSourceData() {
    if (!isset($this->_sourceData['entity_id'])) {
      $this->_logger->logMessage('Error', 'EntityTag has no entity_id, not migrated. Source data is '.implode(';', $this->_sourceData));
      return FALSE;
    }
    if (empty($this->_sourceData['tag_id'])) {
      $this->_logger->logMessage('Error', 'EntityTag has no tag_id, not migrated. Source data is '.implode(';', $this->_sourceData));
      return FALSE;
    }
    return TRUE;
  }
}