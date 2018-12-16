<?php
defined('TYPO3_MODE') || die();

$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['backend']['loginProviders']['google_auth'] = [
    'provider' => \Codemonkey1988\BeGoogleAuth\LoginProvider\GoogleAuthProvider::class,
    'sorting' => 25,
    'icon-class' => 'fa-google',
    'label' => 'LLL:EXT:be_google_auth/Resources/Private/Language/locallang_be.xlf:backendLogin.switch.label'
];

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addService(
    'be_google_auth',
    'auth',
    'tx_googlelogin_service',
    [
        'title' => 'Google Login Authentication',
        'description' => 'Google oAuth2 login service for backend',
        'subtype' => 'getUserBE,authUserBE',
        'available' => true,
        'priority' => 75,
        'quality' => 50,
        'os' => '',
        'exec' => '',
        'className' => \Codemonkey1988\BeGoogleAuth\Service\GoogleAuthenticationService::class
    ]
);

$GLOBALS['T3_SERVICES']['auth']['TYPO3\CMS\Sv\AuthenticationService']['className'] = \Codemonkey1988\BeGoogleAuth\Service\ExtendedAuthenticationService::class;

$iconRegistry = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Imaging\IconRegistry::class);
$iconRegistry->registerIcon(
    'ext-begoogleauth-logo',
    \TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
    ['source' => 'EXT:be_google_auth/Resources/Public/Icons/Logo.svg']
);