<?php

/**
 * A special page to allow mortals to close their accounts.
 * Originally used to be a part of the main EditAccount special page, but a
 * rather essential bug prevented this feature from ever working as intended.
 * It's easier to have that feature implemented as a special page than fixing
 * the broken-by-design logic.
 *
 * @file
 * @date 27 February 2015
 * @see https://bugzilla.shoutwiki.com/show_bug.cgi?id=294
 */
use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserNameUtils;
use MediaWiki\User\UserOptionsManager;

// @note Extends EditAccount so that we don't have to duplicate closeAccount() etc.
class CloseAccount extends EditAccount {

	/**
	 * @var null|User User object for the account that is to be disabled
	 */
	public ?User $mUser;

	/** @var UserOptionsManager */
	private UserOptionsManager $userOptionsManager;

	/**
	 * @var UserGroupManager
	 */
	private UserGroupManager $userGroupManager;

	/** @var UserNameUtils */
	private UserNameUtils $userNameUtils;

	/** @var PasswordFactory */
	private PasswordFactory $passwordFactory;

	/**
	 * Constructor -- set up the new special page
	 *  *
	 * @param UserGroupManager $userGroupManager
	 * @param UserNameUtils $userNameUtils
	 */
	public function __construct(
		UserGroupManager $userGroupManager,
		UserNameUtils $userNameUtils,
		UserOptionsManager $userOptionsManager,
		PasswordFactory $passwordFactory
	) {
		SpecialPage::__construct( 'CloseAccount' );
		$this->userGroupManager = $userGroupManager;
		$this->userNameUtils = $userNameUtils;
		$this->userOptionsManager = $userOptionsManager;
		$this->passwordFactory = $passwordFactory;
	}

	/**
	 * Group this special page under the correct header in Special:SpecialPages.
	 *
	 * @return string
	 */
	public function getGroupName(): string {
		return 'users';
	}

	/**
	 * Special page description shown on Special:SpecialPages (for mortals)
	 *
	 * @return string Special page description
	 */
	public function getDescription(): string {
		return $this->msg( 'editaccount-general-description' )->plain();
	}

	/**
	 * Show this special page on Special:SpecialPages only for registered users
	 * who are not staff members
	 *
	 * @return bool
	 */
	public function isListed(): bool {
		$user = $this->getUser();
		$effectiveGroups = $this->userGroupManager->getUserEffectiveGroups( $user );
		$isStaff = in_array( 'staff', $effectiveGroups );
		return $user->isRegistered() && !$isStaff;
	}

