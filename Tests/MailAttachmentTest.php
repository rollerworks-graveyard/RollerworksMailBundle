<?php
namespace Rollerworks\MailBundle\Tests;

use Rollerworks\MailBundle\Template;
use Rollerworks\MailBundle\AttachmentDecorator;

use Symfony\Bundle\FrameworkBundle\Templating\EngineInterface;
use Symfony\Bundle\FrameworkBundle\Templating\GlobalVariables;
use Symfony\Component\Templating\TemplateNameParserInterface;
use Symfony\Bundle\TwigBundle\TwigEngine;

use \Twig_Loader_Filesystem, \Twig_Environment;

use \Swift_MailTransport, \Swift_Mailer, \Swift_Events_SendEvent, \Swift_Message, \Swift_Attachment, \Swift_Mime_MimeEntity;

class AttachmentTest extends \PHPUnit_Framework_TestCase
{
	function testSimpleReplace()
	{
		$transport = Swift_MailTransport::newInstance();

		$message = Swift_Message::newInstance('Wonderful Subject')
				->setFrom(array('john@doe.com' => 'John Doe'))
				->setTo(array('info@rollerscapes.net', 'webmaster@google.nl'));

		$message->setBody('Here is the message itself');

		$oSendEvent = new Swift_Events_SendEvent($transport, $message);

		$aReplacements = array(
			'info@rollerscapes.net'  => array(Swift_Attachment::newInstance('this an test document', 		'Invoice-2011-4342.txt', 'plain/text')),
			'webmaster@google.nl'	 => array(Swift_Attachment::newInstance('this an none-test document',	'Invoice-2011-8480.txt', 'plain/text'))
		);

		$oMailTemplate = new AttachmentDecorator($aReplacements);

		foreach ($aReplacements as $sEmail => $aReplacements)
		{
			$oSendEvent->getMessage()->setTo($sEmail);

			$oMailTemplate->beforeSendPerformed($oSendEvent);

			$oMessage = $oSendEvent->getMessage();

			$this->assertEquals('Here is the message itself', trim($oMessage->getBody()));

			$children = (array)$message->getChildren();

			foreach ($children as $child)
			{
				if ($child->getNestingLevel() === Swift_Mime_MimeEntity::LEVEL_MIXED)
				{
					$this->assertEquals($aReplacements[ 0 ], $child);
				}
			}

			$oMailTemplate->sendPerformed($oSendEvent);
		}
	}


	function testKeepOriginal()
	{
		$transport = Swift_MailTransport::newInstance();

		$message = Swift_Message::newInstance('Wonderful Subject')
				->setFrom(array('john@doe.com' => 'John Doe'))
				->setTo(array('info@rollerscapes.net', 'webmaster@google.nl'));

		$message->setBody('Here is the message itself');

		$oLooseAttachment = Swift_Attachment::newInstance('this an none-test document', 'Invoice-2011-848.txt', 'plain/text');

		$message->attach($oLooseAttachment);

		$oSendEvent = new Swift_Events_SendEvent($transport, $message);

		$aReplacements = array(
			'info@rollerscapes.net'  => array(Swift_Attachment::newInstance('this an test document',		'Invoice-2011-4342.txt', 'plain/text')),
			'webmaster@google.nl'	 => array(Swift_Attachment::newInstance('this an none-test document',	'Invoice-2011-8480.txt', 'plain/text'))
		);

		$oMailTemplate = new AttachmentDecorator($aReplacements);

		foreach ($aReplacements as $sEmail => $aReplacements)
		{
			$oSendEvent->getMessage()->setTo($sEmail);

			$oMailTemplate->beforeSendPerformed($oSendEvent);

			$oMessage = $oSendEvent->getMessage();

			$this->assertEquals('Here is the message itself', trim($oMessage->getBody()));

			$children = (array)$message->getChildren();

			foreach ($children as $child)
			{
				if ($child->getNestingLevel() === Swift_Mime_MimeEntity::LEVEL_MIXED && $child->getFilename() == 'Invoice-2011-848.txt')
				{
					continue;
				}

				if ($child->getNestingLevel() === Swift_Mime_MimeEntity::LEVEL_MIXED)
				{
					$this->assertEquals($aReplacements[ 0 ], $child);
				}
			}

			$oMailTemplate->sendPerformed($oSendEvent);

			$children = (array)$message->getChildren();

			// Check to make sure the original Attachment is still there
			foreach ($children as $child)
			{
				if ($child->getNestingLevel() === Swift_Mime_MimeEntity::LEVEL_MIXED)
				{
					$this->assertEquals($oLooseAttachment->toString(), $child->toString());
				}
			}
		}
	}


