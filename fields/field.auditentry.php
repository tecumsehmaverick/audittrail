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
			return true;
		}
		
		public function canPrePopulate() {
			return false;
		}
		
		public function isSortable() {
			return true;
		}
		
	/*-------------------------------------------------------------------------
		Settings:
	-------------------------------------------------------------------------*/
		
		public function displaySettingsPanel(&$wrapper, $errors = null, $append_before = null, $append_after = null) {
			parent::displaySettingsPanel($wrapper, $errors);
			
			$this->appendShowColumnCheckbox($wrapper);
		}
		
	/*-------------------------------------------------------------------------
		Publish:
	-------------------------------------------------------------------------*/
		
		public function displayPublishPanel(&$wrapper, $data = null, $error = null, $prefix = null, $postfix = null, $entry_id = null) {
			$this->_driver->addPublishHeaders($this->_engine->Page);
			$em = new EntryManager(Administration::instance());
			$element_name = $this->get('element_name');
			
			$label = new XMLElement('div');
			$label->setAttribute('class', 'label');
			$span = new XMLElement('span');
			
			$button = new XMLElement('button');
			$button->setAttribute('name', "fields{$prefix}[$element_name]{$postfix}");
			$button->setAttribute('value', 'restore');
			$button->setAttribute('type', 'submit');
			
			if (@is_null($data['source_entry']) or @is_null($data['source_section'])) {
				$button->setValue(__('Undelete Entry'));
				$button->setAttribute('disabled', 'disabled');
			}
			
			else {
				$entry = @array_shift($em->fetch($data['source_entry']));
				
				if (is_null($entry)) {
					$button->setValue(__('Undelete Entry'));
				}
				
				else {
					$button->setValue(__('Restore Entry'));
				}
			}
			
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
		
		public function prepareTableValue($data, XMLElement $link = null) {
			$em = new EntryManager(Administration::instance()); $value = '';
			$max_length = $this->_engine->Configuration->get('cell_truncation_length', 'symphony');
			$max_length = ($max_length ? $max_length : 75);
			
			$entry_id = $data['source_entry'];
			$entry = @array_shift($em->fetch($entry_id));
			
			if ($entry instanceof Entry) {
				$value = $this->_driver->getEntryValue($entry);
				
				if (!$link instanceof XMLElement) {
					$link = $this->_driver->getEntryLink($entry);
				}
			}
			
			$value = (strlen($value) <= $max_length ? $value : substr($value, 0, $max_length) . '...');
			
			if ($value == '') $value = __('None');
			
			if ($link) {
				$link->setValue($value . ' &#x2192;');
				
				return $link->generate();
			}
			
			return $value;
		}
		
	/*-------------------------------------------------------------------------
		Filtering:
	-------------------------------------------------------------------------*/
		
		public function displayDatasourceFilterPanel(&$wrapper, $data = null, $errors = null, $prefix = null, $postfix = null) {
			$field_id = $this->get('id');
			
			$wrapper->appendChild(new XMLElement(
				'h4', sprintf(
					'%s <i>%s</i>',
					$this->get('label'),
					$this->name()
				)
			));
			
			$prefix = ($prefix ? "[{$prefix}]" : '');
			$postfix = ($postfix ? "[{$postfix}]" : '');
			
			$label = Widget::Label('Value');
			$label->appendChild(Widget::Input(
				"fields[filter]{$prefix}[{$field_id}]{$postfix}",
				($data ? General::sanitize($data) : null)
			));	
			$wrapper->appendChild($label);
			
			$help = new XMLElement('p');
			$help->setAttribute('class', 'help');
			$help->setValue(__('To do a negative filter, prefix the value with <code>not:</code>.'));
			
			$wrapper->appendChild($help);
		}
		
		public function buildDSRetrivalSQL($data, &$joins, &$where, $andOperation = false) {
			$field_id = $this->get('id');
			$method_not = false;
			
			// Find mode:
			if (preg_match('/^(not):/', $data[0], $match)) {
				$data[0] = trim(substr($data[0], strlen(next($match)) + 1));
				$name = 'method_' . current($match); $$name = true;
			}
			
			if ($andOperation) {
				$match = ($method_not ? '!=' : '=');
				
				foreach ($data as $value) {
					$this->_key++;
					$value = $this->cleanValue($value);
					$joins .= "
						LEFT JOIN
							`tbl_entries_data_{$field_id}` AS t{$field_id}_{$this->_key}
							ON (e.id = t{$field_id}_{$this->_key}.entry_id)
					";
					$where .= "
						AND t{$field_id}_{$this->_key}.source_entry {$match} '{$value}'
					";
				}
				
			} else {
				$match = ($method_not ? 'NOT IN' : 'IN');
				
				if (!is_array($data)) $data = array($data);
				
				foreach ($data as &$value) {
					$value = $this->cleanValue($value);
				}
				
				$this->_key++;
				$data = implode("', '", $data);
				$joins .= "
					LEFT JOIN
						`tbl_entries_data_{$field_id}` AS t{$field_id}_{$this->_key}
						ON (e.id = t{$field_id}_{$this->_key}.entry_id)
				";
				$where .= "
					AND t{$field_id}_{$this->_key}.source_entry {$match} ('{$data}')
				";
			}

			return true;
		}
		
	/*-------------------------------------------------------------------------
		Sorting:
	-------------------------------------------------------------------------*/
		
		public function buildSortingSQL(&$joins, &$where, &$sort, $order = 'ASC') {
			$field_id = $this->get('id');
			
			$joins .= "LEFT OUTER JOIN `tbl_entries_data_{$field_id}` AS ed ON (e.id = ed.entry_id) ";
			$sort = 'ORDER BY ' . (strtolower($order) == 'random' ? 'RAND()' : "ed.source_entry {$order}");
		}
	}
	
?>