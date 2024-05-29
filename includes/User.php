<?php

namespace MediaWiki\Extension\EditAccount;

use User as UserAccount;
use MediaWiki\User\UserOptionsManager as UserManager;
use PasswordFactory as PassFactory;
use ManualLogEntry as LogEntry;
use MediaWiki\MediaWikiServices as WikiService;
use EditAccount as Edit;

class User {

	/** @var UserAccount|null */
	private ?UserAccount $mUser;

	/** @var UserAccount|null */
	private ?UserAccount $mTempUser;

	/** @var UserAccount|null */
	private ?UserAccount $user;

 
	public function __construct(UserAccount $mUser, UserAccount $mTempUser, UserAccount $user) {
		$this->mUser = $mUser;
		$this->mTempUser = $mTempUser;
		$this->user = $user;
	}

	public function getUserToEdit() {
		return $this->mUser;
	}

	public function getTempUser() {
		return $this->mTempUser;
	}

	public function getLoggedUser() {
		return $this->user;
	}

    /**
	 * Set a user's e-mail
	 
	 * @param string $email E-mail address to set to the user
	 * @param string $changeReason Reason for change
	 * @param UserAccount $mUser 
	 * @param UserAccount $mTempUser
	 * @param UserManager $userOptionsManager
	 * @param UserAccount $user
	 * @return bool True on success, false on failure (i.e. if we were given an invalid email address)
	 */
	public function setEmail( string $email, UserAccount $mUser, UserAccount $mTempUser, UserManager $userOptionsManager, UserAccount $user, string $changeReason = '' ): bool {

			if ( $mTempUser->mName || $mTempUser->mId ) {
				if ( $email == '' ) {
					return false;
				} else {
					$mTempUser->setEmail( $email );
					$mUser = $mTempUser->activateUser( $mUser );

					// reset temp user after activating the user
					$mTempUser = null;
				}
			} else {
				$mUser->setEmail( $email );
				if ( $email != '' ) {
					$mUser->confirmEmail();
					$userOptionsManager->setOption( $mUser, 'new_email', null );
				} else {
					$mUser->invalidateEmail();
				}
				$mUser->saveSettings();
			}

			// Check if everything went through OK, just in case
			if ( $mUser->getEmail() == $email ) {
				// Log the change
				$logEntry = new LogEntry( 'editaccnt', 'mailchange' );
				$logEntry->setPerformer( $user );
				$logEntry->setTarget( $mUser->getUserPage() );
				// JP 13 April 2013: not sure if this is the correct one, CHECKME
				$logEntry->setComment( $changeReason );
				$logEntry->insert();

				return true;
			} else {
				return false;
			}
	}

	/**
	 * Set a user's password.
	 *
	 * @param mixed $pass Password to set to the user
	 * @param string $changeReason Reason for change
	 * @param UserAccount $mUser
	 * @param UserAccount $mTempUser
	 * @param PassFactory $passFactory
	 * @param UserAccount $user
	 * @return bool True on success, false on failure
	 */
	public function setPassword( $pass, UserAccount $mUser, UserAccount $mTempUser, PassFactory $passFactory, UserAccount $user, string $changeReason = '' ): bool {
		if ( $this->setPasswordForUser( $mUser, $pass, $passFactory ) ) {
			// Save the new settings
			if ( $mTempUser->mName || $mTempUser->mId ) {
				$this->setPasswordForUser( $mTempUser, $pass, $passFactory );
				$mTempUser->updateData();
				$mTempUser->saveSettingsTempUserToUser( $mUser );
				$mUser->mName = $mTempUser->getName();
			} else {
				$mUser->saveSettings();
			}

			// Log what was done
			$logEntry = new LogEntry( 'editaccnt', 'passchange' );
			$logEntry->setPerformer( $user );
			$logEntry->setTarget( $mUser->getUserPage() );
			// JP 13 April 2013: not sure if this is the correct one, CHECKME
			$logEntry->setComment( $changeReason );
			$logEntry->insert();

			return true;
		} else {
			return false;
		}
	}

		/**
	 * Set the password on a user
	 *
	 * @param UserAccount $mUser
	 * @param string $password
	 * @param PassFactory $passFactory
	 * @return bool
	 */
	public function setPasswordForUser( UserAccount $mUser, string $password, PassFactory $passFactory ): bool {
		if ( !$mUser->getId() ) {
			return false;
			// throw new MWException( "Passed User has not been added to the database yet!" );
		}

		$dbw = WikiService::getInstance()->getDBLoadBalancer()->getMaintenanceConnectionRef( DB_PRIMARY );
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

		$passwordHash = $passFactory->newFromPlaintext( $password );
		$dbw->update(
			'user',
			[ 'user_password' => $passwordHash->toString() ],
			[ 'user_id' => $mUser->getId() ],
			__METHOD__
		);

		return true;
	}

		/**
	 * Set a user's real name.
	 *
	 * @param mixed $realName Real name to set to the user
	 * @param string $changeReason Reason for change
	 * @param UserAccount $mUser
	 * @param UserAccount $user
	 * @return bool True on success, false on failure
	 */
	public function setRealName( $realName, UserAccount $mUser, UserAccount $user, string $changeReason = '' ): bool {
		$mUser->setRealName( $realName );
		$mUser->saveSettings();

		// Was the change saved successfully? The setRealName function doesn't
		// return a boolean value...
		if ( $mUser->getRealName() == $realName ) {
			// Log what was done
			$logEntry = new LogEntry( 'editaccnt', 'realnamechange' );
			$logEntry->setPerformer( $user );
			$logEntry->setTarget( $mUser->getUserPage() );
			// JP 13 April 2013: not sure if this is the correct one, CHECKME
			$logEntry->setComment( $changeReason );
			$logEntry->insert();

			return true;
		} else {
			return false;
		}
	}

