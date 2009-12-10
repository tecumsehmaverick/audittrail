<?php
	
	class Extension_AuditTrail extends Extension {
	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/
		
		protected $initialized = false;
		protected $section = null;
		protected $fields = null;
		
		public function about() {
			return array(
				'name'			=> 'Audit Trail',
				'version'		=> '1.0.0',
				'release-date'	=> '2009-12-10',
				'author'		=> array(
					'name'			=> 'Rowan Lewis',
					'website'		=> 'http://rowanlewis.com/',
					'email'			=> 'me@rowanlewis.com'
				),
				'description'	=> 'Converts date fields into calendars.'
			);
		}
		
		public function getSubscribedDelegates() {
			return array(
				array(
					'page'		=> '/publish/new/',
					'delegate'	=> 'EntryPostCreate',
					'callback'	=> 'logCreation'
				),
				array(
					'page'		=> '/publish/edit/',
					'delegate'	=> 'EntryPostEdit',
					'callback'	=> 'logModification'
				)
			);
		}
		
		public function initialize($context = null) {
			if ($this->initialized) return true;
			
			$this->initialized = true;
			$sm = new SectionManager(Administration::instance());
			
			$this->section = $this->_Parent->Database->fetchVar('id', 0, "
				SELECT
					s.id
				FROM
					`tbl_sections` AS s
				WHERE
					2 = (
						SELECT
							count(*)
						FROM
							`tbl_fields` AS f
						WHERE
							f.parent_section = s.id
							AND f.type IN (
								'auditdump',
								'auditentry'
							)
					)
				LIMIT 1
			");
			
			if (is_null($this->section)) return false;
			
			$fields = $this->_Parent->Database->fetch(sprintf(
				"
					SELECT
						f.type, f.id, f.element_name
					FROM
						`tbl_fields` AS f
					WHERE
						f.parent_section = '%s'
						AND f.type IN (
							'auditdump',
							'auditentry'
						)
				",
				$this->section
			));
			
			foreach ($fields as $field) {
				$this->fields[$field['type']] = (object)array(
					'id'		=> $field['id'],
					'handle'	=> $field['element_name']
				);
			}
			
			if (is_null($this->fields)) return false;
			
			return true;
		}
		
	/*-------------------------------------------------------------------------
		Utilities:
	-------------------------------------------------------------------------*/
		
		protected $addedPublishHeaders = false;
		
		public function addPublishHeaders($page) {
			if (!$page or $this->addedPublishHeaders) return;
			
			$page->addStylesheetToHead(URL . '/extensions/audittrail/assets/publish.css', 'screen', 3112401);
			
			$this->addedPublishHeaders = true;
		}
		
		public function getSection($id) {
			$sm = new SectionManager(Administration::instance());
			
			return $sm->fetch($id);
		}
		
		public function getAuditSection() {
			return $this->getSection($this->section);
		}
		
		public function getEntryLink(Entry $entry) {
			$section = $this->getSection($entry->get('section_id'));
			
			$link = new XMLElement('a');
			$link->setAttribute('href', sprintf(
				'%s/symphony/publish/%s/edit/%d/',
				URL,
				$section->get('handle'),
				$entry->get('id')
			));
			
			return $link;
		}
		
		public function getEntryValue(Entry $entry) {
			if (!$this->initialize()) return '';
			
			$section = $this->getSection($entry->get('section_id'));
			$field = @array_shift($section->fetchVisibleColumns());
			$span = new XMLElement('span');
			
			if (is_null($field)) return '';
			
			$data = $entry->getData($field->get('id'));
			
			if (empty($data)) return '';
			
			$data = $field->prepareTableValue($data, $span);
			
			if ($data instanceof XMLElement) {
				$data = $data->generate();
			}
			
			return strip_tags($data);
		}
		
	/*-------------------------------------------------------------------------
		Restoring:
	-------------------------------------------------------------------------*/
		
		public function restore($audit_id) {
			header('content-type: text/plain');
			
			if (!$this->initialize()) return false;
			
			$admin = Administration::instance();
			$em = new EntryManager($admin);
			$section = $this->getAuditSection();
			
			$audit = @array_shift($em->fetch($audit_id));
			
			if (is_null($audit)) return false;
			
			$data = $audit->getData($this->fields['auditdump']->id);
			$entry_id = $audit->getData($this->fields['auditentry']->id);
			
			if (
				is_null($data) or is_null($data['value'])
				or is_null($entry_id) or is_null($entry_id['source_entry'])
				or is_null($entry_id['source_section'])
			) return false;
			
			$entry = @array_shift($em->fetch($entry_id['source_entry']));
			
			// Entry doesn't exist, create a new one:
			if (is_null($entry)) {
				$entry = $em->create();
				$entry->set('id', $entry_id['source_entry']);
				$entry->set('section_id', $entry_id['source_section']);
				$entry->set('author_id', $admin->Author->get('id'));
				$entry->set('creation_date', DateTimeObj::get('Y-m-d H:i:s'));
				$entry->set('creation_date_gmt', DateTimeObj::getGMT('Y-m-d H:i:s'));
				
				$this->_Parent->Database->insert($entry->_fields, 'tbl_entries');
			}
			
			// Save the entry:
			$data = eval(sprintf('return %s;', $data['value']));
			
			foreach ($data as $field_id => $field_data) {
				$entry->setData($field_id, $field_data);
			}
			
			$entry->commit();
			
			// Redirect to the entry:
			$section = $this->getSection($entry->get('section_id'));
			
		   	redirect(sprintf(
				'%s/symphony/publish/%s/edit/%d/',
				URL,
				$section->get('handle'),
				$entry->get('id')
			));
		}
		
	/*-------------------------------------------------------------------------
		Logging:
	-------------------------------------------------------------------------*/
		
		public function logCreation($context) {
			$this->log('Created', $context['entry']);
		}
		
		public function logModification($context) {
			$this->log('Modified', $context['entry']);
		}
		
		public function log($type, $entry) {
			if (!$this->initialize()) return false;
			
			$admin = Administration::instance();
			$em = new EntryManager($admin);
			$section = $this->getAuditSection();
			$fields = array(
				'author'	=> $admin->Author->get('id'),
				'created'	=> 'now',
				'dump'		=> var_export($entry->getData(), true),
				'entry'		=> array(
					'source_entry'		=> $entry->get('id'),
					'source_section'	=> $entry->get('section_id')
				),
				'type'		=> $type
			);
			
			// No auditing the audits section:
			if ($entry->get('section_id') == $section->get('id')) return false;
			
			$audit = $em->create();
			$audit->set('section_id', $section->get('id'));
			$audit->set('author_id', $admin->Author->get('id'));
			$audit->set('creation_date', DateTimeObj::get('Y-m-d H:i:s'));
			$audit->set('creation_date_gmt', DateTimeObj::getGMT('Y-m-d H:i:s'));
			
			$audit->setDataFromPost($fields, $error);
			$audit->commit();
		}
	}
		
?>