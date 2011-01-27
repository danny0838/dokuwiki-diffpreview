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
		$controller->register_hook('TPL_METAHEADER_OUTPUT', 'BEFORE',  $this, '_tpl_metaheader_output');
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
	
	function _tpl_metaheader_output(&$event, $param) {
		global $conf;
		global $INFO;
		
		if(!$this->_change_headers) return;
		
		for($i = 0; $i < count($event->data['script']); $i++) {
			if(false !== strpos($event->data['script'][$i]['src'], DOKU_BASE."lib/exe/js.php?edit=0")) {
				$event->data['script'][$i]['src'] = DOKU_BASE.'lib/exe/js.php?edit=1&write=1';
				break;
			}
		}
		if($i < count($event->data['script'])) {
			$script = "NS='".$INFO['namespace']."';";
			if($conf['useacl'] && $_SERVER['REMOTE_USER']){
				require_once(DOKU_INC.'inc/toolbar.php');
				$script .= "SIG='".toolbar_signature()."';";
			}
			array_unshift($event->data['script'], array( 'type'=>'text/javascript', 'charset'=>'utf-8',
										'_data'=> $script));
		}
	}
}