	function testMultiple()
	{
		$transport = Swift_MailTransport::newInstance();

		$message = Swift_Message::newInstance('Wonderful Subject')
				->setFrom(array('john@doe.com' => 'John Doe'))
				->setTo(array('info@rollerscapes.net', 'webmaster@google.nl'));

		$message->setBody('Here is the message itself');

		$oLooseAttachment = Swift_Attachment::newInstance('this an none-test document', 'Invoice-2011-848.txt', 'plain/text');

		$message->attach($oLooseAttachment);

		$oSendEvent = new Swift_Events_SendEvent($transport, $message);

		$aReplacements = array(
			'info@rollerscapes.net'  => array(Swift_Attachment::newInstance('this an test document', 		'Invoice-2011-4342.txt', 'plain/text')),
			'webmaster@google.nl'	 => array(Swift_Attachment::newInstance('this an none-test document',	'Invoice-2011-8480.txt', 'plain/text'), Swift_Attachment::newInstance('this an none-test2 document', 'Invoice-2011-8580.txt', 'plain/text'))
		);

		$oMailTemplate = new AttachmentDecorator($aReplacements);

		foreach ($aReplacements as $sEmail => $aReplacements)
		{
			$oSendEvent->getMessage()->setTo($sEmail);

			$oMailTemplate->beforeSendPerformed($oSendEvent);

			$oMessage = $oSendEvent->getMessage();

			$this->assertEquals('Here is the message itself', trim($oMessage->getBody()));

			$children = (array)$message->getChildren();

			$aAttachments = array();

			foreach ($children as $child)
			{
				if ($child->getNestingLevel() === Swift_Mime_MimeEntity::LEVEL_MIXED && $child->getFilename() == 'Invoice-2011-848.txt')
				{
					continue;
				}

				if ($child->getNestingLevel() === Swift_Mime_MimeEntity::LEVEL_MIXED)
				{
					$aAttachments[ ] = $child;
				}
			}

			$this->assertEquals($aAttachments, $aAttachments);

			$oMailTemplate->sendPerformed($oSendEvent);

			$children = (array)$message->getChildren();

			// Check to make sure the original Attachment is still there
			foreach ($children as $child)
			{
				if ($child->getNestingLevel() === Swift_Mime_MimeEntity::LEVEL_MIXED)
				{
					$this->assertEquals($oLooseAttachment->toString(), $child->toString());
				}
			}
		}
	}


	function testMultiArray()
	{
		$transport = Swift_MailTransport::newInstance();

		$mailer = Swift_Mailer::newInstance($transport);

		$message = Swift_Message::newInstance('Wonderful Subject')
				->setFrom(array('john@doe.com' => 'John Doe'))
				->setTo(array('info@rollerscapes.net', 'webmaster@google.nl'));

		$message->setBody('Here is the message itself');

		$oLooseAttachment = Swift_Attachment::newInstance('this an none-test document', 'Invoice-2011-848.txt', 'plain/text');

		$message->attach($oLooseAttachment);

		$oSendEvent = new Swift_Events_SendEvent($transport, $message);

		$aReplacements = array(
			'info@rollerscapes.net'  => array(Swift_Attachment::newInstance('this an test document', 'Invoice-2011-4342.txt', 'plain/text')),
			 'webmaster@google.nl'	 => array(Swift_Attachment::newInstance('this an none-test document', 'Invoice-2011-8480.txt', 'plain/text'), array('data'     => 'this an none-test2 document',
																																											'filename' => 'Invoice-2011-8580.txt')),);

		$oMailTemplate = new AttachmentDecorator($aReplacements);

		foreach ($aReplacements as $sEmail => $aReplacements)
		{
			$oSendEvent->getMessage()->setTo($sEmail);

			$oMailTemplate->beforeSendPerformed($oSendEvent);

			$oMessage = $oSendEvent->getMessage();

			$this->assertEquals('Here is the message itself', trim($oMessage->getBody()));

			$children = (array)$message->getChildren();

			$aAttachments = array();

			foreach ($children as $child)
			{
				if ($child->getNestingLevel() === Swift_Mime_MimeEntity::LEVEL_MIXED && $child->getFilename() == 'Invoice-2011-848.txt')
				{
					continue;
				}

				if ($child->getNestingLevel() === Swift_Mime_MimeEntity::LEVEL_MIXED)
				{
					$aAttachments[ ] = $child;
				}
			}

			$this->assertEquals($aAttachments, $aAttachments);

			$oMailTemplate->sendPerformed($oSendEvent);

			$children = (array)$message->getChildren();

			// Check to make sure the original Attachment is still there
			foreach ($children as $child)
			{
				if ($child->getNestingLevel() === Swift_Mime_MimeEntity::LEVEL_MIXED)
				{
					$this->assertEquals($oLooseAttachment->toString(), $child->toString());
				}
			}
		}
	}
}