		/**
	 * Scrambles the user's password, sets an empty e-mail and marks the
	 * account as disabled
	 *
	 * @param string $changeReason Reason for change
	 * @param UserAccount $mUser
	 * @param UserAccount $user
	 * @param PassFactory $passFactory
	 * @param UserManager $userOptionsManager
	 * @param Edit $editAccount
	 * @return bool True on success, false on failure
	 */
	public function closeAccount( UserAccount $mUser, UserAccount $user, PassFactory $passFactory, UserManager $userOptionsManager, Edit $editAccount, string $changeReason = '' ): bool {
		// Set flag for Special:Contributions
		// NOTE: requires FlagClosedAccounts.php to be included separately
		if ( defined( 'CLOSED_ACCOUNT_FLAG' ) ) {
			// @phan-suppress-next-line PhanTypeMismatchArgumentNullable
			$mUser->setRealName( CLOSED_ACCOUNT_FLAG );
		} else {
			// magic value not found, so let's at least blank it
			$mUser->setRealName( '' );
		}

		// Remove e-mail address and password
		$mUser->setEmail( '' );
		$newPass = $this->generateRandomScrambledPassword();
		$this->setPasswordForUser( $mUser, $newPass, $passFactory );

		// Save the new settings
		$mUser->saveSettings();

		$id = $mUser->getId();

		// Reload user
		$mUser = WikiService::getInstance()->getUserFactory()->newFromId( $id );

		if ( $mUser->getEmail() == '' ) {
			// ShoutWiki patch begin
			$this->setDisabled();
			// ShoutWiki patch end
			// Mark as disabled in a more real way, that doesn't depend on the real_name text
			$userOptionsManager->setOption( $mUser, 'disabled', 1 );
			$userOptionsManager->setOption( $mUser, 'disabled_date', wfTimestamp( TS_DB ) );
			// BugId:18085 - setting a new token causes the user to be logged out.
			$mUser->setToken( md5( microtime() . mt_rand( 0, 0x7fffffff ) ) );

			// BugID:95369 This forces saveSettings() to commit the transaction
			// FIXME: this is a total hack, we should add a commit=true flag to saveSettings
			$editAccount->getRequest()->setVal( 'action', 'ajax' );

			// Need to save these additional changes
			$mUser->saveSettings();

			// Log what was done
			$logEntry = new LogEntry( 'editaccnt', 'closeaccnt' );
			$logEntry->setPerformer( $user );
			$logEntry->setTarget( $mUser->getUserPage() );
			// JP 13 April 2013: not sure if this is the correct one, CHECKME
			$logEntry->setComment( $changeReason );
			$logEntry->insert();

			return true;
		} else {
			return false;
		}
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

		/**
	 * Clears the magic unsub bit
	 * @param UserManager $userOptionsManager
	 * @param UserAccount $mUser
	 * @return bool Always true
	 */
	public function clearUnsubscribe( UserManager $userOptionsManager, UserAccount $mUser ): bool {
		$userOptionsManager->setOption( $mUser, 'unsubscribed', null );
		$userOptionsManager->saveOptions( $mUser );

		return true;
	}

		/**
	 * Clears the magic disabled bit
	 * @param UserManager $userOptionsManager
	 * @param UserAccount $mUser
	 * @return bool Always true
	 */
	public function clearDisable( UserManager $userOptionsManager, UserAccount $mUser ): bool {
		$userOptionsManager->setOption( $mUser, 'disabled', null );
		$userOptionsManager->setOption( $mUser, 'disabled_date', null );
		$userOptionsManager->saveOptions( $mUser );

		// ShoutWiki patch begin
		// We also need to clear GlobalPreferences data; otherwise it's possible
		// (though unlikely) that a staff member reactivates a disabled account
		// but the "this account has been disabled" notice on Special:Contributions
		// won't go away.
		if ( class_exists( 'GlobalPreferences' ) ) {
			$dbw = GlobalPreferences::getPrefsDB( DB_PRIMARY );

			$dbw->startAtomic( __METHOD__ );
			$dbw->delete(
				'global_preferences',
				[
					'gp_property' => 'disabled',
					'gp_value' => 1,
					'gp_user' => $mUser->getId()
				],
				__METHOD__
			);
			$dbw->delete(
				'global_preferences',
				[
					'gp_property' => 'disabled_date',
					'gp_user' => $mUser->getId()
				],
				__METHOD__
			);
			$dbw->endAtomic( __METHOD__ );
		}
		// ShoutWiki patch end

		return true;
	}

		/**
	 * Set the adoption status (i.e. is the user who is being edited allowed to
	 * automatically adopt wikis or not).
	 * @param UserManager $userOptionsManager
	 * @param UserAccount $mUser
	 * @return bool Always true
	 */
	public function toggleAdopterStatus( UserManager $userOptionsManager, UserAccount $mUser ): bool {
		$userOptionsManager->setOption(
			$mUser,
			'AllowAdoption',
			(int)!$userOptionsManager->getOption( $mUser, 'AllowAdoption', 1 )
		);
		$userOptionsManager->saveOptions( $mUser );

		return true;
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

}