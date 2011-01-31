<?php

if(!defined('DOKU_INC')) die();
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'action.php');
require_once(DOKU_INC.'inc/form.php');

class action_plugin_diffpreview extends DokuWiki_Action_Plugin {

	function action_plugin_diffpreview() {
		$this->_change_headers = false;
	}
	
	/**
	 * Register its handlers with the DokuWiki's event controller
	 */
	function register(&$controller) {
		$controller->register_hook('HTML_EDITFORM_OUTPUT', 'BEFORE',  $this, '_edit_form');
		$controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE',  $this, '_action_act_preprocess');
		$controller->register_hook('TPL_ACT_UNKNOWN', 'BEFORE',  $this, '_tpl_act_changes');
	}

	function _edit_form(&$event, $param) {
		$preview = $event->data->findElementById('edbtn__preview');
		if($preview) {
			$event->data->insertElement($preview+1, form_makeButton('submit', 'changes', $this->getLang('changes'), array('id' => 'edbtn__changes')));
		}
	}
	
	function _tpl_act_changes(&$event, $param) {
		global $TEXT;
		global $PRE;
		global $SUF;
				
		if('changes' != $event->data) return;
		
		html_edit($TEXT);
		echo '<br id="scroll__here" />';
		html_diff(con($PRE,$TEXT,$SUF));
		$event->preventDefault();
		return;
	}
	
	function _action_act_preprocess(&$event, $param) {
		global $ACT;
		global $INFO;
		
		if(!is_array($event->data) || !array_key_exists('changes', $event->data)) return;
		
		if('preview' == act_permcheck('preview')
			&& 'preview' == act_draftsave('preview')
			&& $INFO['editable']
			&& 'preview' == act_edit('preview')) {
			$ACT = 'changes';
			$event->stoppropagation();
			$event->preventDefault();
			$this->_change_headers = true;
		}else{
			$ACT = 'preview';
		}
	}
}
