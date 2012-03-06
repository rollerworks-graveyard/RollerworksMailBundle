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

namespace Rollerworks\MailBundle\Tests;

use Rollerworks\MailBundle\Template;

use Symfony\Component\Templating\TemplateNameParserInterface;
use Symfony\Bundle\FrameworkBundle\Templating\EngineInterface;
use Symfony\Bundle\FrameworkBundle\Templating\GlobalVariables;

use \Twig_Loader_Filesystem, \Twig_Environment ;

use \Swift_MailTransport, \Swift_Mailer, \Swift_Events_SendEvent, \Swift_Message, \Swift_Attachment, \Swift_Mime_MimeEntity;

class MailTemplateTest extends \PHPUnit_Framework_TestCase
{
	function testSimpleReplace()
	{
		$oTemplateHandler = $this->getTwigInstance();

		$transport = Swift_MailTransport::newInstance();

		$message = Swift_Message::newInstance('Wonderful Subject')
			->setFrom(array('john@doe.com' => 'John Doe'))
			->setTo(array('info@rollerscapes.net', 'webmaster@google.nl'));

		$oSendEvent = new Swift_Events_SendEvent($transport, $message);

		$aReplacements = array('info@rollerscapes.net'   => array('sName'   => 'John', 'sGender' => 'Sir'),
							   'webmaster@google.nl'	 => array('sName'   => 'Piet', 'sGender' => 'Heer'));

		$oMailTemplate = new Template($oTemplateHandler, $aReplacements, array('html' => 'TestMsg1.twig' ));

		foreach ($aReplacements as $sEmail => $aReplacements) {
			$oSendEvent
				->getMessage()
				->setTo($sEmail);

			$oMailTemplate->beforeSendPerformed($oSendEvent);

			$oMessage = $oSendEvent->getMessage();
			$this->assertEquals('Geachte ' . $aReplacements[ 'sGender' ] . ' ' . $aReplacements[ 'sName' ] . ',

Dit is een testbericht.

This an test message.

Rollerscapes', trim($oMessage->getBody()));

			$children = (array) $message->getChildren();

			foreach ($children as $child) {
				if ('text/html' == $child->getContentType()) {
					$this->assertEquals('<p>Geachte ' . $aReplacements[ 'sGender' ] . ' ' . $aReplacements[ 'sName' ] . ',</p><p>Dit is een testbericht.</p><p>This an test message.</p><p>Rollerscapes</p>', $child->getBody());
				}
			}

			$oMailTemplate->sendPerformed($oSendEvent);
		}
	}

	function testHTMLAndText()
	{
		$oTemplateHandler = $this->getTwigInstance();

		$transport = Swift_MailTransport::newInstance();

		$message = Swift_Message::newInstance('Wonderful Subject')
			->setFrom(array('john@doe.com' => 'John Doe'))
			->setTo(array('info@rollerscapes.net', 'webmaster@google.nl'));

		$oSendEvent = new Swift_Events_SendEvent($transport, $message);

		$aReplacements = array('info@rollerscapes.net'   => array('sName'   => 'John',  'sGender' => 'Sir'),
							   'webmaster@google.nl'	 => array('sName'   => 'Piet',  'sGender' => 'Heer'));

		$oMailTemplate = new Template($oTemplateHandler, $aReplacements, array('html' => 'TestMsg1.twig', 'text' => 'TestMsg1.txt.twig' ));

		foreach ($aReplacements as $sEmail => $aReplacements) {
			$oSendEvent
				->getMessage()
				->setTo($sEmail);

			$oMailTemplate->beforeSendPerformed($oSendEvent);

			$oMessage = $oSendEvent->getMessage();
			$this->assertEquals('Geachte ' . $aReplacements[ 'sGender' ] . ' ' . $aReplacements[ 'sName' ] . ',

Dit is een testbericht.

This an test message.
Rollerscapes-', trim($oMessage->getBody()));

			$children = (array) $message->getChildren();

			foreach ($children as $child) {
				if ('text/html' == $child->getContentType()) {
					$this->assertEquals('<p>Geachte ' . $aReplacements[ 'sGender' ] . ' ' . $aReplacements[ 'sName' ] . ',</p><p>Dit is een testbericht.</p><p>This an test message.</p><p>Rollerscapes</p>', $child->getBody());
				}
			}

			$oMailTemplate->sendPerformed($oSendEvent);
		}
	}

	function testHTMLOnly()
	{
		$oTemplateHandler = $this->getTwigInstance();

		$transport = Swift_MailTransport::newInstance();

		$message = Swift_Message::newInstance('Wonderful Subject')
			->setFrom(array('john@doe.com' => 'John Doe'))
			->setTo(array('info@rollerscapes.net', 'webmaster@google.nl'));

		$oSendEvent = new Swift_Events_SendEvent($transport, $message);

		$aReplacements = array('info@rollerscapes.net'   => array('sName'   => 'John',  'sGender' => 'Sir'),
							   'webmaster@google.nl'	 => array('sName'   => 'Piet',  'sGender' => 'Heer'));

		$oMailTemplate = new Template($oTemplateHandler, $aReplacements, array('html' => 'TestMsg1.twig', 'text' => false ));

		foreach ($aReplacements as $sEmail => $aReplacements) {
			$oSendEvent
				->getMessage()
				->setTo($sEmail);

			$oMailTemplate->beforeSendPerformed($oSendEvent);

			$oMessage = $oSendEvent->getMessage();

			$this->assertEquals('text/html', $oMessage->getContentType());
			$this->assertEquals('<p>Geachte ' . $aReplacements[ 'sGender' ] . ' ' . $aReplacements[ 'sName' ] . ',</p><p>Dit is een testbericht.</p><p>This an test message.</p><p>Rollerscapes</p>', trim($oMessage->getBody()));

			$oMailTemplate->sendPerformed($oSendEvent);
		}
	}

	function testSubjectReplace()
	{
		$oTemplateHandler = $this->getTwigInstance();

		$transport = Swift_MailTransport::newInstance();

		$message = Swift_Message::newInstance('Message for {name}')
			->setFrom(array('john@doe.com' => 'John Doe'))
			->setTo(array('info@rollerscapes.net', 'webmaster@google.nl'));

		$oSendEvent = new Swift_Events_SendEvent($transport, $message);

		$aReplacements = array('info@rollerscapes.net'   => array('sName'  => 'John', 'sGender'  => 'Sir',
																  '_subject' => array('{name}' => 'SJohn')),
							   'webmaster@google.nl'	 => array('sName'  => 'Piet', 'sGender'  => 'Heer',
																  '_subject' => array('{name}' => 'SPiet')));

		$oMailTemplate = new Template($oTemplateHandler, $aReplacements, array('html' => 'TestMsg1.twig' ));

		foreach ($aReplacements as $sEmail => $aReplacements) {
			$oSendEvent
				->getMessage()
				->setTo($sEmail);

			$oMailTemplate->beforeSendPerformed($oSendEvent);

			$oMessage = $oSendEvent->getMessage();

			$this->assertEquals('Message for ' . $aReplacements[ '_subject' ][ '{name}' ], $oMessage->getSubject());
			$this->assertEquals('Geachte ' . $aReplacements[ 'sGender' ] . ' ' . $aReplacements[ 'sName' ] . ',

Dit is een testbericht.

This an test message.

Rollerscapes', trim($oMessage->getBody()));

			$children = (array)$message->getChildren();

			foreach ($children as $child) {
				if ('text/html' == $child->getContentType()) {
					$this->assertEquals('<p>Geachte ' . $aReplacements[ 'sGender' ] . ' ' . $aReplacements[ 'sName' ] . ',</p><p>Dit is een testbericht.</p><p>This an test message.</p><p>Rollerscapes</p>', $child->getBody());
				}
			}

			$oMailTemplate->sendPerformed($oSendEvent);
		}
	}

	function testReplaceWithDate()
	{
		$oTemplateHandler = $this->getTwigInstance();

		$transport = Swift_MailTransport::newInstance();

		$message = Swift_Message::newInstance('Wonderful Subject')
			->setFrom(array('john@doe.com' => 'John Doe'))
			->setTo(array('info@rollerscapes.net', 'webmaster@google.nl'));

		$oSendEvent = new Swift_Events_SendEvent($transport, $message);

		$aReplacements = array('info@rollerscapes.net'   => array('sName'   => 'John',
																  'sGender' => 'Sir',
																  'sDate'   => '2010-08-25 15:28',
																  'sLang'   => 'en',
																  'sDate2'  => 'Wednesday, August 25, 2010 3:28 PM'),

							   'webmaster@google.nl'	 => array('sName'   => 'Piet',
																  'sGender' => 'Heer',
																  'sDate'   => '2010-08-25 14:28',
																  'sLang'   => 'nl',
																  'sDate2'  => 'woensdag 25 augustus 2010 14:28'));

		$oMailTemplate = new Template($oTemplateHandler, $aReplacements, array('html' => 'TestMsg2.twig' ));

		foreach ($aReplacements as $sEmail => $aReplacements) {
			$oSendEvent
				->getMessage()
				->setTo($sEmail);

			$oMailTemplate->beforeSendPerformed($oSendEvent);

			$oMessage = $oSendEvent->getMessage();

			$this->assertEquals('Geachte ' . $aReplacements[ 'sGender' ] . ' ' . $aReplacements[ 'sName' ] . ',

Currentdate: ' . $aReplacements[ 'sDate2' ] . '', $oMessage->getBody());

			$children = (array)$message->getChildren();

			foreach ($children as $child) {
				if ('text/html' == $child->getContentType()) {
					$this->assertEquals('<p>Geachte ' . $aReplacements[ 'sGender' ] . ' ' . $aReplacements[ 'sName' ] . ',</p><p>Currentdate: ' . $aReplacements[ 'sDate2' ] . '</p>', $child->getBody());
				}
			}

			$oMailTemplate->sendPerformed($oSendEvent);
		}
	}

	function testOnlyText()
	{
		$oTemplateHandler = $this->getTwigInstance();

		$transport = Swift_MailTransport::newInstance();

		$message = Swift_Message::newInstance('Wonderful Subject')
			->setFrom(array('john@doe.com' => 'John Doe'))
			->setTo(array('info@rollerscapes.net', 'webmaster@google.nl'));

		$oSendEvent = new Swift_Events_SendEvent($transport, $message);

		$aReplacements = array('info@rollerscapes.net'   => array('sName'   => 'John',
																  'sGender' => 'Sir',
																  'sDate'   => '2010-08-25 15:28',
																  'sLang'   => 'en',
																  'sDate2'  => 'Wednesday, August 25, 2010 3:28:00 PM Central European Summer Time'),

							   'webmaster@google.nl'	 => array('sName'   => 'Piet',
																  'sGender' => 'Heer',
																  'sDate'   => '2010-08-25 14:28',
																  'sLang'   => 'nl',
																  'sDate2'  => 'woensdag 25 augustus 2010 14:28:00 Midden-Europese zomertijd')
		);

		$oMailTemplate = new Template($oTemplateHandler, $aReplacements, array('text' => 'TestMsg3.twig' ));

		$this->assertTrue( $oMailTemplate->isTextOnly() );

		foreach ($aReplacements as $sEmail => $aReplacements) {
			$oSendEvent
				->getMessage()
				->setTo($sEmail);

			$oMailTemplate->beforeSendPerformed($oSendEvent);

			$oMessage = $oSendEvent->getMessage();

			$this->assertEquals('Geachte ' . $aReplacements[ 'sGender' ] . ' ' . $aReplacements[ 'sName' ] . ',

Currentdate: ' . $aReplacements[ 'sDate2' ] . '', str_replace("\r", '', trim($oMessage->getBody())));

			$children = (array)$message->getChildren();

			foreach ($children as $child) {
				if ('text/plain' == $child->getContentType() && Swift_Mime_MimeEntity::LEVEL_ALTERNATIVE === $child->getNestingLevel()) {
					$this->fail('This must not exist.');
				}
			}

			$oMailTemplate->sendPerformed($oSendEvent);
		}
	}

	function testAttachedHTML()
	{
		$oTemplateHandler = $this->getTwigInstance();

		$transport = Swift_MailTransport::newInstance();

		$message = Swift_Message::newInstance('Wonderful Subject')
				->setFrom(array('john@doe.com' => 'John Doe'))
				->setTo(array('info@rollerscapes.net', 'webmaster@google.nl'));

		$message->attach(Swift_Attachment::fromPath(__DIR__ . '/Files/TestMsg2.twig', 'text/html'));

		$oSendEvent = new Swift_Events_SendEvent($transport, $message);

		$aReplacements = array('info@rollerscapes.net'   => array('sName'   => 'John',
																  'sGender' => 'Sir',
																  'sDate'   => '2010-08-25 15:28',
																  'sLang'   => 'en',
																  'sDate2'  => 'Wednesday, August 25, 2010 3:28 PM'),

							   'webmaster@google.nl'	 => array('sName'   => 'Piet',
																  'sGender' => 'Heer',
																  'sDate'   => '2010-08-25 14:28',
																  'sLang'   => 'nl',
																  'sDate2'  => 'woensdag 25 augustus 2010 14:28')
		);

		$oMailTemplate = new Template($oTemplateHandler, $aReplacements, array('html' => 'TestMsg2.twig' ));

		foreach ($aReplacements as $sEmail => $aReplacements) {
			$oSendEvent
					->getMessage()
					->setTo($sEmail);

			$oMailTemplate->beforeSendPerformed($oSendEvent);

			$oMessage = $oSendEvent->getMessage();

			$this->assertEquals('Geachte ' . $aReplacements[ 'sGender' ] . ' ' . $aReplacements[ 'sName' ] . ',

Currentdate: ' . $aReplacements[ 'sDate2' ] . '', $oMessage->getBody());

			$children = (array)$message->getChildren();

			foreach ($children as $child)
			{
				if ('text/html' == $child->getContentType() && Swift_Mime_MimeEntity::LEVEL_ALTERNATIVE === $child->getNestingLevel()) {
					$this->assertEquals('<p>Geachte ' . $aReplacements[ 'sGender' ] . ' ' . $aReplacements[ 'sName' ] . ',</p><p>Currentdate: ' . $aReplacements[ 'sDate2' ] . '</p>', $child->getBody());
				}
				elseif ('text/html' == $child->getContentType() && Swift_Mime_MimeEntity::LEVEL_MIXED === $child->getNestingLevel()) {
					$oHeaders = $child->getHeaders();

					if ($oHeaders->has('Content-Disposition')) {
						$sOrig = 'Content-Type: text/html; name=TestMsg2.twig
Content-Transfer-Encoding: base64
Content-Disposition: attachment; filename=TestMsg2.twig

PHA+R2VhY2h0ZSB7eyBzR2VuZGVyIH19IHt7IHNOYW1lIH19LDwvcD48cD5DdXJyZW50ZGF0ZTog
e3sgc0RhdGUgfCBsb2NhbGl6ZWRkYXRlKCAnZnVsbCcsICdzaG9ydCcsIHNMYW5nICkgfX08L3A+';

						// The $sOrig does not have \r (since this file is UNIX encoded)
						$sChild = str_replace("\r", '', trim($child->toString()));

						$this->assertEquals($sOrig, $sChild);
					}
				}
			}

			$oMailTemplate->sendPerformed($oSendEvent);
		}
	}

	function testWrongInput()
	{
		$oTemplateHandler = $this->getTwigInstance();

		$transport = Swift_MailTransport::newInstance();

		$message = Swift_Message::newInstance('Wonderful Subject')
				->setFrom(array('john@doe.com' => 'John Doe'))
				->setTo(array('info@rollerscapes.net', 'webmaster@google.nl'));

		$message->attach(Swift_Attachment::fromPath(__DIR__ . '/Files/TestMsg2.twig', 'text/html'));

		$oSendEvent = new Swift_Events_SendEvent($transport, $message);

		$aReplacements = array('info@rollerscapes.net'   => array('sName'   => 'John',
																  'sGender' => 'Sir',
																  'sDate'   => '2010-08-25 15:28',
																  'sLang'   => 'en',
																  'sDate2'  => 'Wednesday, August 25, 2010 3:28 PM'),

							   'webmaster@google.nl'	 => array('sName'   => 'Piet',
																  'sGender' => 'Heer',
																  'sDate'   => '2010-08-25 14:28',
																  'sLang'   => 'nl',
																  'sDate2'  => 'woensdag 25 augustus 2010 14:28')
		);

		$this->setExpectedException( '\InvalidArgumentException', '$paTemplates must contain either html and/or text');
		new Template($oTemplateHandler, $aReplacements, array());
	}

	protected function getTwigInstance()
	{
		$config = array(
			'cache' => __DIR__ . '/TwigCache',
			'strict_variables' => true);

		$loader = new Twig_Loader_Filesystem(array(__DIR__ . '/Files'));

		$twig   = new Twig_Environment($loader, $config);
		$twig->addExtension( new \Twig_Extensions_Extension_Intl() );

		$oEngine = new TwigEngine($twig);

		return $oEngine ;
	}
}
