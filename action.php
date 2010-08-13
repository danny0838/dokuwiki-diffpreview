<?php

if(!defined('DOKU_INC')) die();
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'action.php');
require_once(DOKU_INC.'inc/form.php');
require_once(DOKU_INC.'inc/DifferenceEngine.php');

class UnifiedTableDiffFormatter extends DiffFormatter {
	function UnifiedTableDiffFormatter($context_lines = 3) {
		$this->leading_context_lines = $context_lines;
		$this->trailing_context_lines = $context_lines;
	}

	function format($diff) {
		$val = parent::format($diff);
		$val = str_replace('  ','&nbsp; ', $val);
		$val = str_replace("\t",'&nbsp;&nbsp;', $val);
		return $val;
	}

	function _lineNumber($number = "") {
		return "<td class=\"line_numbers\">$number</td>";
	}

	function _block_header($xbeg, $xlen, $ybeg, $ylen) {
		$this->_x = $xbeg;
		$this->_y = $ybeg;
		if ($xlen != 1)
			$xbeg .= "," . $xlen;
		if ($ylen != 1)
			$ybeg .= "," . $ylen;
		return "<tr>".$this->_lineNumber("...")."".$this->_lineNumber("...")."<td class=\"gc\">@@ -$xbeg +$ybeg @@</td>\n";
	}

	function _added($lines) {
		foreach ($lines as $line) {
			print("<tr>".$this->_lineNumber()."".$this->_lineNumber($this->_y)."<td class=\"line_add\">+$line</td></tr>\n");
			$this->_y++;
		}
	}

	function _deleted($lines) {
		foreach ($lines as $line) {
			print("<tr>".$this->_lineNumber($this->_x)."".$this->_lineNumber()."<td class=\"line_del\">-$line</td></tr>\n");
			$this->_x++;
		}
	}

	function _context( $lines ) {
		foreach ($lines as $line) {
			print("<tr>".$this->_lineNumber($this->_x)."".$this->_lineNumber($this->_y)."<td>&nbsp;$line</td></tr>\n");
			$this->_x++;
			$this->_y++;
		}
	}

	function _changed($orig, $final) {
		$this->_deleted($orig);
		$this->_added($final);
	}
}


class action_plugin_diffpreview extends DokuWiki_Action_Plugin {

	function action_plugin_diffpreview() {
		$this->_change_headers = false;
	}
	
