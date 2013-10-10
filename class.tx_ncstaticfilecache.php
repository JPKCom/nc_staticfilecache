<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2006 Tim Lochmueller (tim@fruit-lab.de)
 *  All rights reserved
 *
 *  This script is part of the Typo3 project. The Typo3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/
/**
 * class 'tx_ncstaticfilecache' for the 'nc_staticfilecache' extension.
 *
 */

/**
 * [CLASS/FUNCTION INDEX of SCRIPT]
 *
 *
 *
 *   56: class tx_ncstaticfilecache
 *   70:     function clearCachePostProc (&$params, &$pObj)
 *  164:     function clearStaticFile (&$_params)
 *  219:     function getRecordForPageID($pid)
 *  237:     function headerNoCache (&$params, $parent)
 *  254:     function insertPageIncache (&$pObj, &$timeOutTime)
 *  380:     function logNoCache (&$params)
 *  395:     function removeExpiredPages (&$pObj)
 *  420:     function rm ($dir)
 *
 * TOTAL FUNCTIONS: 8
 * (This index is automatically created/updated by the extension "extdeveval")
 *
 */

/**
 * Static file cache extension
 *
 * @author	Michiel Roos <extensions@netcreators.com>
 * @package TYPO3
 * @subpackage tx_ncstaticfilecache
 */
class tx_ncstaticfilecache {
	var $extKey = 'nc_staticfilecache';
	var $fileTable = 'tx_ncstaticfilecache_file';
	var $cacheDir = 'typo3temp/tx_ncstaticfilecache/';
	var $debug = false;

