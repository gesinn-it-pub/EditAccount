<?php

namespace MediaWiki\Extension\EditAccount;

use ManualLogEntry as LogEntry;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserOptionsManager as UserManager;
use PasswordFactory as PassFactory;
use User as UserAccount;
use Wikimedia\Rdbms\ILoadBalancer;

class User {

	/** @var UserAccount|null */
	private ?UserAccount $mUser;

	/** @var UserAccount|null */
	private ?UserAccount $user;

	/** @var ILoadBalancer */
	private ILoadBalancer $loadBalancer;

	/** @var UserFactory */
	private UserFactory $userFactory;

	public function __construct(
		UserAccount $mUser,
		UserAccount $user,
		ILoadBalancer $loadBalancer,
		UserFactory $userFactory
	) {
		$this->mUser = $mUser;
		$this->user = $user;
		$this->loadBalancer = $loadBalancer;
		$this->userFactory = $userFactory;
	}

	/**
	 * Get User who is going to be edited
	 *
	 * @return User
	 */
	public function getUserToEdit() {
		return $this->mUser;
	}

	/**
	 * Get User who is logged in to the system
	 *
	 * @return User
	 */
	public function getLoggedUser() {
		return $this->user;
	}

	// methods for EditAccount feature

	/**
	 * Set a user's e-mail
	 *
	 * @param string $email E-mail address to set to the user
	 * @param UserAccount $mUser
	 * @param UserManager $userOptionsManager
	 * @param UserAccount $user
	 * @param string $changeReason Reason for change
	 * @return bool True on success, false on failure (i.e. if we were given an invalid email address)
	 */
	public function setEmail( string $email, UserAccount $mUser, UserManager $userOptionsManager, UserAccount $user, string $changeReason = '' ): bool {
		$mUser->setEmail( $email );
		if ( $email != '' ) {
			$mUser->confirmEmail();
			$userOptionsManager->setOption( $mUser, 'new_email', null );
		} else {
			$mUser->invalidateEmail();
		}
		$mUser->saveSettings();

		// Check if everything went through OK, just in case
		if ( $mUser->getEmail() == $email ) {
			// Log the change
			$logEntry = new LogEntry( 'editaccnt', 'mailchange' );
			$logEntry->setPerformer( $user );
			$logEntry->setTarget( $mUser->getUserPage() );
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
	 * @param UserAccount $mUser
	 * @param PassFactory $passFactory
	 * @param UserAccount $user
	 * @param string $changeReason Reason for change
	 * @return bool True on success, false on failure
	 */
	public function setPassword( $pass, UserAccount $mUser, PassFactory $passFactory, UserAccount $user, string $changeReason = '' ): bool {
		if ( $this->setPasswordForUser( $mUser, $pass, $passFactory ) ) {
			$mUser->saveSettings();

			// Log what was done
			$logEntry = new LogEntry( 'editaccnt', 'passchange' );
			$logEntry->setPerformer( $user );
			$logEntry->setTarget( $mUser->getUserPage() );
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
		}

		$dbw = $this->loadBalancer->getConnection( DB_PRIMARY );
		$row = $dbw->selectRow(
			'user',
			'user_id',
			[ 'user_id' => $mUser->getId() ],
			__METHOD__
		);
		if ( !$row ) {
			return false;
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
	 * @param UserAccount $mUser
	 * @param UserAccount $user
	 * @param string $changeReason Reason for change
	 * @return bool True on success, false on failure
	 */
	public function setRealName( $realName, UserAccount $mUser, UserAccount $user, string $changeReason = '' ): bool {
		$mUser->setRealName( $realName );
		$mUser->saveSettings();

		if ( $mUser->getRealName() == $realName ) {
			// Log what was done
			$logEntry = new LogEntry( 'editaccnt', 'realnamechange' );
			$logEntry->setPerformer( $user );
			$logEntry->setTarget( $mUser->getUserPage() );
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
	 * @param UserAccount $mUser
	 * @param UserAccount $user
	 * @param PassFactory $passFactory
	 * @param UserManager $userOptionsManager
	 * @param SpecialEditAccount $editAccount
	 * @param string $changeReason Reason for change
	 * @return bool True on success, false on failure
	 */
	public function closeAccount( UserAccount $mUser, UserAccount $user, PassFactory $passFactory, UserManager $userOptionsManager, SpecialEditAccount $editAccount, string $changeReason = '' ): bool {
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
		$mUser = $this->userFactory->newFromId( $id );

		if ( $mUser->getEmail() == '' ) {
			// Mark as disabled
			$userOptionsManager->setOption( $mUser, 'disabled', 1 );
			$userOptionsManager->setOption( $mUser, 'disabled_date', wfTimestamp( TS_DB ) );

			// BugID:95369 This forces saveSettings() to commit the transaction
			// FIXME: this is a total hack, we should add a commit=true flag to saveSettings
			$editAccount->getRequest()->setVal( 'action', 'ajax' );

			// Need to save these additional changes
			$mUser->saveSettings();

			// Log what was done
			$logEntry = new LogEntry( 'editaccnt', 'closeaccnt' );
			$logEntry->setPerformer( $user );
			$logEntry->setTarget( $mUser->getUserPage() );
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
		// Append fixed chars to satisfy any policy requiring uppercase, digit, lowercase.
		// This does not reduce entropy of the 32-char hex prefix.
		return self::generateToken() . 'A1a';
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
	 * @return string 32-character cryptographically secure hex string
	 */
	public static function generateToken(): string {
		return bin2hex( random_bytes( 16 ) );
	}

	// methods for CloseAccount feature

	/**
	 * Scrambles the user's password, sets an empty e-mail and marks the
	 * account as disabled;
	 * Activated by clicking on Closes the user account option on page Special Pages
	 *
	 * @param UserAccount $mUser
	 * @param PassFactory $passFactory
	 * @param UserManager $userOptionsManager
	 * @param SpecialCloseAccount $closeAccount
	 * @param string $changeReason Reason for change
	 * @return bool True on success, false on failure
	 */
	public function closeUserAccount( UserAccount $mUser, PassFactory $passFactory, UserManager $userOptionsManager, SpecialCloseAccount $closeAccount, string $changeReason = '' ): bool {
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
		$mUser = $this->userFactory->newFromId( $id );

		if ( $mUser->getEmail() == '' ) {
			// Mark as disabled
			$userOptionsManager->setOption( $mUser, 'disabled', 1 );
			$userOptionsManager->setOption( $mUser, 'disabled_date', wfTimestamp( TS_DB ) );

			// BugID:95369 This forces saveSettings() to commit the transaction
			// FIXME: this is a total hack, we should add a commit=true flag to saveSettings
			$closeAccount->getRequest()->setVal( 'action', 'ajax' );

			// Need to save these additional changes
			$mUser->saveSettings();

			// Log what was done
			$logEntry = new LogEntry( 'editaccnt', 'closeaccnt' );
			$logEntry->setPerformer( $mUser );
			$logEntry->setTarget( $mUser->getUserPage() );
			$logEntry->setComment( $changeReason );
			$logEntry->insert();

			return true;
		} else {
			return false;
		}
	}
}
