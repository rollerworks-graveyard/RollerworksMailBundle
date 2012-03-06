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
	protected $templateEngine = null;

	/**
	 * Template files to use
	 *
	 * @var array
	 */
	protected $aTemplatesFiles = array();

	/**
	 * The replacement map
	 *
	 * @var \Swift_Plugins_Decorator_Replacements|Array $pmReplacements
	 */
	protected $replacements;

	/**
	 * The original subject of the message, before replacements
	 *
	 * @var string
	 */
	protected $originalSubject;

	/**
	 * The Message that was last replaced
	 *
	 * @var \Swift_Mime_Message
	 */
	protected $lastMessage;

	/**
	 * Remember the init state.
	 * This used to change the alternative content replace process.
	 *
	 * @var boolean
	 */
	protected $bInit = false;

	/**
	 * Instance of HTM2Text Filter.
	 *
	 * @var \RF\Filter\cHTML2Text
	 */
	protected $HTML2Text = null;

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
			$this->HTML2Text = new Html2Text();
		}

		if (empty($paTemplates[ 'text' ]) || $paTemplates[ 'text' ] === false)	{
			unset($paTemplates[ 'text' ]);
		}

		$this->templateEngine	= $poTemplate;
		$this->aTemplatesFiles	= $paTemplates;

		if (!($pmReplacements instanceof \Swift_Plugins_Decorator_Replacements)) {
			$this->replacements = (array)$pmReplacements;
		}
		else {
			$this->replacements = $pmReplacements;
		}
	}

	/**
	 * Get whether the message will is an text/plain only version.
	 *
	 * @return bool
	 */
	public function isTextOnly()
	{
		return (!isset($this->aTemplatesFiles[ 'html' ]) && isset($this->aTemplatesFiles[ 'text' ]));
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
			if (isset($aReplacements[ '_subject' ])) {
				$aSubSearch  = array_keys($aReplacements[ '_subject' ]);
				$aSubReplace = array_values($aReplacements[ '_subject' ]);

				$sSubject         = $oMessage->getSubject();
				$sSubjectReplaced = str_replace($aSubSearch, $aSubReplace, $sSubject);

				if ($sSubject != $sSubjectReplaced) {
					$this->originalSubject = $sSubject;
					$oMessage->setSubject($sSubjectReplaced);
				}
			}

			// Text-only
			if (!isset($this->aTemplatesFiles[ 'html' ]) && isset($this->aTemplatesFiles[ 'text' ])) {
				$sMessageBodyText = $this->templateEngine->render( $this->aTemplatesFiles[ 'text' ], $aReplacements );
				$oMessage->setBody($sMessageBodyText, 'text/plain');
			}
			else {
				$sMessageBodyHTML = $this->templateEngine->render($this->aTemplatesFiles[ 'html' ], $aReplacements);

				if (isset($this->aTemplatesFiles[ 'text' ])) {
					$sMessageBodyText = $this->templateEngine->render($this->aTemplatesFiles[ 'text' ], $aReplacements);
				}
				elseif ($this->HTML2Text !== null) {
					$this->HTML2Text->setHTML($sMessageBodyHTML);
					$sMessageBodyText = $this->HTML2Text->getText();
				}

				// Text is always the primary one
				if (isset($sMessageBodyText)) {
					$oMessage->setBody($sMessageBodyText, 'text/plain');
				}

				// HTML-only
				if (!isset($sMessageBodyText))	{
					$oMessage->setBody($sMessageBodyHTML, 'text/html');
				}
				elseif ($this->bInit === false) {
					$oMessage->addPart($sMessageBodyHTML, 'text/html');

					$this->bInit = true;
				}
				else {
					$children = (array)$oMessage->getChildren();

					/**
					 * @var $oChild \Swift_Mime_MimeEntity
					 */
					foreach ($children as $oChild) {
						// We are only interested in the 'alternative' HTML version (not the attached ones)
						if ('text/html' === $oChild->getContentType() && $oChild->getNestingLevel() === \Swift_Mime_MimeEntity::LEVEL_ALTERNATIVE) {
							$sBody = $oChild->getBody();

							if ($sBody != $sMessageBodyHTML) {
								$oChild->setBody($sMessageBodyHTML);
							}
						}
					}
				}
			}

			$this->lastMessage = $oMessage;
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
		if ($this->replacements instanceof \Swift_Plugins_Decorator_Replacements) {
			return $this->replacements->getReplacementsFor($psAddress);
		}
		else {
			return isset($this->replacements[ $psAddress ]) ? $this->replacements[ $psAddress ] : null;
		}
	}


	/**
	 * Restore a changed message back to its original state
	 *
	 * @param \Swift_Mime_Message $oMessage
	 */
	protected function _restoreMessage(\Swift_Mime_Message $oMessage)
	{
		if ($this->lastMessage === $oMessage)	{
			if (isset($this->originalSubject)) {
				$oMessage->setSubject($this->originalSubject);
				$this->originalSubject = null;
			}

			$this->lastMessage = null;
		}
	}
}