	/**
	 * return some info
	 */
	function getInfo(){
		return array(
	 'author' => 'Mikhail I. Izmestev',
	 'email'  => 'izmmishao5@gmail.com',
	 'date'   => '2010-01-10',
	 'name'   => 'diffpreview',
	 'desc'   => 'add diff preview',
	 'url'    => '',
		);
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
			$event->data->insertElement($preview, form_makeButton('submit', 'changes', $this->getLang('changes'), array('id' => 'edbtn__changes')));
		}
	}
	
	function _unified_diff($text='',$intro=true) {
	// This is just copy of html_diff until diff generation and output

		global $ID;
		global $REV;
		global $lang;
		global $conf;
		
		// we're trying to be clever here, revisions to compare can be either
		// given as rev and rev2 parameters, with rev2 being optional. Or in an
		// array in rev2.
		$rev1 = $REV;

		if(is_array($_REQUEST['rev2'])){
			$rev1 = (int) $_REQUEST['rev2'][0];
			$rev2 = (int) $_REQUEST['rev2'][1];

			if(!$rev1){
				$rev1 = $rev2;
				unset($rev2);
			}
		}else{
			$rev2 = (int) $_REQUEST['rev2'];
		}

		if($text){                      // compare text to the most current revision
			$l_rev   = '';
			$l_text  = rawWiki($ID,'');
			$l_head  = '<a class="wikilink1" href="'.wl($ID).'">'.
				$ID.' '.dformat((int) @filemtime(wikiFN($ID))).'</a> '.
				$lang['current'];

			$r_rev   = '';
			$r_text  = cleanText($text);
			$r_head  = $lang['yours'];
		}else{
			if($rev1 && $rev2){            // two specific revisions wanted
				// make sure order is correct (older on the left)
				if($rev1 < $rev2){
					$l_rev = $rev1;
					$r_rev = $rev2;
				}else{
					$l_rev = $rev2;
					$r_rev = $rev1;
				}
			}elseif($rev1){                // single revision given, compare to current
				$r_rev = '';
				$l_rev = $rev1;
			}else{                        // no revision was given, compare previous to current
				$r_rev = '';
				$revs = getRevisions($ID, 0, 1);
				$l_rev = $revs[0];
				$REV = $l_rev; // store revision back in $REV
			}

			// when both revisions are empty then the page was created just now
			if(!$l_rev && !$r_rev){
				$l_text = '';
			}else{
				$l_text = rawWiki($ID,$l_rev);
			}
			$r_text = rawWiki($ID,$r_rev);

			if(!$l_rev){
				$l_head = '&mdash;';
			}else{
				$l_info   = getRevisionInfo($ID,$l_rev,true);
				if($l_info['user']){
					$l_user = editorinfo($l_info['user']);
					if(auth_ismanager()) $l_user .= ' ('.$l_info['ip'].')';
				} else {
					$l_user = $l_info['ip'];
				}
				$l_user  = '<span class="user">'.$l_user.'</span>';
				$l_sum   = ($l_info['sum']) ? '<span class="sum">'.hsc($l_info['sum']).'</span>' : '';
				if ($l_info['type']===DOKU_CHANGE_TYPE_MINOR_EDIT) $l_minor = 'class="minor"';

				$l_head = '<a class="wikilink1" href="'.wl($ID,"rev=$l_rev").'">'.
				$ID.' ['.dformat($l_rev).']</a>'.
				'<br />'.$l_user.' '.$l_sum;
			}

			if($r_rev){
				$r_info   = getRevisionInfo($ID,$r_rev,true);
				if($r_info['user']){
					$r_user = editorinfo($r_info['user']);
					if(auth_ismanager()) $r_user .= ' ('.$r_info['ip'].')';
				} else {
					$r_user = $r_info['ip'];
				}
				$r_user = '<span class="user">'.$r_user.'</span>';
				$r_sum  = ($r_info['sum']) ? '<span class="sum">'.hsc($r_info['sum']).'</span>' : '';
				if ($r_info['type']===DOKU_CHANGE_TYPE_MINOR_EDIT) $r_minor = 'class="minor"';

				$r_head = '<a class="wikilink1" href="'.wl($ID,"rev=$r_rev").'">'.
				$ID.' ['.dformat($r_rev).']</a>'.
				'<br />'.$r_user.' '.$r_sum;
			}elseif($_rev = @filemtime(wikiFN($ID))){
				$_info   = getRevisionInfo($ID,$_rev,true);
				if($_info['user']){
					$_user = editorinfo($_info['user']);
					if(auth_ismanager()) $_user .= ' ('.$_info['ip'].')';
				} else {
					$_user = $_info['ip'];
				}
				$_user = '<span class="user">'.$_user.'</span>';
				$_sum  = ($_info['sum']) ? '<span class="sum">'.hsc($_info['sum']).'</span>' : '';
				if ($_info['type']===DOKU_CHANGE_TYPE_MINOR_EDIT) $r_minor = 'class="minor"';

				$r_head  = '<a class="wikilink1" href="'.wl($ID).'">'.
				$ID.' ['.dformat($_rev).']</a> '.
				'('.$lang['current'].')'.				'<br />'.$_user.' '.$_sum;
			}else{
				$r_head = '&mdash; ('.$lang['current'].')';
			}
		}

		$df = new Diff(explode("\n",htmlspecialchars($l_text)), explode("\n",htmlspecialchars($r_text)));
		$tdf = new UnifiedTableDiffFormatter();

		print p_locale_xhtml('diff');

		if (!$text) {
			$diffurl = wl($ID, array('do'=>'diff', 'rev2[0]'=>$l_rev, 'rev2[1]'=>$r_rev));
			ptln('<p class="difflink">');
			ptln('  <a class="wikilink1" href="'.$diffurl.'">'.$lang['difflink'].'</a>');
			ptln('</p>');
		}

		echo '<table>';
		echo $tdf->format($df);
		echo '</table>';
	}

	function _tpl_act_changes(&$event, $param) {
		global $TEXT;
		global $PRE;
		global $SUF;
		global $ID;
	
		switch($event->data)
		{
		case 'changes':
			html_edit($TEXT);
			echo '<br id="scroll__here" />';
			if('unified' == $this->getconf('diff_type'))
				$this->_unified_diff(con($PRE,$TEXT,$SUF));
			else
				html_diff(con($PRE,$TEXT,$SUF));
			$event->preventDefault();
			break;
		case 'unified_diff':
			$this->_unified_diff();
			$event->preventDefault();
			break;
		}
		return;
	}
	
	function _action_act_preprocess(&$event, $param) {
		global $ACT;
		global $INFO;
	
		if(is_array($event->data) && array_key_exists('changes', $event->data)) {
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
		}elseif(is_array($event->data) && array_key_exists('diff', $event->data) 
			|| 'diff' == $event->data) {
			if('unified' == $this->getconf('diff_type'))
			{
				$ACT = 'unified_diff';
				$event->stoppropagation();
				$event->preventDefault();
			}
		}
		return;
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
