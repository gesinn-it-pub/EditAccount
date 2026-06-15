<?php

namespace MediaWiki\Extension\EditAccount;

use ALItem;
use ALRow;
use OutputPage;
use Skin;
use User;

class Hooks {
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

	/**
	 * Show a notice on Special:Contributions for disabled accounts.
	 *
	 * @param int $id User ID being viewed
	 * @param User $user User object being viewed
	 * @param OutputPage $out
	 * @param Skin $skin
	 * @return bool
	 */
	public static function onSpecialContributionsBeforeMainOutput(
		int $id,
		User $user,
		OutputPage $out,
		Skin $skin
	): bool {
		if ( !SpecialEditAccount::isAccountDisabled( $user ) ) {
			return true;
		}
		$out->wrapWikiMsg(
			"<div class=\"errorbox account-disabled-box\" style=\"padding: 1em;\">\n$1\n</div>",
			'edit-account-closed-flag'
		);
		$out->addHTML( '<br clear="both" />' );
		return true;
	}
}
