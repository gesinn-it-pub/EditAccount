<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserNameUtils;
use MediaWiki\User\UserOptionsManager;
use MediaWiki\Extension\EditAccount\User as UserToEdit;

/**
 * Main logic of the EditAccount extension
 *
 * @file
 * @ingroup Extensions
 * @author Łukasz Garczewski (TOR) <tor@wikia-inc.com>
 * @date 2008-09-17
 * @copyright Copyright © 2008 Łukasz Garczewski, Wikia Inc.
 * @license GPL-2.0-or-later
 */

class EditAccount extends SpecialPage {

	/** @var User|null */
	public ?User $mUser = null;
	/** @var bool|null */
	public ?bool $mStatus = null;
	/** @var string|null */
	public ?string $mStatusMsg = null;
	/** @var string|null */
	public ?string $mStatusMsg2 = null;
	/** @var User|null */
	public ?User $mTempUser = null;
	/** @var User|null */
	public ?User $tmpUser = null;
	/** @var UserToEdit|null */
	public ?UserToEdit $userToEdit = null;

	/** @var PasswordFactory */
	private PasswordFactory $passwordFactory;

	/** @var UserNameUtils */
	private UserNameUtils $userNameUtils;

	/** @var UserOptionsManager */
	private UserOptionsManager $userOptionsManager;

	/**
	 * @param PasswordFactory $passwordFactory
	 * @param UserNameUtils $userNameUtils
	 * @param UserOptionsManager $userOptionsManager
	 */
	public function __construct(
		PasswordFactory $passwordFactory,
		UserNameUtils $userNameUtils,
		UserOptionsManager $userOptionsManager
	) {
		parent::__construct( 'EditAccount', 'editaccount' );
		$this->passwordFactory = $passwordFactory;
		$this->userNameUtils = $userNameUtils;
		$this->userOptionsManager = $userOptionsManager;
	}

