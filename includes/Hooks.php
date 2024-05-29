<?php

namespace MediaWiki\Extension\EditAccount;

class EditAccountHooks {
	/**
	 * Add a link to 'Special:ApprovedPages' to the page
	 * 'Special:AdminLinks', defined by the Admin Links extension.
	 */
	public static function addToAdminLinks( $admin_links_tree ): bool {
		$general_section = $admin_links_tree->getSection( wfMessage( 'adminlinks_users' )->text() );
		$extensions_row = $general_section->getRow( 'main' );
		if ( $extensions_row === null ) {
			$extensions_row = new ALRow( 'main' );
			$general_section->addRow( $extensions_row );
		}
		$extensions_row->addItem( ALItem::newFromSpecialPage( 'EditAccount' ), 'Userrights' );
		return true;
	}
}
