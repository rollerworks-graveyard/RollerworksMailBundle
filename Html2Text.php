<?php

/**
 * This file is part of the RollerworksMailBundle.
 *
 * (c) Rollerscapes
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @link    http://projects.rollerscapes.net/RollerFramework
 * @license http://www.opensource.org/licenses/lgpl-license.php LGPL
 */

namespace Rollerworks\MailBundle;

/**
 *  Takes HTML and converts it to formatted, plain text.
 *
 *  Thanks to Alexander Krug (http://www.krugar.de/) to pointing out and
 *  correcting an error in the regexp search array. Fixed 7/30/03.
 *
 *  Updated set_html() function's file reading mechanism, 9/25/03.
 *
 *  Thanks to Joss Sanglier (http://www.dancingbear.co.uk/) for adding
 *  several more HTML entity codes to the $search and $replace arrays.
 *  Updated 11/7/03.
 *
 *  Thanks to Darius Kasperavicius (http://www.dar.dar.lt/) for
 *  suggesting the addition of $allowed_tags and its supporting function
 *  (which I slightly modified). Updated 3/12/04.
 *
 *  Thanks to Justin Dearing for pointing out that a replacement for the
 *  <TH> tag was missing, and suggesting an appropriate fix.
 *  Updated 8/25/04.
 *
 *  Thanks to Mathieu Collas (http://www.myefarm.com/) for finding a
 *  display/formatting bug in the _build_link_list() function: email
 *  readers would show the left bracket and number ("[1") as part of the
 *  rendered email address.
 *  Updated 12/16/04.
 *
 *  Thanks to Wojciech Bajon (http://histeria.pl/) for submitting code
 *  to handle relative links, which I hadn't considered. I modified his
 *  code a bit to handle normal HTTP links and MAILTO links. Also for
 *  suggesting three additional HTML entity codes to search for.
 *  Updated 03/02/05.
 *
 *  Thanks to Jacob Chandler for pointing out another link condition
 *  for the _build_link_list() function: "https".
 *  Updated 04/06/05.
 *
 *  Thanks to Marc Bertrand (http://www.dresdensky.com/) for
 *  suggesting a revision to the word wrapping functionality; if you
 *  specify a $width of 0 or less, word wrapping will be ignored.
 *  Updated 11/02/06.
 *
 *  *** Big housecleaning updates below:
 *
 *  Thanks to Colin Brown (http://www.sparkdriver.co.uk/) for
 *  suggesting the fix to handle </li> and blank lines (whitespace).
 *  Christian Basedau (http://www.movetheweb.de/) also suggested the
 *  blank lines fix.
 *
 *  Special thanks to Marcus Bointon (http://www.synchromedia.co.uk/),
 *  Christian Basedau, Norbert Laposa (http://ln5.co.uk/),
 *  Bas van de Weijer, and Marijn van Butselaar
 *  for pointing out my glaring error in the <th> handling. Marcus also
 *  supplied a host of fixes.
 *
 *  Thanks to Jeffrey Silverman (http://www.newtnotes.com/) for pointing
 *  out that extra spaces should be compressed--a problem addressed with
 *  Marcus Bointon's fixes but that I had not yet incorporated.
 *
 *	Thanks to Daniel Schledermann (http://www.typoconsult.dk/) for
 *  suggesting a valuable fix with <a> tag handling.
 *
 *  Thanks to Wojciech Bajon (again!) for suggesting fixes and additions,
 *  including the <a> tag handling that Daniel Schledermann pointed
 *  out but that I had not yet incorporated. I haven't (yet)
 *  incorporated all of Wojciech's changes, though I may at some
 *  future time.
 *
 *  *** End of the housecleaning updates. Updated 08/08/07.
 *
 *  This version is slightly modified for RollerworksMailBundle.
 *  * Unicode support is always used.
 *  * Class propertys are protected
 *  * PHP5 Compliant
 *  * Namespace usage
 *  * Parameter naming (Rollerscapes Coding guidelines)
 *  * Support for scope="col" on the TD and TH
 *
 *  Author Jon Abernathy <jon@chuggnutt.com>
 *  Copyright Copyright (c) 2005-2007 Jon Abernathy <jon@chuggnutt.com>
 *  Version 1.0.0
 */
class Html2Text
{
	/**
	 * Contains the HTML content to convert.
	 *
	 * @var string
	 */
	protected $_sHTML;

	/**
	 * Contains the converted, formatted text.
	 *
	 * @var string
	 */
	protected $_sText;

	/**
	 * Maximum width of the formatted text, in columns.
	 *
	 * Set this value to 0 (or less) to ignore word wrapping
	 * and not constrain text to a fixed-width column.
	 *
	 * @var integer
	 */
	protected $_iWidth = 70;

