<?php
/**
 * Set dependency on PF and register the new Input Type
 *
 * @author Felix Ashu Aba
 * @file
 * @group GesinnIT
 */

class EditAccountHooks {

	public static function onBeforePageDisplay( OutputPage &$out, $skin ) {
             
		$out->addModules( 'ext.editAccount' );
	}
}