	/**
	 * Clear cache post processor.
	 * The same structure as t3lib_TCEmain::clear_cache
	 *
	 * @param	object		$_params: parameter array
	 * @param	object		$pObj: partent object
	 * @return	void
	 */
	function clearCachePostProc (&$params, &$pObj) {
		if($params['cacheCmd']) {
			$this->clearStaticFile($params);
		}
		else {
			$uid = intval($params['uid']);
			$table = strval($params['table']);
			if ($uid > 0)	{

				// Get Page TSconfig relavant:
				list($tscPID) = t3lib_BEfunc::getTSCpid($table,$uid,'');
				$TSConfig = $pObj->getTCEMAIN_TSconfig($tscPID);

				if (!$TSConfig['clearCache_disable'])	{

					// If table is "pages":
					if (t3lib_extMgm::isLoaded('cms'))	{
						$list_cache = array();
						if ($table == 'pages')	{

							// Builds list of pages on the SAME level as this page (siblings)
							$res_tmp = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
											'A.pid AS pid, B.uid AS uid',
											'pages A, pages B',
											'A.uid='.intval($uid).' AND B.pid=A.pid AND B.deleted=0'
										);

							$pid_tmp = 0;
							while ($row_tmp = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res_tmp)) {
								$list_cache[] = $row_tmp['uid'];
								$pid_tmp = $row_tmp['pid'];

								// Add children as well:
								if ($TSConfig['clearCache_pageSiblingChildren'])	{
									$res_tmp2 = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
													'uid',
													'pages',
													'pid='.intval($row_tmp['uid']).' AND deleted=0'
												);
									while ($row_tmp2 = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res_tmp2))	{
										$list_cache[] = $row_tmp2['uid'];
									}
								}
							}

							// Finally, add the parent page as well:
							$list_cache[] = $pid_tmp;

							// Add grand-parent as well:
							if ($TSConfig['clearCache_pageGrandParent'])	{
								$res_tmp = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
												'pid',
												'pages',
												'uid='.intval($pid_tmp)
											);
								if ($row_tmp = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res_tmp))	{
									$list_cache[] = $row_tmp['pid'];
								}
							}
						} else {
							// For other tables than "pages", delete cache for the records "parent page".
							$list_cache[] = intval($pObj->getPID($table,$uid));
						}

						// Delete cache for selected pages:
						if (is_array($list_cache))	{
							$ids = $GLOBALS['TYPO3_DB']->cleanIntArray($list_cache);
							foreach ($ids as $id) {
								$cmd = array ('cacheCmd' => $id);
								$this->clearStaticFile ($cmd);
							}
						}
					}
				}

				// Clear cache for pages entered in TSconfig:
				if ($TSConfig['clearCacheCmd'])	{
					$Commands = t3lib_div::trimExplode(',',strtolower($TSConfig['clearCacheCmd']),1);
					$Commands = array_unique($Commands);
					foreach($Commands as $cmdPart)	{
						$cmd = array ('cacheCmd' => $cmdPart);
						$this->clearStaticFile ($cmd);
					}
				}
			}
		}
	}

	/**
	 * Clear static file
	 *
	 * @param	object		$_params: array containing 'cacheCmd'
	 * @return	void
	 */
	function clearStaticFile (&$_params) {

		$conf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][$this->extKey]);
		if ($_params['host'])
			$cacheDir = $this->cacheDir.$_params['host'];
		else
			$cacheDir = $this->cacheDir.t3lib_div::getIndpEnv('HTTP_HOST');

		if ($_params['cacheCmd']) {
			$cacheCmd = $_params['cacheCmd'];
			switch ($cacheCmd) {
				case 'all':
				case 'temp_CACHED':
					if ($conf['clearCacheForAllDomains']) {
						$cacheDir = $this->cacheDir;
					}
					$this->rm(PATH_site.$cacheDir);
					$GLOBALS['TYPO3_DB']->exec_DELETEquery($this->fileTable, '1=1');
					if ($this->debug)	t3lib_div::devlog("clearing all static cache", $this->extKey, 1);
					break;
				default:
					if (t3lib_div::testInt($cacheCmd)) {
						$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('file', $this->fileTable, 'pid='.$cacheCmd);
						if ($res) {
							$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
							if (is_file(PATH_site.$cacheDir.$row['file'])) {
								if ($this->debug) t3lib_div::devlog("clearing cache for pid: ".$cacheCmd, $this->extKey, 1);
								// Try to remove static cache file
								@unlink(PATH_site.$cacheDir.$row['file']);
								// Try to remove .htaccess file
								@unlink(PATH_site.$cacheDir.dirname($row['file']).'/.htaccess');
								// Try to remove the directory it was in
								@rmdir(PATH_site.$cacheDir.dirname($row['file']));
							}
							else {
								if ($this->debug) t3lib_div::devlog("file not found: ".PATH_site.$cacheDir.$row['file'], $this->extKey, 1);
							}
							$GLOBALS['TYPO3_DB']->sql_free_result($res);
							$GLOBALS['TYPO3_DB']->exec_DELETEquery($this->fileTable, 'pid='.$cacheCmd);
						}
					}
					else {
						if ($this->debug)	t3lib_div::devLog("something funky going on . . . Fix It! ;-)", $this->extKey, 1, $cacheCmd);
					}
					break;
			}
		}
	}

	/**
	 * Returns records for a page id
	 *
	 * @param	integer		Page id
	 * @return	array		Array of records
	 */
	function getRecordForPageID($pid)	{
		return $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
					'*',
					'tx_ncstaticfilecache_file',
					'pid='.intval($pid)
				);
	}

	/**
	 * Detecting if shift-reload has been clicked. Will not be called if re-
	 * generation of page happens by other reasons (for instance that the page
	 * is not in cache yet!) Also, a backend user MUST be logged in for the
	 * shift-reload to be detected due to DoS-attack-security reasons.
	 *
	 * @param	object		$_params: array containing pObj among other things
	 * @param	[type]		$parent: ...
	 * @return	void
	 */
	function headerNoCache (&$params, $parent) {
		if (strtolower($_SERVER['HTTP_CACHE_CONTROL'])==='no-cache' || strtolower($_SERVER['HTTP_PRAGMA'])==='no-cache')	{
			if ($parent->beUserLogin)	{
				if ($this->debug)	t3lib_div::devlog("no-cache header found", $this->extKey, 1);
				$cmd = array ('cacheCmd' => $parent->id);
				$this->clearStaticFile ($cmd);
			}
		}
	}

	/**
	 * Write the static file and .htaccess
	 *
	 * @param	object		$pObj: The parent object
	 * @param	string		$timeOutTime: The timestamp when the page times out
	 * @return	[type]		...
	 */
	function insertPageIncache (&$pObj, &$timeOutTime) {
		if ($this->debug)	t3lib_div::devlog("insertPageIncache", $this->extKey, 1);

		$cacheDir = $this->cacheDir.t3lib_div::getIndpEnv('TYPO3_HOST_ONLY');
		if (!strstr(t3lib_div::getIndpEnv('REQUEST_URI'), '?')
		&& (substr(t3lib_div::getIndpEnv('REQUEST_URI'), -1, 1) == '/')) {

			$loginsDeniedCfg = !$pObj->config['config']['sendCacheHeaders_onlyWhenLoginDeniedInBranch'] || !$pObj->loginAllowedInBranch;
			$doCache = $pObj->isStaticCacheble();

			if (!$pObj->page['tx_ncstaticfilecache_cache']) {
				if ($this->debug)	t3lib_div::devlog("insertPageIncache: static cache disabled by user", $this->extKey, 1);
				$explanation = "static cache disabled on page";
			}
			if ($pObj->no_cache) {
				if ($this->debug)	t3lib_div::devlog("insertPageIncache: no_cache setting is true", $this->extKey, 1);
				$explanation = "config.no_cache is true";
			}
			if ($pObj->isINTincScript()) {
				if ($this->debug)	t3lib_div::devlog("insertPageIncache: page has INTincScript", $this->extKey, 1);
				$explanation = "page has INTincScript";
			}
			if ($pObj->isEXTincScript()) {
				if ($this->debug)	t3lib_div::devlog("insertPageIncache: page has EXTincScript", $this->extKey, 1);
				$explanation = "page has EXTincScript";
			}
			if ($pObj->isUserOrGroupSet()) {
				if ($this->debug)	t3lib_div::devlog("insertPageIncache: page has user or group set", $this->extKey, 1);
				$explanation = "page has user or group set";
			}
			if ($pObj->doWorkspacePreview()) {
				if ($this->debug)	t3lib_div::devlog("insertPageIncache: workspace preview", $this->extKey, 1);
				$explanation = "workspace preview";
							}
			if (!$loginsDeniedCfg) {
				if ($this->debug)	t3lib_div::devlog("insertPageIncache: loginsDeniedCfg is true", $this->extKey, 1);
				$explanation = "loginsDeniedCfg is true";
			}

			// This is supposed to have "&& !$pObj->beUserLogin" in there as well
			// This fsck's up the ctrl-shift-reload hack, so I pulled it out.
			if ($pObj->page['tx_ncstaticfilecache_cache']
			&& $doCache
			&& !$pObj->doWorkspacePreview()
			&& $loginsDeniedCfg) {

				$file = t3lib_div::getIndpEnv('REQUEST_URI').'index.html';
				t3lib_div::mkdir_deep(PATH_site, $cacheDir.t3lib_div::getIndpEnv('REQUEST_URI'));

				$conf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][$this->extKey]);
				if ($conf['showGenerationSignature'])
					$pObj->content .= "\n<!-- ".strftime ($conf['strftime'], $GLOBALS['EXEC_TIME']).' -->';

				if ($this->debug)	t3lib_div::devlog("writing cache for pid: ".$pObj->id, $this->extKey, 1);

				if ($conf['sendCacheControlHeader']) {
					$htaccess = t3lib_div::getIndpEnv('REQUEST_URI').'.htaccess';
					$timeOutSeconds = $timeOutTime - $GLOBALS['EXEC_TIME'];
					if ($this->debug)	t3lib_div::devlog("writing .htaccess with timeout: ".$timeOutSeconds, $this->extKey, 1);

					$htaccessContent = '<IfModule mod_expires.c>
	ExpiresActive on
	ExpiresByType text/html M'.$timeOutSeconds.'
</IfModule>';
					t3lib_div::writeFile(PATH_site.$cacheDir.$htaccess, $htaccessContent);
				}

				t3lib_div::writeFile(PATH_site.$cacheDir.$file, $pObj->content);

				// Check for existing entries with the same uid and file, if a
				// record exists, update timestamp, otherwise create a new record.
				$rows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
					'uid',
					$this->fileTable,
					'pid='.$pObj->page['uid'].' AND host = '.$GLOBALS['TYPO3_DB']->fullQuoteStr(t3lib_div::getIndpEnv('TYPO3_HOST_ONLY'), $this->fileTable).' AND file='.$GLOBALS['TYPO3_DB']->fullQuoteStr($file, $this->fileTable));

				if ($rows[0]['uid']) {
					$fields_values['tstamp'] = $GLOBALS['EXEC_TIME'];
					$fields_values['expires'] = $timeOutTime;
					$GLOBALS['TYPO3_DB']->exec_UPDATEquery($this->fileTable, 'uid='.$rows[0]['uid'], $fields_values);
				} else {
					$fields_values = array(
						'crdate' => $GLOBALS['EXEC_TIME'],
						'tstamp' => $GLOBALS['EXEC_TIME'],
						'expires' => $timeOutTime,
						'file' => $file,
						'pid' => $pObj->page['uid'],
						'host' => t3lib_div::getIndpEnv('TYPO3_HOST_ONLY'),
					);
					$GLOBALS['TYPO3_DB']->exec_INSERTquery($this->fileTable, $fields_values);
				}
			}
			else {
				// Check for existing entries with the same uid and file, if a
				// record exists, update timestamp, otherwise create a new record.
				$rows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
					'uid',
					$this->fileTable,
					'pid='.$pObj->page['uid'].' AND host = '.$GLOBALS['TYPO3_DB']->fullQuoteStr(t3lib_div::getIndpEnv('TYPO3_HOST_ONLY'), $this->fileTable).' AND file='.$GLOBALS['TYPO3_DB']->fullQuoteStr(t3lib_div::getIndpEnv('REQUEST_URI'), $this->fileTable));

				if ($rows[0]['uid']) {
					$fields_values['explanation'] = $explanation;
					$GLOBALS['TYPO3_DB']->exec_UPDATEquery($this->fileTable, 'uid='.$rows[0]['uid'], $fields_values);
				} else {
					$fields_values = array(
						'explanation' => $explanation,
						'file' => t3lib_div::getIndpEnv('REQUEST_URI'),
						'pid' => $pObj->page['uid'],
						'host' => t3lib_div::getIndpEnv('TYPO3_HOST_ONLY'),
					);
					$GLOBALS['TYPO3_DB']->exec_INSERTquery($this->fileTable, $fields_values);
				}

				if ($this->debug)	{
					t3lib_div::devlog("insertPageIncache: . . . . so we're not caching this page!", $this->extKey, 1);
				}
			}
		}
	}

	/**
	 * Log cache miss if no_cache is true
	 *
	 * @param	object		$pObj: partent object
	 * @return	void
	 */
	function logNoCache (&$params) {
		if($params['pObj']) {
			if($params['pObj']->no_cache) {
				$timeOutTime = 0;
				$this->insertPageInCache($params['pObj'], $timeOutTime);
			}
		}
	}

	/**
	 * Remove expired pages. Call from cli script.
	 *
	 * @param	[type]		$$pObj: ...
	 * @return	void
	 */
	function removeExpiredPages (&$pObj) {
		$rows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'expires, file, host, pid',
			$this->fileTable,
			'expires <='.$GLOBALS['EXEC_TIME']);

		if ($rows) {
			foreach ($rows as $row) {
				$pObj->cli_echo("Removed pid: ".$row['pid']."\t".$row['file'].", expired by ".($GLOBALS['EXEC_TIME'] - $row['expires'])." seconds.\n");

				$params = array('cacheCmd' => $row['pid'], 'host' => $row['host']);
				$this->clearStaticFile ($params);
			}
		}
		else {
			$pObj->cli_echo("No expired pages found.\n");
		}
	}

	/**
	 * Delete directories recursively
	 *
	 * @param	string		$dir: The full path
	 * @return	void
	 */
	function rm ($dir) {
		if (!$dh = @opendir($dir)) return;
		while (($obj = readdir($dh))) {
			if ($obj=='.' || $obj=='..') continue;
			if (!@unlink($dir.'/'.$obj)) $this->rm($dir.'/'.$obj);
		}
		@rmdir($dir);
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/nc_staticfilecache/class.tx_ncstaticfilecache.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/nc_staticfilecache/class.tx_ncstaticfilecache.php']);
}
?>