	/**
	 * List of preg* regular expression patterns to search for,
	 * used in conjunction with $replace.
	 *
	 * @var array
	 * @see $replace
	 */
	protected $_aSearch = array(
			"/\r/",                                  // Non-legal carriage return
			"/[\n\t]+/",                             // Newlines and tabs
			'/[ ]{2,}/',                             // Runs of spaces, pre-handling
			'/<script[^>]*>.*?<\/script>/i',         // <script>s -- which strip_tags supposedly has problems with
			'/<style[^>]*>.*?<\/style>/i',           // <style>s -- which strip_tags supposedly has problems with
			'/<p[^>]*>/i',                           // <P>
			'/<br[^>]*>/i',                          // <br>
			'/<i[^>]*>(.*?)<\/i>/i',                 // <i>
			'/<em[^>]*>(.*?)<\/em>/i',               // <em>
			'/(<ul[^>]*>|<\/ul>)/i',                 // <ul> and </ul>
			'/(<ol[^>]*>|<\/ol>)/i',                 // <ol> and </ol>
			'/<li[^>]*>(.*?)<\/li>/i',               // <li> and </li>
			'/<li[^>]*>/i',                          // <li>
			'/<hr[^>]*>/i',                          // <hr>
			'/<div[^>]*>/i',                         // <div>
			'/(<table[^>]*>|<\/table>)/i',           // <table> and </table>
			'/(<tr[^>]*>|<\/tr>)/i',                 // <tr> and </tr>
			'/&(nbsp|#160);/i',                      // Non-breaking space
			'/&(quot|rdquo|ldquo|#8220|#8221|#147|#148);/i',
																				// Double quotes
			'/&(apos|rsquo|lsquo|#8216|#8217);/i',   // Single quotes
			'/&gt;/i',                               // Greater-than
			'/&lt;/i',                               // Less-than
			'/&(amp|#38);/i',                        // Ampersand
			'/&(copy|#169);/i',                      // Copyright
			'/&(trade|#8482|#153);/i',               // Trademark
			'/&(reg|#174);/i',                       // Registered
			'/&(mdash|#151|#8212);/i',               // mdash
			'/&(ndash|minus|#8211|#8722);/i',        // ndash
			'/&(bull|#149|#8226);/i',                // Bullet
			'/&(pound|#163);/i',                     // Pound sign
			'/&(euro|#8364);/i',                     // Euro sign
			'/[ ]{2,}/'                              // Runs of spaces, post-handling
	);

	/**
	 * List of pattern replacements corresponding to patterns searched.
	 *
	 * @var array
	 * @see $search
	 */
	protected $_aReplace = array(
		'',                                     // Non-legal carriage return
		' ',                                    // Newlines and tabs
		' ',                                    // Runs of spaces, pre-handling
		'',                                     // <script>s -- which strip_tags supposedly has problems with
		'',                                     // <style>s -- which strip_tags supposedly has problems with
		"\n\n",                                 // <P>
		"\n",                                   // <br>
		'_\\1_',                                // <i>
		'_\\1_',                                // <em>
		"\n\n",                                 // <ul> and </ul>
		"\n\n",                                 // <ol> and </ol>
		"\t* \\1\n",                            // <li> and </li>
		"\n\t* ",                               // <li>
		"\n-------------------------\n",        // <hr>
		"<div>\n",                                   // <div>
		"\n\n",                                 // <table> and </table>
		"\n",                                   // <tr> and </tr>
		' ',                                    // Non-breaking space
		'"',                                    // Double quotes
		"'",                                    // Single quotes
		'>',
		'<',
		'&',
		'(c)',
		'(tm)',
		'(R)',
		'--',
		'-',
		'*',
		'£',
		'EUR',                                  // Euro sign. €
		' '                                     // Runs of spaces, post-handling
	);

	/**
	 * List of PCRE regular expression patterns to search for
	 * and replace using callback function.
	 *
	 * @var array
	 */
	protected $_aCallback_search = array(
		'/<(h)[123456][^>]*>(.*?)<\/h[123456]>/i', // H1 - H3
		'/<(b)[^>]*>(.*?)<\/b>/i',                 // <b>
		'/<(strong)[^>]*>(.*?)<\/strong>/i',       // <strong>
		'/<(a) [^>]*href=("|\')([^"\']+)\2[^>]*>(.*?)<\/a>/i',
																							// <a href="">
		'/<(th)[^>]*>(.*?)<\/th>/i',               // <th> and </th>
		'/<(td)[^>]*>(.*?)<\/td>/i',               // <td> and </td>
	);