	/**
	 * Show the special page
	 *
	 * @param string|null $subPage Parameter (user name) passed to the page or null
	 */
	public function execute( $subPage ) {
		$out = $this->getOutput();
		$request = $this->getRequest();
		$user = $this->getUser();

		// Anons should not be allowed to access this special page
		if ( !$user->isRegistered() ) {
			throw new PermissionsError( 'editaccount' );
		}

		// Show a message if the database is in read-only mode
		$this->checkReadOnly();

		// If user is blocked, s/he doesn't need to access this page
		if ( $user->getBlock() ) {
			// @phan-suppress-next-line PhanTypeMismatchArgumentNullable
			throw new UserBlockedError( $user->getBlock() );
		}

		// Redirect staff members to Special:EditAccount instead
		$effectiveGroups = $this->userGroupManager->getUserEffectiveGroups( $user );
		if ( in_array( 'staff', $effectiveGroups ) ) {
			$out->redirect( SpecialPage::getTitleFor( 'EditAccount' )->getFullURL() );
		}

		// Set page title and other stuff
		$this->setHeaders();

		// Special:EditAccount is a fairly stupid page title
		$out->setPageTitle( $this->getDescription() );

		// Mortals can only close their own account
		$userName = $user->getName();
		// Clean up the user name
		$userName = str_replace( '_', ' ', trim( $userName ) );
		// User names begin with a capital letter
		$userName = $this->getLanguage()->ucfirst( $userName );

		// Check if user name is an existing user
		if ( $this->userNameUtils->isValid( $userName ) ) {
			$this->mUser = MediaWikiServices::getInstance()->getUserFactory()->newFromName( $userName );
		}

		$changeReason = $request->getVal( 'wpReason' );

		if ( $request->wasPosted() ) {
			$this->mStatus = $this->closeUserAccount( $changeReason );
			if ( $this->mStatus ) {
				$color = 'darkgreen';
			} else {
				$color = '#fe0000';
			}

			$out->addHTML(
				"<fieldset>\n<legend>" . $this->msg( 'editaccount-status' )->escaped() .
				'</legend>' .
				Xml::element( 'span', [ 'style' => "color: $color; font-weight: bold;" ], $this->mStatusMsg ) .
				'</fieldset>'
			);
		} else {
			// Load the correct template file and initiate a new template object
			include __DIR__ . '/../../templates/closeaccount.tmpl.php';
			$tmpl = new EditAccountCloseAccountTemplate;

			$templateVariables = [
				// the value of this is irrelevant, it just needs to be defined
				// for the template because we're reusing EditAccount's UI template
				// and otherwise we'll get "undefined index" notices
				'status' => '',
				'statusMsg' => '',
				// likewise
				'user' => $userName,
				'user_hsc' => htmlspecialchars( $userName )
			];
			foreach ( $templateVariables as $templateVariable => $variableValue ) {
				$tmpl->set( $templateVariable, $variableValue );
			}

			// Output everything!
			// @phan-suppress-next-line PhanTypeMismatchArgument
			$out->addTemplate( $tmpl );
		}
	}

	/**
	 * Scrambles the user's password, sets an empty e-mail and marks the
	 * account as disabled; 
	 * Activated by clicking on Closes the user account option on page Special Pages
	 * 
	 * @param string $changeReason Reason for change
	 * @return bool True on success, false on failure
	 */
	public function closeUserAccount( string $changeReason = '' ): bool {
		// Set flag for Special:Contributions
		// NOTE: requires FlagClosedAccounts.php to be included separately
		if ( defined( 'CLOSED_ACCOUNT_FLAG' ) ) {
			// @phan-suppress-next-line PhanTypeMismatchArgumentNullable
			$this->mUser->setRealName( CLOSED_ACCOUNT_FLAG );
		} else {
			// magic value not found, so let's at least blank it
			$this->mUser->setRealName( '' );
		}

		if ( class_exists( 'Masthead' ) ) {
			// Wikia's avatar extension
			$avatar = Masthead::newFromUser( $this->mUser );
			if ( !$avatar->isDefault() ) {
				if ( !$avatar->removeFile( false ) ) {
					// don't quit here, since the avatar is a non-critical part
					// of closing, but flag for later
					$this->mStatusMsg2 = $this->msg( 'editaccount-remove-avatar-fail' )->plain();
				}
			}
		}

		// Remove e-mail address and password
		$this->mUser->setEmail( '' );
		$newPass = $this->generateRandomScrambledPassword();
		$this->setPasswordForDeactivatedUser( $this->mUser, $newPass );

		// Save the new settings
		$this->mUser->saveSettings();

		$id = $this->mUser->getId();

		// Reload user
		$this->mUser = MediaWikiServices::getInstance()->getUserFactory()->newFromId( $id );

		if ( $this->mUser->getEmail() == '' ) {
			// ShoutWiki patch begin
			$this->setDisabled();
			// ShoutWiki patch end
			// Mark as disabled in a more real way, that doesn't depend on the real_name text
			$this->userOptionsManager->setOption( $this->mUser, 'disabled', 1 );
			$this->userOptionsManager->setOption( $this->mUser, 'disabled_date', wfTimestamp( TS_DB ) );
			// BugId:18085 - setting a new token causes the user to be logged out.
			$this->mUser->setToken( md5( microtime() . mt_rand( 0, 0x7fffffff ) ) );

			// BugID:95369 This forces saveSettings() to commit the transaction
			// FIXME: this is a total hack, we should add a commit=true flag to saveSettings
			$this->getRequest()->setVal( 'action', 'ajax' );

			// Need to save these additional changes
			$this->mUser->saveSettings();

			// Log what was done
			$logEntry = new ManualLogEntry( 'editaccnt', 'closeaccnt' );
			$logEntry->setPerformer( $this->getUser() );
			$logEntry->setTarget( $this->mUser->getUserPage() );
			// JP 13 April 2013: not sure if this is the correct one, CHECKME
			$logEntry->setComment( $changeReason );
			$logEntry->insert();

			// All clear!
			$this->mStatusMsg = $this->msg( 'editaccount-success-close', $this->mUser->mName )->text();
			return true;
		} else {
			// There were errors...inform the user about those
			$this->mStatusMsg = $this->msg( 'editaccount-error-close', $this->mUser->mName )->text();
			return false;
		}
	}

