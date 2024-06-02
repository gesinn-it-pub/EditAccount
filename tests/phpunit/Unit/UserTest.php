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
use MediaWiki\User\UserNameUtils as UserNameUtils;
use MediaWiki\User\UserGroupManager;
use ManualLogEntry as LogEntry;
use MediaWiki\MediaWikiServices as WikiService;
use EditAccount as Edit;
use CloseAccount as Close;

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
    private UserGroupManager $userGroupManager;
    private UserNameUtils $userNameUtils;
    private User $userToEdit;
    private Edit $editAccount;
    private Close $closeAccount;
    private $changeReason;
    private $checkFunction;
    private $password;

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

        $this->userNameUtils = $this->getMockBuilder(UserNameUtils::class)
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->disallowMockingUnknownTypes()
            ->getMock();  

        $this->userGroupManager = $this->getMockBuilder(UserGroupManager::class)
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->disallowMockingUnknownTypes()
            ->getMock();  

        $this->changeReason = '';  
        $this->password = 'test#0309!';
        
        $idUser = 1;
        $this->user = UserAccount::newFromId( $idUser );
		$id_mUser = 2;
        $this->mUser = UserAccount::newFromId( $id_mUser );

        $this->passFactory = new PassFactory();
        $this->userToEdit = new User($this->mUser, $this->mTempUser, $this->user);
	}

    protected function tearDown() : void {
        unset($this->userGroupManager);
        unset($this->userManager);
        unset($this->userNameUtils);
        unset($this->passFactory);
        unset($this->mTempUser);
        unset($this->user);
        unset($this->mUser);
        unset($this->userToEdit);
        parent::tearDown();
    }

    //phpunit tests for EditAccount feature (SpecialEditAccount.php)
    public function testSetEmail() {
        $newEmail = 'testing@gmail.com';
        $oldEmail = 'test@gmail.com';
        $name = 'Marko991';
        $this->user->setName($name);
        $this->mUser->setName($name);
        $this->mUser->setEmail($oldEmail);

        $this->checkFunction = $this->userToEdit->setEmail( $newEmail, $this->mUser, $this->mTempUser, $this->userManager, $this->user, $this->changeReason );
        $this->assertTrue($this->checkFunction, 'Function setEmail() returns false, check everything once again!');
    }   

    public function testSetRealName() {
        $realName = 'Test Purpose';

        $this->checkFunction = $this->userToEdit->setRealName( $realName, $this->mUser, $this->user, $this->changeReason );
        $this->assertTrue($this->checkFunction, 'Function setRealName() returns false, check everything once again!');
    }

    public function testValidateMail() {
        $email_first = 'emailTesting';
        $email_second = 'test@gmail.com';

        $this->assertFalse((Sanitizer::validateEmail( $email_first )), "Added Email is valid!");
        $this->assertTrue((Sanitizer::validateEmail( $email_second )), "Added Email is not valid!");
    }

    public function testClearUnsubscribe() {

        $this->checkFunction = $this->userToEdit->clearUnsubscribe( $this->userManager, $this->mUser );
        $this->assertTrue($this->checkFunction, 'Function clearUnsubscribe() returns false, check everything once again!');
    }

    public function testClearDisable() {

        $this->checkFunction = $this->userToEdit->clearDisable( $this->userManager, $this->mUser );
        $this->assertTrue($this->checkFunction, 'Function clearDisable() returns false, check everything once again!');
    }

    public function testCloseAccount() {
        $this->editAccount = new Edit($this->passFactory, $this->userNameUtils, $this->userManager);

        $this->checkFunction = $this->userToEdit->closeAccount( $this->mUser, $this->user,  $this->passFactory, $this->userManager, $this->editAccount, $this->changeReason );
        $this->assertTrue($this->checkFunction, 'Function closeAccount() returns false, check everything once again!');
    }

    public function testSetPasswordForUser() {

        $this->checkFunction = $this->userToEdit->setPasswordForUser( $this->mUser, $this->password,  $this->passFactory );
        $this->assertTrue($this->checkFunction, 'Function setPasswordForUser() returns false, check everything once again!');
    }

    public function testSetPassword() {

        $this->checkFunction = $this->userToEdit->setPassword( $this->password, $this->mUser, $this->mTempUser, $this->passFactory, $this->user, $this->changeReason );
        $this->assertTrue($this->checkFunction, 'Function setPassword() returns false, check everything once again!');

    }

    public function testToggleAdopterStatus() {

        $this->checkFunction = $this->userToEdit->toggleAdopterStatus( $this->userManager, $this->mUser );
        $this->assertTrue($this->checkFunction, 'Function toggleAdopterStatus() returns false, check everything once again!');
    }

    //phpunit tests for CloseAccount feature (SpecialCloseAccount.php)
    public function testCloseUserAccount() {
        $this->closeAccount = new Close($this->userGroupManager, $this->userNameUtils, $this->userManager, $this->passFactory);
        
        $this->checkFunction = $this->userToEdit->closeUserAccount( $this->mUser, $this->passFactory, $this->userManager, $this->closeAccount, $this->changeReason );
        $this->assertTrue($this->checkFunction, 'Function closeUserAccount() returns false, check everything once again!');
    }
}
