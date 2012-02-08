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

use Symfony\Component\Templating\EngineInterface as TemplateInterface;
use Rollerworks\MailBundle\Html2Text ;
use \InvalidArgumentException;

/**
 * Handle e-mail messages using the Template engine.
 *
 * For use with SwiftMailerBundle
 */
class Template implements \Swift_Events_SendListener, \Swift_Plugins_Decorator_Replacements
{
	/**
	 * Template to use in the sending process
	 *
	 * @var \Symfony\Component\Templating\EngineInterface
	 */
	protected $_oTemplate = null;

	/**
	 * Template files to use
	 *
	 * @var array
	 */
	protected $_aTemplates = array();

	/**
	 * The replacement map
	 *
	 * @var \Swift_Plugins_Decorator_Replacements|Array $pmReplacements
	 */
	protected $_mReplacements;

	/**
	 * The original subject of the message, before replacements
	 *
	 * @var string
	 */
	protected $_sOriginalSubject;

	/**
	 * The Message that was last replaced
	 *
	 * @var \Swift_Mime_Message
	 */
	protected $_oLastMessage;

	/**
	 * Remember the init state.
	 * This used to change the alternative content replace process.
	 *
	 * @var boolean
	 */
	protected $_bInit = false;

	/**
	 * Instance of HTM2Text Filter.
	 *
	 * @var \RF\Filter\cHTML2Text
	 */
	protected $_oHTML2Text = null;

	/**
	 * Create a new TemplateDecoratorPlugin with $pmReplacements.
	 *
	 * The $replacements can either be an associative array,
	 *  or an implementation of {@see \Swift_Plugins_Decorator_Replacements}.
	 *
	 * Different then the DecoratorPlugin,
	 *  the replacements are 'directly' send to the template engine for replacement.
	 *
	 * You can use any Templating engine
	 *  as long as it implements the \Symfony\Component\Templating\EngineInterface interface
	 *
	 * When using an array, it should be of the form:
	 * <code>
	 *  $replacements = array(
	 *    "address1@domain.tld" => array("a" => "b", "c" => "d"),
	 *	  "address2@domain.tld" => array("a" => "x", "c" => "y")
	 *  )
	 * </code>
	 *
	 * @see \Symfony\Component\Templating\EngineInterface#render()
	 *
	 * Replacements array-key '_subject', is used for the subject.
	 * Replacements for the subject must contain {}, like: {name} => value
	 *
	 * When using an instance of {@see \Swift_Plugins_Decorator_Replacements},
	 * the object should return just the array of replacements for the address
	 * given to {@see \Swift_Plugins_Decorator_Replacements::getReplacementsFor()}.
	 *
	 * $paTemplates must be an array which will follow these rules.
	 * __The location is send directly to render(), so the path/location must be correct.__
	 *
	 * * If the there is **only** one key 'text', the message will be only text.
	 * * If the there is **only** one key 'html', the message will be html
	 * 	 And the HTML output is converted to plain/text using HTML2Text and added as alternative.
	 * * If both the 'html' and 'text' keys are present, they are both used for the correct format respectively.
	 * * If the 'text' is either false or empty, only HTML is used.
	 *
	 * Example:
	 * <code>
	 *  $templates = array(
	 * 	  'html' => 'AcmeHelloBundle:Email:Order.html.twig'
	 * 	  'text' => 'AcmeHelloBundle:Email:Order.txt.twig'
	 *  )
	 * </code>
	 *
	 * Converting with HTML2Text is done 'as best as possible', but may not be perfect.
	 * See {@see Rollerworks\MailBundle\Filter\Html2Text} for more information.
	 *
	 * __Don't forget, all links/references must be absolute to work properly.__
	 *
	 * @param \Symfony\Component\Templating\EngineInterface	$poTemplate
	 * @param array|\Swift_Plugins_Decorator_Replacements	$pmReplacements
	 * @param array                                         $paTemplates
	 * @api
	 */
	public function __construct(TemplateInterface $poTemplate, $pmReplacements, array $paTemplates)
	{
		if (!isset($paTemplates[ 'html' ]) && !isset($paTemplates[ 'text' ])) {
			throw new InvalidArgumentException('$paTemplates must contain either html and/or text');
		}
		elseif (isset($paTemplates[ 'html' ]) && !isset($paTemplates[ 'text' ])) {
			$this->_oHTML2Text = new Html2Text();
		}

		if (empty($paTemplates[ 'text' ]) || $paTemplates[ 'text' ] === false)	{
			unset($paTemplates[ 'text' ]);
		}

		$this->_oTemplate	= $poTemplate;
		$this->_aTemplates	= $paTemplates;

		if (!($pmReplacements instanceof \Swift_Plugins_Decorator_Replacements)) {
			$this->_mReplacements = (array)$pmReplacements;
		}
		else {
			$this->_mReplacements = $pmReplacements;
		}
	}