	/**
		* List of preg* regular expression patterns to search for in PRE body,
		* used in conjunction with $pre_replace.
		*
		* @var array
		* @see $pre_replace
		*/
	protected $_aPre_search = array(
		"/\n/",
		"/\t/",
		'/ /',
		'/<pre[^>]*>/i',
		'/<\/pre>/i'
	);

	/**
	 * List of pattern replacements corresponding to patterns searched for PRE body.
	 *
	 * @var array
	 * @see $pre_search
	 */
	protected $_aPre_replace = array(
		'<br>',
		'&nbsp;&nbsp;&nbsp;&nbsp;',
		'&nbsp;',
		'',
		''
	);

	/**
	 *  Contains a list of HTML tags to allow in the resulting text.
	 *
	 *  @var string $allowed_tags
	 *  @see set_allowed_tags()
	 */
	protected $_sAllowed_tags = '';

	/**
	 *  Contains the base URL that relative links should resolve to.
	 *
	 *  @var string $url
	 */
	protected $_sURL;

	/**
	 *  Indicates whether content in the $html variable has been converted yet.
	 *
	 *  @var boolean $_converted
	 *  @see $html, $text
	 */
	protected $_bConverted = false;

	/**
	 *  Contains URL addresses from links to be rendered in plain text.
	 *
	 *  @var string
	 *  @see _build_link_list()
	 */
	protected $_sLinkList = '';

	/**
	 *  Number of valid links detected in the text, used for plain text
	 *  display (rendered similar to footnotes).
	 *
	 *  @var integer
	 *  @see _build_link_list()
	 */
	protected $_iLinkCount = 0;

	/**
	 * Boolean flag, true if a table of link URLs should be listed after the text.
	 *
	 * @var boolean $_do_links
	 * @see __construct()
	 */
	protected $_bDoLinks = true;

	/**
	 * Constructor.
	 *
	 * If the HTML source string (or file) is supplied, the class
	 * will instantiate with that source propagated, all that has
	 * to be done it to call get_text().
	 *
	 * @param string  $psSource  HTML content
	 * @param boolean $pbDoLinks Indicate whether a table of link URLs is desired
	 * @param integer $piWidth   Maximum width of the formatted text, 0 for no limit
	 *
	 * @api
	 */
	function __construct($psSource = '', $pbDoLinks = false, $piWidth = 75)
	{
		if (! empty($psSource))	{
			$this->setHTML($psSource);
		}

		$this->setBaseURL();

		$this->_bDoLinks = $pbDoLinks;
		$this->_iWidth   = $piWidth;
	}

	/**
	 * Loads source HTML into memory.
	 *
	 * @param string $psSource HTML content
	 *
	 * @api
	 */
	function setHTML($psSource)
	{
		$this->_sHTML      = $psSource;
		$this->_bConverted = false;
	}

	/**
	 * Returns the text, converted from HTML.
	 *
	 * @return string
	 *
	 * @api
	 */
	function getText()
	{
		if (!$this->_bConverted) {
			$this->_convert();
		}

		return $this->_sText;
	}

	/**
	 * Sets the allowed HTML tags to pass through to the resulting text.
	 *
	 * Tags should be in the form "<p>", with no corresponding closing tag.
	 *
	 * @param string $psAllowedTags
	 *
	 * @api
	 */
	function setAllowedTags($psAllowedTags = '')
	{
		$this->_sAllowed_tags = $psAllowedTags;
	}

	/**
	 * Sets a base URL to handle relative links.
	 *
	 * @param string $psURL
	 *
	 * @api
	 */
	function setBaseURL($psURL = '')
	{
		if (empty($psURL))
		{
			if (! empty($_SERVER[ 'HTTP_HOST' ])) {
				$this->_sURL = 'http://' . $_SERVER[ 'HTTP_HOST' ];
			}
			else {
				$this->_sURL = '';
			}
		}
		else
		{
			// Strip any trailing slashes for consistency (relative
			// URLs may already start with a slash like "/file.html")

			if (mb_substr($psURL, - 1) == '/') {
				$psURL = mb_substr($psURL, 0, - 1);
			}

			$this->_sURL = $psURL;
		}
	}