	public function doesWrites(): bool {
		return true;
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
	 * Special page description shown on Special:SpecialPages -- different for
	 * privileged users and mortals
	 *
	 * @return string Special page description
	 */
	public function getDescription(): string {
		if ( $this->getUser()->isAllowed( 'editaccount' ) ) {
			return $this->msg( 'editaccount' )->plain();
		} else {
			return $this->msg( 'editaccount-general-description' )->plain();
		}
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
		$services = MediaWikiServices::getInstance();

		// Redirect mortals to Special:CloseAccount
		if ( !$user->isAllowed( 'editaccount' ) ) {
			// throw new PermissionsError( 'editaccount' );
			$out->redirect( SpecialPage::getTitleFor( 'CloseAccount' )->getFullURL() );
		}

		// Show a message if the database is in read-only mode
		$this->checkReadOnly();

		// If user is blocked, s/he doesn't need to access this page
		if ( $user->getBlock() ) {
			// @phan-suppress-next-line PhanTypeMismatchArgumentNullable
			throw new UserBlockedError( $user->getBlock() );
		}

		// Set page title and other stuff
		$this->setHeaders();

		// Special:EditAccount is a fairly stupid page title
		$out->setPageTitle( $this->getDescription() );

		// Get name to work on. Subpage is supported, but form submit name trumps
		$userName = $request->getVal( 'wpUserName', $subPage );
		$action = $request->getVal( 'wpAction' );

		if ( $userName !== null ) {
			// Got a name, clean it up
			$userName = str_replace( '_', ' ', trim( $userName ) );
			// User names begin with a capital letter
			$userName = $this->getLanguage()->ucfirst( $userName );

			// Check if user name is an existing user
			if ( $this->userNameUtils->isValid( $userName ) ) {
				$userFactory = $services->getUserFactory();
				$this->mUser = $userFactory->newFromName( $userName );
				$actor = $services->getUserIdentityLookup()->getUserIdentityByName( $userName );
				$id = $actor ? $actor->getId() : null;

				if ( !$action ) {
					$action = 'displayuser';
				}

				if ( !$id ) {
					// Wikia stuff...
					if ( class_exists( 'TempUser' ) ) {
						$this->mTempUser = TempUser::getTempUserFromName( $userName );
					}

					if ( $this->mTempUser ) {
						$id = $this->mTempUser->getId();
						$this->mUser = $userFactory->newFromId( $id );
					} else {
						$this->mStatus = false;
						$this->mStatusMsg = $this->msg( 'editaccount-nouser', $userName )->text();
						$action = '';
					}
				}

				if ( $this->mTempUser == null ) {
					$tmpUser = new User();
					//create an object of User class with reference on mUser who is going to be edited
					$userToEdit = new UserToEdit($this->mUser, $tmpUser , $user);
				} else {
					//create an object of User class with reference on mUser who is going to be edited
					$userToEdit = new UserToEdit($this->mUser, $this->mTempUser , $user);
				}

					$mUser = $userToEdit->getUserToEdit();
					$tmpUser = $userToEdit->getTempUser();
					$loggedUser = $userToEdit->getLoggedUser();
			}
		}

		// FB:23860
		if ( !( $this->mUser instanceof User ) ) {
			$action = '';
		}

		$changeReason = $request->getVal( 'wpReason' );
		
		// What to do, what to show? Hmm...
		switch ( $action ) {
			case 'setemail':
				$newEmail = $request->getVal( 'wpNewEmail' );
				if ( Sanitizer::validateEmail( $newEmail ) || $newEmail == '' ) { 
					$isEmailSet = $userToEdit->setEmail( $newEmail, $changeReason, $mUser, $tmpUser, $this->userOptionsManager, $loggedUser );
					if ( $mUser->getEmail() == $newEmail ) {
						if ( $isEmailSet !== false ) {
							if ( $newEmail == '' ) {
								$this->mStatusMsg = $this->msg( 'editaccount-success-email-blank', $mUser->mName )->text();
								$this->mStatus = $this->mStatusMsg;
							} else {
								$this->mStatusMsg = $this->msg( 'editaccount-success-email', $mUser->mName, $newEmail )->text();
								$this->mStatus = $this->mStatusMsg;
							}
						}
					} else {
						$this->mStatusMsg = $this->msg( 'editaccount-error-email', $mUser->mName )->text();
						$this->mStatus = $this->mStatusMsg;
					}
				} else {
					$this->mStatusMsg = $this->msg( 'editaccount-invalid-email', $newEmail )->text();
					$this->mStatus = $this->mStatusMsg;
				}
				$template = 'DisplayUser';
				break;
			case 'setpass':
				$newPass = $request->getVal( 'wpNewPass' );
				$isPassSet = $userToEdit->setPassword( $newPass, $changeReason, $mUser, $tmpUser, $this->passwordFactory, $loggedUser );
				if ( $isPassSet ) {
					$this->mStatusMsg = $this->msg( 'editaccount-success-pass', $mUser->mName )->text();
					$this->mStatus = $this->mStatusMsg;
				} else {
					$this->mStatusMsg = $this->msg( 'editaccount-error-pass', $mUser->mName )->text();
					$this->mStatus = $this->mStatusMsg;
				}
				$template = 'DisplayUser';
				break;
			case 'setrealname':
				$newRealName = $request->getVal( 'wpNewRealName' );
				$isRealNameSet = $userToEdit->setRealName( $newRealName, $changeReason, $mUser, $loggedUser );
				if ( $isRealNameSet ) {
					$this->mStatusMsg = $this->msg( 'editaccount-success-realname', $mUser->mName )->text();
					$this->mStatus = $this->mStatusMsg;
				} else {
					$this->mStatusMsg = $this->msg( 'editaccount-error-realname', $mUser->mName )->text();
					$this->mStatus = $this->mStatusMsg;
				}
				$template = 'DisplayUser';
				break;
			case 'closeaccount':
				$template = 'CloseAccount';
				$this->mStatus = (bool)$this->userOptionsManager->getOption( $this->mUser, 'requested-closure', 0 );
				if ( $this->mStatus ) {
					$this->mStatusMsg = $this->msg( 'editaccount-requested' )->text();
				} else {
					$this->mStatusMsg = $this->msg( 'editaccount-not-requested' )->text();
				}
				break;
			case 'closeaccountconfirm':
				$closeAccount = $userToEdit->closeAccount( $changeReason, $mUser, $loggedUser, $this->passwordFactory, $this->userOptionsManager, $this );
				if ( $closeAccount ) {
					$checkMasterClassAvatar = $this->checkMasterClass();
					if ( $checkMasterClassAvatar ) {
						$this->mStatusMsg2 = $this->msg( 'editaccount-remove-avatar-fail' )->plain();
						$this->mStatus = $this->mStatusMsg2;
					} 
					$this->mStatusMsg = $this->msg( 'editaccount-success-close', $mUser->mName )->text();
					$this->mStatus = $this->mStatusMsg;
				} else {
					$this->mStatusMsg = $this->msg( 'editaccount-error-close', $mUser->mName )->text();
					$this->mStatus = $this->mStatusMsg;
				}
				$template = $closeAccount ? 'SelectUser' : 'DisplayUser';
				break;
			case 'clearunsub':
				$isClearUnsub = $userToEdit->clearUnsubscribe( $this->userOptionsManager, $mUser );
				if ( $isClearUnsub ) {
					$this->mStatusMsg = $this->msg( 'editaccount-success-unsub', $mUser->mName )->text();
					$this->mStatus = $this->mStatusMsg;
				}
				$template = 'DisplayUser';
				break;
			case 'cleardisable':
				$isClearDisable = $userToEdit->clearDisable( $this->userOptionsManager, $mUser );
				if ( $isClearDisable ) {
					$this->mStatusMsg = $this->msg( 'editaccount-success-disable', $mUser->mName )->text();
					$this->mStatus = $this->mStatusMsg;
				}
				$template = 'DisplayUser';
				break;
			case 'toggleadopter':
				$toggleAdopter = $userToEdit->toggleAdopterStatus( $this->userOptionsManager, $mUser );
				if ( $toggleAdopter ) {
					$this->mStatusMsg = $this->msg( 'editaccount-success-toggleadopt', $mUser->mName )->text();
					$this->mStatus = $this->mStatusMsg;
				}
				$template = 'DisplayUser';
				break;
			case 'displayuser':
				$template = 'DisplayUser';
				break;
			default:
				$template = 'SelectUser';
		}

		// Load the correct template file, build the class name and initiate a
		// new template object (so that we can set variables later on)
		include __DIR__ . '/../../templates/' . strtolower( $template ) . '.tmpl.php';
		$templateClassName = 'EditAccount' . $template . 'Template';
		$tmpl = new $templateClassName;

		$linkRenderer = $services->getLinkRenderer();
		$templateVariables = [
			'status' => $this->mStatus,
			'statusMsg' => $this->mStatusMsg,
			'statusMsg2' => $this->mStatusMsg2,
			'user' => $userName,
			'userEmail' => null,
			'userRealName' => null,
			'userEncoded' => urlencode( $userName ),
			'user_hsc' => htmlspecialchars( $userName ),
			'userId' => null,
			'userReg' => null,
			'isUnsub' => null,
			'isDisabled' => null,
			'isAdopter' => null,
			'returnURL' => $this->getFullTitle()->getFullURL(),
			'logLink' => $linkRenderer->makeLink(
				SpecialPage::getTitleFor( 'Log', 'editaccnt' ),
				$this->msg( 'log-name-editaccnt' )
			),
			'userStatus' => null,
			'emailStatus' => null,
			'disabled' => null,
			'changeEmailRequested' => null,
		];
		foreach ( $templateVariables as $templateVariable => $variableValue ) {
			$tmpl->set( $templateVariable, $variableValue );
		}

		if ( is_object( $this->mUser ) ) {
			if ( $this->mTempUser ) {
				$this->mUser = $this->mTempUser->mapTempUserToUser( false );
				$userStatus = $this->msg( 'editaccount-status-tempuser' )->plain();
				$tmpl->set( 'disabled', 'disabled="disabled"' );
			} else {
				$userStatus = $this->msg( 'editaccount-status-realuser' )->plain();
			}
			$this->mUser->load();

			// get new e-mail (unconfirmed)
			$optionNewEmail = $this->userOptionsManager->getOption( $this->mUser, 'new_email' );
			if ( !$optionNewEmail ) {
				$changeEmailRequested = '';
			} else {
				$changeEmailRequested = $this->msg( 'editaccount-email-change-requested', $optionNewEmail )->parse();
			}

			// emailStatus is the status of the e-mail in the "Set new email address" field
			if ( $this->mUser->isEmailConfirmed() ) {
				$emailStatus = $this->msg( 'editaccount-status-confirmed' )->plain();
			} else {
				$emailStatus = $this->msg( 'editaccount-status-unconfirmed' )->plain();
			}

			$templateVariables2 = [
				'userEmail' => $this->mUser->getEmail(),
				'userRealName' => $this->mUser->getRealName(),
				'userId' => $this->mUser->getId(),
				'userReg' => date( 'r', strtotime( $this->mUser->getRegistration() ) ),
				'isUnsub' => $this->userOptionsManager->getOption( $this->mUser, 'unsubscribed' ),
				'isDisabled' => $this->userOptionsManager->getOption( $this->mUser, 'disabled' ),
				'isAdopter' => $this->userOptionsManager->getOption( $this->mUser, 'AllowAdoption', 1 ),
				'userStatus' => $userStatus,
				'emailStatus' => $emailStatus,
				'changeEmailRequested' => $changeEmailRequested,
			];
			// This will overwrite the previous variables which are null
			foreach ( $templateVariables2 as $templateVariable2 => $variableValue2 ) {
				$tmpl->set( $templateVariable2, $variableValue2 );
			}
		}

		// HTML output
		$out->addTemplate( $tmpl );
		$out->addModules("ext.editAccount");
		$out->addModules("ext.editAccount.displayuser");
	}

	public function checkMasterClass() {
		if ( class_exists( 'Masthead' ) ) {
			// Wikia's avatar extension
			$avatar = Masthead::newFromUser( $this->mUser );
			if ( !$avatar->isDefault() ) {
				if ( !$avatar->removeFile( false ) ) {
					// don't quit here, since the avatar is a non-critical part
					// of closing, but flag for later
					
					return true;
				}
			}
		}
	}

	/**
	 * Is the given user account disabled?
	 *
	 * @param User $user
	 * @return bool|void True if it is disabled, otherwise false
	 */
	public static function isAccountDisabled( User $user ) {
		if ( !class_exists( 'GlobalPreferences' ) ) {
			error_log( 'Cannot use the GlobalPreferences class in ' . __METHOD__ );
			return;
		}
		$dbr = GlobalPreferences::getPrefsDB();
		$retVal = $dbr->selectField(
			'global_preferences',
			'gp_value',
			[
				'gp_property' => 'disabled',
				'gp_user' => $user->getId()
			],
			__METHOD__
		);

		return (bool)$retVal;
	}
}
