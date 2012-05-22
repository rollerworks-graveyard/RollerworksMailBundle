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

use Symfony\Component\Templating\EngineInterface as TemplateInterface;

/**
 * Handle e-mail messages using the Template engine.
 *
 * For use with SwiftMailerBundle
 *
 * @author Sebastiaan Stok <s.stok@rollerscapes.net>
 */
class TemplateDecorator implements \Swift_Events_SendListener, \Swift_Plugins_Decorator_Replacements
{
    /**
     * Template to use in the sending process
     *
     * @var \Symfony\Component\Templating\EngineInterface
     */
    protected $templating = null;

    /**
     * Template files to use
     *
     * @var array
     */
    protected $templates = array();

    /**
     * The replacements map
     *
     * @var \Swift_Plugins_Decorator_Replacements|array
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
     *
     * Used in alternative content replace process.
     *
     * @var boolean
     */
    protected $isInit = false;

    /**
     * Create a new TemplateDecoratorPlugin with $replacements.
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
     *      "address1@domain.tld" => array("a" => "b", "c" => "d"),
     *      "address2@domain.tld" => array("a" => "x", "c" => "y")
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
     * $templates must be an array which will follow these rules.
     * __The location is send directly to render(), so the path/location must be correct.__
     *
     * * If the there is **only** one key 'text', the message will be only text.
     * * If the there is **only** one key 'html', the message will be only html
     * * If both the 'html' and 'text' keys are present, they are both used for the correct format respectively.
     * * If the 'text' is either false or empty, only HTML is used.
     * * You must use at minimum one of the two.
     *
     * __And all links/references must be absolute to work properly.__
     *
     * Example:
     * <code>
     *  $templates = array(
     *      'html' => 'AcmeHelloBundle:Email:Order.html.twig',
     *      'text' => 'AcmeHelloBundle:Email:Order.txt.twig'
     *  )
     * </code>
     *
     * @param TemplateInterface                           $templating
     * @param array|\Swift_Plugins_Decorator_Replacements $replacements
     * @param array                                       $templates
     *
     * @throws \InvalidArgumentException When the template file-names are invalid
     *
     * @api
     */
    public function __construct(TemplateInterface $templating, $replacements, array $templates)
    {
        if (!isset($templates['html']) && !isset($templates['text'])) {
            throw new \InvalidArgumentException('$templates must contain either html and/or text');
        }

        if (empty($templates['text']) || false === $templates['text']) {
            unset($templates['text']);
        }

        $this->templating = $templating;
        $this->templates = $templates;

        if (!$replacements instanceof \Swift_Plugins_Decorator_Replacements) {
            $replacements = (array) $replacements;
        }

        $this->replacements = $replacements;
    }

    /**
     * Get whether the message will is an text/plain only version.
     *
     * @return boolean
     */
    public function isTextOnly()
    {
        return !isset($this->templates['html']) && isset($this->templates['text']);
    }

    /**
     * Invoked immediately before the Message is sent.
     *
     * @param \Swift_Events_SendEvent $sendEvent
     * @param \Swift_Events_SendEvent $sendEvent
     */
    public function beforeSendPerformed(\Swift_Events_SendEvent $sendEvent)
    {
        /** @var \Swift_Message $message */
        $message = $sendEvent->getMessage();
        $this->restoreMessage($message);

        $to = array_keys($message->getTo());
        $address = array_shift($to);

        if (($replacements = $this->getReplacementsFor($address))) {
            if (isset($replacements['_subject'])) {
                $subjectSearch = array_keys($replacements['_subject']);
                $subjectReplace = array_values($replacements['_subject']);

                $subject = $message->getSubject();
                $subjectReplaced = str_replace($subjectSearch, $subjectReplace, $subject);

                if ($subjectReplaced !== $subject) {
                    $this->originalSubject = $subject;
                    $message->setSubject($subjectReplaced);
                }
            }

            // Text-only
            if (!isset($this->templates['html']) && isset($this->templates['text'])) {
                $messageBodyText = $this->templating->render($this->templates['text'], $replacements);
                $message->setBody($messageBodyText, 'text/plain');
            } else {
                $messageBodyHtml = $this->templating->render($this->templates['html'], $replacements);

                if (isset($this->templates['text'])) {
                    $messageBodyText = $this->templating->render($this->templates['text'], $replacements);
                }

                // HTML is always the primary one
                // https://github.com/swiftmailer/swiftmailer/issues/184
                $message->setBody($messageBodyHtml, 'text/html');

                if (isset($messageBodyText)) {
                    if (false === $this->isInit) {
                        $message->addPart($messageBodyText, 'text/plain');
                        $this->isInit = true;
                    } else {
                        /** @var $child \Swift_Mime_MimeEntity */
                        foreach ($message->getChildren() as $child) {
                            // We are only interested in the 'alternative' text version (not any attached ones)
                            if ('text/plain' === $child->getContentType() && \Swift_Mime_MimeEntity::LEVEL_ALTERNATIVE === $child->getNestingLevel()) {
                                if ($child->getBody() !== $messageBodyText) {
                                    $child->setBody($messageBodyText);
                                }
                            }
                        }
                    }
                }
            }

            $this->lastMessage = $message;
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
     * Find a map of replacements for the address.
     * If this plug-in was provided with a delegate instance of {@link Swift_Plugins_Decorator_Replacements} then the call will be delegated to it.
     * Otherwise, it will attempt to find the replacements from the array provided in the constructor.
     *
     * If no replacements can be found, an empty value (NULL) is returned.
     *
     * @param string $address
     *
     * @return array
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
        if ($this->lastMessage === $message) {
            if (isset($this->originalSubject)) {
                $message->setSubject($this->originalSubject);
                $this->originalSubject = null;
            }

            $this->lastMessage = null;
        }
    }
}
