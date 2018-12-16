<?php
declare(strict_types=1);
namespace Codemonkey1988\BeGoogleAuth\Service;

use Codemonkey1988\BeGoogleAuth\Google\Client;
use Codemonkey1988\BeGoogleAuth\Google\Gsuite;
use Codemonkey1988\BeGoogleAuth\Google\InvalidClientResponseException;
use Codemonkey1988\BeGoogleAuth\UserProvider\BackendUserProvider;
use TYPO3\CMS\Core\Authentication\AbstractUserAuthentication;
use TYPO3\CMS\Core\Service\AbstractService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class GoogleAuthenticationService extends AbstractService
{
    const EXTKEY = 'be_google_auth';

    /**
     * @var array
     */
    protected $loginData;

    /**
     * google response
     */
    protected $googleResponse = [];

    /**
     * Additional authentication information provided by AbstractUserAuthentication.
     * We use it to decide what database table contains user records.
     *
     * @var array
     */
    protected $authenticationInformation = [];

    /**
     * Initializes authentication for this service.
     *
     * @param string $subType : Subtype for authentication (either "getUserFE" or "getUserBE")
     * @param array $loginData : Login data submitted by user and preprocessed by AbstractUserAuthentication
     * @param array $authenticationInformation : Additional TYPO3 information for authentication services (unused here)
     * @param AbstractUserAuthentication $parentObject Calling object
     * @return void
     */
    public function initAuth($subType, array $loginData, array $authenticationInformation, AbstractUserAuthentication $parentObject)
    {
        // Store login and authentication data
        $this->loginData = $loginData;
        $this->authenticationInformation = $authenticationInformation;

        $token = $this->getToken();
        if ($token) {
            $client = $this->getGoogleClient();
            try {
                $this->googleResponse = $client->fetchUserProfile($token);
            } catch (InvalidClientResponseException $e) {
                $this->googleResponse = null;
            }
        }
    }

    /**
     * This function returns the user record back to the AbstractUserAuthentication.
     * It does not mean that user is authenticated, it means only that user is found. This
     * function makes sure that user cannot be authenticated by any other service
     * if user tries to use OpenID to authenticate.
     *
     * @return mixed User record (content of fe_users/be_users as appropriate for the current mode)
     */
    public function getUser()
    {
        if ($this->loginData['status'] !== 'login' || empty($this->googleResponse) || $this->authenticationInformation['loginType'] !== 'BE') {
            return false;
        }

        $configurationService = $this->getConfigurationService();
        $gsuite = GeneralUtility::makeInstance(Gsuite::class);
        $gsuite->injectConfigurationService($configurationService);
        $userProvider = GeneralUtility::makeInstance(BackendUserProvider::class, $this->authenticationInformation);
        $userProvider->injectConfigurationService($configurationService);
        $userRecord = $userProvider->getUserByEmail($this->googleResponse['email']);

        if (!empty($userRecord) && is_array($userRecord)) {
            $message = sprintf('User \'%s\' logged in with google login \'%s\'', $userRecord['username'], $this->googleResponse['email']);
            GeneralUtility::sysLog($message, self::EXTKEY, GeneralUtility::SYSLOG_SEVERITY_NOTICE);
        } elseif ($gsuite->enabled() && $gsuite->isGsuiteUser($this->googleResponse) && $gsuite->isInOrganisation($this->googleResponse)) {
            $userRecordWithoutRestrictions = $userProvider->getUserByEmail($this->googleResponse['email'], false);

            if (empty($userRecordWithoutRestrictions)) {
                $userProvider->createUser($this->googleResponse['email'], $this->googleResponse['name'] ?? '');
                $userRecord = $this->getUser();
            } elseif ($userRecordWithoutRestrictions['deleted'] === 1) {
                $userProvider->restoreUser($userRecordWithoutRestrictions['uid']);

                $message = sprintf('A deleted user \'%s\' is logging in using google login. Restore user (undelete).', $this->googleResponse['email']);
                GeneralUtility::sysLog($message, self::EXTKEY, GeneralUtility::SYSLOG_SEVERITY_NOTICE);

                $userRecord = $this->getUser();
            } else {
                $message = sprintf('A disabled user \'%s\' is trying to login in using google login. Update user data', $this->googleResponse['email']);
                GeneralUtility::sysLog($message, self::EXTKEY, GeneralUtility::SYSLOG_SEVERITY_ERROR);
            }
        }

        return $userRecord;
    }

    /**
     * Authenticates user
     *
     * @param array $userRecord User record
     * @return int Code that shows if user is really authenticated.
     */
    public function authUser(array $userRecord): int
    {
        $result = 100;

        if (!empty($this->googleResponse)) {
            if ($this->googleResponse['email'] === $userRecord['email']) {
                $result = 200;
            } else {
                GeneralUtility::sysLog('Google oAuth login failed. Google email address does not match users email address.', self::EXTKEY, GeneralUtility::SYSLOG_SEVERITY_NOTICE);
            }
        } else {
            GeneralUtility::sysLog('Google oAuth login failed. Could not fetch google response.', self::EXTKEY, GeneralUtility::SYSLOG_SEVERITY_NOTICE);
        }

        return $result;
    }

    /**
     * @return Client
     */
    protected function getGoogleClient(): Client
    {
        return GeneralUtility::makeInstance(Client::class);
    }

    /**
     * @return string
     */
    protected function getToken(): string
    {
        return GeneralUtility::_POST('google_token');
    }

    /**
     * @return ConfigurationService|object
     */
    protected function getConfigurationService()
    {
        return GeneralUtility::makeInstance(ConfigurationService::class);
    }
}