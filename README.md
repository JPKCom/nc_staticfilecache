# TYPO3 Extension "nc_staticfilecache"
Transparent static file cache solution using mod_rewrite and mod_expires. Increase response times for static pages by a factor of 230!

## Version 2.3.1




<table>
	<tr><td>Version</td><td>2.3.1 stable</td></tr>
	<tr><td>Release date</td><td>11. May 2008</td></tr>
	<tr><td>Author</td><td>Michiel Roos, Tim Lochmller, Marc Hrsken</td></tr>
	<tr><td>Comment</td><td>fixed bug, $timeOutSeconds was set only when $conf[sendCacheControlHeader] was set but used also it $conf[sendCacheControlHeader] was not set . . . This caused expiry time to be 0 if sendCacheControlHeader was not set.

</td></tr>
</table>

## !! Attention !!
This is an **automatically** generated git version, based on the release into the [TYPO3 Extension Repository](http://www.typo3.org/extensions/).
You can find the original version at http://typo3.org/extensions/repository/view/nc_staticfilecache/ .

It does not make sense to make pull requests as this repository has been created automatically from 3rd party, not from the original author(s).

Every version of the extension is tagged with the version number, therefore you can switch quite easily between different versions.


-- never edit the line below (typo3-ter) --