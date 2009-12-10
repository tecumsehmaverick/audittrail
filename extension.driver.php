<?php
	
	class Extension_AuditTrail extends Extension {
	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/
		
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
		
		/*
		$this->_Parent->ExtensionManager->notifyMembers('EntryPostCreate', '/publish/new/', array('section' => $section, 'entry' => $entry, 'fields' => $fields));
		*/
		
		/*
		$this->_Parent->ExtensionManager->notifyMembers('EntryPostEdit', '/publish/edit/', array('section' => $section, 'entry' => $entry, 'fields' => $fields));
		*/
		
	/*-------------------------------------------------------------------------
		Utilities:
	-------------------------------------------------------------------------*/
		
		protected $addedPublishHeaders = false;
		
		public function addPublishHeaders($page) {
			if (!$page or $this->addedPublishHeaders) return;
			
			$page->addStylesheetToHead(URL . '/extensions/audittrail/assets/publish.css', 'screen', 3112401);
			$page->addScriptToHead(URL . '/extensions/audittrail/assets/publish.js', 3112401);
			
			$this->addedPublishHeaders = true;
		}
		
		public function getSection($handle) {
			$sm = new SectionManager(Administration::instance());
			$id = $sm->fetchIDFromHandle($handle);
			
			return $sm->fetch($id);
		}
		
		public function getAuditSection() {
			// TODO: Pull section handle from configuration.
			$handle = 'audit-trail';
			
			return $this->getSection($handle);
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
			header('content-type: text/plain');
			
			$em = new EntryManager(Administration::instance());
			$section = $this->getAuditSection();
			$fields = array(
				'created'	=> 'now',
				'content'	=> var_export($entry->getData(), true),
				'type'		=> $type
			);
			
			// No auditing the audits section:
			if ($entry->get('section_id') == $section->get('id')) return false;
			
			$audit = $em->create();
			$audit->set('section_id', $section->get('id'));
			$audit->set('author_id', $entry->get('author_id'));
			$audit->set('creation_date', DateTimeObj::get('Y-m-d H:i:s'));
			$audit->set('creation_date_gmt', DateTimeObj::getGMT('Y-m-d H:i:s'));
			
			$audit->setDataFromPost($fields, $error);
			$audit->commit();
		}
	}
		
?>