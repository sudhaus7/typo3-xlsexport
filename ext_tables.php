<?php
if (!defined('TYPO3_MODE')) {
    die('Access denied.');
}


// Backend Modules
\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
    'SUDHAUS7.' . $_EXTKEY,
    'web',
    'xlsexport',
    '',
    array('Xlsexport' => 'index, export'),
    array(
        'access' => 'user,group',
        'icon'   => 'EXT:' . $_EXTKEY . '/Resources/Public/Icons/xls-exporter.png',
        'labels' => 'LLL:EXT:' . $_EXTKEY . '/Resources/Private/Language/locallang_db.xlf',
    )
);


\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile($_EXTKEY, 'Configuration/TypoScript', 'SUDHAUS7 Xlsexport');
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPageTSConfig('
<INCLUDE_TYPOSCRIPT: source="FILE:EXT:sudhaus7_xlsexport/Configuration/PageTSconfig/page.ts">
');