	/**
	 * Workhorse function that does actual conversion.
	 *
	 * First performs custom tag replacement specified by $search and $replace arrays.
	 * Then strips any remaining HTML tags, reduces whitespace and newlines to a readable format,
	 * and word wraps the text to $width characters.
	 */
	protected function _convert()
	{
		// Variables used for building the link list
		$this->_iLinkCount = 0;
		$this->_sLinkList  = '';

		$sText = \trim(stripslashes($this->_sHTML));

		// Convert <PRE>
		$sText = $this->_convertPre($sText);

		// Run our defined search-and-replace
		$sText = \preg_replace($this->_aSearch, $this->_aReplace, $sText);

		// Replace known html entities
		$sText = \html_entity_decode($sText, ENT_COMPAT, 'UTF-8');

		// Run our defined search-and-replace with callback
		$sText = \preg_replace_callback($this->_aCallback_search, array($this, '_pregCallback'), $sText);

		// Remove unknown/unhandled entities (this cannot be done in search-and-replace block)
		$sText = \preg_replace('/&#?[a-z0-9]{2,7};/i', '', $sText);

		// Strip any other HTML tags
		$sText = \strip_tags($sText, $this->_sAllowed_tags);

		// Bring down number of empty lines to 2 max
		$sText = \preg_replace("/\\n\\s+\\n/", "\n\n", $sText);
		$sText = \preg_replace("/[\\n]{3,}/", "\n\n", $sText);

		// Add link list
		if (! empty($this->_sLinkList)) {
			$sText .= "\n\nLinks:\n------\n" . $this->_sLinkList;
		}

		// Wrap the text to a readable format
		// for PHP versions >= 4.0.2. Default width is 75
		// If width is 0 or less, don't wrap the text.
		if ($this->_iWidth > 0)	{
			$sText = \wordwrap($sText, $this->_iWidth);
		}

		$this->_sText      = trim($sText);
		$this->_bConverted = true;
	}

	/**
	 * Helper function called by preg_replace() on link replacement.
	 *
	 * Maintains an internal list of links to be displayed at the end of the
	 * text, with numeric indices to the original point in the text they
	 * appeared. Also makes an effort at identifying and handling absolute
	 * and relative links.
	 *
	 * @param string $psLink 		URL of the link
	 * @param string $psDisplay Part of the text to associate number with
	 * @return string
	 */
	protected function _buildLinkList($psLink, $psDisplay)
	{
		if (! $this->_bDoLinks)	{
			return $psDisplay;
		}

		$sProto = substr($psLink, 0, 7);

		if ($sProto == 'http://' || substr($psLink, 0, 8) == 'https://' || $sProto == 'mailto:') {
			$this->_iLinkCount ++;
			$this->_sLinkList .= "[" . $this->_iLinkCount . "] $psLink\n";
			$sAdditional = ' [' . $this->_iLinkCount . ']';
		}
		elseif (substr($psLink, 0, 11) == 'javascript:') {
			// Don't count the link; ignore it
			$sAdditional = '';
			// what about href="#anchor" ?
		}
		else {
			$this->_iLinkCount ++;
			$this->_sLinkList .= "[" . $this->_iLinkCount . "] " . $this->_sURL;

			if (substr($psLink, 0, 1) != '/') {
				$this->_sLinkList .= '/';
			}

			$this->_sLinkList .= "$psLink\n";
			$sAdditional = ' [' . $this->_iLinkCount . ']';
		}

		return $psDisplay . $sAdditional;
	}

	/**
	 * Helper function for PRE body conversion.
	 *
	 * @param string $psText HTML content
	 * @return string
	 */
	function _convertPre($psText)
	{
		while (preg_match('/<pre[^>]*>(.*)<\/pre>/ismU', $psText, $matches)) {
			$sResult = preg_replace($this->_aPre_search, $this->_aPre_replace, $matches[ 1 ]);
			$psText  = preg_replace('/<pre[^>]*>.*<\/pre>/ismU', '<div><br>' . $sResult . '<br></div>', $psText, 1);
		}

		return $psText;
	}

	/**
	 * Callback function for preg_replace_callback use.
	 *
	 * @param  array $paMatches PREG matches
	 * @return string
	 * @access protected
	 */
	public function _pregCallback($paMatches)
	{
		if ($paMatches[ 1 ] == 'td' || $paMatches[ 1 ] == 'th')
		{
			if ($paMatches[ 1 ] == 'th') {
				$paMatches[ 2 ] = mb_strtoupper($paMatches[ 2 ]);
			}

			// Mark them as horizontal and not vertical
			if (preg_match('#<(td|th).+scope=[\'"]?col[\'"]?.+>#is', $paMatches[ 0 ]))	{
				return "\t\t" . $paMatches[ 2 ];
			}
			else {
				return "\t\t" . $paMatches[ 2 ] . "\n";
			}
		}

		switch ($paMatches[ 1 ])
		{
			case 'b' :
			case 'strong' :
				return mb_strtoupper($paMatches[ 2 ]);
			case 'h' :
				return mb_strtoupper("\n\n" . $paMatches[ 2 ] . "\n\n");
			case 'a' :
				return $this->_buildLinkList($paMatches[ 3 ], $paMatches[ 4 ]);
		}
	}
}
