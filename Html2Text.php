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
    protected $html;

    /**
     * Contains the converted, formatted text.
     *
     * @var string
     */
    protected $text;

    /**
     * Maximum width of the formatted text, in columns.
     *
     * Set this value to 0 (or less) to ignore word wrapping
     * and not constrain text to a fixed-width column.
     *
     * @var integer
     */
    protected $wrapAt = 70;

    /**
     * List of preg* regular expression patterns to search for,
     * used in conjunction with $replace.
     *
     * @var array
     * @see $replace
     */
    protected $search = array(
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
    protected $replace = array(
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
    protected $callback_search = array(
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
    protected $pre_search = array(
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
    protected $pre_replace = array(
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
     *  @see setAllowedTags()
     */
    private $allowed_tags = '';

    /**
     *  Contains the base URL that relative links should resolve to.
     *
     *  @var string $url
     */
    protected $baseUrl;

    /**
     *  Indicates whether content in the $html variable has been converted yet.
     *
     *  @var boolean
     *  @see $html, $text
     */
    protected $isConverted = false;

    /**
     *  Contains URL addresses from links to be rendered in plain text.
     *
     *  @var string
     *  @see buildLinkList()
     */
    protected $linkList = '';

    /**
     *  Number of valid links detected in the text, used for plain text
     *  display (rendered similar to footnotes).
     *
     *  @var integer
     *  @see buildLinkList()
     */
    protected $linkCount = 0;

    /**
     * Boolean flag, true if a table of link URLs should be listed after the text.
     *
     * @var boolean $_do_links
     * @see __construct()
     */
    protected $doLinks = true;

    /**
     * Constructor.
     *
     * If the HTML source string (or file) is supplied, the class
     * will instantiate with that source propagated, all that has
     * to be done it to call get_text().
     *
     * @param string  $source   HTML content
     * @param boolean $doLinks  Indicate whether a table of link URLs is desired
     * @param integer $wrapAt   Maximum width of the formatted text, 0 for no limit
     *
     * @api
     */
    function __construct($source = '', $doLinks = false, $wrapAt = 75)
    {
        if (!empty($source)) {
            $this->setHTML($source);
        }

        $this->setBaseURL();

        $this->doLinks = $doLinks;
        $this->wrapAt  = $wrapAt;
    }

    /**
     * Loads source HTML into memory.
     *
     * @param string $source HTML content
     *
     * @api
     */
    function setHTML($source)
    {
        $this->html        = $source;
        $this->isConverted = false;
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
        if (!$this->isConverted) {
            $this->convert();
        }

        return $this->text;
    }

    /**
     * Sets the allowed HTML tags to pass through to the resulting text.
     *
     * Tags should be in the form "<p>", with no corresponding closing tag.
     *
     * @param string $allowedTags
     *
     * @api
     */
    function setAllowedTags($allowedTags = '')
    {
        $this->allowed_tags = $allowedTags;
    }

    /**
     * Sets a base URL to handle relative links.
     *
     * @param string $url
     *
     * @api
     */
    public function setBaseURL($url = '')
    {
        if (empty($url)) {
            if (!empty($_SERVER['HTTP_HOST'])) {
                $this->baseUrl = 'http://' . $_SERVER['HTTP_HOST'];
            }
            else {
                $this->baseUrl = '';
            }
        }
        else {
            // Strip any trailing slashes for consistency (relative
            // URLs may already start with a slash like "/file.html")

            if (mb_substr($url, - 1) == '/') {
                $url = mb_substr($url, 0, - 1);
            }

            $this->baseUrl = $url;
        }
    }

    /**
     * Callback function for preg_replace_callback use.
     *
     * Internal usage only
     *
     * @param  array $matches PREG matches
     * @return string
     * @access protected
     */
    public function pregCallback($matches)
    {
        if ($matches[1] == 'td' || $matches[1] == 'th') {
            if ($matches[1] == 'th') {
                $matches[2] = mb_strtoupper($matches[2]);
            }

            // Mark them as horizontal and not vertical
            if (preg_match('#<(td|th).+scope=[\'"]?col[\'"]?.+>#is', $matches[0])) {
                return "\t\t" . $matches[2];
            }
            else {
                return "\t\t" . $matches[2] . "\n";
            }
        }

        switch ($matches[1]) {
            case 'b' :
            case 'strong' :
                return mb_strtoupper($matches[2]);
                break;
            case 'h' :
                return mb_strtoupper("\n\n" . $matches[2] . "\n\n");
                break;
            case 'a' :
                return $this->buildLinkList($matches[3], $matches[4]);
                break;
        }
    }

    /**
     * Workhorse function that does actual conversion.
     *
     * First performs custom tag replacement specified by $search and $replace arrays.
     * Then strips any remaining HTML tags, reduces whitespace and newlines to a readable format,
     * and word wraps the text to $width characters.
     */
    protected function convert()
    {
        $this->linkCount = 0;
        $this->linkList  = '';

        $text = \trim(stripslashes($this->html));
        $text = $this->convertPre($text);

        $text = \preg_replace($this->search, $this->replace, $text);

        // Replace known html entities
        $text = \html_entity_decode($text, ENT_COMPAT, 'UTF-8');

        $text = \preg_replace_callback($this->callback_search, array($this, 'pregCallback'), $text);

        // Remove unknown/unhandled entities (this cannot be done in search-and-replace block)
        $text = \preg_replace('/&#?[a-z0-9]{2,7};/i', '', $text);

        $text = \strip_tags($text, $this->allowed_tags);

        // Bring down number of empty lines to 2 max
        $text = \preg_replace("/\\n\\s+\\n/", "\n\n", $text);
        $text = \preg_replace("/[\\n]{3,}/", "\n\n", $text);

        if (! empty($this->linkList)) {
            $text .= "\n\nLinks:\n------\n" . $this->linkList;
        }

        // Wrap the text to a readable format
        // for PHP versions >= 4.0.2. Default width is 75
        // If width is 0 or less, don't wrap the text.
        if ($this->wrapAt > 0)	{
            $text = \wordwrap($text, $this->wrapAt);
        }

        $this->text        = trim($text);
        $this->isConverted = true;
    }

    /**
     * Helper function called by preg_replace() on link replacement.
     *
     * Maintains an internal list of links to be displayed at the end of the
     * text, with numeric indices to the original point in the text they
     * appeared. Also makes an effort at identifying and handling absolute
     * and relative links.
     *
     * @param string $link     URL of the link
     * @param string $display  Part of the text to associate number with
     * @return string
     */
    protected function buildLinkList($link, $display)
    {
        if (!$this->doLinks) {
            return $display;
        }

        $protocol = substr($link, 0, 7);

        if ($protocol == 'http://' || substr($link, 0, 8) == 'https://' || $protocol == 'mailto:') {
            $this->linkCount ++;
            $this->linkList .= "[" . $this->linkCount . "] $link\n";
            $additional = ' [' . $this->linkCount . ']';
        }
        elseif (substr($link, 0, 11) == 'javascript:') {
            // Don't count the link; ignore it
            $additional = '';
            // what about href="#anchor" ?
        }
        else {
            $this->linkCount++;
            $this->linkList .= "[" . $this->linkCount . "] " . $this->baseUrl;

            if (substr($link, 0, 1) != '/') {
                $this->linkList .= '/';
            }

            $this->linkList .= "$link\n";
            $additional = ' [' . $this->linkCount . ']';
        }

        return $display . $additional;
    }

    /**
     * Helper function for PRE body conversion.
     *
     * @param string $text HTML content
     * @return string
     */
    protected function convertPre($text)
    {
        while (preg_match('/<pre[^>]*>(.*)<\/pre>/ismU', $text, $matches)) {
            $result = preg_replace($this->pre_search, $this->pre_replace, $matches[1]);
            $text   = preg_replace('/<pre[^>]*>.*<\/pre>/ismU', '<div><br>' . $result . '<br></div>', $text, 1);
        }

        return $text;
    }
}
