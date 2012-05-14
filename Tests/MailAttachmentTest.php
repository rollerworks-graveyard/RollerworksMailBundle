<?php

/**
 * This file is part of the RollerworksMailBundle.
 *
 * (c) Sebastiaan Stok <s.stok@rollerscapes.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Rollerworks\MailBundle\Tests;

use Rollerworks\MailBundle\Decorator\TemplateDecorator;
use Rollerworks\MailBundle\Decorator\AttachmentDecorator;

class AttachmentTest extends \PHPUnit_Framework_TestCase
{
    function testSimpleReplace()
    {
        $transport = \Swift_MailTransport::newInstance();
        $message = \Swift_Message::newInstance('Wonderful Subject')
                ->setFrom(array('john@doe.com' => 'John Doe'))
                ->setTo(array('info@rollerscapes.net', 'webmaster@google.nl'));

        $message->setBody('Here is the message itself');

        $sendEvent = new \Swift_Events_SendEvent($transport, $message);
        $replacements = array(
            'info@rollerscapes.net'  => array(\Swift_Attachment::newInstance('this an test document',      'Invoice-2011-4342.txt', 'plain/text')),
            'webmaster@google.nl'    => array(\Swift_Attachment::newInstance('this an none-test document', 'Invoice-2011-8480.txt', 'plain/text')));

        $mailDecorator = new AttachmentDecorator($replacements);

        foreach ($replacements as $email => $msgReplacements) {
            $sendEvent->getMessage()->setTo($email);

            $mailDecorator->beforeSendPerformed($sendEvent);
            $message = $sendEvent->getMessage();

            $this->assertEquals('Here is the message itself', trim($message->getBody()));

            $children = (array) $message->getChildren();

            foreach ($children as $child) {
                if (\Swift_Mime_MimeEntity::LEVEL_MIXED === $child->getNestingLevel()) {
                    $this->assertEquals($msgReplacements[0], $child);
                }
            }

            $mailDecorator->sendPerformed($sendEvent);
        }
    }

    function testKeepOriginal()
    {
        $transport = \Swift_MailTransport::newInstance();
        $message = \Swift_Message::newInstance('Wonderful Subject')
                ->setFrom(array('john@doe.com' => 'John Doe'))
                ->setTo(array('info@rollerscapes.net', 'webmaster@google.nl'));

        $message->setBody('Here is the message itself');

        $attachment = \Swift_Attachment::newInstance('this an none-test document', 'Invoice-2011-848.txt', 'plain/text');
        $message->attach($attachment);

        $sendEvent = new \Swift_Events_SendEvent($transport, $message);
        $replacements = array(
            'info@rollerscapes.net'  => array(\Swift_Attachment::newInstance('this an test document',      'Invoice-2011-4342.txt', 'plain/text')),
            'webmaster@google.nl'    => array(\Swift_Attachment::newInstance('this an none-test document', 'Invoice-2011-8480.txt', 'plain/text')));

        $mailDecorator = new AttachmentDecorator($replacements);

        foreach ($replacements as $email => $msgReplacements) {
            $sendEvent->getMessage()->setTo($email);
            $mailDecorator->beforeSendPerformed($sendEvent);

            $message = $sendEvent->getMessage();
            $this->assertEquals('Here is the message itself', trim($message->getBody()));

            $children = (array) $message->getChildren();

            foreach ($children as $child) {
                if (\Swift_Mime_MimeEntity::LEVEL_MIXED === $child->getNestingLevel() && 'Invoice-2011-848.txt' == $child->getFilename()) {
                    continue;
                }

                if (\Swift_Mime_MimeEntity::LEVEL_MIXED === $child->getNestingLevel()) {
                    $this->assertEquals($msgReplacements[0], $child);
                }
            }

            $mailDecorator->sendPerformed($sendEvent);

            $children = (array) $message->getChildren();

            // Check to make sure the original Attachment is still there
            foreach ($children as $child) {
                if (\Swift_Mime_MimeEntity::LEVEL_MIXED === $child->getNestingLevel()) {
                    $this->assertEquals($attachment->toString(), $child->toString());
                }
            }
        }
    }

    function testMultiple()
    {
        $transport = \Swift_MailTransport::newInstance();
        $message = \Swift_Message::newInstance('Wonderful Subject')
                ->setFrom(array('john@doe.com' => 'John Doe'))
                ->setTo(array('info@rollerscapes.net', 'webmaster@google.nl'));

        $message->setBody('Here is the message itself');

        $oLooseAttachment = \Swift_Attachment::newInstance('this an none-test document', 'Invoice-2011-848.txt', 'plain/text');
        $message->attach($oLooseAttachment);

        $sendEvent = new \Swift_Events_SendEvent($transport, $message);
        $replacements = array(
            'info@rollerscapes.net'  => array(\Swift_Attachment::newInstance('this an test document', 'Invoice-2011-4342.txt', 'plain/text')),
            'webmaster@google.nl'    => array(
                \Swift_Attachment::newInstance('this an none-test document',  'Invoice-2011-8480.txt', 'plain/text'),
                \Swift_Attachment::newInstance('this an none-test2 document', 'Invoice-2011-8580.txt', 'plain/text')));

        $mailDecorator = new AttachmentDecorator($replacements);

        foreach ($replacements as $email => $msgReplacements) {
            $sendEvent->getMessage()->setTo($email);
            $mailDecorator->beforeSendPerformed($sendEvent);

            $message = $sendEvent->getMessage();
            $this->assertEquals('Here is the message itself', trim($message->getBody()));

            $children = (array) $message->getChildren();

            $attachments = array();

            foreach ($children as $child) {
                if (\Swift_Mime_MimeEntity::LEVEL_MIXED === $child->getNestingLevel() && 'Invoice-2011-848.txt' == $child->getFilename()) {
                    continue;
                }

                if (\Swift_Mime_MimeEntity::LEVEL_MIXED === $child->getNestingLevel()) {
                    $attachments[] = $child;
                }
            }

            $this->assertEquals($attachments, $attachments);

            $mailDecorator->sendPerformed($sendEvent);

            $children = (array) $message->getChildren();

            // Check to make sure the original Attachment is still there
            foreach ($children as $child) {
                if (\Swift_Mime_MimeEntity::LEVEL_MIXED === $child->getNestingLevel()) {
                    $this->assertEquals($oLooseAttachment->toString(), $child->toString());
                }
            }
        }
    }

    function testMultiArray()
    {
        $transport = \Swift_MailTransport::newInstance();
        $message = \Swift_Message::newInstance('Wonderful Subject')
                ->setFrom(array('john@doe.com' => 'John Doe'))
                ->setTo(array('info@rollerscapes.net', 'webmaster@google.nl'));

        $message->setBody('Here is the message itself');

        $oLooseAttachment = \Swift_Attachment::newInstance('this an none-test document', 'Invoice-2011-848.txt', 'plain/text');
        $message->attach($oLooseAttachment);

        $sendEvent = new \Swift_Events_SendEvent($transport, $message);

        $replacements = array(
            'info@rollerscapes.net'  => array(\Swift_Attachment::newInstance('this an test document',      'Invoice-2011-4342.txt', 'plain/text')),
             'webmaster@google.nl'   => array(\Swift_Attachment::newInstance('this an none-test document', 'Invoice-2011-8480.txt', 'plain/text'),
                 array('data' => 'this an none-test2 document', 'filename' => 'Invoice-2011-8580.txt')));

        $mailDecorator = new AttachmentDecorator($replacements);

        foreach ($replacements as $email => $msgReplacements) {
            $sendEvent->getMessage()->setTo($email);
            $mailDecorator->beforeSendPerformed($sendEvent);

            $message = $sendEvent->getMessage();
            $this->assertEquals('Here is the message itself', trim($message->getBody()));

            $children = (array) $message->getChildren();

            $attachments = array();

            foreach ($children as $child) {
                if (\Swift_Mime_MimeEntity::LEVEL_MIXED === $child->getNestingLevel() && 'Invoice-2011-848.txt' == $child->getFilename()) {
                    continue;
                }

                if (\Swift_Mime_MimeEntity::LEVEL_MIXED === $child->getNestingLevel()) {
                    $attachments[] = $child;
                }
            }

            $this->assertEquals($attachments, $attachments);
            $mailDecorator->sendPerformed($sendEvent);

            $children = (array) $message->getChildren();

            // Check to make sure the original Attachment is still there
            foreach ($children as $child) {
                if (\Swift_Mime_MimeEntity::LEVEL_MIXED === $child->getNestingLevel()) {
                    $this->assertEquals($oLooseAttachment->toString(), $child->toString());
                }
            }
        }
    }
}
