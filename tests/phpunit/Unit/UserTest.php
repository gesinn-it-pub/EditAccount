<?php
/**
 * MediaWiki EditAccount Extension
 *
 * @link https://github.com/gesinn-it/EditAccount
 *
 * @author gesinn.it GmbH & Co. KG
 * @license MIT
 */

use MediaWiki\Extension\EditAccount\User as User;
use PHPUnit\Framework\TestCase;
use User as UserAccount;
use MediaWiki\User\UserOptionsManager as UserManager;
use PasswordFactory as PassFactory;
use ManualLogEntry as LogEntry;
use MediaWiki\MediaWikiServices as WikiService;

/**
 * @group User
 * @covers \MediaWiki\Extension\EditAccount\User
 */
class UserTest extends TestCase {

    private UserAccount $mUser;
    private UserAccount $mTempUser;
    private UserAccount $user;
    private UserManager $userManager;
    private PassFactory $passFactory;
    private $changeReason;

    protected function setUp() : void {
		parent::setUp();

        $this->userManager = $this->getMockBuilder(UserManager::class)
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->disallowMockingUnknownTypes()
            ->getMock();     
            
        $this->mTempUser = $this->getMockBuilder(UserAccount::class)
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->disallowMockingUnknownTypes()
            ->getMock();  

        $this->changeReason = '';   
        
        $idUser = 1;
        $this->user = UserAccount::newFromId( $idUser );
		$id_mUser = 2;
        $this->mUser = UserAccount::newFromId( $id_mUser );
	}

    protected function tearDown() : void {
        unset($this->userManager);
        unset($this->mTempUser);
        unset($this->user);
        unset($this->mUser);
        parent::tearDown();
    }

    public function testSetEmail() {
        $newEmail = 'testing@gmail.com';
        $oldEmail = 'test@gmail.com';
        $name = 'Marko991';
        $this->user->setName($name);
        $this->mUser->setName($name);
        $this->mUser->setEmail($oldEmail);
        
        if ( $this->mTempUser->mName || $this->mTempUser->mId ) {
            if ( $newEmail == '' ) {
                return false;
            } else {
                $this->mTempUser->setEmail( $newEmail );
                $this->mUser = $this->mTempUser->activateUser( $this->mUser );

                // reset temp user after activating the user
                $this->mTempUser = null;
            }
        } else {
            $this->mUser->setEmail( $newEmail );
            if ( $newEmail != '' ) {
                $this->mUser->confirmEmail();
                $this->userManager->setOption( $this->mUser, 'new_email', null );
            } else {
                $this->mUser->invalidateEmail();
            }
            $this->mUser->saveSettings();
        }
        
        if ( $this->mUser->getEmail() == $newEmail ) {
            // Log the change
            $logEntry = new LogEntry( 'editaccnt', 'mailchange' );
            $logEntry->setPerformer( $this->user );
            $logEntry->setTarget( $this->mUser->getUserPage() );
            // JP 13 April 2013: not sure if this is the correct one, CHECKME
            $logEntry->setComment( $this->changeReason );
            $logEntry->insert();

            $this->assertSame($newEmail, $this->mUser->getEmail());
        }
    }   

    public function testSetRealName() {
        $realName = 'Test Purpose';
        
        $this->mUser->setRealName( $realName );
		$this->mUser->saveSettings();

		// Was the change saved successfully? The setRealName function doesn't
		// return a boolean value...
		if ( $this->mUser->getRealName() == $realName ) {
			// Log what was done
			$logEntry = new LogEntry( 'editaccnt', 'realnamechange' );
			$logEntry->setPerformer( $this->user );
			$logEntry->setTarget( $this->mUser->getUserPage() );
			// JP 13 April 2013: not sure if this is the correct one, CHECKME
			$logEntry->setComment( $this->changeReason );
			$logEntry->insert();
		}

        $this->assertSame($realName, $this->mUser->getRealName());
    }

    public function testValidateMail() {
        $email_first = 'emailTesting';
        $email_second = 'test@gmail.com';

        $this->assertFalse((Sanitizer::validateEmail( $email_first )), "Added Email is valid!");
        $this->assertTrue((Sanitizer::validateEmail( $email_second )), "Added Email is not valid!");
    }

    public function testClearUnsubscribe() {
        $this->userManager->setOption( $this->mUser, 'unsubscribed', null );
		$this->userManager->saveOptions( $this->mUser );
        $option = $this->mUser->getOption('unsubscribed');

        $this->assertNull($option, 'Value is not NULL');
    }

    public function testClearDisable() {
        $this->userManager->setOption( $this->mUser, 'disabled', null );
		$this->userManager->setOption( $this->mUser, 'disabled_date', null );
		$this->userManager->saveOptions( $this->mUser );
        $option = $this->mUser->getOption('disabled');
        $optionDate = $this->mUser->getOption('disabled_date');

        $this->assertNull($option, 'Value is not NULL');
        $this->assertNull($optionDate, 'Date is not NULL');
    }

}