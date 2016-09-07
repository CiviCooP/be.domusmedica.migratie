<?php

/**
 * Class for Domus Medica Relationship Migration to CiviCRM
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date August 2016
 * @license AGPL-3.0
 */
class CRM_Migratie_Relationship extends CRM_Migratie_Domus {

  /**
   * Method to migrate incoming data
   * 
   * @return bool|array
   */
  public function migrate() {
    if ($this->validSourceData()) {
      if ($this->contactExists($this->_sourceData['contact_id_a']) && $this->contactExists($this->_sourceData['contact_id_b'])) {
        // set insert clauses and params
        $this->setClausesAndParams();
        $insertQuery = 'INSERT INTO civicrm_relationship SET '.implode(', ', $this->_insertClauses);
        try {
          CRM_Core_DAO::executeQuery($insertQuery, $this->_insertParams);
          return TRUE;
        } catch (Exception $ex) {
          $this->_logger->logMessage('Error', 'Error from CRM_Core_DAO::executeQuery, could not insert relationship with data '
            .implode('; ', $this->_sourceData).', not migrated. Error message : '.$ex->getMessage());
        }
      } else {
        $this->_logger->logMessage('Error', 'Could not find a contact with contact_id '
          .$this->_sourceData['contact_id_a'].' or '.$this->_sourceData['contact_id_b'].' for relationship, not migrated.');
      }
    }
  }

  /**
   * Implementation of method to set the insert clauses and params for relationship
   */
  public function setClausesAndParams() {
    $this->_insertClauses[] = 'contact_id_a = %1';
    $this->_insertParams[1] = array($this->_sourceData['contact_id_a'], 'Integer');
    $this->_insertClauses[] = 'contact_id_b = %2';
    $this->_insertParams[2] = array($this->_sourceData['contact_id_b'], 'Integer');
    $this->_insertClauses[] = 'relationship_type_id = %3';
    $this->_insertParams[3] = array($this->_sourceData['relationship_type_id'], 'Integer');
    $this->_insertClauses[] = 'is_active = %4';
    $this->_insertParams[4] = array($this->_sourceData['is_active'], 'Integer');
    $this->_insertClauses[] = 'is_permission_a_b = %5';
    $this->_insertParams[5] = array($this->_sourceData['is_permission_a_b'], 'Integer');
    $this->_insertClauses[] = 'is_permission_b_a = %6';
    $this->_insertParams[6] = array($this->_sourceData['is_permission_b_a'], 'Integer');
  }

  /**
   * Method to check if relationship type is valid
   *
   * @return bool
   * @access private
   */

  private function validRelationshipType() {
    try {
      $count = civicrm_api3('RelationshipType', 'getcount', array(
        'id' => $this->_sourceData['relationship_type_id']));
      if ($count != 1) {
        $this->_logger->logMessage('Warning', 'Relationship with contact_id_a '.$this->_sourceData['contact_id_a']
          .' and contact_id_b '.$this->_sourceData['contact_id_b']. ' does not have a valid relationship_type_id ('
          .$count. ' of '.$this->_sourceData['relationship_type_id'].'found), not migrated');
        return FALSE;
      }
    } catch (CiviCRM_API3_Exception $ex) {
      $this->_logger->logMessage('Error', 'Error retrieving relationship_type_id from CiviCRM for relationship with contact_id_a '
        .$this->_sourceData['contact_id_a'].' and contact_id_b '.$this->_sourceData['contact_id_b']. ' and 
        relationship_type_id' . $this->_sourceData['relationship_type_id'] . ', not migrated. Error from API 
        RelationshipType getcount : ' . $ex->getMessage());
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Implementation of method to validate if source data is good enough for relationship
   *
   * @return bool
   */
  public function validSourceData() {
    if (!isset($this->_sourceData['contact_id_a'])) {
      $this->_logger->logMessage('Error', 'Relationship has no contact_id_a, relationship not migrated. Source data is '.implode(';', $this->_sourceData));
      return FALSE;
    }

    if (!isset($this->_sourceData['contact_id_b'])) {
      $this->_logger->logMessage('Error', 'Relationship has no contact_id_b, relationship not migrated. Source data is '.implode(';', $this->_sourceData));
      return FALSE;
    }

    if (empty($this->_sourceData['relationship_type_id'])) {
      $this->_logger->logMessage('Error', 'Relationship has an empty relationship_type_id, relationship not migrated. Source data is '.implode(';', $this->_sourceData));
      return FALSE;
    }

    if (!$this->validRelationshipType()) {
      return FALSE;
    }
    return TRUE;
  }
}