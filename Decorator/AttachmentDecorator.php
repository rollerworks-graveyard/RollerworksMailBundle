<?php

/**
 * This file is part of the RollerworksMailBundle.
 *
 * (c) Sebastiaan Stok <s.stok@rollerscapes.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Rollerworks\Bundle\MailBundle\Decorator;

/**
 * Handle e-mail messages attachments using the Decorator pattern.
 *
 * For use with SwiftMailerBundle
 *
 * @author Sebastiaan Stok <s.stok@rollerscapes.net>
 */
class AttachmentDecorator implements \Swift_Events_SendListener, \Swift_Plugins_Decorator_Replacements
{
    /**
     * The replacement map.
     *
     * @var \Swift_Plugins_Decorator_Replacements|array
     */
    protected $replacements;

    /**
     * Array containing all the attachments that must be removed.
     *
     * @var \Swift_Attachment[]
     */
    protected $attachments;

    /**
     * Create a new AttachmentDecoratorPlugin with $replacements.
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
     * @param \Swift_Plugins_Decorator_Replacements|Array $replacements
     *
     * @api
     */
    public function __construct($replacements)
    {
        if (!$replacements instanceof \Swift_Plugins_Decorator_Replacements) {
            $replacements = (array) $replacements;
        }

        $this->replacements = $replacements;
    }


    /**
     * Invoked immediately before the Message is sent.
     *
     * @param \Swift_Events_SendEvent $sendEvent
     * @param \Swift_Events_SendEvent $sendEvent
     *
     * @api
     */
    public function beforeSendPerformed(\Swift_Events_SendEvent $sendEvent)
    {
        /** @var \Swift_Message $message */
        $message = $sendEvent->getMessage();
        $this->restoreMessage($message);

        $to = array_keys($message->getTo());
        $address = array_shift($to);

        if ($replacements = $this->getReplacementsFor($address)) {
            foreach ($replacements as $attachment) {
                if (is_array($attachment)) {
                    if (!isset($attachment['type'])) {
                        $attachment['type'] = null;
                    }

                    $attachment = \Swift_Attachment::newInstance($attachment['data'], $attachment['filename'], $attachment['type']);
                }

                $this->attachments[] = $attachment;
                $message->attach($attachment);
            }
        }
    }


    /**
     * Invoked immediately after the Message is sent.
     *
     * @param \Swift_Events_SendEvent $sendEvent
     */
    public function sendPerformed(\Swift_Events_SendEvent $sendEvent)
    {
        $this->restoreMessage($sendEvent->getMessage());
    }


    /**
     * Returns the attachments registered after beforeSendPerformed() was called.
     *
     * @return \Swift_Attachment[]
     */
    public function getAttachments()
    {
        return $this->attachments;
    }


    /**
     * Find a map of replacements for the address.
     *
     * If this plug-in was provided with a delegate instance of {@link Swift_Plugins_Decorator_Replacements} then the call will be delegated to it.
     * Otherwise, it will attempt to find the replacements from the array provided in the constructor.
     *
     * If no replacements can be found, an empty value (NULL) is returned.
     *
     * @param string $address
     *
     * @return array
     *
     * @api
     */
    public function getReplacementsFor($address)
    {
        if ($this->replacements instanceof \Swift_Plugins_Decorator_Replacements) {
            return $this->replacements->getReplacementsFor($address);
        } else {
            return isset($this->replacements[$address]) ? $this->replacements[$address] : null;
        }
    }

    /**
     * Restore a changed message back to its original state
     *
     * @param \Swift_Mime_Message $message
     */
    protected function restoreMessage(\Swift_Mime_Message $message)
    {
        if (count($this->attachments) > 0) {
            foreach ($this->attachments as $attachment) {
                $message->detach($attachment);
            }

            $this->attachments = array();
        }
    }
}
