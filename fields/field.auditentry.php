<?php
	
	if (!defined('__IN_SYMPHONY__')) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');
	
	require_once(TOOLKIT . '/class.xsltprocess.php');
	
	class FieldAuditEntry extends Field {
		protected $_driver = null;
		
	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/
		
		public function __construct(&$parent) {
			parent::__construct($parent);
			
			$this->_name = 'Audit Entry';
			$this->_required = true;
			$this->_driver = $this->_engine->ExtensionManager->create('audittrail');
			
			// Set defaults:
			$this->set('show_column', 'yes');
		}
		
		public function createTable() {
			$field_id = $this->get('id');
			
			return $this->_engine->Database->query("
				CREATE TABLE IF NOT EXISTS `tbl_entries_data_{$field_id}` (
					`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
					`entry_id` INT(11) UNSIGNED NOT NULL,
					`value` INT(11) UNSIGNED DEFAULT NULL,
					PRIMARY KEY (`id`),
					KEY `entry_id` (`entry_id`),
					KEY `value` (`value`)
				)
			");
		}
		
		public function allowDatasourceOutputGrouping() {
			return false;
		}
		
		public function allowDatasourceParamOutput() {
			return false;
		}
		
		public function canFilter() {
			return false;
		}
		
		public function canPrePopulate() {
			return false;
		}
		
		public function isSortable() {
			return false;
		}
		
	/*-------------------------------------------------------------------------
		Publish:
	-------------------------------------------------------------------------*/
		
		public function displayPublishPanel(&$wrapper, $data = null, $error = null, $prefix = null, $postfix = null, $entry_id = null) {
			$this->_driver->addPublishHeaders($this->_engine->Page);
			$element_name = $this->get('element_name');
			
			if (empty($data)) {
				$data = __('No audited data to show.');
			}
			
			if (is_array($data)) {
				$data = array_shift($data);
			}
			
			$label = new XMLElement('div');
			$label->setAttribute('class', 'label');
			$span = new XMLElement('span');
			
			$button = new XMLElement('button');
			$button->setAttribute('name', "fields{$prefix}[$element_name]{$postfix}");
			$button->setAttribute('value', 'restore');
			$button->setAttribute('type', 'submit');
			$button->setValue(__('Restore Entry'));
			
			$span->appendChild($button);
			$label->appendChild($span);
			
			if ($entry_id) $wrapper->appendChild($label);
		}
		
	/*-------------------------------------------------------------------------
		Input:
	-------------------------------------------------------------------------*/
		
		public function checkPostFieldData($data, &$message, $entry_id = null) {
			return self::__OK__;
		}
		
		public function processRawFieldData($data, &$status, $simulate = false, $entry_id = null) {
			$status = self::__OK__;
			
			// Entry data cannot be changed:
			$values = $this->_engine->Database->fetchRow(0, sprintf(
				"
					SELECT
						f.source_entry, f.source_section
					FROM
						`tbl_entries_data_%s` AS f
					WHERE
						f.entry_id = '%s'
					LIMIT 1
				",
				$this->get('id'),
				$entry_id
			));
			
			// Restore entry:
			if ($data == 'restore') {
				$this->_driver->restore($entry_id);
			}
			
			if (!empty($values)) {
				$data = $values;
			}
			
			return $data;
		}
		
	/*-------------------------------------------------------------------------
		Output:
	-------------------------------------------------------------------------*/
		
		public function appendFormattedElement(&$wrapper, $data, $encode = false, $mode = null) {
			$wrapper->appendChild(new XMLElement(
				$this->get('element_name'),
				General::sanitize($value)
			));
		}
	}
	
?>