<?php
/**
 * FlagClosedAccounts
 *
 * This code displays a clear indication that an account has been disabled
 * on that user's Special:Contributions page
 *
 * @file
 * @ingroup Extensions
 * @author Łukasz Garczewski (TOR) <tor@wikia-inc.com>
 * @date 2008-01-29
 * @copyright Copyright © 2009 Łukasz Garczewski, Wikia Inc.
 * @license GPL-2.0-or-later
 */

if ( !defined( 'MEDIAWIKI' ) ) {
	echo "Not a valid entry point.\n";
	exit( 1 );
}

define( 'CLOSED_ACCOUNT_FLAG', 'Account Disabled' );

$wgMessagesDirs['EditAccount'] = __DIR__ . '/i18n';