	/**
	 * Set the password on a user
	 *
	 * @param User $user
	 * @param string $password
	 * @return bool
	 */
	public function setPasswordForDeactivatedUser( User $mUser, string $password ): bool {
		if ( !$mUser->getId() ) {
			return false;
			// throw new MWException( "Passed User has not been added to the database yet!" );
		}

		$dbw = MediaWikiServices::getInstance()->getDBLoadBalancer()->getMaintenanceConnectionRef( DB_PRIMARY );
		$row = $dbw->selectRow(
			'user',
			'user_id',
			[ 'user_id' => $mUser->getId() ],
			__METHOD__
		);
		if ( !$row ) {
			return false;
			// throw new MWException( "Passed User has an ID but is not in the database?" );
		}

		$passwordHash = $this->passwordFactory->newFromPlaintext( $password );
		$dbw->update(
			'user',
			[ 'user_password' => $passwordHash->toString() ],
			[ 'user_id' => $mUser->getId() ],
			__METHOD__
		);

		return true;
	}

	/**
	 * Returns a random password which conforms to our password requirements
	 * and is not easily guessable.
	 *
	 * @return string
	 */
	public function generateRandomScrambledPassword(): string {
		// Password requirements need a capital letter, a digit, and a lowercase letter.
		// wfGenerateToken() returns a 32 char hex string, which will almost
		// always satisfy the digit/letter but not always.
		// This suffix shouldn't reduce the entropy of the intentionally
		// scrambled password.
		$REQUIRED_CHARS = 'A1a';
		return ( self::generateToken() . $REQUIRED_CHARS );
	}

	/**
	* Copypasta from pre-1.23 /includes/GlobalFunctions.php
	* @see https://phabricator.wikimedia.org/rMW118567a4ba0ded669f43a58713733cab915afe39
	*
	* @param string $salt
	* @return string
	*/
   public static function generateToken( string $salt = '' ): string {
	   $salt = serialize( $salt );
	   return md5( mt_rand( 0, 0x7fffffff ) . $salt );
   }

   /**
	 * Marks the account as disabled, the ShoutWiki way.
	 */
	public function setDisabled() {
		if ( !class_exists( 'GlobalPreferences' ) ) {
			error_log( 'Cannot use the GlobalPreferences class in ' . __METHOD__ );
			return;
		}
		$dbw = GlobalPreferences::getPrefsDB( DB_PRIMARY );

		$dbw->startAtomic( __METHOD__ );
		$dbw->insert(
			'global_preferences',
			[
				'gp_property' => 'disabled',
				'gp_value' => 1,
				'gp_user' => $this->mUser->getId()
			],
			__METHOD__
		);
		$dbw->insert(
			'global_preferences',
			[
				'gp_property' => 'disabled_date',
				'gp_value' => wfTimestamp( TS_DB ),
				'gp_user' => $this->mUser->getId()
			],
			__METHOD__
		);
		$dbw->endAtomic( __METHOD__ );
	}
}
