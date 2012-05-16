<?php

/**
 * This file is part of the RollerworksMailBundle.
 *
 * (c) Sebastiaan Stok <s.stok@rollerscapes.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Rollerworks\Bundle\MailBundle\Tests;

use Rollerworks\Bundle\MailBundle\Decorator\TemplateDecorator;

class MailTemplateTest extends \PHPUnit_Framework_TestCase
{
    function testHTMLAndText()
    {
        $templating = $this->getTwigInstance();

        $transport = \Swift_MailTransport::newInstance();
        $message = \Swift_Message::newInstance('Wonderful Subject')
            ->setFrom(array('john@doe.com' => 'John Doe'))
            ->setTo(array('info@rollerscapes.net', 'webmaster@example.com'));

        $sendEvent = new \Swift_Events_SendEvent($transport, $message);
        $replacements = array('info@rollerscapes.net'   => array('name'   => 'John',  'gender' => 'Sir'),
                              'webmaster@example.com'   => array('name'   => 'Piet',  'gender' => 'Heer'));

        $mailDecorator = new TemplateDecorator($templating, $replacements, array('html' => 'TestMsg1.twig', 'text' => 'TestMsg1.txt.twig' ));

        foreach ($replacements as $sEmail => $msgReplacements) {
            $sendEvent->getMessage()->setTo($sEmail);

            $mailDecorator->beforeSendPerformed($sendEvent);

            $message = $sendEvent->getMessage();
            $this->assertEquals('<p>Geachte ' . $msgReplacements['gender'] . ' ' . $msgReplacements['name'] . ',</p><p>Dit is een testbericht.</p><p>This an test message.</p><p>Rollerscapes</p>', trim($message->getBody()));

            $children = (array) $message->getChildren();

            foreach ($children as $child) {
                if ('text/plain' == $child->getContentType()) {
                    $this->assertEquals('Geachte ' . $msgReplacements['gender'] . ' ' . $msgReplacements['name'] . ',

Dit is een testbericht.

This an test message.
Rollerscapes-', $child->getBody());
                }
            }

            $mailDecorator->sendPerformed($sendEvent);
        }
    }

    function testHTMLOnly()
    {
        $templating = $this->getTwigInstance();

        $transport = \Swift_MailTransport::newInstance();
        $message = \Swift_Message::newInstance('Wonderful Subject')
            ->setFrom(array('john@doe.com' => 'John Doe'))
            ->setTo(array('info@rollerscapes.net', 'webmaster@example.com'));

        $sendEvent = new \Swift_Events_SendEvent($transport, $message);
        $replacements = array('info@rollerscapes.net'   => array('name'   => 'John',  'gender' => 'Sir'),
                              'webmaster@example.com'   => array('name'   => 'Piet',  'gender' => 'Heer'));

        $mailDecorator = new TemplateDecorator($templating, $replacements, array('html' => 'TestMsg1.twig', 'text' => false ));

        foreach ($replacements as $sEmail => $msgReplacements) {
            $sendEvent->getMessage()->setTo($sEmail);

            $mailDecorator->beforeSendPerformed($sendEvent);

            $message = $sendEvent->getMessage();

            $this->assertEquals('text/html', $message->getContentType());
            $this->assertEquals('<p>Geachte ' . $msgReplacements['gender'] . ' ' . $msgReplacements['name'] . ',</p><p>Dit is een testbericht.</p><p>This an test message.</p><p>Rollerscapes</p>', trim($message->getBody()));

            $mailDecorator->sendPerformed($sendEvent);
        }
    }

    function testSubjectReplace()
    {
        $templating = $this->getTwigInstance();

        $transport = \Swift_MailTransport::newInstance();
        $message = \Swift_Message::newInstance('Message for {name}')
            ->setFrom(array('john@doe.com' => 'John Doe'))
            ->setTo(array('info@rollerscapes.net', 'webmaster@example.com'));

        $sendEvent = new \Swift_Events_SendEvent($transport, $message);
        $replacements = array('info@rollerscapes.net'   => array('name'     => 'John',  'gender'   => 'Sir',
                                                                 '_subject' => array('{name}' => 'SJohn')),
                              'webmaster@example.com'   => array('name'     => 'Piet',  'gender'   => 'Heer',
                                                                 '_subject' => array('{name}' => 'SPiet')));

        $mailDecorator = new TemplateDecorator($templating, $replacements, array('html' => 'TestMsg1.twig' ));

        foreach ($replacements as $sEmail => $msgReplacements) {
            $sendEvent->getMessage()->setTo($sEmail);

            $mailDecorator->beforeSendPerformed($sendEvent);

            $message = $sendEvent->getMessage();

            $this->assertEquals('Message for ' . $msgReplacements['_subject']['{name}'], $message->getSubject());

            $children = (array) $message->getChildren();

            foreach ($children as $child) {
                if ('text/html' == $child->getContentType()) {
                    $this->assertEquals('<p>Geachte ' . $msgReplacements['gender'] . ' ' . $msgReplacements['name'] . ',</p><p>Dit is een testbericht.</p><p>This an test message.</p><p>Rollerscapes</p>', $child->getBody());
                }
            }

            $mailDecorator->sendPerformed($sendEvent);
        }
    }

    function testReplaceWithDate()
    {
        $templating = $this->getTwigInstance();

        $transport = \Swift_MailTransport::newInstance();
        $message = \Swift_Message::newInstance('Wonderful Subject')
            ->setFrom(array('john@doe.com' => 'John Doe'))
            ->setTo(array('info@rollerscapes.net', 'webmaster@example.com'));

        $sendEvent = new \Swift_Events_SendEvent($transport, $message);
        $replacements = array('info@rollerscapes.net'     => array('name'   => 'John',
                                                                   'gender' => 'Sir',
                                                                   'date'   => '2010-08-25 15:28',
                                                                   'lang'   => 'en',
                                                                   'date2'  => 'Wednesday, August 25, 2010 3:28 PM'),

                              'webmaster@example.com'     => array('name'   => 'Piet',
                                                                   'gender' => 'Heer',
                                                                   'date'   => '2010-08-25 14:28',
                                                                   'lang'   => 'nl',
                                                                   'date2'  => 'woensdag 25 augustus 2010 14:28'));

        $mailDecorator = new TemplateDecorator($templating, $replacements, array('html' => 'TestMsg2.twig' ));

        foreach ($replacements as $sEmail => $msgReplacements) {
            $sendEvent->getMessage()->setTo($sEmail);

            $mailDecorator->beforeSendPerformed($sendEvent);

            $message = $sendEvent->getMessage();

            $children = (array) $message->getChildren();

            foreach ($children as $child) {
                if ('text/html' == $child->getContentType()) {
                    $this->assertEquals('<p>Geachte ' . $msgReplacements['gender'] . ' ' . $msgReplacements['name'] . ',</p><p>Currentdate: ' . $msgReplacements['date2'] . '</p>', $child->getBody());
                }
            }

            $mailDecorator->sendPerformed($sendEvent);
        }
    }

    function testOnlyText()
    {
        $templating = $this->getTwigInstance();

        $transport = \Swift_MailTransport::newInstance();
        $message = \Swift_Message::newInstance('Wonderful Subject')
            ->setFrom(array('john@doe.com' => 'John Doe'))
            ->setTo(array('info@rollerscapes.net', 'webmaster@example.com'));

        $sendEvent = new \Swift_Events_SendEvent($transport, $message);
        $replacements = array('info@rollerscapes.net'     => array('name'   => 'John',
                                                                   'gender' => 'Sir',
                                                                   'date'   => '2010-08-25 15:28',
                                                                   'lang'   => 'en',
                                                                   'date2'  => 'Wednesday, August 25, 2010 3:28:00 PM Central European Summer Time'),

                              'webmaster@example.com'     => array('name'   => 'Piet',
                                                                   'gender' => 'Heer',
                                                                   'date'   => '2010-08-25 14:28',
                                                                   'lang'   => 'nl',
                                                                   'date2'  => 'woensdag 25 augustus 2010 14:28:00 Midden-Europese zomertijd'));

        $mailDecorator = new TemplateDecorator($templating, $replacements, array('text' => 'TestMsg3.twig' ));

        $this->assertTrue( $mailDecorator->isTextOnly() );

        foreach ($replacements as $sEmail => $msgReplacements) {
            $sendEvent->getMessage()->setTo($sEmail);

            $mailDecorator->beforeSendPerformed($sendEvent);

            $message = $sendEvent->getMessage();

            $this->assertEquals('Geachte ' . $msgReplacements['gender'] . ' ' . $msgReplacements['name'] . ',

Currentdate: ' . $msgReplacements['date2'] . '', str_replace("\r", '', trim($message->getBody())));

            $children = (array) $message->getChildren();

            foreach ($children as $child) {
                if ('text/plain' == $child->getContentType() && \Swift_Mime_MimeEntity::LEVEL_ALTERNATIVE === $child->getNestingLevel()) {
                    $this->fail('This must not exist.');
                }
            }

            $mailDecorator->sendPerformed($sendEvent);
        }
    }

    function testAttachedHTML()
    {
        $templating = $this->getTwigInstance();

        $transport = \Swift_MailTransport::newInstance();
        $message = \Swift_Message::newInstance('Wonderful Subject')
                ->setFrom(array('john@doe.com' => 'John Doe'))
                ->setTo(array('info@rollerscapes.net', 'webmaster@example.com'));

        $message->attach(\Swift_Attachment::fromPath(__DIR__ . '/Fixtures/TestMsg2.twig', 'text/html'));

        $sendEvent = new \Swift_Events_SendEvent($transport, $message);
        $replacements = array('info@rollerscapes.net'     => array('name'   => 'John',
                                                                   'gender' => 'Sir',
                                                                   'date'   => '2010-08-25 15:28',
                                                                   'lang'   => 'en',
                                                                   'date2'  => 'Wednesday, August 25, 2010 3:28 PM'),

                              'webmaster@example.com'     => array('name'   => 'Piet',
                                                                   'gender' => 'Heer',
                                                                   'date'   => '2010-08-25 14:28',
                                                                   'lang'   => 'nl',
                                                                   'date2'  => 'woensdag 25 augustus 2010 14:28'));

        $mailDecorator = new TemplateDecorator($templating, $replacements, array('html' => 'TestMsg2.twig' ));

        foreach ($replacements as $sEmail => $msgReplacements) {
            $sendEvent->getMessage()->setTo($sEmail);

            $mailDecorator->beforeSendPerformed($sendEvent);

            $message = $sendEvent->getMessage();

            $children = (array) $message->getChildren();

            foreach ($children as $child) {
                if ('text/html' == $child->getContentType() && \Swift_Mime_MimeEntity::LEVEL_ALTERNATIVE === $child->getNestingLevel()) {
                    $this->assertEquals('<p>Geachte ' . $msgReplacements['gender'] . ' ' . $msgReplacements['name'] . ',</p><p>Currentdate: ' . $msgReplacements['date2'] . '</p>', $child->getBody());
                }
                elseif ('text/html' == $child->getContentType() && \Swift_Mime_MimeEntity::LEVEL_MIXED === $child->getNestingLevel()) {
                    $headers = $child->getHeaders();

                    if ($headers->has('Content-Disposition')) {
                        $sOrig = 'Content-Type: text/html; name=TestMsg2.twig
Content-Transfer-Encoding: base64
Content-Disposition: attachment; filename=TestMsg2.twig

PHA+R2VhY2h0ZSB7eyBnZW5kZXIgfX0ge3sgbmFtZSB9fSw8L3A+PHA+Q3VycmVudGRhdGU6IHt7
IGRhdGUgfCBsb2NhbGl6ZWRkYXRlKCAnZnVsbCcsICdzaG9ydCcsIGxhbmcgKSB9fTwvcD4=';

                        // The $sOrig does not have \r (since this file is UNIX encoded)
                        $sChild = str_replace("\r", '', trim($child->toString()));

                        $this->assertEquals($sOrig, $sChild);
                    }
                }
            }

            $mailDecorator->sendPerformed($sendEvent);
        }
    }

    function testWrongInput()
    {
        $templating = $this->getTwigInstance();

        $transport = \Swift_MailTransport::newInstance();
        $message = \Swift_Message::newInstance('Wonderful Subject')
                ->setFrom(array('john@doe.com' => 'John Doe'))
                ->setTo(array('info@rollerscapes.net', 'webmaster@example.com'));

        $message->attach(\Swift_Attachment::fromPath(__DIR__ . '/Fixtures/TestMsg2.twig', 'text/html'));

        //$sendEvent = new \Swift_Events_SendEvent($transport, $message);
        $replacements = array('info@rollerscapes.net'     => array('name'   => 'John',
                                                                   'gender' => 'Sir',
                                                                   'date'   => '2010-08-25 15:28',
                                                                   'lang'   => 'en',
                                                                   'date2'  => 'Wednesday, August 25, 2010 3:28 PM'),

                              'webmaster@example.com'     => array('name'   => 'Piet',
                                                                   'gender' => 'Heer',
                                                                   'date'   => '2010-08-25 14:28',
                                                                   'lang'   => 'nl',
                                                                   'date2'  => 'woensdag 25 augustus 2010 14:28'));

        $this->setExpectedException( '\InvalidArgumentException', '$templates must contain either html and/or text');
        new TemplateDecorator($templating, $replacements, array());
    }

    /**
     * @return TwigEngine
     */
    protected function getTwigInstance()
    {
        $config = array('cache' => __DIR__ . '/TwigCache', 'strict_variables' => true);

        $loader = new \Twig_Loader_Filesystem(array(__DIR__ . '/Fixtures'));
        $twig   = new \Twig_Environment($loader, $config);

        $twig->addExtension(new \Twig_Extensions_Extension_Intl());
        $engine = new TwigEngine($twig);

        return $engine;
    }
}
