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

use Swift_Events_SendListener, Swift_Plugins_Decorator_Replacements;

/**
 * Handle e-mail messages attachments using the Decorator pattern.
 *
 * For use with SwiftMailerBundle
 */
class AttachmentDecorator implements Swift_Events_SendListener, Swift_Plugins_Decorator_Replacements
{
	/**
	 * The replacement map
	 *
	 * @var \Swift_Plugins_Decorator_Replacements|Array $pmReplacements
	 */
	protected $_mReplacements;

	/**
	 * Array containing all the attachments that must be removed.
	 *
	 * @var array
	 */
	protected $_aAttachments;

	/**
	 * Create a new AttachmentDecoratorPlugin with $pmReplacements.
	 *
	 * The $replacements can either be an associative array,
	 *  or an implementation of {@see \Swift_Plugins_Decorator_Replacements}.
	 *
	 * Different then the 'Template' decorator,
	 *  the replacements are attachments.
	 *
	 * Any already registered attachments will remain.
	 *
	 * When using an array, it should be of the form:
	 * <code>
	 *  $replacements = array(
	 *    "address1@domain.tld" => array( (Swift_Attachment object) ),
	 *    "address2@domain.tld" => array( array( 'data' => 'raw-file-content', 'filename' => 'some-file.txt', 'type' => 'optional mime-type' ) )
	 *  )
	 * </code>
	 *
	 * Even when not using an Swift_Attachment object, internally one is always created.
	 *
	 * @see \Symfony\Component\Templating\EngineInterface#render()
	 *
	 * When using an instance of {@see \Swift_Plugins_Decorator_Replacements},
	 * the object should return just the array of replacements for the address
	 * given to {@see \Swift_Plugins_Decorator_Replacements::getReplacementsFor()}.
	 *
	 * @param \Swift_Plugins_Decorator_Replacements|Array    $pmReplacements
	 *
	 * @api
	 */
	public function __construct($pmReplacements)
	{
		if (! ($pmReplacements instanceof Swift_Plugins_Decorator_Replacements)) {
			$this->_mReplacements = ( array )$pmReplacements;
		}
		else {
			$this->_mReplacements = $pmReplacements;
		}
	}


	/**
	 * Invoked immediately before the Message is sent.
	 *
	 * @param \Swift_Events_SendEvent $poSendEvent
	 * @param \Swift_Events_SendEvent $poSendEvent
	 *
	 * @api
	 */
	public function beforeSendPerformed(\Swift_Events_SendEvent $poSendEvent)
	{
		/**
		 * @var \Swift_Message $oMessage
		 */
		$oMessage = $poSendEvent->getMessage();
		//$oMessage	= new \Swift_Message( );

		$this->_restoreMessage($oMessage);

		$aTo      = array_keys($oMessage->getTo());
		$aAddress = array_shift($aTo);

		if ($aReplacements = $this->getReplacementsFor($aAddress))
		{
			foreach ($aReplacements as $mAttachment)
			{
				if (is_array($mAttachment))
				{
					if (! isset($mAttachment[ 'type' ])) {
						$mAttachment[ 'type' ] = null;
					}

					$oAttachment = \Swift_Attachment::newInstance($mAttachment[ 'data' ], $mAttachment[ 'filename' ], $mAttachment[ 'type' ]);

					$this->_aAttachments[ ] = $oAttachment;
					$oMessage->attach($oAttachment);
				}
				else {
					$this->_aAttachments[ ] = $mAttachment;
					$oMessage->attach($mAttachment);
				}
			}
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
	 * Returns the attachments registered after beforeSendPerformed() was called.
	 * This intended for debugging purposes only.
	 *
	 * @return \Swift_Attachment[]
	 */
	public function getAttachments( )
	{
		return $this->_aAttachments ;
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
	 *
	 * @api
	 */
	public function getReplacementsFor($psAddress)
	{
		if ($this->_mReplacements instanceof Swift_Plugins_Decorator_Replacements) {
			return $this->_mReplacements->getReplacementsFor($psAddress);
		}
		else {
			return isset($this->_mReplacements[ $psAddress ]) ? $this->_mReplacements[ $psAddress ] : null;
		}
	}


	/**
	 * Restore a changed message back to its original state
	 *
	 * @param \Swift_Mime_Message $poMessage
	 */
	protected function _restoreMessage(\Swift_Mime_Message $poMessage)
	{
		if (count($this->_aAttachments) > 0) {
			foreach ($this->_aAttachments as $oAttachment)	{
				$poMessage->detach($oAttachment);
			}

			$this->_aAttachments = array();
		}
	}
}
