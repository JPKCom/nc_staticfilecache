<?php
if (!defined ('TYPO3_MODE')) die('Access denied.');

$tmp = Array (
	'tx_ncstaticfilecache_cache' => Array (
		'exclude' => 0,
		'label' => 'LLL:EXT:nc_staticfilecache/locallang_db.xml:nc_staticfilecache.field',
		'config' => Array (
			'type' => 'check',
			'default' => '1',
		),
	),
);

t3lib_div::loadTCA('pages');
t3lib_extMgm::addTCAcolumns('pages', $tmp, 1);
t3lib_extMgm::addToAllTCAtypes('pages', 'tx_ncstaticfilecache_cache;;;;1-1-1');
?>