	/**
	 * Get whether the message will is an text/plain only version.
	 *
	 * @return bool
	 */
	public function isTextOnly()
	{
		return (!isset($this->_aTemplates[ 'html' ]) && isset($this->_aTemplates[ 'text' ]));
	}

	/**
	 * Invoked immediately before the Message is sent.
	 *
	 * @param \Swift_Events_SendEvent $poSendEvent
	 * @param \Swift_Events_SendEvent $poSendEvent
	 */
	public function beforeSendPerformed(\Swift_Events_SendEvent $poSendEvent)
	{
		/**
		 * @var \Swift_Mime_Message $oMessage
		 */
		$oMessage = $poSendEvent->getMessage();

		$this->_restoreMessage($oMessage);

		$aTo      = array_keys($oMessage->getTo());
		$aAddress = array_shift($aTo);

		if ($aReplacements = $this->getReplacementsFor($aAddress))
		{
			if ( isset( $aReplacements['_subject'] ) )
			{
				$aSubSearch  = array_keys($aReplacements[ '_subject' ]);
				$aSubReplace = array_values($aReplacements[ '_subject' ]);

				$sSubject 			= $oMessage->getSubject();
				$sSubjectReplaced	= str_replace($aSubSearch, $aSubReplace, $sSubject);

				if ($sSubject != $sSubjectReplaced)	{
					$this->_sOriginalSubject = $sSubject;
					$oMessage->setSubject($sSubjectReplaced);
				}
			}

			// Text-only
			if (!isset($this->_aTemplates[ 'html' ]) && isset($this->_aTemplates[ 'text' ])) {
				$sMessageBodyText = $this->_oTemplate->render( $this->_aTemplates[ 'text' ], $aReplacements );
				$oMessage->setBody($sMessageBodyText, 'text/plain');
			}
			else
			{
				$sMessageBodyHTML = $this->_oTemplate->render($this->_aTemplates[ 'html' ], $aReplacements);

				if (isset($this->_aTemplates[ 'text' ])) {
					$sMessageBodyText = $this->_oTemplate->render($this->_aTemplates[ 'text' ], $aReplacements);
				}
				elseif ($this->_oHTML2Text !== null) {
					$this->_oHTML2Text->setHTML($sMessageBodyHTML);
					$sMessageBodyText = $this->_oHTML2Text->getText();
				}

				// Text is always the primary one
				if (isset($sMessageBodyText)) {
					$oMessage->setBody($sMessageBodyText, 'text/plain');
				}

				// HTML-only
				if (!isset($sMessageBodyText))	{
					$oMessage->setBody($sMessageBodyHTML, 'text/html');
				}
				elseif ($this->_bInit === false) {
					$oMessage->addPart($sMessageBodyHTML, 'text/html');

					$this->_bInit = true;
				}
				else
				{
					$children = (array)$oMessage->getChildren();

					/**
					 * @var $oChild \Swift_Mime_MimeEntity
					 */
					foreach ($children as $oChild)	{
						// We are only interested in the 'alternative' HTML version (not the attached ones)
						if ('text/html' === $oChild->getContentType() && $oChild->getNestingLevel() === \Swift_Mime_MimeEntity::LEVEL_ALTERNATIVE)	{
							$sBody = $oChild->getBody();

							if ($sBody != $sMessageBodyHTML) {
								$oChild->setBody($sMessageBodyHTML);
							}
						}
					}
				}
			}

			$this->_oLastMessage = $oMessage;
		}
	}


	/**
	 * Invoked immediately after the Message is sent.
	 *
	 * @param \Swift_Events_SendEvent $poSendEvent
	 */
	public function sendPerformed(\Swift_Events_SendEvent $poSendEvent)
	{
		$this->_restoreMessage($poSendEvent->getMessage());
	}


	/**
	 * Find a map of replacements for the address.
	 * If this plug-in was provided with a delegate instance of {@link Swift_Plugins_Decorator_Replacements} then the call will be delegated to it.
	 * Otherwise, it will attempt to find the replacements from the array provided in the constructor.
	 *
	 * If no replacements can be found, an empty value (NULL) is returned.
	 *
	 * @param string $psAddress
	 * @return array
	 */
	public function getReplacementsFor($psAddress)
	{
		if ($this->_mReplacements instanceof \Swift_Plugins_Decorator_Replacements) {
			return $this->_mReplacements->getReplacementsFor($psAddress);
		}
		else {
			return isset($this->_mReplacements[ $psAddress ]) ? $this->_mReplacements[ $psAddress ] : null;
		}
	}


	/**
	 * Restore a changed message back to its original state
	 *
	 * @param \Swift_Mime_Message $oMessage
	 */
	protected function _restoreMessage(\Swift_Mime_Message $oMessage)
	{
		if ($this->_oLastMessage === $oMessage)	{
			if (isset($this->_sOriginalSubject)) {
				$oMessage->setSubject($this->_sOriginalSubject);
				$this->_sOriginalSubject = null;
			}

			$this->_oLastMessage = null;
		}
	}
}